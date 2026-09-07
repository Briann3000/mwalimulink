<?php
// views/teacher_job_apply.php - Modern 1-Click Educator Application Experience
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];
$teacher = R::load('teacher', $teacher_id);

$job_id = intval($_GET['job_id'] ?? 0);
$job = R::load('job', $job_id);

$msg = '';
$error = '';
$applied = false;
$existingApplication = null;

if (!$job->id) {
    $error = "The requested vacancy was not found or has expired.";
} else {
    $isDeadlinePassed = false;
    if (!empty($job->deadline)) {
        if ((new DateTime($job->deadline))->format('Y-m-d') < date('Y-m-d')) {
            $isDeadlinePassed = true;
            $error = "Applications for this vacancy closed on " . date('M d, Y', strtotime($job->deadline)) . ".";
        }
    }

    $school = R::load('school', $job->school_id);
    $existingApplication = R::findOne('applications', 'teacher_id = ? AND job_id = ?', [$teacher_id, $job_id]);
    if ($existingApplication) {
        $applied = true;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$applied && !$isDeadlinePassed) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $error = "Security token mismatch. Please try again.";
        } else {
            $coverNote = trim($_POST['cover_note'] ?? '');
            $expectedSalary = (!empty($_POST['expected_salary']) && is_numeric($_POST['expected_salary'])) ? floatval($_POST['expected_salary']) : null;
            $availOption = trim($_POST['available_from'] ?? 'Immediately');
            if ($availOption === 'Specific Date' && !empty($_POST['custom_available_date'])) {
                $availableFrom = 'From ' . date('M d, Y', strtotime($_POST['custom_available_date']));
            } else {
                $availableFrom = $availOption;
            }

            // Optional tailored attachment
            $attachmentPath = null;
            if (!empty($_FILES['custom_doc']['name']) && $_FILES['custom_doc']['error'] === UPLOAD_ERR_OK) {
                $uploadDir = __DIR__ . '/../uploads/applications/';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0755, true);
                }
                $ext = strtolower(pathinfo($_FILES['custom_doc']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                    $newName = 'app_' . $teacher_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($_FILES['custom_doc']['tmp_name'], $uploadDir . $newName)) {
                        $attachmentPath = 'uploads/applications/' . $newName;
                    }
                }
            }

            $application = R::dispense('applications');
            $application->teacher_id = $teacher_id;
            $application->job_id = $job_id;
            $application->status = 'applied';
            $application->cover_note = $coverNote;
            $application->expected_salary = $expectedSalary;
            $application->available_from = $availableFrom;
            $application->attachment_doc = $attachmentPath;
            $application->application_date = date('Y-m-d H:i:s');
            $application->updated_at = date('Y-m-d H:i:s');
            $appId = R::store($application);

            // Dispatch instant email alert to hiring school administration
            if ($school && $school->email) {
                @send_job_application_notification_email($school, $job, $teacher, $application);
            }

            $applied = true;
            $existingApplication = $application;
            $msg = "Your application has been delivered to {$school->name}!";
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="max-width: 740px; margin: 0 auto;">
            
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                <a href="/teacher/jobs" style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; font-weight: 600;">
                    <i class="fa fa-arrow-left"></i> Back to Vacancies
                </a>
                <a href="/teacher/applications" style="color: #0f766e; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
                    My Applications &rarr;
                </a>
            </div>

            <?php if ($msg): ?>
                <div style="background: white; border: 1px solid #86efac; border-radius: 10px; padding: 2.5rem 1.75rem; text-align: center; box-shadow: 0 4px 12px rgba(22,163,74,0.08); margin-bottom: 2rem;">
                    <div style="width: 56px; height: 56px; border-radius: 50%; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center; font-size: 1.6rem; margin: 0 auto 1rem;">
                        <i class="fa fa-check"></i>
                    </div>
                    <h2 style="margin: 0 0 6px; color: #166534; font-size: 1.4rem;">Application Submitted Successfully!</h2>
                    <p style="margin: 0 0 1.5rem; font-size: 0.95rem; color: #475569; max-width: 520px; margin-left: auto; margin-right: auto; line-height: 1.5;">
                        <?= h($msg) ?> We have notified the school recruitment team with your credentials and pitch.
                    </p>
                    <div style="display: flex; gap: 12px; justify-content: center; flex-wrap: wrap;">
                        <a href="/teacher/applications" class="btn-primary" style="padding: 10px 22px; font-size: 0.9rem;">
                            <i class="fa fa-list-check"></i> Track Application Status
                        </a>
                        <a href="/teacher/jobs" style="background: #f1f5f9; color: #334155; padding: 10px 20px; border-radius: 6px; font-size: 0.9rem; font-weight: 600; text-decoration: none;">
                            Browse More Vacancies
                        </a>
                    </div>
                </div>

            <?php elseif ($error): ?>
                <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 1.25rem 1.5rem; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 10px;">
                    <i class="fa fa-exclamation-circle fa-lg"></i>
                    <span style="font-size: 0.9rem; font-weight: 600;"><?= h($error) ?></span>
                </div>

            <?php elseif ($applied && $existingApplication): ?>
                <?php 
                    $currStatus = $existingApplication->status ?: 'applied';
                    $statusLabels = [
                        'applied' => ['label' => 'Application Submitted', 'color' => '#0369a1', 'bg' => '#e0f2fe', 'icon' => 'fa-paper-plane'],
                        'reviewing' => ['label' => 'Under Review by School', 'color' => '#854d0e', 'bg' => '#fef9c3', 'icon' => 'fa-eye'],
                        'shortlisted' => ['label' => 'Shortlisted for Next Stage', 'color' => '#15803d', 'bg' => '#dcfce7', 'icon' => 'fa-star'],
                        'interview_scheduled' => ['label' => 'Interview Invited / Scheduled', 'color' => '#6d28d9', 'bg' => '#ede9fe', 'icon' => 'fa-calendar-check'],
                        'hired' => ['label' => 'Offered / Hired', 'color' => '#166534', 'bg' => '#bbf7d0', 'icon' => 'fa-check-double'],
                        'rejected' => ['label' => 'Not Selected', 'color' => '#991b1b', 'bg' => '#fee2e2', 'icon' => 'fa-times-circle'],
                    ];
                    $cfg = $statusLabels[$currStatus] ?? $statusLabels['applied'];
                ?>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2.5rem 1.75rem; text-align: center; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="width: 52px; height: 52px; border-radius: 50%; background: <?= $cfg['bg'] ?>; color: <?= $cfg['color'] ?>; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; margin: 0 auto 1rem;">
                        <i class="fa <?= $cfg['icon'] ?>"></i>
                    </div>
                    <h3 style="margin: 0 0 6px; color: #0f172a; font-size: 1.3rem;">You Have Already Applied</h3>
                    <p style="margin: 0 0 1rem; font-size: 0.9rem; color: #64748b;">
                        You submitted an application for <strong><?= h($job->title) ?></strong> at <strong><?= h($school->name) ?></strong> on <?= date('M d, Y', strtotime($existingApplication->application_date ?? 'now')) ?>.
                    </p>
                    <div style="margin-bottom: 1.5rem;">
                        <span style="display: inline-block; background: <?= $cfg['bg'] ?>; color: <?= $cfg['color'] ?>; font-size: 0.85rem; font-weight: 700; padding: 6px 14px; border-radius: 20px;">
                            Current Status: <?= $cfg['label'] ?>
                        </span>
                    </div>
                    <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                        <a href="/teacher/applications" class="btn-primary" style="padding: 9px 20px; font-size: 0.88rem;">
                            <i class="fa fa-envelope-open-text"></i> View Status & Messages
                        </a>
                        <a href="/teacher/jobs" style="background: #f1f5f9; color: #334155; padding: 9px 18px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; text-decoration: none;">
                            Browse Other Vacancies
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Application Form -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 4px rgba(0,0,0,0.03);">
                    
                    <div style="border-bottom: 1px solid #f1f5f9; padding-bottom: 1.25rem; margin-bottom: 1.5rem;">
                        <span style="background: #ede9fe; color: #6d28d9; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; text-transform: uppercase;">
                            1-Click Easy Apply
                        </span>
                        <h2 style="margin: 8px 0 4px; font-size: 1.35rem; color: #0f172a; font-weight: 800;">
                            Apply for <?= h($job->title) ?>
                        </h2>
                        <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
                            <i class="fa fa-school" style="color: #0f766e;"></i> <strong><?= h($school->name) ?></strong> &bull; 
                            <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($school->county ?: 'Kenya') ?>
                            <?php if (!empty($job->salary)): ?>
                                &bull; <span style="color: #166534; font-weight: 700;">KES <?= number_format($job->salary) ?> / mo</span>
                            <?php endif; ?>
                        </p>
                    </div>

                    <!-- Teacher Credential Snapshot -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                            <span style="font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
                                Profile Information Sent With Application
                            </span>
                            <a href="/teacher/update" target="_blank" style="font-size: 0.78rem; font-weight: 700; color: #0f766e; text-decoration: none;">
                                <i class="fa fa-pen"></i> Edit Profile
                            </a>
                        </div>

                        <div style="display: flex; gap: 12px; align-items: center; flex-wrap: wrap;">
                            <div style="width: 44px; height: 44px; border-radius: 50%; background: #0f766e; color: white; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 1.1rem;">
                                <?= strtoupper(substr($teacher->name ?: 'T', 0, 1)) ?>
                            </div>
                            <div>
                                <h4 style="margin: 0 0 2px; font-size: 0.98rem; color: #0f172a;">
                                    <?= h($teacher->name) ?>
                                    <?php if ($teacher->verification_status === 'verified'): ?>
                                        <span style="background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px; margin-left: 6px;">
                                            ✓ Verified Educator
                                        </span>
                                    <?php elseif (!empty($teacher->good_conduct_doc)): ?>
                                        <span style="background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px; margin-left: 6px;">
                                            Clearance on File
                                        </span>
                                    <?php endif; ?>
                                </h4>
                                <p style="margin: 0; font-size: 0.82rem; color: #64748b;">
                                    <strong>Subjects:</strong> <?= h($teacher->teaching_subjects ?: 'General') ?> &bull; 
                                    <strong>Exp:</strong> <?= intval($teacher->years_of_experience) ?> Years
                                    <?php if (!empty($teacher->tsc_number)): ?>
                                        &bull; <strong>TSC:</strong> <?= h($teacher->tsc_number) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($job->deadline)): ?>
                        <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 10px 14px; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-clock"></i>
                            <span>Application Deadline: <strong><?= date('M d, Y', strtotime($job->deadline)) ?></strong> (<?= (new DateTime($job->deadline))->diff(new DateTime('today'))->days ?> days remaining)</span>
                        </div>
                    <?php endif; ?>

                    <form method="POST" action="" enctype="multipart/form-data">
                        <?= csrf_field() ?>

                        <!-- Personalized Pitch / Note -->
                        <div style="margin-bottom: 1.25rem;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
                                Why are you a great fit for this position? <span style="color: #94a3b8; font-weight: normal;">(Recommended)</span>
                            </label>
                            <textarea name="cover_note" rows="4" placeholder="Briefly highlight your teaching achievements, subject mastery, classroom management skills, or why you want to join <?= h($school->name) ?>..." style="width: 100%; padding: 10px 14px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; font-family: inherit; box-sizing: border-box; resize: vertical;"></textarea>
                            <span style="font-size: 0.75rem; color: #64748b; margin-top: 4px; display: block;">
                                This personal pitch appears prominently to the headteacher/recruiter.
                            </span>
                        </div>

                        <!-- Availability & Expected Salary -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; margin-bottom: 1.25rem;">
                            <div>
                                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
                                    Earliest Availability
                                </label>
                                <select name="available_from" id="availableFromSelect" onchange="document.getElementById('customAvailableDateWrapper').style.display = (this.value === 'Specific Date') ? 'block' : 'none';" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; background: white; margin: 0; box-sizing: border-box;">
                                    <option value="Immediately">Available Immediately</option>
                                    <option value="Within 2 Weeks">Within 2 Weeks</option>
                                    <option value="1 Month Notice">1 Month Notice</option>
                                    <option value="Beginning of Next Term">Beginning of Next Term</option>
                                    <option value="Specific Date">Specific Date (Custom Date)</option>
                                </select>
                                <div id="customAvailableDateWrapper" style="display: none; margin-top: 8px;">
                                    <label style="display: block; font-size: 0.78rem; font-weight: 700; color: #0f766e; margin-bottom: 4px;">Choose Available Date:</label>
                                    <input type="date" name="custom_available_date" min="<?= date('Y-m-d') ?>" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box; background: white;">
                                </div>
                            </div>

                            <div>
                                <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 6px;">
                                    Expected Monthly Salary (KES) <span style="color: #94a3b8; font-weight: normal;">(Optional)</span>
                                </label>
                                <input type="number" name="expected_salary" min="0" step="500" placeholder="e.g. 35000" style="width: 100%; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.88rem; margin: 0; box-sizing: border-box;">
                            </div>
                        </div>

                        <!-- Optional Tailored Attachment -->
                        <div style="margin-bottom: 1.5rem; background: #f8fafc; border: 1px dashed #cbd5e1; border-radius: 8px; padding: 1rem;">
                            <label style="display: block; font-size: 0.85rem; font-weight: 700; color: #1e293b; margin-bottom: 4px;">
                                <i class="fa fa-paperclip" style="color: #0f766e;"></i> Tailored Application Document / Portfolio <span style="color: #94a3b8; font-weight: normal;">(Optional)</span>
                            </label>
                            <p style="margin: 0 0 8px; font-size: 0.78rem; color: #64748b;">
                                Upload a job-specific cover letter, CBC lesson plan sample, or specialized certificate (PDF, Word, or Image, Max 5MB). Your primary MwalimuLink profile & CV are attached automatically.
                            </p>
                            <input type="file" name="custom_doc" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="font-size: 0.85rem; color: #475569;">
                        </div>

                        <div style="display: flex; gap: 10px; justify-content: flex-end; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1.25rem;">
                            <a href="/teacher/jobs" style="background: #f1f5f9; color: #475569; padding: 10px 18px; border-radius: 6px; font-size: 0.88rem; font-weight: 600; text-decoration: none;">
                                Cancel
                            </a>
                            <button type="submit" class="btn-primary" style="font-size: 0.9rem; padding: 10px 24px;">
                                <i class="fa fa-paper-plane"></i> Submit Application Now
                            </button>
                        </div>
                    </form>

                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

