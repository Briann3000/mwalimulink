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

<style>
.admin-table-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
}
.admin-table th, .admin-table td {
    padding: 10px 12px;
    vertical-align: middle;
}
@media (max-width: 768px) {
    .admin-header-flex {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    .admin-filter-form {
        flex-direction: column !important;
    }
    .admin-filter-form > div, .admin-filter-form button {
        width: 100% !important;
    }
    .stat-grid-responsive {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
        gap: 0.5rem !important;
    }
}
</style>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1200px; margin: 0 auto; padding: 1.25rem 1rem;">
        <div style="padding-bottom: 3rem;">
            
            <!-- Breadcrumb & Header -->
            <div class="admin-header-flex" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 4px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; font-size: 1.35rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-clipboard-list" style="color: #0f766e;"></i> System & Administrative Audit Trail
                    </h2>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="/admin/audit?export=csv<?= $search ? '&q=' . urlencode($search) : '' ?><?= $category ? '&cat=' . urlencode($category) : '' ?>" style="background: #0f766e; color: white !important; padding: 7px 14px; border-radius: 6px; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-file-csv"></i> Export CSV
                    </a>
                </div>
            </div>

            <?php if ($msg): ?>
                <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-check"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-exclamation"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metrics Summary Cards -->
            <div class="stat-grid-responsive" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Total Events</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-top: 2px;"><?= number_format($statTotal) ?></div>
                </div>
                <div style="background: white; border: 1px solid #ccfbf1; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #0f766e; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Clearance & Background</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #0f766e; margin-top: 2px;"><?= number_format($statClearance) ?></div>
                </div>
                <div style="background: white; border: 1px solid #e0f2fe; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #0369a1; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">School Operations</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #0284c7; margin-top: 2px;"><?= number_format($statSchools) ?></div>
                </div>
                <div style="background: white; border: 1px solid #f3e8ff; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #7e22ce; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Educator Accounts</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #9333ea; margin-top: 2px;"><?= number_format($statTeachers) ?></div>
                </div>
                <div style="background: white; border: 1px solid #fef3c7; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #b45309; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Vacancies</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #d97706; margin-top: 2px;"><?= number_format($statJobs) ?></div>
                </div>
            </div>

            <!-- Filter & Search Controls -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem 1rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/audit" class="admin-filter-form" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin: 0;">
                    <div style="flex: 2; min-width: 200px;">
                        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search target name, email, action, reason notes..." style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <select name="cat" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white; box-sizing: border-box;">
                            <option value="all">All Event Categories</option>
                            <option value="clearance" <?= $category === 'clearance' ? 'selected' : '' ?>>Clearances & Verifications</option>
                            <option value="school" <?= $category === 'school' ? 'selected' : '' ?>>School Subscriptions & Status</option>
                            <option value="teacher" <?= $category === 'teacher' ? 'selected' : '' ?>>Educator Accounts</option>
                            <option value="job" <?= $category === 'job' ? 'selected' : '' ?>>Job Vacancies</option>
                            <option value="publication" <?= $category === 'publication' ? 'selected' : '' ?>>Publications Hub</option>
                            <option value="security" <?= $category === 'security' ? 'selected' : '' ?>>Security & Logins</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 130px;">
                        <select name="timeframe" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white; box-sizing: border-box;">
                            <option value="">All Time</option>
                            <option value="today" <?= $timeframe === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="7days" <?= $timeframe === '7days' ? 'selected' : '' ?>>Past 7 Days</option>
                            <option value="30days" <?= $timeframe === '30days' ? 'selected' : '' ?>>Past 30 Days</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="background: #0f766e; color: white !important; padding: 7px 14px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $category || $timeframe): ?>
                            <a href="/admin/audit" style="margin-left: 6px; font-size: 0.82rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Audit Trail Table -->
            <div class="admin-table-container">
                <?php if (empty($auditLogs)): ?>
                    <div style="padding: 3.5rem; text-align: center; color: #64748b;">
                        <i class="fa fa-clipboard-check" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 1rem;">No audit logs matched your search filters.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569; font-size: 0.76rem; text-transform: uppercase;">
                                <th style="padding: 12px 14px; min-width: 135px;">Timestamp & Actor</th>
                                <th style="padding: 12px 14px; min-width: 130px;">Action / Event</th>
                                <th style="padding: 12px 14px; min-width: 160px;">Target Entity</th>
                                <th style="padding: 12px 14px; min-width: 220px;">Audit Details & Notes</th>
                                <th style="padding: 12px 14px; text-align: center; width: 60px;">Actions</th>
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
                                } elseif (str_contains($actionType, 'PRO_') || str_contains($actionType, 'RESET') || str_contains($actionType, 'MANUAL_MPESA')) {
                                    $badgeColor = '#0369a1';
                                    $badgeBg = '#e0f2fe';
                                }
                                ?>
                                <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 700; color: #0f172a; font-size: 0.84rem; white-space: nowrap;">
                                            <?= date('M d, Y H:i:s', strtotime($log->created_at)) ?>
                                        </div>
                                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px; overflow-wrap: anywhere;">
                                            <i class="fa fa-user-shield" style="font-size: 0.72rem; color: #0f766e;"></i> <?= h($log->actor_email) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <span style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                                            <?= h($log->action_type) ?>
                                        </span>
                                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px; text-transform: uppercase; font-weight: 600;">
                                            <?= h($log->category) ?>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <div style="font-weight: 700; color: #0f172a; overflow-wrap: anywhere;">
                                            <?php if ($log->target_type === 'teacher' && $log->target_id): ?>
                                                <a href="/teacher/profile?teacher_id=<?= $log->target_id ?>" target="_blank" style="color: #0f766e; text-decoration: underline;">
                                                    <?= h($log->target_name ?: 'Teacher #' . $log->target_id) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.7rem; opacity: 0.7;"></i>
                                                </a>
                                            <?php elseif ($log->target_type === 'school' && $log->target_id): ?>
                                                <a href="/admin/jobs?school_id=<?= $log->target_id ?>" target="_blank" style="color: #0f766e; text-decoration: underline;">
                                                    <?= h($log->target_name ?: 'School #' . $log->target_id) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.7rem; opacity: 0.7;"></i>
                                                </a>
                                            <?php else: ?>
                                                <?= h($log->target_name ?: 'Target #' . $log->target_id) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.74rem; color: #64748b; margin-top: 2px;">
                                            Entity: <code style="background: #f1f5f9; padding: 1px 4px; border-radius: 3px;"><?= h($log->target_type) ?> #<?= intval($log->target_id) ?></code>
                                        </div>
                                    </td>
                                    <td style="padding: 12px 14px;">
                                        <div style="font-size: 0.84rem; color: #334155; overflow-wrap: anywhere;">
                                            <?= h($log->details) ?>
                                        </div>
                                        <?php if (!empty($log->notes)): ?>
                                            <div style="font-size: 0.76rem; color: #b45309; background: #fffbeb; border-left: 3px solid #f59e0b; padding: 4px 8px; margin-top: 4px; border-radius: 0 4px 4px 0;">
                                                <strong>Notes:</strong> <?= h($log->notes) ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 12px 14px; text-align: center; position: relative;">
                                        <!-- 3-Dot Dropdown Menu -->
                                        <div style="position: relative; display: inline-block;">
                                            <button type="button" onclick="toggleAuditMenu(event, <?= $log->id ?>)" style="background: transparent; border: 1px solid transparent; border-radius: 6px; padding: 6px 10px; cursor: pointer; color: #64748b; font-size: 1rem; transition: all 0.15s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'" title="Actions">
                                                <i class="fa fa-ellipsis-v"></i>
                                            </button>

                                            <div id="audit-menu-<?= $log->id ?>" class="audit-dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; z-index: 100; min-width: 190px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 4px 0; text-align: left;">
                                                <?php if (str_contains($log->action_type, 'CLEARANCE_REJECTED') && $log->target_id): ?>
                                                    <form method="POST" action="/admin/audit" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="audit_id" value="<?= $log->id ?>">
                                                        <input type="hidden" name="target_id" value="<?= $log->target_id ?>">
                                                        <input type="hidden" name="target_type" value="teacher">
                                                        <button type="submit" name="revert_action" value="reapprove" onclick="return confirm('Re-approve clearance for this educator?')" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #166534; font-weight: 600; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0fdf4'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-check-circle" style="width: 16px;"></i> Re-Approve Clearance
                                                        </button>
                                                    </form>
                                                <?php elseif (str_contains($log->action_type, 'SUSPENDED') && $log->target_id): ?>
                                                    <form method="POST" action="/admin/audit" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="audit_id" value="<?= $log->id ?>">
                                                        <input type="hidden" name="target_id" value="<?= $log->target_id ?>">
                                                        <input type="hidden" name="target_type" value="<?= h($log->target_type) ?>">
                                                        <button type="submit" name="revert_action" value="activate" onclick="return confirm('Reactivate this account?')" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #0f766e; font-weight: 600; cursor: pointer; text-align: left;" onmouseover="this.style.background='#f0fdfa'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-circle-check" style="width: 16px;"></i> Reactivate Account
                                                        </button>
                                                    </form>
                                                <?php elseif (str_contains($log->action_type, 'PRO_ACTIVATED') && $log->target_id): ?>
                                                    <form method="POST" action="/admin/audit" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="audit_id" value="<?= $log->id ?>">
                                                        <input type="hidden" name="target_id" value="<?= $log->target_id ?>">
                                                        <input type="hidden" name="target_type" value="school">
                                                        <button type="submit" name="revert_action" value="revoke_pro" onclick="return confirm('Revoke Pro plan?')" style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #b45309; cursor: pointer; text-align: left;" onmouseover="this.style.background='#fffbeb'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-ban" style="width: 16px;"></i> Revoke Pro Tier
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($log->target_type === 'teacher' && $log->target_id): ?>
                                                    <a href="/teacher/profile?teacher_id=<?= $log->target_id ?>" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; text-decoration: none;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                        <i class="fa fa-user" style="width: 16px;"></i> View Teacher Profile
                                                    </a>
                                                <?php elseif ($log->target_type === 'school' && $log->target_id): ?>
                                                    <a href="/admin/jobs?school_id=<?= $log->target_id ?>" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; text-decoration: none;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                        <i class="fa fa-school" style="width: 16px;"></i> View School Vacancies
                                                    </a>
                                                <?php endif; ?>

                                                <a href="/admin/audit?q=<?= urlencode($log->actor_email) ?>" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #334155; text-decoration: none;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                                    <i class="fa fa-filter" style="width: 16px; color: #64748b;"></i> Filter by this Actor
                                                </a>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

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

<script>
function toggleAuditMenu(event, logId) {
    event.stopPropagation();
    var targetMenu = document.getElementById('audit-menu-' + logId);
    var isVisible = targetMenu && targetMenu.style.display === 'block';

    // Close all open audit menus first
    document.querySelectorAll('.audit-dropdown-menu').forEach(function(menu) {
        menu.style.display = 'none';
    });

    if (targetMenu && !isVisible) {
        targetMenu.style.display = 'block';
    }
}

// Global click-outside listener
document.addEventListener('click', function(event) {
    if (!event.target.closest('.audit-dropdown-menu') && !event.target.closest('button')) {
        document.querySelectorAll('.audit-dropdown-menu').forEach(function(menu) {
            menu.style.display = 'none';
        });
    }
});
</script>
