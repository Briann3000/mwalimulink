<?php
// views/referee_endorse.php - Secure Referee & School Experience Endorsement
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

$token = trim($_GET['token'] ?? '');
$message = '';
$error = '';
$endorsement = null;

init_session();
$authUser = auth_user();
$isSelfCandidate = false;
if (!empty($token)) {
    $endorsement = R::findOne('refereeendorsement', 'token = ?', [$token]);
    if ($authUser && ($authUser['role'] ?? '') === 'teacher' && $endorsement && $authUser['user_id'] == $endorsement->teacher_id) {
        $isSelfCandidate = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $endorsement) {
    if ($isSelfCandidate) {
        $error = "Candidate self-endorsement is prohibited. You cannot endorse your own professional profile.";
    } elseif (verify_csrf($_POST['csrf_token'] ?? '')) {
        $action = $_POST['action'] ?? '';
        $feedback = trim($_POST['feedback'] ?? '');

        if ($action === 'confirm') {
            $endorsement->status = 'endorsed';
            $endorsement->endorsed_at = date('Y-m-d H:i:s');
            $endorsement->feedback = $feedback;
            R::store($endorsement);

            // Also mark teacher profile or CV experience as school-endorsed
            $teacher = R::load('teacher', $endorsement->teacher_id);
            if ($teacher && $teacher->id && !empty($teacher->cv_data)) {
                $cv = json_decode($teacher->cv_data, true);
                if (!empty($cv['experience'])) {
                    foreach ($cv['experience'] as &$exp) {
                        if (stripos($exp['school'] ?? '', $endorsement->institution) !== false) {
                            $exp['endorsed'] = true;
                            $exp['endorsed_by'] = $endorsement->referee_name;
                        }
                    }
                    $teacher->cv_data = json_encode($cv, JSON_UNESCAPED_UNICODE);
                    R::store($teacher);
                }
            }

            $message = "Thank you! Your institutional endorsement for " . htmlspecialchars($endorsement->teacher_name) . " has been securely confirmed.";
        } elseif ($action === 'dispute') {
            $endorsement->status = 'disputed';
            $endorsement->endorsed_at = date('Y-m-d H:i:s');
            $endorsement->feedback = $feedback;
            R::store($endorsement);
            $message = "Thank you for notifying us. Our academic verification compliance team will review your notes.";
        }
    } else {
        $error = "Security token expired. Please try again.";
    }
}
?>

<div class="container" style="max-width: 680px; margin: 3rem auto; padding: 0 1rem; font-family: 'Inter', -apple-system, sans-serif;">
    <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; box-shadow: 0 4px 14px rgba(15, 23, 42, 0.06);">
        
        <div style="text-align: center; margin-bottom: 1.5rem;">
            <div style="width: 50px; height: 50px; background: #f0fdfa; color: #0f766e; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.4rem; margin-bottom: 0.75rem;">
                <i class="fa fa-shield-halved"></i>
            </div>
            <h2 style="margin: 0; color: #0f172a; font-size: 1.35rem; font-weight: 800;">Academic Reference & Tenure Endorsement</h2>
            <p style="margin: 4px 0 0; color: #64748b; font-size: 0.85rem;">MwalimuLink Official Institutional Verification Network</p>
        </div>

        <?php if (!empty($message)): ?>
            <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.9rem; text-align: center; margin-bottom: 1rem;">
                <i class="fa fa-check-circle" style="font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                <?= h($message) ?>
            </div>
            <div style="text-align: center; margin-top: 1.5rem;">
                <a href="/" style="color: #0f766e; font-weight: 600; font-size: 0.88rem; text-decoration: none;">&larr; Return to MwalimuLink Home</a>
            </div>
        <?php elseif (!$endorsement): ?>
            <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.9rem; text-align: center;">
                <i class="fa fa-circle-exclamation" style="font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                Invalid or expired endorsement link. Please contact MwalimuLink support if you believe this is an error.
            </div>
        <?php elseif ($isSelfCandidate): ?>
            <div style="background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 1.5rem; border-radius: 8px; text-align: center;">
                <i class="fa fa-shield-xmark" style="font-size: 2rem; display: block; margin-bottom: 8px; color: #dc2626;"></i>
                <h3 style="margin: 0 0 6px; font-size: 1.15rem; color: #991b1b;">Self-Endorsement Prohibited</h3>
                <p style="margin: 0 0 14px; font-size: 0.88rem; line-height: 1.5; color: #7f1d1d;">
                    You are currently logged in as <strong><?= h($endorsement->teacher_name) ?></strong>. Institutional references must be reviewed and endorsed directly by your referee (<strong><?= h($endorsement->referee_name) ?></strong>).
                </p>
                <a href="/teacher/dashboard" style="display: inline-block; background: #0f766e; color: #ffffff !important; font-weight: 600; font-size: 0.84rem; padding: 8px 18px; border-radius: 5px; text-decoration: none;">
                    Return to Educator Dashboard
                </a>
            </div>
        <?php elseif ($endorsement->status === 'endorsed'): ?>
            <div style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 1rem 1.25rem; border-radius: 8px; font-size: 0.9rem; text-align: center;">
                <i class="fa fa-check-double" style="font-size: 1.2rem; display: block; margin-bottom: 4px;"></i>
                This reference has already been confirmed on <?= date('M d, Y', strtotime($endorsement->endorsed_at)) ?>. Thank you!
            </div>
        <?php else: ?>
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                <h4 style="margin: 0 0 8px; font-size: 0.94rem; color: #0f172a;">Candidate Details:</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 8px; font-size: 0.86rem;">
                    <div><strong style="color: #64748b;">Educator:</strong> <?= h($endorsement->teacher_name) ?></div>
                    <div><strong style="color: #64748b;">Institution:</strong> <?= h($endorsement->institution) ?></div>
                    <div><strong style="color: #64748b;">Role Listed:</strong> <?= h($endorsement->role_title) ?></div>
                    <div><strong style="color: #64748b;">Referee:</strong> <?= h($endorsement->referee_name) ?></div>
                </div>
            </div>

            <form method="POST" action="/verify-referee?token=<?= urlencode($token) ?>">
                <?= csrf_field() ?>

                <div style="margin-bottom: 1.25rem;">
                    <label style="font-size: 0.82rem; font-weight: 600; color: #475569; display: block; margin-bottom: 4px;">Referee Notes / Recommendation (Optional):</label>
                    <textarea name="feedback" rows="3" style="width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 12px; font-size: 0.85rem;" placeholder="e.g. Recommended educator with strong classroom engagement and CBC lesson delivery..."></textarea>
                </div>

                <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                    <button type="submit" name="action" value="dispute" onclick="return confirm('Report this tenure record as inaccurate?')" style="background: #ffffff; color: #dc2626; border: 1px solid #fca5a5; padding: 8px 16px; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer;">
                        Report Discrepancy
                    </button>
                    <button type="submit" name="action" value="confirm" style="background: #0f766e; color: #ffffff; border: none; padding: 8px 20px; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer;">
                        <i class="fa fa-check"></i> Confirm & Endorse Tenure
                    </button>
                </div>
            </form>
        <?php endif; ?>

    </div>
</div>
