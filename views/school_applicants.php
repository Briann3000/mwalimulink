<?php
// views/school_applicants.php - Comprehensive Applicant Management, Pipeline & BOG Export Matrix
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];
$school = R::load('school', $school_id);

// Ensure active subscription
$now = new DateTime();
$isActive = false;
if (!empty($school->subscription_expiry)) {
    try {
        $exp = new DateTime($school->subscription_expiry);
        if ($school->status === 'active' && $exp >= $now) {
            $isActive = true;
        }
    } catch (Exception $e) {
        $isActive = false;
    }
}
$isPro = $isActive;

$successMsg = '';
$errorMsg = '';

// Load all jobs posted by this school for filtering
$postedJobs = R::find('job', 'school_id = ? ORDER BY id DESC', [$school_id]);
$jobIds = array_keys($postedJobs);

// Handle POST actions: Single status update, Bulk actions, Message/Interview invite, Delete application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Security token mismatch. Please try again.";
    } else {
        $action = trim($_POST['action'] ?? '');
        
        // 1. Bulk Actions (Bulk Shortlist & Bulk Regret)
        if ($action === 'bulk_shortlist') {
            $selectedIds = $_POST['selected_app_ids'] ?? [];
            $count = 0;
            foreach ($selectedIds as $appId) {
                $appId = intval($appId);
                $app = R::load('applications', $appId);
                if ($app->id && in_array($app->job_id, $jobIds)) {
                    $app->status = 'shortlisted';
                    $app->updated_at = date('Y-m-d H:i:s');
                    R::store($app);
                    $count++;
                }
            }
            $successMsg = "Successfully shortlisted {$count} candidate(s)!";
        } elseif ($action === 'bulk_regret') {
            $selectedIds = $_POST['selected_app_ids'] ?? [];
            $customRegret = trim($_POST['bulk_regret_message'] ?? '');
            $count = 0;
            foreach ($selectedIds as $appId) {
                $appId = intval($appId);
                $app = R::load('applications', $appId);
                if ($app->id && in_array($app->job_id, $jobIds)) {
                    $app->status = 'rejected';
                    $app->updated_at = date('Y-m-d H:i:s');
                    R::store($app);

                    $teacher = R::load('teacher', $app->teacher_id);
                    $job = R::load('job', $app->job_id);

                    // Record regret in communication thread
                    $msgBean = R::dispense('applicationmessage');
                    $msgBean->application_id = $app->id;
                    $msgBean->sender_type = 'school';
                    $msgBean->sender_id = $school_id;
                    $msgBean->recipient_type = 'teacher';
                    $msgBean->recipient_id = $teacher->id;
                    $msgBean->message = $customRegret ?: "Thank you for your application to {$school->name}. The recruitment committee has moved forward with other applicants for this vacancy.";
                    $msgBean->created_at = date('Y-m-d H:i:s');
                    $msgBean->is_read = 0;
                    R::store($msgBean);

                    // Dispatch respectful regret email
                    if ($teacher && $teacher->email) {
                        @send_application_regret_email($teacher, $school, $job, $customRegret);
                    }
                    $count++;
                }
            }
            $successMsg = "Polite regret notifications dispatched to {$count} candidate(s).";
        } else {
            // Single Application Actions
            $applicationId = intval($_POST['application_id'] ?? 0);
            $app = R::load('applications', $applicationId);

            if ($app->id && in_array($app->job_id, $jobIds)) {
                $job = R::load('job', $app->job_id);
                $teacher = R::load('teacher', $app->teacher_id);

                if ($action === 'update_status') {
                    $newStatus = trim($_POST['status'] ?? '');
                    $validStatuses = ['applied', 'reviewing', 'shortlisted', 'interview_scheduled', 'hired', 'rejected'];
                    if (in_array($newStatus, $validStatuses)) {
                        $app->status = $newStatus;
                        $app->updated_at = date('Y-m-d H:i:s');
                        R::store($app);
                        $successMsg = "Candidate status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . "!";
                    }
                } elseif ($action === 'send_message') {
                    $messageText = trim($_POST['message'] ?? '');
                    $interviewFormat = trim($_POST['interview_format'] ?? 'in_person');
                    $interviewDate = trim($_POST['interview_date'] ?? '');
                    $interviewLocation = trim($_POST['interview_location'] ?? '');
                    $interviewVirtualLink = trim($_POST['interview_virtual_link'] ?? '');

                    if (!empty($messageText)) {
                        $msgBean = R::dispense('applicationmessage');
                        $msgBean->application_id = $applicationId;
                        $msgBean->sender_type = 'school';
                        $msgBean->sender_id = $school_id;
                        $msgBean->recipient_type = 'teacher';
                        $msgBean->recipient_id = $teacher->id;
                        $msgBean->message = $messageText;
                        $msgBean->created_at = date('Y-m-d H:i:s');
                        $msgBean->is_read = 0;
                        R::store($msgBean);

                        // If interview schedule provided, update status & location
                        if (!empty($interviewDate)) {
                            $app->status = 'interview_scheduled';
                            $app->interview_date = $interviewDate;
                            $app->interview_format = $interviewFormat;
                            $app->interview_location = ($interviewFormat === 'virtual') ? $interviewVirtualLink : $interviewLocation;
                        }
                        $app->updated_at = date('Y-m-d H:i:s');
                        R::store($app);

                        // Send rich email invite
                        if ($teacher && $teacher->email) {
                            @send_interview_invite_email($teacher, $school, $job, $messageText, $interviewDate, $interviewLocation, $interviewFormat, $interviewVirtualLink);
                        }

                        $successMsg = "Message & interview invitation dispatched to {$teacher->name}!";
                    } else {
                        $errorMsg = "Please provide a message for the candidate.";
                    }
                } elseif ($action === 'delete_application') {
                    $messages = R::find('applicationmessage', 'application_id = ?', [$applicationId]);
                    foreach ($messages as $m) {
                        R::trash($m);
                    }
                    R::trash($app);
                    $successMsg = "Application from {$teacher->name} has been removed from this pipeline.";
                }
            } else {
                $errorMsg = "Unauthorized or invalid application.";
            }
        }
    }
}

// Build query for applications belonging to this school's jobs
$selectedJobId = intval($_GET['job_id'] ?? 0);
$selectedStatus = trim($_GET['status'] ?? 'all');
$verifiedOnly = !empty($_GET['verified_only']);

$applicants = [];
if (!empty($jobIds)) {
    $whereParts = [];
    $params = [];

    if ($selectedJobId > 0 && in_array($selectedJobId, $jobIds)) {
        $whereParts[] = "job_id = ?";
        $params[] = $selectedJobId;
    } else {
        $placeholders = implode(',', array_fill(0, count($jobIds), '?'));
        $whereParts[] = "job_id IN ($placeholders)";
        $params = array_merge($params, $jobIds);
    }

    if ($selectedStatus !== 'all' && !empty($selectedStatus)) {
        $whereParts[] = "status = ?";
        $params[] = $selectedStatus;
    }

    $whereClause = implode(' AND ', $whereParts);
    $rawApplicants = R::find('applications', "$whereClause ORDER BY id DESC", $params);

    foreach ($rawApplicants as $app) {
        $teacher = R::load('teacher', $app->teacher_id);
        if ($verifiedOnly && $teacher->verification_status !== 'verified' && empty($teacher->good_conduct_doc)) {
            continue;
        }
        $applicants[] = $app;
    }
}

// ----------------------------------------------------------------------------------
// CSV EXPORT FOR BOARD OF MANAGEMENT (BOG) & INTERVIEW PANELS
// ----------------------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $jobTitleSnippet = "All_Vacancies";
    if ($selectedJobId > 0) {
        $exportJob = R::load('job', $selectedJobId);
        if ($exportJob->id) {
            $jobTitleSnippet = preg_replace('/[^a-zA-Z0-9_-]/', '_', $exportJob->title);
        }
    }

    $filename = "MwalimuLink_BOG_Matrix_{$jobTitleSnippet}_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Candidate Full Name', 'Email Address', 'Mobile Number', 'TSC Number', 'Highest Qualification',
        'Teaching Subject Combinations', 'Years of Experience', 'County of Residence', 'Target Vacancy',
        'Pipeline Status', 'Expected Monthly Salary (KES)', 'Earliest Availability', 'Safeguarding / Good Conduct', 'Applied Date'
    ]);

    foreach ($applicants as $app) {
        $t = R::load('teacher', $app->teacher_id);
        $j = R::load('job', $app->job_id);
        fputcsv($out, [
            $t->name,
            $t->email,
            $t->mobile,
            $t->tsc_number ?: 'Non-TSC / Tutor',
            $t->qualification ?: 'Degree / Diploma',
            $t->teaching_subjects ?: 'General',
            intval($t->years_of_experience) . ' Yrs',
            $t->county ?: 'Kenya',
            $j->title,
            ucfirst(str_replace('_', ' ', $app->status)),
            $app->expected_salary ? number_format($app->expected_salary) : 'Negotiable',
            $app->available_from ?: 'Immediately',
            ($t->verification_status === 'verified' || !empty($t->good_conduct_doc)) ? 'Verified / Cleared' : 'Pending Upload',
            date('Y-m-d H:i', strtotime($app->application_date ?? 'now'))
        ]);
    }
    fclose($out);
    exit();
}

$statusPills = [
    'applied' => ['label' => 'New Application', 'color' => '#0369a1', 'bg' => '#e0f2fe'],
    'reviewing' => ['label' => 'Under Review', 'color' => '#854d0e', 'bg' => '#fef9c3'],
    'shortlisted' => ['label' => 'Shortlisted', 'color' => '#15803d', 'bg' => '#dcfce7'],
    'interview_scheduled' => ['label' => 'Interview Invited', 'color' => '#6d28d9', 'bg' => '#ede9fe'],
    'hired' => ['label' => 'Hired / Accepted', 'color' => '#166534', 'bg' => '#bbf7d0'],
    'rejected' => ['label' => 'Declined', 'color' => '#991b1b', 'bg' => '#fee2e2'],
];

// Helper: Smart Match Badge Check
function check_smart_matches($teacher, $job, $school) {
    $matches = [];
    
    // 1. Subject Combination overlap
    if (!empty($teacher->teaching_subjects) && !empty($job->title)) {
        $subjectsArr = array_map('trim', explode(',', str_replace(['/', '&'], ',', strtolower($teacher->teaching_subjects))));
        $jobText = strtolower($job->title . ' ' . $job->description);
        foreach ($subjectsArr as $subj) {
            if (strlen($subj) >= 3 && str_contains($jobText, $subj)) {
                $matches['subject'] = "✓ Subject Match";
                break;
            }
        }
    }

    // 2. Local County
    if (!empty($teacher->county) && !empty($school->county)) {
        if (strcasecmp(trim($teacher->county), trim($school->county)) === 0) {
            $matches['county'] = "📍 Local (" . h($teacher->county) . ")";
        }
    }

    return $matches;
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="padding-top: 1.5rem !important;">
        
        <!-- Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a; font-weight: 800;">Applicant Management & Screening Pipeline</h2>
                <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                    Screen candidates, review qualifications, schedule virtual or physical interviews, and export interview matrix for <?= h($school->name) ?>.
                </p>
            </div>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php if (!empty($applicants)): ?>
                    <a href="/school/applicants?export=csv&job_id=<?= $selectedJobId ?>&status=<?= urlencode($selectedStatus) ?>" style="background: white; color: #166534; border: 1px solid #bbf7d0; font-size: 0.85rem; font-weight: 700; padding: 8px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <i class="fa fa-file-excel"></i> Export BOG Matrix (CSV)
                    </a>
                <?php endif; ?>
                <a href="/school/post-job" class="btn-primary" style="font-size: 0.85rem; padding: 8px 16px;">
                    <i class="fa fa-plus-circle"></i> Post Vacancy
                </a>
            </div>
        </div>

        <?php if ($successMsg): ?>
            <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-check-circle fa-lg"></i>
                <span style="font-size: 0.88rem; font-weight: 600;"><?= h($successMsg) ?></span>
            </div>
        <?php endif; ?>

        <?php if ($errorMsg): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-exclamation-circle fa-lg"></i>
                <span style="font-size: 0.88rem; font-weight: 600;"><?= h($errorMsg) ?></span>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <form method="GET" action="/school/applicants" style="margin: 0; display: flex; gap: 12px; flex-wrap: wrap; align-items: flex-end;">
                
                <div style="flex: 2; min-width: 220px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Vacancy</label>
                    <select name="job_id" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                        <option value="0">All Active Vacancies (<?= count($postedJobs) ?>)</option>
                        <?php foreach ($postedJobs as $pj): ?>
                            <option value="<?= $pj->id ?>" <?= ($selectedJobId == $pj->id) ? 'selected' : '' ?>>
                                <?= h($pj->title) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="flex: 1; min-width: 170px;">
                    <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px;">Status</label>
                    <select name="status" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                        <option value="all" <?= ($selectedStatus === 'all') ? 'selected' : '' ?>>All Statuses</option>
                        <option value="applied" <?= ($selectedStatus === 'applied') ? 'selected' : '' ?>>New Applied</option>
                        <option value="reviewing" <?= ($selectedStatus === 'reviewing') ? 'selected' : '' ?>>Under Review</option>
                        <option value="shortlisted" <?= ($selectedStatus === 'shortlisted') ? 'selected' : '' ?>>Shortlisted</option>
                        <option value="interview_scheduled" <?= ($selectedStatus === 'interview_scheduled') ? 'selected' : '' ?>>Interview Invited</option>
                        <option value="hired" <?= ($selectedStatus === 'hired') ? 'selected' : '' ?>>Hired</option>
                        <option value="rejected" <?= ($selectedStatus === 'rejected') ? 'selected' : '' ?>>Declined</option>
                    </select>
                </div>

                <div style="display: flex; align-items: center; padding-bottom: 10px;">
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 0.82rem; font-weight: 700; color: #0f766e; cursor: pointer; margin: 0;">
                        <input type="checkbox" name="verified_only" value="1" <?= $verifiedOnly ? 'checked' : '' ?> style="margin: 0;">
                        <span>✓ Verified Credentials Only</span>
                    </label>
                </div>

                <div style="display: flex; gap: 6px;">
                    <button type="submit" class="btn-primary" style="padding: 8px 18px; font-size: 0.85rem; margin: 0;">
                        <i class="fa fa-filter"></i> Filter
                    </button>
                    <?php if ($selectedJobId > 0 || $selectedStatus !== 'all' || $verifiedOnly): ?>
                        <a href="/school/applicants" style="padding: 8px 12px; color: #64748b; font-size: 0.85rem; text-decoration: none; display: inline-flex; align-items: center;">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>

            </form>
        </div>

        <?php if (empty($applicants)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; padding: 3.5rem 1.5rem;">
                <i class="fa fa-user-clock fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h4 style="color: #0f172a; margin: 0 0 6px;">No Applicants Found</h4>
                <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.25rem;">
                    No candidates currently match the selected criteria. You can also proactively search our candidate pool!
                </p>
                <a href="/school/search-candidates" class="btn-primary">
                    <i class="fa fa-search"></i> Search Available Candidates
                </a>
            </div>
        <?php else: ?>

            <!-- Bulk Actions Floating Bar -->
            <form id="bulkForm" method="POST" action="/school/applicants" style="margin: 0;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="bulkActionInput" value="">
                
                <div id="bulkActionBar" style="background: #ffffff; color: #1e293b; border: 1px solid #cbd5e1; padding: 12px 18px; border-radius: 8px; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
                    <div style="display: flex; align-items: center; gap: 12px;">
                        <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 700; cursor: pointer; margin: 0; color: #0f172a;">
                            <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this.checked)" style="width: 16px; height: 16px; cursor: pointer; accent-color: #0f766e;">
                            <span>Select All</span>
                        </label>
                        <span id="selectedCountBadge" style="background: #f1f5f9; color: #0f766e; border: 1px solid #ccfbf1; padding: 2px 10px; border-radius: 12px; font-size: 0.78rem; font-weight: 800;">
                            0 Selected
                        </span>
                    </div>

                    <div style="display: flex; gap: 8px;">
                        <button type="button" onclick="submitBulkAction('bulk_shortlist')" style="background: #16a34a; color: white; border: none; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer;">
                            <i class="fa fa-check"></i> Bulk Shortlist
                        </button>
                        <button type="button" onclick="openBulkRegretModal()" style="background: #dc2626; color: white; border: none; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer;">
                            <i class="fa fa-times-circle"></i> Bulk Reject
                        </button>
                    </div>
                </div>

                <!-- Modal for Customizing Bulk Regret Email -->
                <div id="bulkRegretModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 3000; align-items: center; justify-content: center; padding: 1rem;">
                    <div style="background: white; border-radius: 10px; max-width: 540px; width: 100%; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
                        <h4 style="margin: 0 0 8px; font-size: 1.15rem; color: #0f172a;"><i class="fa fa-times-circle" style="color: #dc2626;"></i> Bulk Reject Candidates</h4>
                        <p style="margin: 0 0 1rem; font-size: 0.85rem; color: #64748b;">
                            A professional notification will be dispatched to all selected applicants.
                        </p>
                        <label style="font-size: 0.82rem; font-weight: 700; color: #334155; margin-bottom: 4px; display: block;">Message to Candidates</label>
                        <textarea name="bulk_regret_message" rows="4" style="width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; line-height: 1.5; margin-bottom: 1.25rem;">Thank you for taking the time to apply for the teaching position at <?= h($school->name) ?>. After thorough review of all profiles, the recruitment committee has proceeded with other candidates for this intake. We appreciate your interest and wish you every success in your educational journey.</textarea>
                        
                        <div style="display: flex; justify-content: flex-end; gap: 8px;">
                            <button type="button" onclick="document.getElementById('bulkRegretModal').style.display='none'" style="background: #f1f5f9; color: #475569; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                            <button type="button" onclick="submitBulkAction('bulk_regret')" style="background: #dc2626; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">Confirm & Reject</button>
                        </div>
                    </div>
                </div>

                <!-- Applicants Cards List -->
                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($applicants as $app): ?>
                        <?php
                            $teacher = R::load('teacher', $app->teacher_id);
                            $job = R::load('job', $app->job_id);
                            $currStatus = $app->status ?: 'applied';
                            $pill = $statusPills[$currStatus] ?? $statusPills['applied'];
                            $thread = R::find('applicationmessage', 'application_id = ? ORDER BY id ASC', [$app->id]);
                            $matches = check_smart_matches($teacher, $job, $school);
                        ?>
                        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                            
                            <!-- Top Applicant Info -->
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 12px; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem;">
                                <div style="display: flex; gap: 12px; align-items: center;">
                                    <input type="checkbox" name="selected_app_ids[]" value="<?= $app->id ?>" class="app-checkbox" onchange="updateSelectedCount()" style="width: 18px; height: 18px; cursor: pointer; accent-color: #0f766e;">
                                    
                                    <div style="width: 48px; height: 48px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.2rem;">
                                        <?= strtoupper(substr($teacher->name ?: 'E', 0, 1)) ?>
                                    </div>
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 2px;">
                                            <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a; font-weight: 800;">
                                                <?= h($teacher->name) ?>
                                            </h3>
                                            
                                            <!-- Smart Matching Badges -->
                                            <?php if (isset($matches['subject'])): ?>
                                                <span style="background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px; border: 1px solid #bbf7d0;">
                                                    <?= $matches['subject'] ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (isset($matches['county'])): ?>
                                                <span style="background: #e0f2fe; color: #0369a1; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px;">
                                                    <?= $matches['county'] ?>
                                                </span>
                                            <?php endif; ?>

                                            <?php if ($teacher->verification_status === 'verified'): ?>
                                                <span style="background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px;">
                                                    ✓ Verified
                                                </span>
                                            <?php elseif (!empty($teacher->good_conduct_doc)): ?>
                                                <span style="background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px;">
                                                    Clearance on File
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($teacher->tsc_number)): ?>
                                                <span style="background: #f1f5f9; color: #475569; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px;">
                                                    TSC: <?= h($teacher->tsc_number) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                                            Applied for: <strong><?= h($job->title) ?></strong> &bull; <?= date('M d, Y', strtotime($app->application_date ?? 'now')) ?>
                                        </p>
                                    </div>
                                </div>

                                <!-- Status Dropdown Quick Updater & Delete Action -->
                                <div style="display: flex; align-items: center; gap: 8px;">
                                    <select onchange="updateSingleStatus(<?= $app->id ?>, this.value)" style="padding: 6px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 700; background: <?= $pill['bg'] ?>; color: <?= $pill['color'] ?>; border: 1px solid #cbd5e1; cursor: pointer; margin: 0;">
                                        <option value="applied" <?= ($currStatus === 'applied') ? 'selected' : '' ?>>Applied</option>
                                        <option value="reviewing" <?= ($currStatus === 'reviewing') ? 'selected' : '' ?>>Reviewing</option>
                                        <option value="shortlisted" <?= ($currStatus === 'shortlisted') ? 'selected' : '' ?>>Shortlisted</option>
                                        <option value="interview_scheduled" <?= ($currStatus === 'interview_scheduled') ? 'selected' : '' ?>>Interview Invited</option>
                                        <option value="hired" <?= ($currStatus === 'hired') ? 'selected' : '' ?>>Hired</option>
                                        <option value="rejected" <?= ($currStatus === 'rejected') ? 'selected' : '' ?>>Declined</option>
                                    </select>
                                    
                                    <button type="button" onclick="deleteSingleApplication(<?= $app->id ?>, '<?= addslashes($teacher->name) ?>')" title="Remove Applicant Record" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; padding: 6px 10px; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-trash-alt"></i> Delete
                                    </button>
                                </div>
                            </div>

                            <!-- Candidate Credentials Matrix -->
                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.82rem;">
                                <div>
                                    <span style="color: #64748b; font-weight: 600;">Teaching Subjects:</span>
                                    <div style="font-weight: 700; color: #0f172a;"><?= h($teacher->teaching_subjects ?: 'General') ?></div>
                                </div>
                                <div>
                                    <span style="color: #64748b; font-weight: 600;">Experience & Degree:</span>
                                    <div style="font-weight: 700; color: #0f172a;"><?= intval($teacher->years_of_experience) ?> Yrs (<?= h($teacher->qualification ?: 'Degree') ?>)</div>
                                </div>
                                <div>
                                    <span style="color: #64748b; font-weight: 600;">Availability:</span>
                                    <div style="font-weight: 700; color: #0f172a;"><?= h($app->available_from ?: 'Immediate') ?></div>
                                </div>
                                <div>
                                    <span style="color: #64748b; font-weight: 600;">Expected Salary:</span>
                                    <div style="font-weight: 700; color: #166534;"><?= !empty($app->expected_salary) ? 'KES ' . number_format($app->expected_salary) . ' / mo' : 'Negotiable' ?></div>
                                </div>
                            </div>

                            <?php if (!empty($app->cover_note)): ?>
                                <div style="margin-bottom: 1rem; font-size: 0.85rem; color: #334155; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                    <strong style="color: #0f172a;">Candidate's Pitch:</strong><br>
                                    "<?= nl2br(h($app->cover_note)) ?>"
                                </div>
                            <?php endif; ?>

                            <!-- Direct Actions & Message Toggle -->
                            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1rem; flex-wrap: wrap; gap: 10px;">
                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                    <a href="/teacher/profile?teacher_id=<?= $teacher->id ?>" target="_blank" style="background: #f1f5f9; color: #0f766e; font-weight: 700; font-size: 0.82rem; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-id-card"></i> View Full Profile & CV
                                    </a>
                                    <?php if (!empty($teacher->email)): ?>
                                        <a href="mailto:<?= h($teacher->email) ?>?subject=<?= urlencode("Teaching Opportunity at {$school->name}") ?>" style="background: #0f766e; color: white !important; font-weight: 600; font-size: 0.82rem; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-envelope"></i> Email Candidate
                                        </a>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <button type="button" onclick="document.getElementById('msg_panel_<?= $app->id ?>').style.display = (document.getElementById('msg_panel_<?= $app->id ?>').style.display === 'none' ? 'block' : 'none')" class="btn-primary" style="font-size: 0.82rem; padding: 6px 14px; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fa fa-calendar-check"></i> Schedule Interview / Message (<?= count($thread) ?>)
                                    </button>
                                </div>
                            </div>

                            <!-- In-App Messaging & Interview Drawer -->
                            <div id="msg_panel_<?= $app->id ?>" style="display: <?= count($thread) > 0 ? 'block' : 'none' ?>; margin-top: 1.25rem; border-top: 1px dashed #cbd5e1; padding-top: 1.25rem;">
                                <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; font-weight: 700; color: #0f172a;">
                                    <i class="fa fa-comments" style="color: #0f766e;"></i> Interview & Communication Thread
                                </h4>

                                <?php if (!empty($thread)): ?>
                                    <div style="display: flex; flex-direction: column; gap: 8px; margin-bottom: 1.25rem;">
                                        <?php foreach ($thread as $m): ?>
                                            <?php $isSchoolMsg = ($m->sender_type === 'school'); ?>
                                            <div style="display: flex; flex-direction: column; align-items: <?= $isSchoolMsg ? 'flex-end' : 'flex-start' ?>;">
                                                <div style="max-width: 85%; background: <?= $isSchoolMsg ? '#0f766e' : '#f1f5f9' ?>; color: <?= $isSchoolMsg ? '#ffffff' : '#1e293b' ?>; border-radius: 8px; padding: 10px 14px; font-size: 0.85rem; line-height: 1.5;">
                                                    <div style="font-size: 0.7rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; color: <?= $isSchoolMsg ? '#ccfbf1' : '#64748b' ?>;">
                                                        <?= $isSchoolMsg ? 'You (School HR)' : h($teacher->name) ?> &bull; <?= date('M d, Y h:i A', strtotime($m->created_at)) ?>
                                                    </div>
                                                    <?= nl2br(h($m->message)) ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <!-- Enhanced Interview Invitation Form (In-Person vs Virtual) -->
                                <form method="POST" action="/school/applicants" style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin: 0;">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="send_message">
                                    <input type="hidden" name="application_id" value="<?= $app->id ?>">

                                    <div style="margin-bottom: 12px; background: white; padding: 10px; border-radius: 6px; border: 1px solid #e2e8f0;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 6px;">Interview Format</label>
                                        <div style="display: flex; gap: 16px;">
                                            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #0f172a;">
                                                <input type="radio" name="interview_format" value="in_person" checked onchange="toggleInterviewFormat(<?= $app->id ?>, 'in_person')">
                                                <span>In-Person</span>
                                            </label>
                                            <label style="display: flex; align-items: center; gap: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer; color: #0f172a;">
                                                <input type="radio" name="interview_format" value="virtual" onchange="toggleInterviewFormat(<?= $app->id ?>, 'virtual')">
                                                <span>Virtual</span>
                                            </label>
                                        </div>
                                    </div>

                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 10px;">
                                        <div>
                                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">Date & Time</label>
                                            <input type="datetime-local" name="interview_date" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; margin: 0; box-sizing: border-box;">
                                        </div>

                                        <!-- In Person Venue Container -->
                                        <div id="venue_container_<?= $app->id ?>">
                                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">Location</label>
                                            <input type="text" name="interview_location" placeholder="e.g. Principal's Office, Boardroom" style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; margin: 0; box-sizing: border-box;">
                                        </div>

                                        <!-- Virtual Link Container -->
                                        <div id="virtual_container_<?= $app->id ?>" style="display: none;">
                                            <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #2563eb; text-transform: uppercase; margin-bottom: 4px;">Meeting Link</label>
                                            <input type="url" name="interview_virtual_link" placeholder="https://meet.google.com/abc-defg-hij" style="width: 100%; padding: 7px 10px; border: 1px solid #93c5fd; border-radius: 6px; font-size: 0.82rem; margin: 0; box-sizing: border-box;">
                                        </div>
                                    </div>

                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; font-size: 0.75rem; font-weight: 700; color: #475569; text-transform: uppercase; margin-bottom: 4px;">Message to Candidate</label>
                                        <textarea name="message" rows="3" placeholder="Hello <?= h($teacher->name) ?>, we were impressed by your profile and would like to invite you for an interview..." required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; resize: vertical;"></textarea>
                                    </div>

                                    <div style="display: flex; justify-content: flex-end;">
                                        <button type="submit" class="btn-primary" style="font-size: 0.85rem; padding: 8px 18px;">
                                            <i class="fa fa-paper-plane"></i> Send Notification & Update Status
                                        </button>
                                    </div>
                                </form>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            </form>

            <!-- Separate Single Action Form for Quick Updates without submitting entire bulk form -->
            <form id="singleActionForm" method="POST" action="/school/applicants" style="display: none;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" id="singleActionType">
                <input type="hidden" name="application_id" id="singleAppId">
                <input type="hidden" name="status" id="singleAppStatus">
            </form>

        <?php endif; ?>

    </main>
</div>

<script>
function toggleSelectAll(checked) {
    document.querySelectorAll('.app-checkbox').forEach(cb => cb.checked = checked);
    updateSelectedCount();
}

function updateSelectedCount() {
    const checked = document.querySelectorAll('.app-checkbox:checked').length;
    document.getElementById('selectedCountBadge').innerText = checked + ' Selected';
}

function submitBulkAction(action) {
    const checked = document.querySelectorAll('.app-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one candidate first.');
        return;
    }
    if (action === 'bulk_regret') {
        document.getElementById('bulkRegretModal').style.display = 'none';
    }
    document.getElementById('bulkActionInput').value = action;
    document.getElementById('bulkForm').submit();
}

function openBulkRegretModal() {
    const checked = document.querySelectorAll('.app-checkbox:checked').length;
    if (checked === 0) {
        alert('Please select at least one candidate first.');
        return;
    }
    document.getElementById('bulkRegretModal').style.display = 'flex';
}

function toggleInterviewFormat(appId, format) {
    const venue = document.getElementById('venue_container_' + appId);
    const virtual = document.getElementById('virtual_container_' + appId);
    if (format === 'virtual') {
        venue.style.display = 'none';
        virtual.style.display = 'block';
    } else {
        venue.style.display = 'block';
        virtual.style.display = 'none';
    }
}

function updateSingleStatus(appId, newStatus) {
    document.getElementById('singleActionType').value = 'update_status';
    document.getElementById('singleAppId').value = appId;
    document.getElementById('singleAppStatus').value = newStatus;
    document.getElementById('singleActionForm').submit();
}

function deleteSingleApplication(appId, teacherName) {
    if (confirm('Are you sure you want to remove application from ' + teacherName + '? This will delete the candidate record and messages for this vacancy.')) {
        document.getElementById('singleActionType').value = 'delete_application';
        document.getElementById('singleAppId').value = appId;
        document.getElementById('singleActionForm').submit();
    }
}
</script>
