<?php
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school = R::load('school', $school_id);
$successMessage = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
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
            $job->posted_date = date('Y-m-d H:i:s');
            R::store($job);
            $successMessage = "Job vacancy '{$title}' posted successfully!";
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

                    <div style="margin-bottom: 1.5rem;">
                        <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">
                            Monthly Gross Salary (KES) (Optional)
                        </label>
                        <input type="number" name="salary" placeholder="e.g. 45000" style="width: 100%; box-sizing: border-box;">
                    </div>

                    <div style="display: flex; justify-content: flex-end; gap: 10px;">
                        <a href="/school/dashboard" style="background: #f1f5f9; color: #475569; padding: 10px 18px; border-radius: 6px; font-weight: 600; text-decoration: none;">Cancel</a>
                        <button type="submit" class="btn-primary" style="padding: 10px 24px; font-size: 0.9rem;">
                            <i class="fa fa-bullhorn"></i> Publish Vacancy
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>
</div>
