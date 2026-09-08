<?php
// views/admin_teachers.php - Admin Educator Management Portal
require_auth('admin');

$msg = '';
$error = '';

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $teacher_id = intval($_POST['teacher_id'] ?? 0);
        $action = $_POST['admin_action'] ?? '';
        $notes = trim($_POST['admin_notes'] ?? '');

        $teacher = R::load('teacher', $teacher_id);
        if ($teacher && $teacher->id) {
            if ($action === 'approve') {
                $teacher->verification_status = 'verified';
                $teacher->good_conduct_status = 'verified';
                $teacher->verified_at = date('Y-m-d H:i:s');
                $teacher->verified_source = 'admin_manual_clearance';
                R::store($teacher);
                if (function_exists('send_verification_status_email')) {
                    send_verification_status_email($teacher, 'verified', $notes);
                }
                log_admin_audit('clearance', 'CLEARANCE_APPROVED', 'teacher', $teacher->id, $teacher->name, "Educator clearance approved manually", $notes);
                $msg = "Educator " . htmlspecialchars($teacher->name) . " verified successfully. Confirmation email dispatched.";
            } elseif ($action === 'reject') {
                $teacher->verification_status = 'failed';
                $teacher->good_conduct_status = 'failed';
                R::store($teacher);
                if (function_exists('send_verification_status_email')) {
                    send_verification_status_email($teacher, 'failed', $notes ?: 'Document details could not be validated against TSC / DCI records.');
                }
                log_admin_audit('clearance', 'CLEARANCE_REJECTED', 'teacher', $teacher->id, $teacher->name, "Educator clearance flagged/rejected", $notes);
                $msg = "Educator " . htmlspecialchars($teacher->name) . " clearance flagged/rejected. Notification email dispatched.";
            } elseif ($action === 'suspend') {
                $teacher->status = 'suspended';
                R::store($teacher);
                log_admin_audit('teacher', 'ACCOUNT_SUSPENDED', 'teacher', $teacher->id, $teacher->name, "Educator account suspended by admin");
                $msg = "Account for " . htmlspecialchars($teacher->name) . " has been suspended.";
            } elseif ($action === 'activate') {
                $teacher->status = 'available';
                R::store($teacher);
                log_admin_audit('teacher', 'ACCOUNT_ACTIVATED', 'teacher', $teacher->id, $teacher->name, "Educator account reactivated by admin");
                $msg = "Account for " . htmlspecialchars($teacher->name) . " has been reactivated.";
            } elseif ($action === 'reset') {
                $teacher->verification_status = 'none';
                $teacher->good_conduct_status = 'unverified';
                $teacher->good_conduct_doc = null;
                $teacher->good_conduct_cert_no = null;
                R::store($teacher);
                log_admin_audit('clearance', 'CLEARANCE_RESET', 'teacher', $teacher->id, $teacher->name, "Clearance status reset by admin");
                $msg = "Clearance data reset for " . htmlspecialchars($teacher->name) . ".";
            }
        } else {
            $error = "Educator account not found.";
        }
    }
}

// Filters & Search
$search = trim($_GET['q'] ?? '');
$vStatus = trim($_GET['v_status'] ?? '');
$accStatus = trim($_GET['acc_status'] ?? '');
$county = trim($_GET['county'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = ['1=1'];

if ($search !== '') {
    $whereClauses[] = '(name LIKE ? OR email LIKE ? OR tsc_number LIKE ? OR national_id LIKE ? OR mobile LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($vStatus !== '' && $vStatus !== 'all') {
    $whereClauses[] = 'verification_status = ?';
    $params[] = $vStatus;
}

if ($accStatus !== '' && $accStatus !== 'all') {
    $whereClauses[] = 'status = ?';
    $params[] = $accStatus;
}

if ($county !== '' && $county !== 'all') {
    $whereClauses[] = 'county = ?';
    $params[] = $county;
}

$whereSql = implode(' AND ', $whereClauses);
$totalTeachers = R::count('teacher', $whereSql, $params);
$teachers = R::find('teacher', "{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

// Aggregate statistics
$statTotal = R::count('teacher');
$statVerified = R::count('teacher', 'verification_status = ?', ['verified']);
$statPending = R::count('teacher', 'verification_status = ?', ['pending']);
$statFlagged = R::count('teacher', 'verification_status = ?', ['failed']);
$statSuspended = R::count('teacher', 'status = ?', ['suspended']);

$totalPages = ceil($totalTeachers / $limit);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">
            
            <!-- Breadcrumb & Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-chalkboard-user" style="color: #0f766e;"></i> Educator Operations & Profiles
                    </h2>
                </div>
                <div>
                    <span style="background: #f1f5f9; color: #475569; padding: 6px 12px; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                        Showing <?= count($teachers) ?> of <?= number_format($totalTeachers) ?> Teachers
                    </span>
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

            <!-- Metric Summary Cards -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Total Registered</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 4px;"><?= number_format($statTotal) ?></div>
                </div>
                <div style="background: white; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #166534; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Verified Clearance</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a; margin-top: 4px;"><?= number_format($statVerified) ?></div>
                </div>
                <div style="background: white; border: 1px solid #fef08a; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #854d0e; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Pending Review</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #ca8a04; margin-top: 4px;"><?= number_format($statPending) ?></div>
                </div>
                <div style="background: white; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #991b1b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Flagged / Action Req.</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #dc2626; margin-top: 4px;"><?= number_format($statFlagged) ?></div>
                </div>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Suspended Accounts</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #64748b; margin-top: 4px;"><?= number_format($statSuspended) ?></div>
                </div>
            </div>

            <!-- Filter & Search Controls -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/teachers" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; margin: 0;">
                    <div style="flex: 2; min-width: 220px;">
                        <input type="text" name="q" value="<?= h($search) ?>" placeholder="Search by name, email, TSC, ID, phone..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 150px;">
                        <select name="v_status" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;">
                            <option value="all">All Clearance</option>
                            <option value="verified" <?= $vStatus === 'verified' ? 'selected' : '' ?>>Verified Only</option>
                            <option value="pending" <?= $vStatus === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                            <option value="failed" <?= $vStatus === 'failed' ? 'selected' : '' ?>>Flagged / Action Req.</option>
                            <option value="none" <?= $vStatus === 'none' ? 'selected' : '' ?>>No Documents Submitted</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="acc_status" style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; box-sizing: border-box;">
                            <option value="all">All Accounts</option>
                            <option value="available" <?= $accStatus === 'available' ? 'selected' : '' ?>>Active / Available</option>
                            <option value="placed" <?= $accStatus === 'placed' ? 'selected' : '' ?>>Placed / Employed</option>
                            <option value="suspended" <?= $accStatus === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" style="background: #0f766e; color: white !important; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.88rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $vStatus || $accStatus || $county): ?>
                            <a href="/admin/teachers" style="margin-left: 6px; font-size: 0.84rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Teachers List Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden;">
                <?php if (empty($teachers)): ?>
                    <div style="padding: 3rem; text-align: center; color: #64748b;">
                        <i class="fa fa-user-slash" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 1rem;">No educators matched your search and filter criteria.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                    <th style="padding: 12px 14px;">Educator Details</th>
                                    <th style="padding: 12px 14px;">Credentials</th>
                                    <th style="padding: 12px 14px;">Subject / County</th>
                                    <th style="padding: 12px 14px;">Clearance Standing</th>
                                    <th style="padding: 12px 14px;">Account</th>
                                    <th style="padding: 12px 14px; text-align: right;">Operations</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($teachers as $t): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 14px;">
                                            <div style="font-weight: 700; color: #0f172a;">
                                                <a href="/teacher/profile?teacher_id=<?= $t->id ?>" target="_blank" style="color: #0f766e; text-decoration: none;" title="Open profile in new tab">
                                                    <?= h($t->name) ?> <i class="fa fa-external-link" style="font-size: 0.72rem; opacity: 0.7;"></i>
                                                </a>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #64748b; margin-top: 2px;">
                                                <span><i class="fa fa-envelope" style="font-size: 0.75rem;"></i> <?= h($t->email) ?></span>
                                                <?php if ($t->mobile): ?>
                                                    <span style="margin-left: 8px;"><i class="fa fa-phone" style="font-size: 0.75rem;"></i> <?= h($t->mobile) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.82rem;">
                                                TSC: <code style="font-weight: 700; color: #0f766e;"><?= h($t->tsc_number ?: 'Not Provided') ?></code>
                                            </div>
                                            <?php if ($t->national_id): ?>
                                                <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                                                    ID: <?= h($t->national_id) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.82rem; color: #334155;">
                                                <?= h($t->subject_1 ?: 'General') ?><?= $t->subject_2 ? ' / ' . h($t->subject_2) : '' ?>
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b; margin-top: 2px;">
                                                <?= h($t->county ?: 'Kenya') ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <?php if ($t->verification_status === 'verified'): ?>
                                                <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-check-circle"></i> Verified
                                                </span>
                                            <?php elseif ($t->verification_status === 'failed'): ?>
                                                <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-triangle-exclamation"></i> Action Req.
                                                </span>
                                            <?php elseif ($t->verification_status === 'pending'): ?>
                                                <span style="background: #fef08a; color: #854d0e; font-weight: 700; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-clock"></i> Pending Review
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #f1f5f9; color: #64748b; font-size: 0.74rem; padding: 4px 8px; border-radius: 4px;">
                                                    Unverified
                                                </span>
                                            <?php endif; ?>

                                            <?php if (!empty($t->good_conduct_doc)): ?>
                                                <div style="margin-top: 4px;">
                                                    <a href="/<?= h($t->good_conduct_doc) ?>" target="_blank" style="font-size: 0.75rem; color: #0284c7; text-decoration: underline;">
                                                        <i class="fa fa-file-pdf"></i> View Doc
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <?php if ($t->status === 'suspended'): ?>
                                                <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">Suspended</span>
                                            <?php elseif ($t->status === 'placed'): ?>
                                                <span style="background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">Placed</span>
                                            <?php else: ?>
                                                <span style="background: #f0fdf4; color: #166534; font-weight: 600; font-size: 0.74rem; padding: 3px 8px; border-radius: 4px;">Active</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <div style="display: inline-flex; gap: 4px; flex-wrap: wrap; justify-content: flex-end;">
                                                <!-- Action Form -->
                                                <form method="POST" action="/admin/teachers" style="margin: 0; display: inline-flex; gap: 4px;" id="teacher-form-<?= $t->id ?>">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="teacher_id" value="<?= $t->id ?>">
                                                    <input type="hidden" name="admin_notes" id="notes-input-<?= $t->id ?>" value="">
                                                    
                                                    <button type="submit" name="admin_action" value="approve" title="Approve & Dispatch Verified Email" style="background: #16a34a; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 5px 9px; border: none; border-radius: 4px; cursor: pointer;">
                                                        <i class="fa fa-check"></i> Approve
                                                    </button>

                                                    <button type="button" onclick="promptReject(<?= $t->id ?>, '<?= addslashes(h($t->name)) ?>')" title="Reject / Request Update" style="background: #ef4444; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 5px 9px; border: none; border-radius: 4px; cursor: pointer;">
                                                        <i class="fa fa-times"></i> Reject
                                                    </button>

                                                    <?php if ($t->status === 'suspended'): ?>
                                                        <button type="submit" name="admin_action" value="activate" title="Reactivate Educator" style="background: #f1f5f9; color: #1e293b; font-size: 0.74rem; padding: 5px 9px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                                                            Activate
                                                        </button>
                                                    <?php else: ?>
                                                        <button type="submit" name="admin_action" value="suspend" onclick="return confirm('Suspend this educator account?')" title="Suspend Educator" style="background: #f8fafc; color: #64748b; font-size: 0.74rem; padding: 5px 9px; border: 1px solid #e2e8f0; border-radius: 4px; cursor: pointer;">
                                                            Suspend
                                                        </button>
                                                    <?php endif; ?>
                                                </form>
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
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                            <div style="display: flex; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="/admin/teachers?p=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&v_status=<?= urlencode($vStatus) ?>&acc_status=<?= urlencode($accStatus) ?>" style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
                                        &laquo; Prev
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="/admin/teachers?p=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&v_status=<?= urlencode($vStatus) ?>&acc_status=<?= urlencode($accStatus) ?>" style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
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

<!-- Modal / Reason Prompt Script -->
<script>
function promptReject(teacherId, teacherName) {
    const reason = prompt('Please enter the reason for flagging / rejecting ' + teacherName + ' clearance (will be emailed to educator):', 'Document details could not be validated against official registry.');
    if (reason !== null) {
        const form = document.getElementById('teacher-form-' + teacherId);
        document.getElementById('notes-input-' + teacherId).value = reason;
        
        const actionInput = document.createElement('input');
        actionInput.type = 'hidden';
        actionInput.name = 'admin_action';
        actionInput.value = 'reject';
        form.appendChild(actionInput);
        
        form.submit();
    }
}
</script>
