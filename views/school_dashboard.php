<?php
// school_dashboard.php - Modern School Recruitment Portal
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];

// Load school details from database
$school = R::load('school', $school_id);

// Subscription expiry calculation
$now = new DateTime();
$isActive = false;
$daysRemaining = 0;
$expiryString = 'Inactive';

if (!empty($school->subscription_expiry)) {
    try {
        $expiryDate = new DateTime($school->subscription_expiry);
        if ($school->status === 'active' && $expiryDate >= $now) {
            $isActive = true;
            $diff = $now->diff($expiryDate);
            $daysRemaining = $diff->days;
            $expiryString = $expiryDate->format('M d, Y') . " ({$daysRemaining} days left)";
        }
    } catch (Exception $e) {
        $isActive = false;
    }
}

// Redirect if subscription is inactive
if (!$isActive) {
    header('Location: /school/pay');
    exit();
}

// Key Stats
$totalTeachersCount = R::count('teacher', 'status = ?', ['available']);
$postedJobs = R::find('job', 'school_id = ? ORDER BY id DESC', [$school_id]);
$postedJobsCount = count($postedJobs);

// Total applicants across all jobs posted by this school
$totalApplicantsCount = 0;
$jobIds = array_keys($postedJobs);
if (!empty($jobIds)) {
    $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
    $totalApplicantsCount = R::count('applications', "job_id IN ($placeholders)", $jobIds);
}

// Recent Registered Candidates (Top 4)
$recentCandidates = R::find('teacher', 'status = ? ORDER BY id DESC LIMIT 4', ['available']);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Right Workspace Pane (Clean kmsurveytool #f8fafc style) -->
    <main class="content-pane">
        
        <!-- Quick Action Shortcuts (kmsurveytool style) -->
        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #0f172a;">Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                <a href="/school/staff" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-users"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Employed Faculty</span>
                </a>

                <a href="/school/post-job" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-plus-circle"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Post Vacancy</span>
                </a>

                <a href="/school/search-candidates" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-search"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Find Teachers</span>
                </a>

                <a href="/schools/public" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-landmark"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Browse Schools</span>
                </a>

                <a href="/school/subscribe" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-credit-card"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Subscription</span>
                </a>
            </div>
        </div>

        <!-- Overview Metrics (kmsurveytool flat white cards) -->
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #0f172a;">Overview Metrics</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            
            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Available Candidates
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= number_format($totalTeachersCount) ?>
                </div>
                <a href="/school/search-candidates" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    Search candidate pool &rarr;
                </a>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Your Active Vacancies
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= $postedJobsCount ?>
                </div>
                <a href="/school/post-job" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    Post new opening &rarr;
                </a>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Applications Received
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= $totalApplicantsCount ?>
                </div>
                <a href="/school/dashboard" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    View applicants &rarr;
                </a>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Subscription Remaining
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #22c55e; margin-bottom: 0.75rem;">
                    <?= $daysRemaining ?> Days
                </div>
                <a href="/school/subscribe" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    Renew plan &rarr;
                </a>
            </div>
        </div>

        <!-- Quick Candidate Search Filter Box -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 2rem;">
            <h4 style="margin: 0 0 1rem; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
                <i class="fa fa-search" style="color: #2271b1;"></i> Search Teacher Candidates
            </h4>
            <form method="GET" action="/school/search-candidates" style="margin: 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; align-items: center;">
                    <select name="county" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0;">
                        <option value="">All Counties</option>
                        <?php foreach (kenyan_counties() as $c): ?>
                            <option value="<?= h($c) ?>"><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <input type="text" name="subject" placeholder="Subject (e.g. Mathematics, English)" style="padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0;">

                    <button type="submit" style="background: #2271b1; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; margin: 0;">
                        <i class="fa fa-search"></i> Search
                    </button>
                </div>
            </form>
        </div>

        <!-- Split Grid: Posted Vacancies & Featured Candidates -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem;">
            
            <!-- Left: Posted Vacancies -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Your Posted Vacancies</h4>
                    <a href="/school/post-job" style="font-size: 0.8rem; font-weight: 700;">+ Post New</a>
                </div>

                <?php if (!empty($postedJobs)): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <?php foreach ($postedJobs as $job): 
                            $appCount = R::count('applications', 'job_id = ?', [$job->id]);
                        ?>
                            <div style="padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h5 style="margin: 0 0 2px; font-size: 0.9rem; font-weight: 700; color: #0f172a;"><?= h($job->title) ?></h5>
                                    <p style="margin: 0; font-size: 0.78rem; color: #64748b;">
                                        Posted <?= date('M d, Y', strtotime($job->posted_date ?? 'now')) ?>
                                    </p>
                                </div>
                                <span style="background: #e0f2fe; color: #0369a1; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 12px;">
                                    <?= $appCount ?> Apps
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #94a3b8; font-size: 0.85rem; margin: 0.5rem 0;">No active vacancies posted yet.</p>
                <?php endif; ?>
            </div>

            <!-- Right: Verified Candidates -->
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Verified Candidates</h4>
                    <a href="/school/search-candidates" style="font-size: 0.8rem; font-weight: 700;">Browse All &rarr;</a>
                </div>

                <?php if (!empty($recentCandidates)): ?>
                    <div style="display: flex; flex-direction: column; gap: 0.6rem;">
                        <?php foreach ($recentCandidates as $cand): ?>
                            <div style="padding: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <h5 style="margin: 0 0 2px; font-size: 0.9rem; font-weight: 700;">
                                        <a href="/teacher/profile?teacher_id=<?= $cand->id ?>" style="color: #0f172a;"><?= h($cand->name) ?></a>
                                    </h5>
                                    <p style="margin: 0; font-size: 0.78rem; color: #64748b;">
                                        <?= h($cand->teaching_subjects ?: 'General') ?> &bull; <?= h($cand->county ?: 'Kenya') ?>
                                    </p>
                                </div>
                                <?php if (!empty($cand->tsc_number)): ?>
                                    <span style="background: #dcfce7; color: #15803d; font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 4px;">
                                        ✓ TSC
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p style="color: #94a3b8; font-size: 0.85rem; margin: 0.5rem 0;">No active candidates.</p>
                <?php endif; ?>
            </div>

        </div>

    </main>
</div>
