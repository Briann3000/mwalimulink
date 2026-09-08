<?php
// views/admin_verifications.php - Admin Verification & Clearance Queue
require_auth('admin');

$msg = '';
$error = '';

// Handle manual and bulk admin actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $action = $_POST['admin_action'] ?? '';
        $notes = trim($_POST['admin_notes'] ?? '');

        // --- 1. BULK OPERATIONS ---
        if ($action === 'bulk_approve' || $action === 'bulk_reject') {
            $selectedLogIds = $_POST['selected_logs'] ?? [];
            $selectedTeacherIds = $_POST['selected_teachers'] ?? [];
            $countAffected = 0;

            // Process selected automated TSC audit logs
            if (!empty($selectedLogIds) && is_array($selectedLogIds)) {
                foreach ($selectedLogIds as $lId) {
                    $lId = intval($lId);
                    $log = R::load('tscverificationlog', $lId);
                    if ($log && $log->id) {
                        $log->is_resolved = 1;
                        $log->resolved_at = date('Y-m-d H:i:s');
                        $log->admin_notes = $notes;

                        $t = R::load('teacher', $log->teacher_id);
                        if ($action === 'bulk_approve') {
                            $log->status = 'verified';
                            if ($t && $t->id) {
                                $t->verification_status = 'verified';
                                $t->good_conduct_status = 'verified';
                                $t->verified_at = date('Y-m-d H:i:s');
                                $t->verified_source = 'admin_bulk_tsc_override';
                                R::store($t);
                                if (function_exists('send_verification_status_email')) {
                                    send_verification_status_email($t, 'verified', $notes);
                                }
                                log_admin_audit('clearance', 'CLEARANCE_APPROVED', 'teacher', $t->id, $t->name, "Bulk approved via TSC audit log #{$log->id}", $notes);
                            }
                        } else {
                            $log->status = 'rejected';
                            if ($t && $t->id) {
                                $t->verification_status = 'failed';
                                $t->good_conduct_status = 'failed';
                                R::store($t);
                                if (function_exists('send_verification_status_email')) {
                                    send_verification_status_email($t, 'failed', $notes ?: 'Details could not be verified against official TSC records.');
                                }
                                log_admin_audit('clearance', 'CLEARANCE_REJECTED', 'teacher', $t->id, $t->name, "Bulk rejected via TSC audit log #{$log->id}", $notes);
                            }
                        }
                        R::store($log);
                        $countAffected++;
                    }
                }
            }

            // Process selected manual teacher documents
            if (!empty($selectedTeacherIds) && is_array($selectedTeacherIds)) {
                foreach ($selectedTeacherIds as $tId) {
                    $tId = intval($tId);
                    $t = R::load('teacher', $tId);
                    if ($t && $t->id) {
                        if ($action === 'bulk_approve') {
                            $t->verification_status = 'verified';
                            $t->good_conduct_status = 'verified';
                            $t->verified_at = date('Y-m-d H:i:s');
                            $t->verified_source = 'admin_bulk_doc_audit';
                            R::store($t);
                            if (function_exists('send_verification_status_email')) {
                                send_verification_status_email($t, 'verified', $notes);
                            }
                            log_admin_audit('clearance', 'CLEARANCE_APPROVED', 'teacher', $t->id, $t->name, "Bulk document verification approved", $notes);
                        } else {
                            $t->verification_status = 'failed';
                            $t->good_conduct_status = 'failed';
                            R::store($t);
                            if (function_exists('send_verification_status_email')) {
                                send_verification_status_email($t, 'failed', $notes ?: 'Document details could not be verified against official records.');
                            }
                            log_admin_audit('clearance', 'CLEARANCE_REJECTED', 'teacher', $t->id, $t->name, "Bulk document verification rejected", $notes);
                        }
                        $countAffected++;
                    }
                }
            }

            $msg = "Bulk " . ($action === 'bulk_approve' ? 'approval' : 'rejection') . " completed for {$countAffected} record(s). Items resolved and cleared from queue.";
        }

        // --- 2. SINGLE ROW OPERATIONS ---
        else {
            $teacher_id = intval($_POST['teacher_id'] ?? 0);
            $log_id = intval($_POST['log_id'] ?? 0);

            if ($log_id > 0) {
                $log = R::load('tscverificationlog', $log_id);
                if ($log && $log->id) {
                    $log->is_resolved = 1;
                    $log->resolved_at = date('Y-m-d H:i:s');
                    $log->admin_notes = $notes;

                    if ($action === 'approve' || $action === 'approve_tsc') {
                        $log->status = 'verified';
                    } elseif ($action === 'flag' || $action === 'reject') {
                        $log->status = 'rejected';
                    }
                    R::store($log);
                }
            }

            $teacher = R::load('teacher', $teacher_id);
            if ($teacher && $teacher->id) {
                if ($action === 'approve' || $action === 'approve_tsc') {
                    $teacher->verification_status = 'verified';
                    if ($action === 'approve') {
                        $teacher->good_conduct_status = 'verified';
                    }
                    $teacher->verified_at = date('Y-m-d H:i:s');
                    $teacher->verified_source = ($action === 'approve_tsc') ? 'admin_tsc_override' : 'admin_document_audit';
                    R::store($teacher);
                    if (function_exists('send_verification_status_email')) {
                        send_verification_status_email($teacher, 'verified', $notes);
                    }
                    log_admin_audit('clearance', 'CLEARANCE_APPROVED', 'teacher', $teacher->id, $teacher->name, "Clearance approved by admin", $notes);
                    $msg = "Teacher " . htmlspecialchars($teacher->name) . " marked as Verified and cleared from queue!";
                } elseif ($action === 'flag' || $action === 'reject') {
                    $teacher->verification_status = 'failed';
                    $teacher->good_conduct_status = 'failed';
                    R::store($teacher);
                    if (function_exists('send_verification_status_email')) {
                        send_verification_status_email($teacher, 'failed', $notes ?: 'Document details could not be validated against official registry.');
                    }
                    log_admin_audit('clearance', 'CLEARANCE_REJECTED', 'teacher', $teacher->id, $teacher->name, "Clearance flagged/rejected by admin", $notes);
                    $msg = "Teacher " . htmlspecialchars($teacher->name) . " clearance rejected / marked as Action Required and cleared from queue!";
                } elseif ($action === 'reset') {
                    $teacher->verification_status = 'none';
                    $teacher->good_conduct_status = 'unverified';
                    $teacher->good_conduct_doc = null;
                    $teacher->good_conduct_cert_no = null;
                    R::store($teacher);
                    log_admin_audit('clearance', 'CLEARANCE_RESET', 'teacher', $teacher->id, $teacher->name, "Clearance details reset by admin");
                    $msg = "Clearance reset for " . htmlspecialchars($teacher->name) . ".";
                }
            }
        }
    }
}

// Fetch ONLY unresolved / pending automated TSC audit logs for the queue
$tscAuditLogs = R::find('tscverificationlog', '(is_resolved IS NULL OR is_resolved = 0) ORDER BY id DESC LIMIT 50');

// Fetch pending document requests & standings
$pendingTeachers = R::find('teacher', "verification_status = 'pending' OR (good_conduct_doc IS NOT NULL AND (verification_status IS NULL OR verification_status = 'pending')) ORDER BY id DESC");
$allTeachersWithDocs = R::find('teacher', "good_conduct_doc IS NOT NULL OR good_conduct_cert_no IS NOT NULL OR verification_status != 'none' ORDER BY id DESC LIMIT 50");
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1150px; margin: 0 auto; padding-bottom: 3rem;">
            
            <!-- Breadcrumb & Header -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-shield-halved" style="color: #0f766e;"></i> Educator Clearance & Background Verification Queue
                    </h2>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="/admin/audit" style="background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-clipboard-list"></i> View Audit Trail
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

            <!-- Automated TSC Portal Verification Audits Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
                
                <form method="POST" action="/admin/verifications" id="bulk-tsc-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" id="bulk-tsc-action" value="">
                    <input type="hidden" name="admin_notes" id="bulk-tsc-notes" value="">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; color: #0f766e; display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-robot"></i> Automated TSC Verification Audits (<?= count($tscAuditLogs) ?>)
                            </h3>
                            <p style="margin: 3px 0 0; font-size: 0.8rem; color: #64748b;">
                                Live background checks against the Teachers Service Commission online database awaiting action.
                            </p>
                        </div>

                        <!-- Bulk Action Controls -->
                        <?php if (!empty($tscAuditLogs)): ?>
                            <div style="display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <span style="font-size: 0.82rem; color: #475569; font-weight: 600;" id="tsc-selected-count">0 selected</span>
                                <button type="button" onclick="executeBulkAction('bulk-tsc-form', 'bulk-tsc-action', 'bulk_approve', 'Approve all selected records and clear them from the queue?')" style="background: #16a34a; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;">
                                    <i class="fa fa-check"></i> Bulk Approve
                                </button>
                                <button type="button" onclick="executeBulkReject('bulk-tsc-form', 'bulk-tsc-action', 'bulk-tsc-notes')" style="background: #ef4444; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;">
                                    <i class="fa fa-times"></i> Bulk Reject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($tscAuditLogs)): ?>
                        <div style="padding: 2.5rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                            <i class="fa fa-circle-check" style="font-size: 2.2rem; color: #16a34a; margin-bottom: 8px;"></i>
                            <h4 style="margin: 4px 0; color: #0f172a; font-size: 1rem;">Clearance Queue is Clear!</h4>
                            <p style="margin: 0; font-size: 0.86rem; color: #64748b;">
                                All automated background checks have been reviewed. Past actions can be viewed in the <a href="/admin/audit" style="color: #0f766e; text-decoration: underline; font-weight: 600;">Audit Trail</a>.
                            </p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.86rem;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b; background: #f8fafc;">
                                        <th style="padding: 10px; width: 36px; text-align: center;">
                                            <input type="checkbox" id="select-all-tsc" onchange="toggleSelectAll('tsc-checkbox', this.checked, 'tsc-selected-count')" style="width: 16px; height: 16px; cursor: pointer;">
                                        </th>
                                        <th style="padding: 10px;">Timestamp</th>
                                        <th style="padding: 10px;">Educator</th>
                                        <th style="padding: 10px;">TSC Number</th>
                                        <th style="padding: 10px;">Portal Match</th>
                                        <th style="padding: 10px;">Score</th>
                                        <th style="padding: 10px;">Standing</th>
                                        <th style="padding: 10px; text-align: right;">Manual Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($tscAuditLogs as $log): ?>
                                        <?php $teacherObj = R::load('teacher', $log->teacher_id); ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                            <td style="padding: 10px; text-align: center;">
                                                <input type="checkbox" name="selected_logs[]" value="<?= $log->id ?>" class="tsc-checkbox" onchange="updateSelectedCount('tsc-checkbox', 'tsc-selected-count')" style="width: 16px; height: 16px; cursor: pointer;">
                                            </td>
                                            <td style="padding: 10px; color: #64748b; font-size: 0.78rem; white-space: nowrap;">
                                                <?= date('M d, H:i', strtotime($log->created_at)) ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <strong style="color: #0f172a;"><?= h($log->candidate_name) ?></strong>
                                                <?php if ($teacherObj && $teacherObj->id): ?>
                                                    <br><a href="/teacher/profile?teacher_id=<?= $teacherObj->id ?>" target="_blank" style="font-size: 0.75rem; color: #0f766e; text-decoration: underline;">View Profile</a>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <code style="font-weight: 700; color: #0f766e;"><?= h($log->tsc_number) ?></code>
                                            </td>
                                            <td style="padding: 10px; color: #334155;">
                                                <?= h($log->portal_name ?: 'None Identified') ?>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?php
                                                $score = intval($log->match_score);
                                                $badgeBg = ($score >= 80) ? '#dcfce7' : (($score >= 55) ? '#fef9c3' : '#fee2e2');
                                                $badgeColor = ($score >= 80) ? '#166534' : (($score >= 55) ? '#854d0e' : '#991b1b');
                                                ?>
                                                <span style="background: <?= $badgeBg ?>; color: <?= $badgeColor ?>; font-weight: 700; font-size: 0.74rem; padding: 2px 7px; border-radius: 4px;">
                                                    <?= $score ?>%
                                                </span>
                                            </td>
                                            <td style="padding: 10px;">
                                                <?php if ($log->status === 'verified'): ?>
                                                    <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                        <i class="fa fa-check"></i> Verified
                                                    </span>
                                                <?php elseif ($log->status === 'pending_manual'): ?>
                                                    <span style="background: #fef9c3; color: #854d0e; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                        Pending Review
                                                    </span>
                                                <?php else: ?>
                                                    <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                        Unmatched
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 10px; text-align: right;">
                                                <div style="display: inline-flex; gap: 4px; justify-content: flex-end;">
                                                    <?php if ($teacherObj && $teacherObj->id): ?>
                                                        <button type="button" onclick="submitSingleAction(<?= $teacherObj->id ?>, <?= $log->id ?>, 'approve_tsc')" style="background: #16a34a; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;" title="Approve Clearance">
                                                            <i class="fa fa-check"></i> Approve
                                                        </button>
                                                        <button type="button" onclick="promptSingleReject(<?= $teacherObj->id ?>, <?= $log->id ?>, '<?= addslashes(h($teacherObj->name)) ?>')" style="background: #ef4444; color: white !important; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;" title="Reject Clearance">
                                                            <i class="fa fa-times"></i> Reject
                                                        </button>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Pending Manual Document Verification Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
                <form method="POST" action="/admin/verifications" id="bulk-doc-form">
                    <?= csrf_field() ?>
                    <input type="hidden" name="admin_action" id="bulk-doc-action" value="">
                    <input type="hidden" name="admin_notes" id="bulk-doc-notes" value="">

                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 12px;">
                        <div>
                            <h3 style="margin: 0; font-size: 1.1rem; color: #0f766e; display: flex; align-items: center; gap: 8px;">
                                <i class="fa fa-file-shield"></i> Pending Document Audits (<?= count($pendingTeachers) ?>)
                            </h3>
                            <p style="margin: 3px 0 0; font-size: 0.8rem; color: #64748b;">
                                Educators who uploaded Police Clearance / Good Conduct certificates for manual review.
                            </p>
                        </div>

                        <!-- Bulk Action Controls for Document Audits -->
                        <?php if (!empty($pendingTeachers)): ?>
                            <div style="display: flex; align-items: center; gap: 8px; background: #f8fafc; padding: 6px 12px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <span style="font-size: 0.82rem; color: #475569; font-weight: 600;" id="doc-selected-count">0 selected</span>
                                <button type="button" onclick="executeBulkAction('bulk-doc-form', 'bulk-doc-action', 'bulk_approve', 'Approve all selected documents?')" style="background: #16a34a; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;">
                                    <i class="fa fa-check"></i> Bulk Approve
                                </button>
                                <button type="button" onclick="executeBulkReject('bulk-doc-form', 'bulk-doc-action', 'bulk-doc-notes')" style="background: #ef4444; color: white !important; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border: none; border-radius: 4px; cursor: pointer;">
                                    <i class="fa fa-times"></i> Bulk Reject
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($pendingTeachers)): ?>
                        <div style="padding: 2.5rem; text-align: center; color: #64748b; background: #f8fafc; border-radius: 8px; border: 1px dashed #cbd5e1;">
                            <i class="fa fa-circle-check" style="font-size: 2.2rem; color: #16a34a; margin-bottom: 8px;"></i>
                            <h4 style="margin: 4px 0; color: #0f172a; font-size: 1rem;">No Pending Document Submissions</h4>
                            <p style="margin: 0; font-size: 0.86rem; color: #64748b;">
                                All uploaded clearance documents have been processed.
                            </p>
                        </div>
                    <?php else: ?>
                        <div style="overflow-x: auto;">
                            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                                <thead>
                                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b; background: #f8fafc;">
                                        <th style="padding: 10px; width: 36px; text-align: center;">
                                            <input type="checkbox" id="select-all-docs" onchange="toggleSelectAll('doc-checkbox', this.checked, 'doc-selected-count')" style="width: 16px; height: 16px; cursor: pointer;">
                                        </th>
                                        <th style="padding: 10px;">Educator</th>
                                        <th style="padding: 10px;">Cert Serial / Dates</th>
                                        <th style="padding: 10px;">Document</th>
                                        <th style="padding: 10px;">Reference</th>
                                        <th style="padding: 10px; text-align: right;">Operations</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($pendingTeachers as $t): ?>
                                        <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;" onmouseover="this.style.background='#fbfcfd'" onmouseout="this.style.background='white'">
                                            <td style="padding: 12px 10px; text-align: center;">
                                                <input type="checkbox" name="selected_teachers[]" value="<?= $t->id ?>" class="doc-checkbox" onchange="updateSelectedCount('doc-checkbox', 'doc-selected-count')" style="width: 16px; height: 16px; cursor: pointer;">
                                            </td>
                                            <td style="padding: 12px 10px;">
                                                <strong><?= h($t->name) ?></strong><br>
                                                <span style="font-size: 0.78rem; color: #64748b;"><?= h($t->email) ?> &bull; <?= h($t->mobile) ?></span>
                                            </td>
                                            <td style="padding: 12px 10px;">
                                                <code><?= h($t->good_conduct_cert_no ?: 'No Serial') ?></code><br>
                                                <span style="font-size: 0.75rem; color: #64748b;">
                                                    Issued: <?= h($t->good_conduct_issue_date ?: 'N/A') ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 10px;">
                                                <?php if (!empty($t->good_conduct_doc)): ?>
                                                    <a href="/<?= h($t->good_conduct_doc) ?>" target="_blank" style="color: #0f766e; font-weight: 700; text-decoration: underline;">
                                                        <i class="fa fa-file-pdf"></i> View PDF
                                                    </a>
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">None</span>
                                                <?php endif; ?>
                                            </td>
                                            <td style="padding: 12px 10px;">
                                                <span style="background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 0.75rem; font-family: monospace;">
                                                    <?= h($t->verification_ref ?: 'None') ?>
                                                </span>
                                            </td>
                                            <td style="padding: 12px 10px; text-align: right;">
                                                <div style="display: inline-flex; gap: 4px; justify-content: flex-end;">
                                                    <button type="button" onclick="submitSingleAction(<?= $t->id ?>, 0, 'approve')" style="background: #16a34a; color: white !important; font-size: 0.78rem; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">
                                                        <i class="fa fa-check"></i> Approve
                                                    </button>
                                                    <button type="button" onclick="promptSingleReject(<?= $t->id ?>, 0, '<?= addslashes(h($t->name)) ?>')" style="background: #ef4444; color: white !important; font-size: 0.78rem; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">
                                                        <i class="fa fa-times"></i> Reject
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </form>
            </div>

            <!-- All Clearance Records Table -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: #0f172a;">All Clearance Records & Standings</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b; background: #f8fafc;">
                                <th style="padding: 10px;">Educator</th>
                                <th style="padding: 10px;">Serial No</th>
                                <th style="padding: 10px;">Status</th>
                                <th style="padding: 10px;">Document</th>
                                <th style="padding: 10px; text-align: right;">Manual Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($allTeachersWithDocs as $t): ?>
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 10px;">
                                        <a href="/teacher/profile?teacher_id=<?= $t->id ?>" target="_blank" style="font-weight: 700; color: #0f766e; text-decoration: none;">
                                            <?= h($t->name) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.72rem; opacity: 0.7;"></i>
                                        </a><br>
                                        <span style="font-size: 0.75rem; color: #64748b;"><?= h($t->email) ?></span>
                                    </td>
                                    <td style="padding: 10px;">
                                        <code><?= h($t->good_conduct_cert_no ?: '—') ?></code>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?php if ($t->verification_status === 'verified'): ?>
                                            <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                <i class="fa fa-check-circle"></i> Verified
                                            </span>
                                        <?php elseif ($t->verification_status === 'failed'): ?>
                                            <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                <i class="fa fa-triangle-exclamation"></i> Action Required
                                            </span>
                                        <?php elseif ($t->verification_status === 'pending'): ?>
                                            <span style="background: #eff6ff; color: #1e40af; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                Processing
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #f1f5f9; color: #64748b; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">
                                                None
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px;">
                                        <?php if (!empty($t->good_conduct_doc)): ?>
                                            <a href="/<?= h($t->good_conduct_doc) ?>" target="_blank" style="color: #0f766e;">View File</a>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td style="padding: 10px; text-align: right;">
                                        <div style="display: inline-flex; gap: 4px; justify-content: flex-end;">
                                            <button type="button" onclick="submitSingleAction(<?= $t->id ?>, 0, 'approve')" style="background: #16a34a; color: white !important; font-size: 0.72rem; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;" title="Verify Educator">
                                                Verify
                                            </button>
                                            <button type="button" onclick="promptSingleReject(<?= $t->id ?>, 0, '<?= addslashes(h($t->name)) ?>')" style="background: #ef4444; color: white !important; font-size: 0.72rem; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;" title="Reject Clearance">
                                                Reject
                                            </button>
                                            <button type="button" onclick="if(confirm('Reset clearance status?')) submitSingleAction(<?= $t->id ?>, 0, 'reset')" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;" title="Reset Data">
                                                Reset
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>
    </main>
</div>

<!-- Hidden Standalone Form for Single Actions -->
<form method="POST" action="/admin/verifications" id="single-action-form" style="display: none;">
    <?= csrf_field() ?>
    <input type="hidden" name="teacher_id" id="single-teacher-id" value="">
    <input type="hidden" name="log_id" id="single-log-id" value="">
    <input type="hidden" name="admin_action" id="single-action" value="">
    <input type="hidden" name="admin_notes" id="single-notes" value="">
</form>

<!-- Verification & Bulk Operations Scripts -->
<script>
function toggleSelectAll(className, isChecked, countElementId) {
    const checkboxes = document.querySelectorAll('.' + className);
    checkboxes.forEach(cb => cb.checked = isChecked);
    updateSelectedCount(className, countElementId);
}

function updateSelectedCount(className, countElementId) {
    const checkedBoxes = document.querySelectorAll('.' + className + ':checked');
    const el = document.getElementById(countElementId);
    if (el) {
        el.textContent = checkedBoxes.length + ' selected';
    }
}

function executeBulkAction(formId, actionInputId, actionValue, confirmText) {
    const form = document.getElementById(formId);
    const checkedBoxes = form.querySelectorAll('input[type="checkbox"]:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one record to proceed.');
        return;
    }
    if (confirm(confirmText || 'Apply bulk action to ' + checkedBoxes.length + ' selected items?')) {
        document.getElementById(actionInputId).value = actionValue;
        form.submit();
    }
}

function executeBulkReject(formId, actionInputId, notesInputId) {
    const form = document.getElementById(formId);
    const checkedBoxes = form.querySelectorAll('input[type="checkbox"]:checked');
    if (checkedBoxes.length === 0) {
        alert('Please select at least one record to proceed.');
        return;
    }
    const reason = prompt('Enter the rejection reason for the ' + checkedBoxes.length + ' selected records (will be emailed to educators):', 'Details could not be verified against official TSC records.');
    if (reason !== null) {
        document.getElementById(notesInputId).value = reason;
        document.getElementById(actionInputId).value = 'bulk_reject';
        form.submit();
    }
}

function submitSingleAction(teacherId, logId, action) {
    document.getElementById('single-teacher-id').value = teacherId;
    document.getElementById('single-log-id').value = logId;
    document.getElementById('single-action').value = action;
    document.getElementById('single-notes').value = '';
    document.getElementById('single-action-form').submit();
}

function promptSingleReject(teacherId, logId, teacherName) {
    const reason = prompt('Enter the reason for rejecting / flagging ' + teacherName + ' clearance (will be emailed to educator):', 'Details could not be verified against official registry.');
    if (reason !== null) {
        document.getElementById('single-teacher-id').value = teacherId;
        document.getElementById('single-log-id').value = logId;
        document.getElementById('single-action').value = 'reject';
        document.getElementById('single-notes').value = reason;
        document.getElementById('single-action-form').submit();
    }
}
</script>
