<?php
// views/tp_hub.php - Teaching Practice (TP) & Student Placement Hub (Role-Differentiated)
$authUser = auth_user();
$isSchool = ($authUser && ($authUser['role'] ?? '') === 'school');
$isTeacher = ($authUser && ($authUser['role'] ?? '') === 'teacher');

$msg = '';
$error = '';

$countyFilter = trim($_GET['county'] ?? '');
$subjectFilter = trim($_GET['subject'] ?? '');

// ----------------------------------------------------------------------------------
// 1. SCHOOL ACTIONS & DATA PROCESSING
// ----------------------------------------------------------------------------------
if ($isSchool) {
    $school_id = $authUser['user_id'];
    $school = R::load('school', $school_id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $error = "Security token mismatch. Please try again.";
        } elseif ($_POST['action_type'] === 'create_tp_slot') {
            $term = trim($_POST['term'] ?? 'Term 1');
            $subjects = trim($_POST['subjects'] ?? '');
            $slotsCount = intval($_POST['slots_count'] ?? 1);
            $stipend = trim($_POST['stipend'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($subjects)) {
                $error = "Please specify the subject combinations required for this TP slot.";
            } else {
                $placement = R::dispense('tpplacement');
                $placement->school_id = $school_id;
                $placement->school_name = $school->name ?: 'School';
                $placement->county = $school->county ?: 'Kenya';
                $placement->level = $school->level ?: 'Secondary';
                $placement->term = $term;
                $placement->subjects = $subjects;
                $placement->slots_count = max(1, $slotsCount);
                $placement->stipend = $stipend;
                $placement->notes = $notes;
                $placement->status = 'open';
                $placement->created_at = date('Y-m-d H:i:s');
                R::store($placement);

                $msg = "Teaching Practice placement opening for '{$subjects}' published successfully!";
            }
        } elseif ($_POST['action_type'] === 'close_tp_slot') {
            $placementId = intval($_POST['placement_id'] ?? 0);
            $placement = R::load('tpplacement', $placementId);
            if ($placement->id && $placement->school_id == $school_id) {
                $placement->status = 'closed';
                R::store($placement);
                $msg = "Placement opening closed.";
            }
        } elseif ($_POST['action_type'] === 'update_tp_app_status') {
            $appId = intval($_POST['app_id'] ?? 0);
            $newStatus = trim($_POST['new_status'] ?? '');
            $tpApp = R::load('tpapplication', $appId);
            if ($tpApp->id && $tpApp->school_id == $school_id && in_array($newStatus, ['accepted', 'declined'])) {
                $tpApp->status = $newStatus;
                $tpApp->reviewed_at = date('Y-m-d H:i:s');
                R::store($tpApp);

                // If accepted, also notify teacher if email exists
                if ($newStatus === 'accepted' && !empty($tpApp->teacher_email)) {
                    $subject = "🎉 Teaching Practice Placement Offer from " . $school->name;
                    $body = "<p>Dear {$tpApp->teacher_name},</p><p>Congratulations! <strong>{$school->name}</strong> has approved and offered you a Teaching Practice (TP) placement for <strong>{$tpApp->desired_term}</strong> ({$tpApp->subjects}).</p><p>Please contact the school administration or report on the designated opening date with your institutional introduction letter.</p>";
                    send_system_email($tpApp->teacher_email, $tpApp->teacher_name, $subject, $body);
                }

                $msg = "Application status updated to " . ucfirst($newStatus) . ".";
            }
        }
    }

    // Load School's Placements & Incoming Applications
    $myPlacements = R::find('tpplacement', 'school_id = ? ORDER BY id DESC', [$school_id]);
    $incomingApps = R::find('tpapplication', 'school_id = ? ORDER BY id DESC', [$school_id]);

    $openSlotsCount = 0;
    foreach ($myPlacements as $p) {
        if ($p->status === 'open') $openSlotsCount += intval($p->slots_count);
    }
    $totalAppsCount = count($incomingApps);
    $placedCount = R::count('tpapplication', 'school_id = ? AND status = ?', [$school_id, 'accepted']);
}

// ----------------------------------------------------------------------------------
// 2. TEACHER & GUEST ACTIONS & DATA PROCESSING
// ----------------------------------------------------------------------------------
if ($isTeacher) {
    $teacher_id = $authUser['user_id'];
    $teacher = R::load('teacher', $teacher_id);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_type'])) {
        if (!verify_csrf($_POST['csrf_token'] ?? '')) {
            $error = "Security token mismatch. Please try again.";
        } elseif ($_POST['action_type'] === 'apply_tp') {
            $targetSchoolId = intval($_POST['school_id'] ?? 0);
            $placementId = intval($_POST['placement_id'] ?? 0);
            $institutionName = trim($_POST['institution_name'] ?? '');
            $regNumber = trim($_POST['reg_number'] ?? '');
            $yearOfStudy = trim($_POST['year_of_study'] ?? '3rd Year');
            $appSubjects = trim($_POST['subjects'] ?? ($teacher->teaching_subjects ?? ''));
            $desiredTerm = trim($_POST['desired_term'] ?? 'Term 2');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($institutionName) || empty($appSubjects)) {
                $error = "Please fill in your College / University name and subject combinations.";
            } else {
                $targetSchool = R::load('school', $targetSchoolId);
                if (!$targetSchool->id) {
                    // Try public school reference
                    $pubSchool = R::load('public_school', $targetSchoolId);
                    $schoolName = $pubSchool->id ? $pubSchool->name : 'Educational Institution';
                } else {
                    $schoolName = $targetSchool->name;
                }

                // Optional University Letter attachment
                $tpLetterPath = null;
                if (!empty($_FILES['tp_letter']['name']) && $_FILES['tp_letter']['error'] === UPLOAD_ERR_OK) {
                    $uploadDir = __DIR__ . '/../uploads/tp_letters/';
                    if (!is_dir($uploadDir)) {
                        @mkdir($uploadDir, 0755, true);
                    }
                    $ext = strtolower(pathinfo($_FILES['tp_letter']['name'], PATHINFO_EXTENSION));
                    if (in_array($ext, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'])) {
                        $newName = 'tp_letter_' . $teacher_id . '_' . time() . '.' . $ext;
                        if (move_uploaded_file($_FILES['tp_letter']['tmp_name'], $uploadDir . $newName)) {
                            $tpLetterPath = 'uploads/tp_letters/' . $newName;
                        }
                    }
                }

                $tpApp = R::dispense('tpapplication');
                $tpApp->school_id = $targetSchoolId;
                $tpApp->school_name = $schoolName;
                $tpApp->placement_id = $placementId;
                $tpApp->teacher_id = $teacher_id;
                $tpApp->teacher_name = $teacher->name ?: 'Student Teacher';
                $tpApp->teacher_email = $teacher->email;
                $tpApp->teacher_phone = $teacher->mobile ?? '';
                $tpApp->institution_name = $institutionName;
                $tpApp->reg_number = $regNumber;
                $tpApp->year_of_study = $yearOfStudy;
                $tpApp->subjects = $appSubjects;
                $tpApp->desired_term = $desiredTerm;
                $tpApp->notes = $notes;
                $tpApp->tp_letter_doc = $tpLetterPath;
                $tpApp->status = 'pending';
                $tpApp->created_at = date('Y-m-d H:i:s');
                R::store($tpApp);

                $msg = "Your Teaching Practice application has been submitted to {$schoolName}!";
            }
        }
    }

    $myApplications = R::find('tpapplication', 'teacher_id = ? ORDER BY id DESC', [$teacher_id]);
}

// ----------------------------------------------------------------------------------
// 3. COMMON CATALOG SEARCH (OPEN SLOTS & FEATURED SCHOOLS)
// ----------------------------------------------------------------------------------
$openPlacements = [];
if ($countyFilter !== '') {
    $countyUpper = strtoupper($countyFilter);
    $openPlacements = R::find('tpplacement', 'status = ? AND UPPER(county) LIKE ? ORDER BY id DESC', ['open', "%{$countyUpper}%"]);
    $featuredSchools = R::find('public_school', 'UPPER(county) = ? AND level LIKE ? LIMIT 15', [$countyUpper, '%SECONDARY%']);
} else {
    $openPlacements = R::find('tpplacement', 'status = ? ORDER BY id DESC LIMIT 20', ['open']);
    $featuredSchools = R::find('public_school', 'level LIKE ? ORDER BY id DESC LIMIT 15', ['%SECONDARY%']);
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="padding-top: 1.5rem !important;">
        
        <?php if ($msg): ?>
            <div style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-check-circle"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- SCHOOL VIEW: PLACEMENT MANAGEMENT CENTER -->
        <!-- ========================================================================= -->
        <?php if ($isSchool): ?>
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a; font-weight: 800;">🎓 School TP Placement Management Center</h2>
                    <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                        Offer Teaching Practice placement slots to student teachers, review candidate university credentials, and approve semester attachments for <?= h($school->name) ?>.
                    </p>
                </div>
                <div>
                    <button onclick="document.getElementById('postTpModal').style.display='flex'" style="background: #0f766e; color: white; border: none; padding: 9px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                        <i class="fa fa-plus-circle"></i> Open TP Placement Slot
                    </button>
                </div>
            </div>

            <!-- School TP KPIs -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div class="metric-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Open TP Slots Available</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f766e;"><?= $openSlotsCount ?></div>
                </div>
                <div class="metric-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Student Teacher Applications</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a;"><?= $totalAppsCount ?></div>
                </div>
                <div class="metric-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Placements Offered / Placed</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #16a34a;"><?= $placedCount ?></div>
                </div>
            </div>

            <!-- Incoming TP Applications -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                    <h4 style="margin: 0; font-size: 1rem; font-weight: 700; color: #0f172a;">Incoming TP Student Applications</h4>
                    <span style="font-size: 0.8rem; color: #64748b;"><?= $totalAppsCount ?> total applicants</span>
                </div>

                <?php if (!empty($incomingApps)): ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 10px 14px; font-weight: 600;">Student Name</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">University / College & Reg</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Subjects</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Target Term</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Status</th>
                                    <th style="padding: 10px 14px; font-weight: 600; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incomingApps as $app): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 14px; font-weight: 600; color: #0f172a;">
                                            <div><?= h($app->teacher_name) ?></div>
                                            <small style="color: #64748b; font-size: 0.78rem;"><i class="fa fa-envelope"></i> <?= h($app->teacher_email) ?></small>
                                            <?php if (!empty($app->tp_letter_doc)): ?>
                                                <div style="margin-top: 4px;">
                                                    <a href="/<?= h($app->tp_letter_doc) ?>" target="_blank" style="display: inline-flex; align-items: center; gap: 4px; font-size: 0.72rem; color: #0f766e; background: #f0fdfa; padding: 2px 6px; border-radius: 4px; text-decoration: none; border: 1px solid #ccfbf1; font-weight: 700;">
                                                        <i class="fa fa-file-pdf"></i> University Letter
                                                    </a>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; color: #334155;">
                                            <strong style="color: #0f766e;"><?= h($app->institution_name) ?></strong>
                                            <div style="font-size: 0.78rem; color: #64748b;">Reg: <?= h($app->reg_number ?: 'N/A') ?> &bull; <?= h($app->year_of_study) ?></div>
                                        </td>
                                        <td style="padding: 12px 14px; font-weight: 600; color: #0f172a;"><?= h($app->subjects) ?></td>
                                        <td style="padding: 12px 14px; color: #475569;"><?= h($app->desired_term) ?></td>
                                        <td style="padding: 12px 14px;">
                                            <?php if ($app->status === 'accepted'): ?>
                                                <span style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 12px;">✓ Placement Offered</span>
                                            <?php elseif ($app->status === 'declined'): ?>
                                                <span style="background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 12px;">Declined</span>
                                            <?php else: ?>
                                                <span style="background: #fef3c7; color: #92400e; font-size: 0.75rem; font-weight: 700; padding: 2px 8px; border-radius: 12px;">⏳ Under Review</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                                <?php if ($app->status !== 'accepted'): ?>
                                                    <form method="POST" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action_type" value="update_tp_app_status">
                                                        <input type="hidden" name="app_id" value="<?= $app->id ?>">
                                                        <input type="hidden" name="new_status" value="accepted">
                                                        <button type="submit" style="background: #0f766e; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.78rem; font-weight: 700; cursor: pointer;">
                                                            Offer Placement
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" onclick="showPlacementSlip('<?= addslashes($school->name) ?>', '<?= addslashes($school->county ?: 'Kenya') ?>', '<?= addslashes($app->teacher_name) ?>', '<?= addslashes($app->reg_number ?: 'N/A') ?>', '<?= addslashes($app->institution_name) ?>', '<?= addslashes($app->subjects) ?>', '<?= addslashes($app->desired_term) ?>', '<?= date('F d, Y', strtotime($app->reviewed_at ?: 'now')) ?>')" style="background: #f0fdfa; color: #0f766e; border: 1px solid #0f766e; padding: 4px 8px; border-radius: 4px; font-size: 0.78rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i class="fa fa-print"></i> Slip
                                                    </button>
                                                <?php endif; ?>
                                                <?php if (!empty($app->teacher_email)): ?>
                                                    <a href="mailto:<?= h($app->teacher_email) ?>?subject=<?= urlencode("Teaching Practice Application - {$school->name}") ?>" style="background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 4px; font-size: 0.78rem; text-decoration: none; border: 1px solid #cbd5e1;">
                                                        <i class="fa fa-envelope"></i> Email
                                                    </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 3rem 1rem; color: #64748b;">
                        <i class="fa fa-graduation-cap fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                        <h4 style="color: #0f172a; margin: 0 0 4px;">No Student Teacher Applications Yet</h4>
                        <p style="font-size: 0.85rem; margin: 0 0 1rem;">Open a TP placement slot to advertise your upcoming term openings to student educators across Kenyan universities.</p>
                        <button onclick="document.getElementById('postTpModal').style.display='flex'" style="background: #0f766e; color: white; border: none; padding: 8px 16px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">
                            <i class="fa fa-plus-circle"></i> Open TP Placement Slot
                        </button>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Active TP Openings Posted by School -->
            <?php if (!empty($myPlacements)): ?>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0;">
                        <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">Your Published TP Placement Openings</h4>
                    </div>
                    <div style="padding: 1.25rem;">
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach ($myPlacements as $pl): ?>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; display: flex; justify-content: space-between; flex-direction: column;">
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <span style="font-weight: 700; color: #0f766e; font-size: 0.9rem;"><?= h($pl->term) ?></span>
                                            <span style="background: <?= $pl->status === 'open' ? '#dcfce7' : '#f1f5f9' ?>; color: <?= $pl->status === 'open' ? '#15803d' : '#64748b' ?>; font-size: 0.72rem; font-weight: 800; padding: 2px 6px; border-radius: 4px;">
                                                <?= strtoupper($pl->status) ?>
                                            </span>
                                        </div>
                                        <h5 style="margin: 0 0 6px; font-size: 0.92rem; color: #0f172a;"><?= h($pl->subjects) ?></h5>
                                        <p style="margin: 0 0 8px; font-size: 0.8rem; color: #64748b;">
                                            <strong>Slots:</strong> <?= intval($pl->slots_count) ?> &bull; 
                                            <?= !empty($pl->stipend) ? h($pl->stipend) : 'Standard TP placement' ?>
                                        </p>
                                    </div>
                                    <?php if ($pl->status === 'open'): ?>
                                        <form method="POST" style="margin: 0; text-align: right;">
                                            <?= csrf_field() ?>
                                            <input type="hidden" name="action_type" value="close_tp_slot">
                                            <input type="hidden" name="placement_id" value="<?= $pl->id ?>">
                                            <button type="submit" style="background: none; border: none; color: #ef4444; font-size: 0.78rem; font-weight: 600; cursor: pointer; padding: 0;">
                                                <i class="fa fa-ban"></i> Close Slot
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- TEACHER & GUEST VIEW: STUDENT PLACEMENT PORTAL -->
        <!-- ========================================================================= -->
        <?php else: ?>
            <div style="margin-bottom: 1.5rem;">
                <h2 style="margin: 0 0 6px; font-size: 1.45rem; color: #0f172a; font-weight: 800;">🎓 Teaching Practice (TP) & Student Placement Hub</h2>
                <p style="margin: 0; font-size: 0.88rem; color: #64748b; line-height: 1.5;">
                    Connect with secondary and junior secondary schools accepting Teaching Practice (TP) placements across all 47 counties in Kenya.
                </p>
            </div>

            <?php if ($isTeacher && !empty($myApplications)): ?>
                <!-- My TP Applications Section -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.03); margin-bottom: 2rem;">
                    <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f0fdfa;">
                        <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f766e;">
                            <i class="fa fa-list-check"></i> My TP Placement Applications
                        </h4>
                        <span style="font-size: 0.78rem; color: #0f766e; font-weight: 600;"><?= count($myApplications) ?> applied</span>
                    </div>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 10px 14px; font-weight: 600;">School Name</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Subject Combinations</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Target Term</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Date Applied</th>
                                    <th style="padding: 10px 14px; font-weight: 600; text-align: right;">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($myApplications as $myApp): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;"><?= h($myApp->school_name) ?></td>
                                        <td style="padding: 12px 14px; color: #334155;"><?= h($myApp->subjects) ?></td>
                                        <td style="padding: 12px 14px; color: #475569;"><?= h($myApp->desired_term) ?></td>
                                        <td style="padding: 12px 14px; color: #64748b; font-size: 0.8rem;"><?= date('M d, Y', strtotime($myApp->created_at)) ?></td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <?php if ($myApp->status === 'accepted'): ?>
                                                <div style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                                    <span style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; border: 1px solid #86efac;">
                                                        🎉 Offered
                                                    </span>
                                                    <button type="button" onclick="showPlacementSlip('<?= addslashes($myApp->school_name) ?>', 'Kenya', '<?= addslashes($teacher->name ?: 'Student Teacher') ?>', '<?= addslashes($myApp->reg_number ?: 'N/A') ?>', '<?= addslashes($myApp->institution_name) ?>', '<?= addslashes($myApp->subjects) ?>', '<?= addslashes($myApp->desired_term) ?>', '<?= date('F d, Y', strtotime($myApp->reviewed_at ?: $myApp->created_at)) ?>')" style="background: #0f766e; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i class="fa fa-print"></i> Print Slip
                                                    </button>
                                                </div>
                                            <?php elseif ($myApp->status === 'declined'): ?>
                                                <span style="background: #fee2e2; color: #991b1b; font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 12px;">
                                                    Position Filled
                                                </span>
                                            <?php else: ?>
                                                <span style="background: #fef3c7; color: #92400e; font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 12px;">
                                                    ⏳ Under Review
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Filter Search Bar -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/tp-hub" style="margin: 0;">
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; align-items: flex-end;">
                        <div>
                            <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block;">County Placement</label>
                            <select name="county" style="width: 100%; height: 42px; box-sizing: border-box; margin: 0; background: #ffffff;">
                                <option value="">All 47 Counties</option>
                                <?php foreach (kenyan_counties() as $c): ?>
                                    <option value="<?= h($c) ?>" <?= ($countyFilter === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 6px; display: block;">Subject Combination</label>
                            <input type="text" name="subject" value="<?= h($subjectFilter) ?>" placeholder="e.g. Kiswahili / CRE, Math / Physics" style="width: 100%; height: 42px; box-sizing: border-box; margin: 0; background: #ffffff;">
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <button type="submit" class="btn-primary" style="height: 42px; flex: 1; margin: 0; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                                <i class="fa fa-search"></i> Search Placements
                            </button>
                            <?php if ($countyFilter || $subjectFilter): ?>
                                <a href="/tp-hub" style="height: 42px; padding: 0 14px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; text-decoration: none;">Reset</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Active Open Placement Slots Posted by Verified Schools -->
            <?php if (!empty($openPlacements)): ?>
                <h3 style="font-size: 1.15rem; font-weight: 800; margin-bottom: 1rem; color: #0f172a;">
                    Verified Open TP Placement Openings
                </h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2.5rem;">
                    <?php foreach ($openPlacements as $op): ?>
                        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                                    <h4 style="margin: 0; font-size: 1rem; font-weight: 800; color: #0f172a;"><?= h($op->school_name) ?></h4>
                                    <span style="background: #ccfbf1; color: #0f766e; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 10px; white-space: nowrap;">
                                        <?= h($op->term) ?>
                                    </span>
                                </div>
                                <p style="margin: 0 0 6px; font-size: 0.82rem; color: #64748b;">
                                    <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($op->county) ?> &bull; <?= h($op->level ?: 'Secondary') ?>
                                </p>
                                <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 10px; font-size: 0.84rem;">
                                    <strong style="color: #0f172a;">Subjects:</strong> <?= h($op->subjects) ?><br>
                                    <strong style="color: #64748b;">Slots Available:</strong> <?= intval($op->slots_count) ?> &bull; 
                                    <?= !empty($op->stipend) ? h($op->stipend) : 'Standard TP placement' ?>
                                </div>
                                <?php if (!empty($op->notes)): ?>
                                    <p style="margin: 0 0 10px; font-size: 0.78rem; color: #64748b; font-style: italic;">
                                        <?= h($op->notes) ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            <div style="border-top: 1px solid #f1f5f9; padding-top: 0.75rem; text-align: right;">
                                <?php if ($isTeacher): ?>
                                    <button onclick="openApplyModal(<?= $op->school_id ?>, <?= $op->id ?>, '<?= addslashes($op->school_name) ?>', '<?= addslashes($op->subjects) ?>', '<?= addslashes($op->term) ?>')" style="background: #0f766e; color: white; border: none; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer;">
                                        Apply for TP Slot &rarr;
                                    </button>
                                <?php else: ?>
                                    <a href="/login/teacher" style="background: #0f766e; color: white !important; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-block;">
                                        Log In to Apply
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- National Schools Directory for Direct Inquiries -->
            <div style="margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.1rem; font-weight: 800; margin: 0 0 4px; color: #0f172a;">
                    National Schools Directory (Direct Placement Inquiry) <?= $countyFilter ? 'in ' . h($countyFilter) : '' ?>
                </h3>
                <p style="font-size: 0.82rem; color: #64748b; margin: 0;">
                    These institutions have not posted active digital slots on MwalimuLink yet. You may contact the administration directly to inquire about upcoming Teaching Practice intake.
                </p>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <?php if (!empty($featuredSchools)): ?>
                    <?php foreach ($featuredSchools as $sc): ?>
                        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.5rem; gap: 8px;">
                                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a; line-height: 1.3;">
                                        <?= h($sc->name) ?>
                                    </h4>
                                    <span style="background: #f1f5f9; color: #475569; font-size: 0.68rem; font-weight: 700; padding: 3px 8px; border-radius: 4px; white-space: nowrap; border: 1px solid #e2e8f0;">
                                        General Inquiry
                                    </span>
                                </div>
                                <p style="margin: 0 0 8px; font-size: 0.8rem; color: #64748b;">
                                    <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($sc->county ?: 'Kenya') ?> &bull; <?= h($sc->constituency ?? $sc->level ?? 'Secondary') ?>
                                </p>
                            </div>

                            <div style="border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center; gap: 8px;">
                                <?php if (!empty($sc->email)): ?>
                                    <a href="mailto:<?= h($sc->email) ?>?subject=<?= urlencode('Teaching Practice Placement Inquiry - ' . $sc->name) ?>" style="background: #f0fdfa; color: #0f766e !important; font-size: 0.78rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #ccfbf1;">
                                        <i class="fa fa-envelope"></i> Inquire via Email
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.75rem; color: #94a3b8;"><i class="fa fa-building"></i> Directory School</span>
                                <?php endif; ?>
                                <a href="/schools/public/detail?id=<?= $sc->id ?>" style="font-size: 0.82rem; font-weight: 700; color: #0f766e; text-decoration: none;">
                                    View School &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

<!-- ========================================================================= -->
<!-- MODALS -->
<!-- ========================================================================= -->

<!-- Modal 1: School Opens TP Placement Slot -->
<?php if ($isSchool): ?>
<div id="postTpModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: white; border-radius: 10px; max-width: 500px; width: 100%; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a;"><i class="fa fa-plus-circle" style="color: #0f766e;"></i> Open TP Placement Slot</h4>
            <button type="button" onclick="document.getElementById('postTpModal').style.display='none'" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form method="POST" action="/tp-hub">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="create_tp_slot">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Target Term <span style="color: red;">*</span></label>
                    <select name="term" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                        <option value="Term 1">Term 1 (Jan - April)</option>
                        <option value="Term 2">Term 2 (May - August)</option>
                        <option value="Term 3">Term 3 (Sept - Nov)</option>
                        <option value="Term 1 & 2">Terms 1 & 2 (Extended)</option>
                    </select>
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Number of Slots <span style="color: red;">*</span></label>
                    <input type="number" name="slots_count" value="1" min="1" max="20" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box;">
                </div>
            </div>

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Subject Combinations Needed <span style="color: red;">*</span></label>
            <input type="text" name="subjects" placeholder="e.g. Mathematics / Physics, Kiswahili / CRE" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Stipend / Benefits (Optional)</label>
            <input type="text" name="stipend" placeholder="e.g. Lunch provided, KES 5,000 monthly allowance" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Additional Requirements / Notes</label>
            <textarea name="notes" rows="2" placeholder="e.g. University introduction letter required upon reporting." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.5rem; box-sizing: border-box;"></textarea>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('postTpModal').style.display='none'" style="background: #f1f5f9; color: #475569; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #0f766e; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">Publish Slot</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modal 2: Student Teacher Applies for TP Slot -->
<?php if ($isTeacher): ?>
<div id="applyTpModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: white; border-radius: 10px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
            <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a;"><i class="fa fa-graduation-cap" style="color: #0f766e;"></i> Apply for TP Placement</h4>
            <button type="button" onclick="document.getElementById('applyTpModal').style.display='none'" style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
        </div>

        <form method="POST" action="/tp-hub" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action_type" value="apply_tp">
            <input type="hidden" name="school_id" id="modalSchoolId">
            <input type="hidden" name="placement_id" id="modalPlacementId">

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 1rem; font-size: 0.85rem;">
                <div style="font-weight: 700; color: #0f172a;" id="modalSchoolName"></div>
                <div style="color: #64748b;" id="modalSlotDetails"></div>
            </div>

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">University / College Name <span style="color: red;">*</span></label>
            <input type="text" name="institution_name" placeholder="e.g. Kenyatta University, Moi University, Machakos TTC" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Student Reg Number</label>
                    <input type="text" name="reg_number" placeholder="e.g. E35/1234/2022" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box;">
                </div>
                <div>
                    <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Year of Study</label>
                    <select name="year_of_study" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                        <option value="3rd Year">3rd Year</option>
                        <option value="4th Year">4th Year</option>
                        <option value="2nd Year (Diploma/TTC)">2nd Year (Diploma/TTC)</option>
                        <option value="Post-Graduate PGDE">Post-Graduate PGDE</option>
                    </select>
                </div>
            </div>

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Subject Combinations <span style="color: red;">*</span></label>
            <input type="text" name="subjects" id="modalSubjectsInput" placeholder="e.g. Mathematics / Physics" required style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Target Placement Term</label>
            <input type="text" name="desired_term" id="modalTermInput" value="Term 2" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

            <!-- University TP Intro Letter Upload -->
            <div style="background: #f0fdfa; border: 1px dashed #0f766e; border-radius: 6px; padding: 10px 12px; margin-bottom: 1rem;">
                <label style="font-size: 0.85rem; font-weight: 700; color: #0f766e; margin-bottom: 4px; display: block;">
                    <i class="fa fa-file-pdf"></i> University Introduction / Recommendation Letter (Optional)
                </label>
                <p style="margin: 0 0 6px; font-size: 0.75rem; color: #64748b;">
                    Upload the official letter from your Faculty / Dean of Education (PDF, JPG, PNG, Max 5MB).
                </p>
                <input type="file" name="tp_letter" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png" style="font-size: 0.82rem; color: #475569;">
            </div>

            <label style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Introductory Note to School Principal</label>
            <textarea name="notes" rows="2" placeholder="Brief statement regarding your readiness, availability, and university requirements." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.5rem; box-sizing: border-box;"></textarea>

            <div style="display: flex; justify-content: flex-end; gap: 8px;">
                <button type="button" onclick="document.getElementById('applyTpModal').style.display='none'" style="background: #f1f5f9; color: #475569; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                <button type="submit" style="background: #0f766e; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">Submit Application</button>
            </div>
        </form>
    </div>
</div>

<script>
function openApplyModal(schoolId, placementId, schoolName, subjects, term) {
    document.getElementById('modalSchoolId').value = schoolId;
    document.getElementById('modalPlacementId').value = placementId;
    document.getElementById('modalSchoolName').innerText = schoolName;
    document.getElementById('modalSlotDetails').innerText = subjects + ' (' + term + ')';
    document.getElementById('modalSubjectsInput').value = subjects;
    document.getElementById('modalTermInput').value = term;
    document.getElementById('applyTpModal').style.display = 'flex';
}
</script>
<?php endif; ?>

<!-- Placement Confirmation Slip Modal (Printable) -->
<div id="placementSlipModal" style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 3000; align-items: center; justify-content: center; padding: 1rem;">
    <div style="background: white; border-radius: 12px; max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.25);">
        <div class="no-print" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 8px; color: #0f766e; font-weight: 800;">
                <i class="fa fa-certificate fa-lg"></i> Official TP Placement Confirmation
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="window.print()" class="btn-primary" style="padding: 6px 14px; font-size: 0.82rem;">
                    <i class="fa fa-print"></i> Print / Save PDF
                </button>
                <button onclick="document.getElementById('placementSlipModal').style.display='none'" style="background: #f1f5f9; color: #475569; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>

        <!-- Printable Slip Document Body -->
        <div id="printableSlipContent" style="border: 2px solid #0f766e; border-radius: 8px; padding: 2rem; background: #ffffff; color: #0f172a; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            <div style="text-align: center; border-bottom: 2px double #cbd5e1; padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #0f766e; letter-spacing: 0.05em; text-transform: uppercase;" id="slipSchoolName">SCHOOL NAME</div>
                <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">TEACHING PRACTICE & ATTACHMENT COORDINATION OFFICE</div>
                <div style="font-size: 0.8rem; color: #94a3b8;" id="slipSchoolCounty">Kenya</div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 1.25rem;">
                <div><strong>Ref:</strong> TP/OFFER/<span id="slipRefCode">2026-001</span></div>
                <div><strong>Date:</strong> <span id="slipDate">Date</span></div>
            </div>

            <div style="text-align: center; margin: 1.5rem 0;">
                <span style="font-size: 1.1rem; font-weight: 800; color: #0f172a; text-transform: uppercase; border-bottom: 2px solid #0f766e; padding-bottom: 4px;">
                    TEACHING PRACTICE PLACEMENT ACCEPTANCE SLIP
                </span>
            </div>

            <p style="font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem;">
                This is to officially confirm that <strong><span id="slipStudentName">STUDENT NAME</span></strong> (Reg. No: <strong><span id="slipRegNo">REG_NO</span></strong>) from <strong><span id="slipUniversity">UNIVERSITY</span></strong> has been offered a Teaching Practice placement opening at our institution for:
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.88rem;">
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 0; font-weight: 700; color: #475569; width: 40%;">Assigned Teaching Term:</td>
                    <td style="padding: 8px 0; font-weight: 800; color: #0f766e;" id="slipTerm">Term 1</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 0; font-weight: 700; color: #475569;">Approved Subject Combinations:</td>
                    <td style="padding: 8px 0; font-weight: 800; color: #0f172a;" id="slipSubjects">Mathematics / Physics</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 0; font-weight: 700; color: #475569;">Institution Status:</td>
                    <td style="padding: 8px 0; color: #166534; font-weight: 700;">✓ Verified Placement Host School</td>
                </tr>
            </table>

            <p style="font-size: 0.85rem; color: #475569; line-height: 1.5; margin-bottom: 2rem;">
                The student teacher is expected to report to the Dean of Studies / TP Coordinator on the official term opening date accompanied by original identification, TSC student registration, and institutional TP assessment logbooks.
            </p>

            <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 2.5rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1;">
                <div>
                    <div style="border-bottom: 1px solid #0f172a; width: 180px; margin-bottom: 4px;"></div>
                    <div style="font-size: 0.78rem; font-weight: 700; color: #0f172a;">Principal / TP Coordinator Signature</div>
                    <div style="font-size: 0.72rem; color: #64748b;" id="slipSignSchool">School Administration</div>
                </div>
                <div style="text-align: right;">
                    <div style="display: inline-block; border: 2px dashed #0f766e; color: #0f766e; padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">
                        ✓ MWALIMULINK VERIFIED<br>PLACEMENT CONFIRMATION
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showPlacementSlip(schoolName, county, studentName, regNo, university, subjects, term, dateApproved) {
    document.getElementById('slipSchoolName').innerText = schoolName.toUpperCase();
    document.getElementById('slipSchoolCounty').innerText = county + ', Kenya';
    document.getElementById('slipStudentName').innerText = studentName;
    document.getElementById('slipRegNo').innerText = regNo || 'N/A';
    document.getElementById('slipUniversity').innerText = university;
    document.getElementById('slipSubjects').innerText = subjects;
    document.getElementById('slipTerm').innerText = term;
    document.getElementById('slipDate').innerText = dateApproved;
    document.getElementById('slipSignSchool').innerText = schoolName;
    document.getElementById('slipRefCode').innerText = 'TP-' + Math.floor(100000 + Math.random() * 900000);
    document.getElementById('placementSlipModal').style.display = 'flex';
}
</script>

<style>
@media print {
    body * {
        visibility: hidden;
    }
    #placementSlipModal, #placementSlipModal * {
        visibility: visible;
    }
    #placementSlipModal {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: white !important;
        padding: 0 !important;
    }
    .no-print {
        display: none !important;
    }
    #printableSlipContent {
        border: 2px solid #000 !important;
        box-shadow: none !important;
    }
}
</style>