<?php
// views/teacher_applications.php - Teacher Application Tracking & Two-Way Messaging Hub with Visual Stepper
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];
$teacher = R::load('teacher', $teacher_id);

$successMsg = '';
$errorMsg = '';

// Handle POST Actions: Reply or Withdraw/Delete Application
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errorMsg = "Security token mismatch. Please try again.";
    } else {
        $action = trim($_POST['action'] ?? '');
        $applicationId = intval($_POST['application_id'] ?? 0);

        $app = R::load('applications', $applicationId);
        if ($app->id && $app->teacher_id == $teacher_id) {
            $job = R::load('job', $app->job_id);
            $school = R::load('school', $job->school_id);

            if ($action === 'delete_application') {
                $messages = R::find('applicationmessage', 'application_id = ?', [$applicationId]);
                foreach ($messages as $m) {
                    R::trash($m);
                }
                R::trash($app);
                $successMsg = "Application for " . ($job->title ?: 'the role') . " has been successfully withdrawn.";
            } elseif ($action === 'send_reply') {
                $replyText = trim($_POST['message'] ?? '');
                if (!empty($replyText)) {
                    $msgBean = R::dispense('applicationmessage');
                    $msgBean->application_id = $applicationId;
                    $msgBean->sender_type = 'teacher';
                    $msgBean->sender_id = $teacher_id;
                    $msgBean->recipient_type = 'school';
                    $msgBean->recipient_id = $school->id;
                    $msgBean->message = $replyText;
                    $msgBean->created_at = date('Y-m-d H:i:s');
                    $msgBean->is_read = 0;
                    R::store($msgBean);

                    $app->updated_at = date('Y-m-d H:i:s');
                    R::store($app);

                    // Notify school administration via email
                    if ($school && $school->email) {
                        @send_teacher_reply_email($school, $teacher, $job, $replyText);
                    }

                    $successMsg = "Your response has been sent to {$school->name}!";
                } else {
                    $errorMsg = "Please enter a message before sending.";
                }
            }
        } else {
            $errorMsg = "Application record not found.";
        }
    }
}

// Fetch all applications submitted by this teacher
$filterStatus = trim($_GET['status'] ?? 'all');
$query = 'teacher_id = ?';
$params = [$teacher_id];

if ($filterStatus === 'active') {
    $query .= " AND (status = 'applied' OR status = 'reviewing')";
} elseif ($filterStatus === 'interview') {
    $query .= " AND (status = 'interview_scheduled' OR status = 'shortlisted')";
} elseif ($filterStatus === 'archive') {
    $query .= " AND (status = 'hired' OR status = 'rejected')";
}

$applications = R::find('applications', "$query ORDER BY id DESC", $params);

// Helper to determine stepper stage state
function get_stepper_state($currentStatus) {
    // 5 main stages: 1. Applied, 2. Under Review, 3. Shortlisted, 4. Interview, 5. Decision
    $stageIndex = 1;
    $isRejected = ($currentStatus === 'rejected');
    $isHired = ($currentStatus === 'hired');

    switch ($currentStatus) {
        case 'applied':
            $stageIndex = 1;
            break;
        case 'reviewing':
            $stageIndex = 2;
            break;
        case 'shortlisted':
            $stageIndex = 3;
            break;
        case 'interview_scheduled':
            $stageIndex = 4;
            break;
        case 'hired':
            $stageIndex = 5;
            break;
        case 'rejected':
            $stageIndex = 5;
            break;
    }

    return [
        'stage' => $stageIndex,
        'is_rejected' => $isRejected,
        'is_hired' => $isHired
    ];
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="padding-top: 1.5rem !important;">
        
        <!-- Header & Breadcrumbs -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a; font-weight: 800;">My Job Applications & Pipeline</h2>
                <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                    Track real-time hiring stages, visual progress timelines, interview meeting links, and direct correspondence with schools.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="/teacher/jobs" class="btn-primary" style="font-size: 0.85rem; padding: 8px 16px;">
                    <i class="fa fa-briefcase"></i> Explore Vacancies
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

        <!-- Filter Tabs -->
        <div style="display: flex; gap: 8px; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.5rem; flex-wrap: wrap;">
            <a href="/teacher/applications" style="padding: 6px 14px; border-radius: 6px; font-size: 0.84rem; font-weight: 600; text-decoration: none; <?= ($filterStatus === 'all') ? 'background: #0f766e; color: white !important;' : 'background: #f1f5f9; color: #475569;' ?>">
                All Applications
            </a>
            <a href="/teacher/applications?status=active" style="padding: 6px 14px; border-radius: 6px; font-size: 0.84rem; font-weight: 600; text-decoration: none; <?= ($filterStatus === 'active') ? 'background: #0f766e; color: white !important;' : 'background: #f1f5f9; color: #475569;' ?>">
                Under Review
            </a>
            <a href="/teacher/applications?status=interview" style="padding: 6px 14px; border-radius: 6px; font-size: 0.84rem; font-weight: 600; text-decoration: none; <?= ($filterStatus === 'interview') ? 'background: #0f766e; color: white !important;' : 'background: #f1f5f9; color: #475569;' ?>">
                Interviews & Shortlists
            </a>
            <a href="/teacher/applications?status=archive" style="padding: 6px 14px; border-radius: 6px; font-size: 0.84rem; font-weight: 600; text-decoration: none; <?= ($filterStatus === 'archive') ? 'background: #0f766e; color: white !important;' : 'background: #f1f5f9; color: #475569;' ?>">
                Archived / Closed
            </a>
        </div>

        <?php if (empty($applications)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; padding: 3rem 1.5rem;">
                <i class="fa fa-envelope-open fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h4 style="color: #0f172a; margin: 0 0 6px;">No Applications Found</h4>
                <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.25rem;">
                    You haven't submitted any applications in this category yet.
                </p>
                <a href="/teacher/jobs" class="btn-primary">Browse Teaching Vacancies</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                <?php foreach ($applications as $app): ?>
                    <?php
                        $job = R::load('job', $app->job_id);
                        $school = R::load('school', $job->school_id);
                        $currStatus = $app->status ?: 'applied';
                        $stepper = get_stepper_state($currStatus);
                        $thread = R::find('applicationmessage', 'application_id = ? ORDER BY id ASC', [$app->id]);
                    ?>
                    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                        
                        <!-- Top Summary Header -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 10px; margin-bottom: 1.25rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 0.75rem;">
                            <div>
                                <h3 style="margin: 0 0 4px; font-size: 1.2rem; color: #0f172a; font-weight: 800;">
                                    <?= h($job->title ?: 'Teaching Position') ?>
                                </h3>
                                <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                                    <i class="fa fa-school" style="color: #0f766e;"></i> <strong><?= h($school->name ?: 'Registered Institution') ?></strong> &bull; 
                                    <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($school->county ?: 'Kenya') ?>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <form method="POST" action="/teacher/applications" style="margin: 0;" onsubmit="return confirm('Are you sure you want to withdraw and delete this application for <?= addslashes(h($job->title)) ?>?');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete_application">
                                    <input type="hidden" name="application_id" value="<?= $app->id ?>">
                                    <button type="submit" title="Withdraw Application" style="background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; border-radius: 6px; padding: 5px 12px; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-trash-alt"></i> Withdraw
                                    </button>
                                </form>
                            </div>
                        </div>

                        <!-- VISUAL 5-STEP APPLICATION PROGRESS STEPPER -->
                        <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem 1rem; margin-bottom: 1.25rem;">
                            <div style="display: flex; justify-content: space-between; align-items: center; position: relative;">
                                
                                <!-- Step 1: Applied -->
                                <div style="flex: 1; text-align: center; position: relative; z-index: 2;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #0f766e; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                        ✓
                                    </div>
                                    <div style="font-size: 0.78rem; font-weight: 700; color: #0f172a;">1. Applied</div>
                                    <div style="font-size: 0.7rem; color: #64748b;"><?= date('d M Y', strtotime($app->application_date ?? 'now')) ?></div>
                                </div>

                                <!-- Step 2: Under Review -->
                                <?php $isS2 = ($stepper['stage'] >= 2); ?>
                                <div style="flex: 1; text-align: center; position: relative; z-index: 2;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= $isS2 ? '#0f766e' : '#e2e8f0' ?>; color: <?= $isS2 ? 'white' : '#64748b' ?>; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                        <?= $isS2 ? '✓' : '2' ?>
                                    </div>
                                    <div style="font-size: 0.78rem; font-weight: <?= $isS2 ? '700' : '500' ?>; color: <?= $isS2 ? '#0f172a' : '#94a3b8' ?>;">2. Reviewing</div>
                                </div>

                                <!-- Step 3: Shortlisted -->
                                <?php $isS3 = ($stepper['stage'] >= 3); ?>
                                <div style="flex: 1; text-align: center; position: relative; z-index: 2;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= $isS3 ? '#0f766e' : '#e2e8f0' ?>; color: <?= $isS3 ? 'white' : '#64748b' ?>; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                        <?= $isS3 ? '✓' : '3' ?>
                                    </div>
                                    <div style="font-size: 0.78rem; font-weight: <?= $isS3 ? '700' : '500' ?>; color: <?= $isS3 ? '#0f172a' : '#94a3b8' ?>;">3. Shortlisted</div>
                                </div>

                                <!-- Step 4: Interview -->
                                <?php $isS4 = ($stepper['stage'] >= 4); ?>
                                <div style="flex: 1; text-align: center; position: relative; z-index: 2;">
                                    <div style="width: 32px; height: 32px; border-radius: 50%; background: <?= $isS4 ? '#2563eb' : '#e2e8f0' ?>; color: <?= $isS4 ? 'white' : '#64748b' ?>; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                        <?= $isS4 ? '🎥' : '4' ?>
                                    </div>
                                    <div style="font-size: 0.78rem; font-weight: <?= $isS4 ? '700' : '500' ?>; color: <?= $isS4 ? '#2563eb' : '#94a3b8' ?>;">4. Interview</div>
                                </div>

                                <!-- Step 5: Final Decision -->
                                <div style="flex: 1; text-align: center; position: relative; z-index: 2;">
                                    <?php if ($stepper['is_hired']): ?>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #16a34a; color: white; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                            🎉
                                        </div>
                                        <div style="font-size: 0.78rem; font-weight: 800; color: #16a34a;">5. Offer Made!</div>
                                    <?php elseif ($stepper['is_rejected']): ?>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #fee2e2; color: #dc2626; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                            ✕
                                        </div>
                                        <div style="font-size: 0.78rem; font-weight: 700; color: #991b1b;">5. Closed</div>
                                    <?php else: ?>
                                        <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #64748b; display: inline-flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 800; margin-bottom: 4px;">
                                            5
                                        </div>
                                        <div style="font-size: 0.78rem; font-weight: 500; color: #94a3b8;">5. Decision</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Scheduled Interview Callout Banner (If Interview Invited) -->
                        <?php if ($currStatus === 'interview_scheduled' && !empty($app->interview_date)): ?>
                            <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                                <div>
                                    <h4 style="margin: 0 0 4px; color: #1e40af; font-size: 0.95rem; font-weight: 800;">
                                        <i class="fa fa-calendar-check"></i> Interview Scheduled: <?= date('D, M d, Y \a\t h:i A', strtotime($app->interview_date)) ?>
                                    </h4>
                                    <?php if ($app->interview_format === 'virtual'): ?>
                                        <span style="font-size: 0.84rem; color: #1e3a8a;">Format: <strong>Virtual Meeting (Online)</strong></span>
                                    <?php else: ?>
                                        <span style="font-size: 0.84rem; color: #1e3a8a;">Venue: <strong><?= h($app->interview_location ?: 'School Campus') ?></strong></span>
                                    <?php endif; ?>
                                </div>
                                <?php if ($app->interview_format === 'virtual' && !empty($app->interview_location)): ?>
                                    <div>
                                        <a href="<?= h($app->interview_location) ?>" target="_blank" style="background: #2563eb; color: white !important; font-size: 0.88rem; font-weight: 700; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="fa fa-video"></i> Join Virtual Interview &rarr;
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Application Details Grid -->
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1rem; font-size: 0.82rem;">
                            <div>
                                <span style="color: #64748b; font-weight: 600;">Availability Submitted:</span>
                                <div style="font-weight: 700; color: #0f172a;"><?= h($app->available_from ?: 'Immediate') ?></div>
                            </div>
                            <?php if (!empty($app->expected_salary)): ?>
                                <div>
                                    <span style="color: #64748b; font-weight: 600;">Expected Salary:</span>
                                    <div style="font-weight: 700; color: #166534;">KES <?= number_format($app->expected_salary) ?> / mo</div>
                                </div>
                            <?php endif; ?>
                            <div>
                                <span style="color: #64748b; font-weight: 600;">Last Activity:</span>
                                <div style="font-weight: 700; color: #0f172a;"><?= date('M d, Y', strtotime($app->updated_at ?: $app->application_date ?: 'now')) ?></div>
                            </div>
                        </div>

                        <?php if (!empty($app->cover_note)): ?>
                            <div style="margin-bottom: 1rem; font-size: 0.85rem; color: #475569; background: #f8fafc; padding: 10px 14px; border-radius: 8px; border: 1px solid #e2e8f0;">
                                <strong style="color: #0f172a;">Your Application Pitch:</strong><br>
                                "<?= nl2br(h($app->cover_note)) ?>"
                            </div>
                        <?php endif; ?>

                        <!-- Two-Way Messaging Thread -->
                        <div style="border-top: 1px solid #f1f5f9; padding-top: 1rem; margin-top: 0.5rem;">
                            <h4 style="margin: 0 0 0.75rem; font-size: 0.9rem; font-weight: 700; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                                <i class="fa fa-comments" style="color: #0f766e;"></i> Direct Communication Thread (<?= count($thread) ?>)
                            </h4>

                            <?php if (empty($thread)): ?>
                                <p style="margin: 0 0 1rem; font-size: 0.82rem; color: #94a3b8; font-style: italic;">
                                    No messages yet. When <?= h($school->name) ?> reaches out regarding your interview, updates will appear here.
                                </p>
                            <?php else: ?>
                                <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 1.25rem;">
                                    <?php foreach ($thread as $m): ?>
                                        <?php $isSchoolMsg = ($m->sender_type === 'school'); ?>
                                        <div style="display: flex; flex-direction: column; align-items: <?= $isSchoolMsg ? 'flex-start' : 'flex-end' ?>;">
                                            <div style="max-width: 85%; background: <?= $isSchoolMsg ? '#eff6ff' : '#0f766e' ?>; color: <?= $isSchoolMsg ? '#1e3a8a' : '#ffffff' ?>; border: 1px solid <?= $isSchoolMsg ? '#bfdbfe' : '#0f766e' ?>; border-radius: 8px; padding: 10px 14px; font-size: 0.88rem; line-height: 1.5;">
                                                <div style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; color: <?= $isSchoolMsg ? '#2563eb' : '#ccfbf1' ?>;">
                                                    <?= $isSchoolMsg ? h($school->name ?: 'School HR') : 'You (Candidate)' ?> &bull; <?= date('M d, Y h:i A', strtotime($m->created_at)) ?>
                                                </div>
                                                <?= nl2br(h($m->message)) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Reply Form -->
                            <form method="POST" action="/teacher/applications" style="margin: 0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="send_reply">
                                <input type="hidden" name="application_id" value="<?= $app->id ?>">
                                <div style="display: flex; gap: 8px; align-items: flex-end; flex-wrap: wrap;">
                                    <div style="flex: 1; min-width: 240px;">
                                        <input type="text" name="message" placeholder="Type your response to <?= h($school->name) ?>..." required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0; box-sizing: border-box;">
                                    </div>
                                    <button type="submit" class="btn-primary" style="padding: 8px 16px; font-size: 0.85rem; margin: 0; white-space: nowrap;">
                                        <i class="fa fa-paper-plane"></i> Send Reply
                                    </button>
                                </div>
                            </form>
                        </div>

                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>
</div>
