<?php
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school = R::load('school', $school_id);
$successMessage = '';
$error = '';

// Check subscription and existing jobs
$now = new DateTime();
$isPro = false;
if (!empty($school->subscription_expiry)) {
    try {
        $expiryDate = new DateTime($school->subscription_expiry);
        if ($school->status === 'active' && $expiryDate >= $now) {
            $isPro = true;
        }
    } catch (Exception $e) {
        $isPro = false;
    }
}

$existingJobsCount = R::count('job', 'school_id = ?', [$school_id]);
$canPost = $isPro || ($existingJobsCount < 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } elseif (!$canPost) {
        $error = "You have used your 1 free job posting on the Freemium plan. Please upgrade to Pro (KES 10 / year) to post unlimited vacancies.";
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $salary = !empty($_POST['salary']) ? (float)$_POST['salary'] : null;

        if (!empty($title) && !empty($description)) {
            $job = R::dispense('job');
            $job->school_id = $school_id;
            $job->title = $title;
            $job->description = $description;
            $job->requirements = $requirements;
            $job->salary = $salary;
            $job->deadline = !empty($_POST['deadline']) ? $_POST['deadline'] : null;
            $job->posted_date = date('Y-m-d H:i:s');
            R::store($job);
            $successMessage = "Job vacancy '{$title}' posted successfully!";
            $existingJobsCount++;
            $canPost = $isPro || ($existingJobsCount < 1);
        } else {
            $error = "Please fill in all required fields.";
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="max-width: 760px; margin: 0 auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">Post a Teaching Vacancy</h2>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                        Reach thousands of qualified educators actively searching for openings.
                    </p>
                </div>
                <div>
                    <a href="/school/dashboard" style="background: #e2e8f0; color: #334155; font-size: 0.85rem; font-weight: 600; padding: 8px 14px; border-radius: 6px; display: inline-flex; align-items: center; gap: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Dashboard
                    </a>
                </div>
            </div>

            <?php if ($successMessage): ?>
                <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <i class="fa fa-check-circle"></i> <?= h($successMessage) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.9rem;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <?php if (!$canPost): ?>
                <!-- Upgrade Card when Free Limit Reached -->
                <div style="background: white; border: 1px solid #fde68a; border-radius: 10px; padding: 2.5rem 1.75rem; text-align: center; box-shadow: 0 4px 12px rgba(217,119,6,0.08); margin-bottom: 2rem;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                        <i class="fa fa-lock"></i>
                    </div>
                    <h3 style="margin: 0 0 8px; color: #0f172a; font-size: 1.3rem;">Freemium Job Post Limit Reached</h3>
                    <p style="margin: 0 0 1.5rem; font-size: 0.92rem; color: #64748b; max-width: 520px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                        You have already posted your <strong>1 Free Teaching Vacancy</strong> on the Freemium plan. Upgrade to <strong>Pro Recruiter</strong> for just <strong>KES 10 / year (Test Sandbox)</strong> to unlock unlimited vacancies and proactive teacher database search!
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="/school/pay" class="btn-primary" style="background: #0f766e; color: white !important; padding: 10px 24px; font-size: 0.92rem; font-weight: 700; text-decoration: none;">
                            <i class="fa fa-bolt"></i> Upgrade to Pro (KES 10) &rarr;
                        </a>
                        <a href="/school/dashboard" style="background: #f1f5f9; color: #475569; padding: 10px 20px; border-radius: 6px; font-weight: 600; text-decoration: none;">
                            Back to Dashboard
                        </a>
                    </div>
                </div>

            <?php else: ?>
                
                <?php if (!$isPro): ?>
                    <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 6px; padding: 8px 14px; margin-bottom: 1.25rem; font-size: 0.82rem; color: #166534; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-info-circle"></i>
                        <span><strong>Freemium Benefit:</strong> You are using your 1 Free Active Job Vacancy. Upgrade to Pro anytime for unlimited postings!</span>
                    </div>
                <?php endif; ?>

                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <form method="POST" action="/school/post-job" style="margin: 0;">
                        <?= csrf_field() ?>

                        <div style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Job Vacancy Title <span style="color: red;">*</span>
                            </label>
                            <input type="text" name="title" placeholder="e.g. High School Physics & Mathematics Teacher" required style="width: 100%; box-sizing: border-box;">
                        </div>

                        <div style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Detailed Description & Responsibilities <span style="color: red;">*</span>
                            </label>
                            <textarea name="description" rows="5" placeholder="Describe the subjects to teach, classes, curriculum (e.g. CBC / 8-4-4 / IGCSE), and duties..." required style="width: 100%; box-sizing: border-box; font-family: inherit;"></textarea>
                        </div>

                        <div style="margin-bottom: 1.25rem;">
                            <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                Candidate Requirements & Qualifications
                            </label>
                            <textarea name="requirements" rows="4" placeholder="e.g. Bachelor of Education, TSC Registration required, 3+ years experience..." style="width: 100%; box-sizing: border-box; font-family: inherit;"></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                    Monthly Gross Salary (KES) (Optional)
                                </label>
                                <input type="number" name="salary" placeholder="" style="width: 100%; box-sizing: border-box;">
                            </div>
                            <div>
                                <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                                    Application Deadline (Optional)
                                </label>
                                <input type="date" name="deadline" min="<?= date('Y-m-d') ?>" style="width: 100%; box-sizing: border-box; background: white; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px;">
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end; gap: 10px;">
                            <a href="/school/dashboard" style="background: #f1f5f9; color: #475569; padding: 10px 18px; border-radius: 6px; font-weight: 600; text-decoration: none;">Cancel</a>
                            <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                                <i class="fa fa-bullhorn"></i> Publish Vacancy
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

