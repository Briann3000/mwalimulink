<?php
// views/admin_audit.php - Administrative Audit Trail & Compliance Activity Center
require_auth('admin');

$authUser = auth_user();
$msg = '';
$error = '';

// Handle Revert / Undo Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $auditId = intval($_POST['audit_id'] ?? 0);
        $revertAction = $_POST['revert_action'] ?? '';
        $targetId = intval($_POST['target_id'] ?? 0);
        $targetType = $_POST['target_type'] ?? '';

        if ($targetType === 'teacher' && $targetId > 0) {
            $teacher = R::load('teacher', $targetId);
            if ($teacher && $teacher->id) {
                if ($revertAction === 'reapprove') {
                    $teacher->verification_status = 'verified';
                    $teacher->good_conduct_status = 'verified';
                    $teacher->verified_at = date('Y-m-d H:i:s');
                    $teacher->verified_source = 'admin_audit_override';
                    R::store($teacher);
                    if (function_exists('send_verification_status_email')) {
                        send_verification_status_email($teacher, 'verified');
                    }
                    log_admin_audit('clearance', 'CLEARANCE_APPROVED', 'teacher', $teacher->id, $teacher->name, "Re-approved via Audit Trail action");
                    $msg = "Reverted status: Educator " . htmlspecialchars($teacher->name) . " marked as Verified.";
                } elseif ($revertAction === 'activate') {
                    $teacher->status = 'available';
                    R::store($teacher);
                    log_admin_audit('teacher', 'ACCOUNT_ACTIVATED', 'teacher', $teacher->id, $teacher->name, "Reactivated via Audit Trail action");
                    $msg = "Reverted status: Educator " . htmlspecialchars($teacher->name) . " account reactivated.";
                }
            }
        } elseif ($targetType === 'school' && $targetId > 0) {
            $school = R::load('school', $targetId);
            if ($school && $school->id) {
                if ($revertAction === 'revoke_pro') {
                    $school->subscription_expiry = null;
                    R::store($school);
                    log_admin_audit('school', 'PRO_REVOKED', 'school', $school->id, $school->name, "Pro plan revoked via Audit Trail action");
                    $msg = "Reverted status: Pro tier revoked for " . htmlspecialchars($school->name) . ".";
                } elseif ($revertAction === 'activate') {
                    $school->status = 'active';
                    R::store($school);
                    log_admin_audit('school', 'ACCOUNT_ACTIVATED', 'school', $school->id, $school->name, "Reactivated via Audit Trail action");
                    $msg = "Reverted status: School " . htmlspecialchars($school->name) . " account reactivated.";
                }
            }
        }
    }
}

// Auto-seed historical logs from existing TSC audits if table is fresh
$existingAuditCount = R::count('adminauditlog');
if ($existingAuditCount === 0) {
    $historicalTscLogs = R::find('tscverificationlog', 'ORDER BY id DESC LIMIT 50');
    foreach ($historicalTscLogs as $hl) {
        $audit = R::dispense('adminauditlog');
        $audit->category = 'clearance';
        $audit->action_type = ($hl->status === 'verified') ? 'TSC_PORTAL_MATCH' : 'TSC_PORTAL_UNMATCHED';
        $audit->target_type = 'teacher';
        $audit->target_id = $hl->teacher_id;
        $audit->target_name = $hl->candidate_name;
        $audit->details = "Automated TSC background query. Portal: " . ($hl->portal_name ?: 'None') . " (Match Score: {$hl->match_score}%)";
        $audit->notes = $hl->details ?: '';
        $audit->actor_email = 'System / Automated Engine';
        $audit->actor_role = 'system';
        $audit->ip_address = '127.0.0.1';
        $audit->created_at = $hl->created_at;
        R::store($audit);
    }
}

// Filter & Search Parameters
$search = trim($_GET['q'] ?? '');
$category = trim($_GET['cat'] ?? '');
$timeframe = trim($_GET['timeframe'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 35;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = ['1=1'];

if ($search !== '') {
    $whereClauses[] = '(target_name LIKE ? OR details LIKE ? OR notes LIKE ? OR actor_email LIKE ? OR action_type LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($category !== '' && $category !== 'all') {
    $whereClauses[] = 'category = ?';
    $params[] = $category;
}

if ($timeframe === 'today') {
    $whereClauses[] = 'created_at >= ?';
    $params[] = date('Y-m-d 00:00:00');
} elseif ($timeframe === '7days') {
    $whereClauses[] = 'created_at >= ?';
    $params[] = date('Y-m-d H:i:s', strtotime('-7 days'));
} elseif ($timeframe === '30days') {
    $whereClauses[] = 'created_at >= ?';
    $params[] = date('Y-m-d H:i:s', strtotime('-30 days'));
}

$whereSql = implode(' AND ', $whereClauses);

// CSV Export Request
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=mwalimulink_audit_logs_' . date('Y-m-d') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ID', 'Timestamp', 'Actor Email', 'Role', 'Category', 'Action Type', 'Target Type', 'Target ID', 'Target Name', 'Details', 'Notes', 'IP Address']);
    
    $exportRecords = R::find('adminauditlog', "{$whereSql} ORDER BY id DESC", $params);
    foreach ($exportRecords as $r) {
        fputcsv($output, [
            $r->id,
            $r->created_at,
            $r->actor_email,
            $r->actor_role,
            $r->category,
            $r->action_type,
            $r->target_type,
            $r->target_id,
            $r->target_name,
            $r->details,
            $r->notes,
            $r->ip_address
        ]);
    }
    fclose($output);
    exit();
}

$totalLogs = R::count('adminauditlog', $whereSql, $params);
$auditLogs = R::find('adminauditlog', "{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

// Aggregate Category Statistics
$statTotal = R::count('adminauditlog');
$statClearance = R::count('adminauditlog', "category = 'clearance'");
$statSchools = R::count('adminauditlog', "category = 'school'");
$statTeachers = R::count('adminauditlog', "category = 'teacher'");
$statJobs = R::count('adminauditlog', "category = 'job'");

$totalPages = ceil($totalLogs / $limit);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">
            
            <!-- Breadcrumb & Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-clipboard-list" style="color: #0f766e;"></i> System & Administrative Audit Trail
                    </h2>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="/admin/audit?export=csv<?= $search ? '&q=' . urlencode($search) : '' ?><?= $category ? '&cat=' . urlencode($category) : '' ?>" style="background: #0f766e; color: white !important; padding: 7px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-file-csv"></i> Export CSV Audit Report
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-check"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-exclamation"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Total Events Logged</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 4px;"><?= number_format($statTotal) ?></div>
                </div>
                <div style="background: white; border: 1px solid #ccfbf1; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #0f766e; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Clearance & Background</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f766e; margin-top: 4px;"><?= number_format($statClearance) ?></div>
                </div>
                <div style="background: white; border: 1px solid #e0f2fe; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #0369a1; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">School Operations</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0284c7; margin-top: 4px;"><?= number_format($statSchools) ?></div>
                </div>
                <div style="background: white; border: 1px solid #f3e8ff; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #7e22ce; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Educator Operations</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #9333ea; margin-top: 4px;"><?= number_format($statTeachers) ?></div>
                </div>
                <div style="background: white; border: 1px solid #fef3c7; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #b45309; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Vacancies Moderated</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #d97706; margin-top: 4px;"><?= number_format($statJobs) ?></div>
                </div>
            </div>

            <!-- Filter & Search Controls -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/audit" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin: 0;">
                    <div style="flex: 2; min-width: 220px;">
                        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search target name, email, action, reason notes..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 160px;">
                        <select name="cat" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;">
                            <option value="all">All Event Categories</option>
                            <option value="clearance" <?= $category === 'clearance' ? 'selected' : '' ?>>Clearances & Verifications</option>
                            <option value="school" <?= $category === 'school' ? 'selected' : '' ?>>School Subscriptions & Status</option>
                            <option value="teacher" <?= $category === 'teacher' ? 'selected' : '' ?>>Educator Accounts</option>
                            <option value="job" <?= $category === 'job' ? 'selected' : '' ?>>Job Vacancies</option>
                            <option value="publication" <?= $category === 'publication' ? 'selected' : '' ?>>Publications Hub</option>
                            <option value="security" <?= $category === 'security' ? 'selected' : '' ?>>Security & Logins</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="timeframe" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;">
                            <option value="">All Time</option>
                            <option value="today" <?= $timeframe === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="7days" <?= $timeframe === '7days' ? 'selected' : '' ?>>Past 7 Days</option>
                            <option value="30days" <?= $timeframe === '30days' ? 'selected' : '' ?>>Past 30 Days</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="background: #0f766e; color: white !important; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $category || $timeframe): ?>
                            <a href="/admin/audit" style="margin-left: 6px; font-size: 0.84rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Audit Trail Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden;">
                <?php if (empty($auditLogs)): ?>
                    <div style="padding: 3.5rem; text-align: center; color: #64748b;">
                        <i class="fa fa-clipboard-check" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 1rem;">No audit logs matched your search filters.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                    <th style="padding: 12px 14px;">Timestamp & Actor</th>
                                    <th style="padding: 12px 14px;">Action / Event</th>
                                    <th style="padding: 12px 14px;">Target Entity</th>
                                    <th style="padding: 12px 14px;">Audit Details & Notes</th>
                                    <th style="padding: 12px 14px; text-align: right;">Contextual Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($auditLogs as $log): ?>
                                    <?php
                                    // Badge styling based on action type
                                    $actionType = $log->action_type;
                                    $badgeColor = '#475569';
                                    $badgeBg = '#f1f5f9';

                                    if (str_contains($actionType, 'APPROVED') || str_contains($actionType, 'ACTIVATED') || str_contains($actionType, 'MATCH')) {
                                        $badgeColor = '#166534';
                                        $badgeBg = '#dcfce7';
                                    } elseif (str_contains($actionType, 'REJECTED') || str_contains($actionType, 'SUSPENDED') || str_contains($actionType, 'DELETED') || str_contains($actionType, 'UNMATCHED')) {
                                        $badgeColor = '#991b1b';
                                        $badgeBg = '#fee2e2';
                                    } elseif (str_contains($actionType, 'PRO_') || str_contains($actionType, 'RESET')) {
                                        $badgeColor = '#0369a1';
                                        $badgeBg = '#e0f2fe';
                                    }
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 14px; white-space: nowrap;">
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.84rem;">
                                                <?= date('M d, Y H:i:s', strtotime($log->created_at)) ?>
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                                                <i class="fa fa-user-shield" style="font-size: 0.72rem;"></i> <?= h($log->actor_email) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <span style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                                <?= h($log->action_type) ?>
                                            </span>
                                            <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px; text-transform: uppercase;">
                                                <?= h($log->category) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-weight: 700; color: #0f172a;">
                                                <?php if ($log->target_type === 'teacher' && $log->target_id): ?>
                                                    <a href="/teacher/profile?teacher_id=<?= $log->target_id ?>" target="_blank" style="color: #0f766e; text-decoration: none;">
                                                        <?= h($log->target_name ?: 'Teacher #' . $log->target_id) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.7rem; opacity: 0.7;"></i>
                                                    </a>
                                                <?php elseif ($log->target_type === 'school' && $log->target_id): ?>
                                                    <a href="/admin/jobs?school_id=<?= $log->target_id ?>" target="_blank" style="color: #0f766e; text-decoration: none;">
                                                        <?= h($log->target_name ?: 'School #' . $log->target_id) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.7rem; opacity: 0.7;"></i>
                                                    </a>
                                                <?php else: ?>
                                                    <?= h($log->target_name ?: 'Target #' . $log->target_id) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                                Entity: <code><?= h($log->target_type) ?> #<?= intval($log->target_id) ?></code>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.84rem; color: #334155;">
                                                <?= h($log->details) ?>
                                            </div>
                                            <?php if (!empty($log->notes)): ?>
                                                <div style="font-size: 0.78rem; color: #b45309; background: #fffbeb; border-left: 3px solid #f59e0b; padding: 4px 8px; margin-top: 4px; border-radius: 0 4px 4px 0;">
                                                    <strong>Dispatched Note:</strong> <?= h($log->notes) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <div style="display: inline-flex; gap: 4px; justify-content: flex-end; flex-wrap: wrap;">
                                                <!-- Actionable Revert & Inspect Shortcuts -->
                                                <?php if (str_contains($log->action_type, 'CLEARANCE_REJECTED') && $log->target_id): ?>
                                                    <form method="POST" action="/admin/audit" style="margin: 0; display: inline-block;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="audit_id" value="<?= $log->id ?>">
                                                        <input type="hidden" name="target_id" value="<?= $log->target_id ?>">
                                                        <input type="hidden" name="target_type" value="teacher">
                                                        <button type="submit" name="revert_action" value="reapprove" onclick="return confirm('Re-approve clearance for this educator?')" style="background: #16a34a; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;">
                                                            Re-Approve
                                                        </button>
                                                    </form>
                                                <?php elseif (str_contains($log->action_type, 'SUSPENDED') && $log->target_id): ?>
                                                    <form method="POST" action="/admin/audit" style="margin: 0; display: inline-block;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="audit_id" value="<?= $log->id ?>">
                                                        <input type="hidden" name="target_id" value="<?= $log->target_id ?>">
                                                        <input type="hidden" name="target_type" value="<?= h($log->target_type) ?>">
                                                        <button type="submit" name="revert_action" value="activate" onclick="return confirm('Reactivate this account?')" style="background: #0f766e; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;">
                                                            Reactivate
                                                        </button>
                                                    </form>
                                                <?php elseif (str_contains($log->action_type, 'PRO_ACTIVATED') && $log->target_id): ?>
                                                    <form method="POST" action="/admin/audit" style="margin: 0; display: inline-block;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="audit_id" value="<?= $log->id ?>">
                                                        <input type="hidden" name="target_id" value="<?= $log->target_id ?>">
                                                        <input type="hidden" name="target_type" value="school">
                                                        <button type="submit" name="revert_action" value="revoke_pro" onclick="return confirm('Revoke Pro plan?')" style="background: #f8fafc; color: #64748b; font-size: 0.74rem; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                                                            Revoke Pro
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($log->target_type === 'teacher' && $log->target_id): ?>
                                                    <a href="/teacher/profile?teacher_id=<?= $log->target_id ?>" target="_blank" style="background: #f1f5f9; color: #334155; padding: 4px 8px; border-radius: 4px; font-size: 0.74rem; font-weight: 600; text-decoration: none; border: 1px solid #e2e8f0;">
                                                        Profile &rarr;
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.82rem; color: #64748b;">
                                Page <?= $page ?> of <?= $totalPages ?> (<?= number_format($totalLogs) ?> events)
                            </span>
                            <div style="display: flex; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="/admin/audit?p=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($category) ?>&timeframe=<?= urlencode($timeframe) ?>" style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
                                        &laquo; Prev
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="/admin/audit?p=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&cat=<?= urlencode($category) ?>&timeframe=<?= urlencode($timeframe) ?>" style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
                                        Next &raquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>
