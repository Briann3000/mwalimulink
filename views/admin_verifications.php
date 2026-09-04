<?php
// views/admin_verifications.php - Admin Verification & Clearance Queue
require_auth('admin');

$msg = '';
$error = '';

// Handle manual admin actions
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
                R::store($teacher);
                send_verification_status_email($teacher, 'verified');
                $msg = "Teacher " . htmlspecialchars($teacher->name) . " verified and confirmation email dispatched!";
            } elseif ($action === 'flag') {
                $teacher->verification_status = 'failed';
                $teacher->good_conduct_status = 'failed';
                R::store($teacher);
                send_verification_status_email($teacher, 'failed', $notes);
                $msg = "Teacher " . htmlspecialchars($teacher->name) . " marked as Action Required and notification email dispatched!";
            } elseif ($action === 'reset') {
                $teacher->verification_status = 'none';
                $teacher->good_conduct_status = 'unverified';
                $teacher->good_conduct_doc = null;
                $teacher->good_conduct_cert_no = null;
                R::store($teacher);
                $msg = "Clearance reset for " . htmlspecialchars($teacher->name) . ".";
            }
        }
    }
}

// Fetch pending and recent requests
$pendingTeachers = R::find('teacher', 'verification_status = ? OR (good_conduct_doc IS NOT NULL AND verification_status = ?) ORDER BY id DESC', ['pending', 'pending']);
$allTeachersWithDocs = R::find('teacher', 'good_conduct_doc IS NOT NULL OR good_conduct_cert_no IS NOT NULL ORDER BY id DESC LIMIT 50');
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1100px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px;">
                        <i class="fa fa-arrow-left"></i> Back to Admin Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a;">Educator Clearance & Background Verification Queue</h2>
                </div>
            </div>

    <?php if ($msg): ?>
        <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fa fa-check-circle"></i> <?= h($msg) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem;">
            <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Pending Verification Table -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
        <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: #0f766e; display: flex; align-items: center; gap: 8px;">
            <i class="fa fa-clock"></i> Pending Clearance Audits (<?= count($pendingTeachers) ?>)
        </h3>

        <?php if (empty($pendingTeachers)): ?>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0;">No clearance requests currently awaiting audit.</p>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b;">
                            <th style="padding: 10px;">Educator</th>
                            <th style="padding: 10px;">Cert Serial / Dates</th>
                            <th style="padding: 10px;">Document</th>
                            <th style="padding: 10px;">Reference</th>
                            <th style="padding: 10px; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pendingTeachers as $t): ?>
                            <tr style="border-bottom: 1px solid #f1f5f9;">
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
                                    <form method="POST" action="/admin/verifications" style="margin: 0; display: inline-flex; gap: 6px;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="teacher_id" value="<?= $t->id ?>">
                                        <button type="submit" name="admin_action" value="approve" style="background: #16a34a; color: white !important; font-size: 0.78rem; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">
                                            ✓ Approve & Email
                                        </button>
                                        <button type="submit" name="admin_action" value="flag" onclick="return confirm('Flag as action required and notify educator?')" style="background: #ef4444; color: white !important; font-size: 0.78rem; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">
                                            ✕ Flag
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Recent Clearance Records Table -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
        <h3 style="margin: 0 0 1rem; font-size: 1.1rem; color: #0f172a;">All Clearance Records & Standings</h3>
        <div style="overflow-x: auto;">
            <table style="width: 100%; border-collapse: collapse; font-size: 0.88rem;">
                <thead>
                    <tr style="border-bottom: 2px solid #e2e8f0; text-align: left; color: #64748b;">
                        <th style="padding: 10px;">Educator</th>
                        <th style="padding: 10px;">Serial No</th>
                        <th style="padding: 10px;">Status</th>
                        <th style="padding: 10px;">Document</th>
                        <th style="padding: 10px; text-align: right;">Manual Override</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allTeachersWithDocs as $t): ?>
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px;">
                                <a href="/teacher/profile?id=<?= $t->id ?>" target="_blank" style="font-weight: 700; color: #0f766e;">
                                    <?= h($t->name) ?>
                                </a><br>
                                <span style="font-size: 0.75rem; color: #64748b;"><?= h($t->email) ?></span>
                            </td>
                            <td style="padding: 10px;">
                                <code><?= h($t->good_conduct_cert_no ?: '—') ?></code>
                            </td>
                            <td style="padding: 10px;">
                                <?php if ($t->verification_status === 'verified'): ?>
                                    <span style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">Verified ✓</span>
                                <?php elseif ($t->verification_status === 'failed'): ?>
                                    <span style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">Action Required</span>
                                <?php elseif ($t->verification_status === 'pending'): ?>
                                    <span style="background: #eff6ff; color: #1e40af; font-weight: 700; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">Processing</span>
                                <?php else: ?>
                                    <span style="background: #f1f5f9; color: #64748b; font-size: 0.75rem; padding: 3px 8px; border-radius: 4px;">None</span>
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
                                <form method="POST" action="/admin/verifications" style="margin: 0; display: inline-flex; gap: 4px;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="teacher_id" value="<?= $t->id ?>">
                                    <?php if ($t->verification_status !== 'verified'): ?>
                                        <button type="submit" name="admin_action" value="approve" style="background: #16a34a; color: white !important; font-size: 0.72rem; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer;">
                                            Verify
                                        </button>
                                    <?php endif; ?>
                                    <button type="submit" name="admin_action" value="reset" onclick="return confirm('Reset clearance status?')" style="background: #f1f5f9; color: #64748b; font-size: 0.72rem; padding: 4px 8px; border: 1px solid #cbd5e1; border-radius: 4px; cursor: pointer;">
                                        Reset
                                    </button>
                                </form>
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
