<?php
// teacher_dashboard.php - Modern Teacher Portal
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];

// Load full teacher record
$teacher = R::load('teacher', $teacher_id);
if (!$teacher->id) {
    header("Location: /logout");
    exit();
}

// Calculate Profile Completeness
$fieldsToCheck = [
    $teacher->name, $teacher->email, $teacher->mobile, 
    $teacher->county, $teacher->grade_levels, $teacher->teaching_subjects, 
    $teacher->qualification, $teacher->institutions_attended, 
    $teacher->brief_profile, $teacher->responsibility
];
$completedFields = count(array_filter($fieldsToCheck, fn($f) => !empty(trim((string)$f))));
$profileCompleteness = round(($completedFields / count($fieldsToCheck)) * 100);

// Key Stats
$activeJobsCount = R::count('job');
$publicSchoolsCount = R::count('public_school');
$privateSchoolsCount = R::count('private_school');
$totalSchools = $publicSchoolsCount + $privateSchoolsCount;
$myApplicationsCount = R::count('applications', 'teacher_id = ?', [$teacher_id]);

// Recommended Jobs (matching teacher's teaching subjects)
$teachingSubjects = (string)($teacher->teaching_subjects ?? '');
$primarySubject = '';
if (!empty($teachingSubjects)) {
    $firstSlash = explode('/', $teachingSubjects)[0] ?? '';
    $primarySubject = trim(explode(',', $firstSlash)[0] ?? '');
}
$recommendedJobs = [];
if (!empty($primarySubject)) {
    $recommendedJobs = R::find('job', 'title LIKE ? OR description LIKE ? OR requirements LIKE ? ORDER BY id DESC LIMIT 3', [
        "%$primarySubject%", "%$primarySubject%", "%$primarySubject%"
    ]);
}
if (empty($recommendedJobs)) {
    $recommendedJobs = R::find('job', 'ORDER BY id DESC LIMIT 3');
}

// Handle AJAX or POST status change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $newStatus = trim($_POST['status'] ?? 'available');
        if (in_array($newStatus, ['available', 'employed', 'open_to_offers'])) {
            $teacher->status = $newStatus;
            R::store($teacher);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => $newStatus]);
                exit();
            }
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Right Workspace Pane (Clean kmsurveytool #f8fafc style) -->
    <main class="content-pane">
        
        <!-- Live Employment / Availability Status Widget -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <div style="width: 12px; height: 12px; border-radius: 50%; background: <?= ($teacher->status === 'available') ? '#22c55e' : (($teacher->status === 'open_to_offers') ? '#f59e0b' : '#64748b') ?>; box-shadow: 0 0 8px <?= ($teacher->status === 'available') ? '#22c55e' : '#f59e0b' ?>;"></div>
                <div>
                    <span style="font-size: 0.85rem; font-weight: 700; color: #0f172a;">
                        Current Status: 
                        <span style="text-transform: capitalize; color: <?= ($teacher->status === 'available') ? '#16a34a' : '#2271b1' ?>;">
                            <?= ($teacher->status === 'available') ? 'Available for Immediate Hire' : (($teacher->status === 'open_to_offers') ? 'Open to New Offers' : 'Employed / Inactive') ?>
                        </span>
                    </span>
                    <p style="margin: 2px 0 0; font-size: 0.78rem; color: #64748b;">
                        <?= ($teacher->status === 'available') ? 'Your profile is highlighted to hiring schools and boosted in search results.' : 'You are currently listed as not actively looking.' ?>
                    </p>
                </div>
            </div>

            <form method="POST" style="margin: 0; display: flex; align-items: center; gap: 8px;">
                <?= csrf_field() ?>
                <input type="hidden" name="update_status" value="1">
                <select name="status" onchange="this.form.submit()" style="padding: 6px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 600; background: white; margin: 0; cursor: pointer;">
                    <option value="available" <?= ($teacher->status === 'available') ? 'selected' : '' ?>>🟢 Available for Hire</option>
                    <option value="open_to_offers" <?= ($teacher->status === 'open_to_offers') ? 'selected' : '' ?>>🟡 Open to Offers</option>
                    <option value="employed" <?= ($teacher->status === 'employed') ? 'selected' : '' ?>>🔴 Employed / Inactive</option>
                </select>
            </form>
        </div>

        <!-- Quick Action Shortcuts (kmsurveytool style) -->
        <div style="margin-bottom: 2rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #0f172a;">Quick Actions</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem;">
                <a href="/teacher/applications" class="quick-action-tile">
                    <div class="quick-action-icon" style="background: #f0fdf4; color: #16a34a;">
                        <i class="fa fa-list-check"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">My Applications (<?= $myApplicationsCount ?>)</span>
                </a>

                <a href="/teacher/jobs" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-search"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Browse Jobs</span>
                </a>

                <a href="/tp-hub" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-graduation-cap"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">TP & Intern Hub</span>
                </a>

                <a href="/teacher/cv-builder" class="quick-action-tile">
                    <div class="quick-action-icon" style="background: #f0fdfa; color: #0f766e;">
                        <i class="fa fa-file-lines"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">CV Builder & Polisher</span>
                </a>

                <a href="/teacher/profile" class="quick-action-tile">
                    <div class="quick-action-icon" style="background: #eff6ff; color: #2563eb;">
                        <i class="fa fa-id-badge"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">View My Profile & CV</span>
                </a>

                <a href="/schools/public" class="quick-action-tile">
                    <div class="quick-action-icon">
                        <i class="fa fa-landmark"></i>
                    </div>
                    <span style="font-size: 0.9rem; font-weight: 600; color: #1e293b;">Public Schools</span>
                </a>
            </div>
        </div>

        <!-- Overview Metrics (kmsurveytool flat white cards) -->
        <h3 style="font-size: 1.1rem; font-weight: 700; margin-bottom: 1rem; color: #0f172a;">Overview Metrics</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
            
            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Active Job Vacancies
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= $activeJobsCount ?>
                </div>
                <a href="/teacher/jobs" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    View vacancies &rarr;
                </a>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Total Registered Schools
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= number_format($totalSchools) ?>
                </div>
                <a href="/schools/public" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    Search directory &rarr;
                </a>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Applications Sent
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= $myApplicationsCount ?>
                </div>
                <a href="/teacher/applications" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    Track applications &rarr;
                </a>
            </div>

            <div class="metric-card">
                <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">
                    Profile Completeness
                </div>
                <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a; margin-bottom: 0.75rem;">
                    <?= $profileCompleteness ?>%
                </div>
                <a href="/teacher/update" style="font-size: 0.78rem; font-weight: 700; color: #2271b1; display: flex; align-items: center; gap: 4px;">
                    Complete profile &rarr;
                </a>
            </div>
        </div>

        <!-- Recommended Jobs Table / Section -->
        <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem; margin-bottom: 2rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a;">Recommended Vacancies For You</h4>
                <a href="/teacher/jobs" style="font-size: 0.82rem; font-weight: 700;">View All Vacancies &rarr;</a>
            </div>

            <?php if (!empty($recommendedJobs)): ?>
                <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                    <?php foreach ($recommendedJobs as $job): 
                        $schoolObj = R::load('school', $job->school_id);
                    ?>
                        <div style="display: flex; justify-content: space-between; align-items: center; padding: 0.85rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <div>
                                <h5 style="margin: 0 0 3px; font-size: 0.95rem; font-weight: 700;">
                                    <a href="/teacher/apply?job_id=<?= $job->id ?>" style="color: #0f172a; text-decoration: none;"><?= h($job->title) ?></a>
                                </h5>
                                <p style="margin: 0; font-size: 0.8rem; color: #64748b;">
                                    <i class="fa fa-school"></i> <?= h($schoolObj->name ?? 'School') ?> &bull; 
                                    <i class="fa fa-map-marker-alt"></i> <?= h($schoolObj->county ?? 'Kenya') ?>
                                </p>
                            </div>
                            <div>
                                <a href="/teacher/apply?job_id=<?= $job->id ?>" style="background: #2271b1; color: white !important; font-size: 0.8rem; font-weight: 600; padding: 6px 14px; border-radius: 6px; text-decoration: none;">
                                    Apply Now
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p style="color: #94a3b8; font-size: 0.85rem; margin: 0;">No active job postings right now.</p>
            <?php endif; ?>
        </div>

    </main>
</div>
