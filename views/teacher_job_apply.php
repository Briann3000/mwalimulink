<?php
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];

$job_id = intval($_GET['job_id'] ?? 0);
$job = R::load('job', $job_id);

$msg = '';
$error = '';
$applied = false;

if (!$job->id) {
    $error = "The requested vacancy was not found or has expired.";
} else {
    $school = R::load('school', $job->school_id);
    $existingApplication = R::findOne('applications', 'teacher_id = ? AND job_id = ?', [$teacher_id, $job_id]);
    if ($existingApplication) {
        $applied = true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$applied) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $error = "Security token mismatch. Please try again.";
        } else {
            $application = R::dispense('applications');
            $application->teacher_id = $teacher_id;
            $application->job_id = $job_id;
            $application->application_date = date('Y-m-d H:i:s');
            R::store($application);
            $applied = true;
            $msg = "Application submitted successfully! {$school->name} has received your profile.";
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="max-width: 680px; margin: 0 auto;">
            <div style="margin-bottom: 1.5rem;">
                <a href="/teacher/jobs" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-arrow-left"></i> Back to Vacancies
                </a>
            </div>

            <?php if ($msg): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem; text-align: center;">
                    <i class="fa fa-check-circle fa-2x" style="color: #16a34a; margin-bottom: 0.5rem; display: block;"></i>
                    <h3 style="margin: 0 0 4px; color: #166534;">Application Submitted!</h3>
                    <p style="margin: 0 0 1rem; font-size: 0.88rem;"><?= h($msg) ?></p>
                    <a href="/teacher/jobs" class="btn-primary">Browse More Openings</a>
                </div>
            <?php elseif ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php elseif ($applied): ?>
                <div style="background: #e0f2fe; border: 1px solid #bae6fd; color: #0369a1; padding: 1.5rem; border-radius: 8px; text-align: center;">
                    <i class="fa fa-info-circle fa-2x" style="margin-bottom: 0.5rem; display: block;"></i>
                    <h3 style="margin: 0 0 4px; color: #0369a1;">Already Applied</h3>
                    <p style="margin: 0 0 1.25rem; font-size: 0.88rem;">You have already submitted an application for this vacancy.</p>
                    <a href="/teacher/jobs" class="btn-primary">Explore Other Vacancies</a>
                </div>
            <?php else: ?>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <h2 style="margin: 0 0 0.5rem; font-size: 1.3rem; color: #0f172a;">Confirm Job Application</h2>
                    <p style="margin: 0 0 1.5rem; font-size: 0.85rem; color: #64748b;">
                        Your digital profile and CV will be submitted to the school administration.
                    </p>

                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                        <h3 style="margin: 0 0 4px; font-size: 1.1rem; color: #0f172a;"><?= h($job->title) ?></h3>
                        <p style="margin: 0 0 8px; font-size: 0.85rem; color: #64748b;">
                            <i class="fa fa-school"></i> <?= h($school->name) ?> &bull; <i class="fa fa-map-marker-alt"></i> <?= h($school->county) ?>
                        </p>
                        <?php if (!empty($job->salary)): ?>
                            <span style="background: #dcfce7; color: #166534; font-size: 0.78rem; font-weight: 700; padding: 2px 8px; border-radius: 12px;">
                                KES <?= number_format($job->salary) ?> / mo
                            </span>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="">
                        <?= csrf_field() ?>
                        <div style="display: flex; gap: 10px; justify-content: flex-end;">
                            <a href="/teacher/jobs" style="background: #f1f5f9; color: #475569; padding: 10px 18px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; text-decoration: none;">
                                Cancel
                            </a>
                            <button type="submit" class="btn-primary" style="font-size: 0.88rem; padding: 10px 22px;">
                                <i class="fa fa-paper-plane"></i> Submit Application
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>
