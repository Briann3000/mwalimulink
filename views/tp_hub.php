<?php
// views/tp_hub.php - Teaching Practice (TP) & Student Placement Hub (Role-Differentiated)
$authUser = auth_user();
$isSchool = ($authUser && ($authUser['role'] ?? '') === 'school');
$isTeacher = ($authUser && ($authUser['role'] ?? '') === 'teacher');

$msg = '';
$error = '';

// Search, Filter, Sorting & Pagination Parameters
$countyFilter = trim($_GET['county'] ?? '');
$subjectFilter = trim($_GET['subject'] ?? '');
$categoryTab = trim($_GET['tab'] ?? 'all'); // 'all' | 'tp_slots' | 'internships'
$sortBy = trim($_GET['sort'] ?? 'kenya_first'); // 'kenya_first' | 'newest' | 'closing_soon' | 'title_asc'
$perPage = max(6, min(100, intval($_GET['per_page'] ?? 12)));
$page = max(1, intval($_GET['page'] ?? 1));

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
        if ($p->status === 'open')
            $openSlotsCount += intval($p->slots_count);
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
                    $pubSchool = R::load('public_school', $targetSchoolId);
                    $schoolName = $pubSchool->id ? $pubSchool->name : 'Educational Institution';
                } else {
                    $schoolName = $targetSchool->name;
                }

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
// 3. COMMON CATALOG SEARCH & UNIFIED STREAM (TP SLOTS + INTERNSHIPS)
// ----------------------------------------------------------------------------------

// 3.1 Fetch School-posted TP Slots
$tpQuery = "status = 'open'";
$tpParams = [];
if (!empty($countyFilter)) {
    $tpQuery .= " AND UPPER(county) LIKE ?";
    $tpParams[] = "%" . strtoupper($countyFilter) . "%";
}
if (!empty($subjectFilter)) {
    $tpQuery .= " AND (subjects LIKE ? OR notes LIKE ?)";
    $tpParams[] = "%{$subjectFilter}%";
    $tpParams[] = "%{$subjectFilter}%";
}
$rawPlacements = R::find('tpplacement', $tpQuery, $tpParams);

// 3.2 Fetch Scraped & Direct Internships / Attachments / Trainees
$internshipWhere = "((opportunity_type = 'internship') OR (title LIKE '%intern%') OR (title LIKE '%attachment%') OR (title LIKE '%trainee%') OR (title LIKE '%resident teacher%') OR (title LIKE '%co-teacher%') OR (title LIKE '%graduate teacher%') OR (description LIKE '%teaching practice%') OR (description LIKE '%student teacher%')) AND (aggregation_status = 'published' OR aggregation_status IS NULL OR source_type = 'direct' OR source_type IS NULL)";
$internshipParams = [];

if (!empty($countyFilter)) {
    $internshipWhere .= " AND (location_text LIKE ? OR description LIKE ?)";
    $internshipParams[] = "%{$countyFilter}%";
    $internshipParams[] = "%{$countyFilter}%";
}
if (!empty($subjectFilter)) {
    $internshipWhere .= " AND (title LIKE ? OR description LIKE ? OR curriculum LIKE ?)";
    $internshipParams[] = "%{$subjectFilter}%";
    $internshipParams[] = "%{$subjectFilter}%";
    $internshipParams[] = "%{$subjectFilter}%";
}
$rawInternships = R::find('job', $internshipWhere, $internshipParams);

// 3.3 Consolidate into unified stream items
$unifiedStream = [];

foreach ($rawPlacements as $tp) {
    if ($categoryTab === 'internships')
        continue; // filter tab check
    $unifiedStream[] = [
        'type' => 'tp_slot',
        'id' => $tp->id,
        'title' => 'Teaching Practice: ' . $tp->subjects,
        'school_id' => $tp->school_id,
        'school_name' => $tp->school_name,
        'location' => $tp->county ?: 'Kenya',
        'is_kenyan' => true,
        'badge' => 'Verified TP Slot',
        'badge_bg' => '#ccfbf1',
        'badge_color' => '#0f766e',
        'subjects' => $tp->subjects,
        'term' => $tp->term,
        'level' => $tp->level ?: 'Secondary',
        'slots_count' => intval($tp->slots_count),
        'stipend' => $tp->stipend,
        'notes' => $tp->notes,
        'posted_date' => $tp->created_at ?? date('Y-m-d H:i:s'),
        'deadline' => null,
        'raw_bean' => $tp
    ];
}

foreach ($rawInternships as $ij) {
    if ($categoryTab === 'tp_slots')
        continue; // filter tab check
    $isExt = ($ij->source_type === 'external');
    $instName = $isExt ? ($ij->company_name ?: ($ij->source_name ?: 'Education Partner')) : 'MwalimuLink Registered School';
    $instLoc = $isExt ? ($ij->location_text ?: 'Kenya') : 'Kenya';

    $isKe = true;
    $locLower = strtolower($instLoc);
    $srcLower = strtolower($ij->source_name ?? '');
    if (
        strpos($locLower, 'kenya') === false && strpos($locLower, 'nairobi') === false && strpos($locLower, 'mombasa') === false &&
        strpos($locLower, 'kisumu') === false && strpos($locLower, 'nakuru') === false && strpos($locLower, 'eldoret') === false &&
        strpos($srcLower, 'myjobmag') === false && strpos($srcLower, 'brighter') === false && $ij->source_type === 'external'
    ) {
        $isKe = false;
    }

    $unifiedStream[] = [
        'type' => 'internship',
        'id' => $ij->id,
        'title' => $ij->title,
        'school_id' => $ij->school_id,
        'school_name' => $instName,
        'location' => $instLoc,
        'is_kenyan' => $isKe,
        'badge' => 'Intern / Attachment',
        'badge_bg' => '#fef3c7',
        'badge_color' => '#92400e',
        'curriculum' => $ij->curriculum,
        'description' => $ij->description,
        'source_name' => $ij->source_name,
        'source_type' => $ij->source_type,
        'posted_date' => $ij->posted_date ?? date('Y-m-d H:i:s'),
        'deadline' => $ij->deadline,
        'raw_bean' => $ij
    ];
}

// 3.4 Sorting Engine (Kenyan First by Default)
usort($unifiedStream, function ($a, $b) use ($sortBy) {
    if ($sortBy === 'kenya_first') {
        $aKe = $a['is_kenyan'] ? 1 : 0;
        $bKe = $b['is_kenyan'] ? 1 : 0;
        if ($aKe !== $bKe) {
            return $bKe - $aKe; // Kenyan first
        }
        $aDate = strtotime($a['posted_date'] ?? 'now');
        $bDate = strtotime($b['posted_date'] ?? 'now');
        return $bDate - $aDate;
    } elseif ($sortBy === 'title_asc') {
        return strcasecmp($a['title'] ?? '', $b['title'] ?? '');
    } elseif ($sortBy === 'closing_soon') {
        $aDeadline = !empty($a['deadline']) ? strtotime($a['deadline']) : 9999999999;
        $bDeadline = !empty($b['deadline']) ? strtotime($b['deadline']) : 9999999999;
        return $aDeadline - $bDeadline;
    } else { // 'newest'
        $aDate = strtotime($a['posted_date'] ?? 'now');
        $bDate = strtotime($b['posted_date'] ?? 'now');
        return $bDate - $aDate;
    }
});

// 3.5 Pagination Computations
$totalItems = count($unifiedStream);
$totalPages = max(1, ceil($totalItems / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pagedStream = array_slice($unifiedStream, $offset, $perPage);

// Helper for query building in pagination
$buildTpQueryUrl = function ($newPage = null, $newPerPage = null, $newSort = null, $newTab = null) use ($countyFilter, $subjectFilter, $categoryTab, $sortBy, $perPage, $page) {
    $params = [
        'county' => $countyFilter,
        'subject' => $subjectFilter,
        'tab' => $newTab ?? $categoryTab,
        'sort' => $newSort ?? $sortBy,
        'per_page' => $newPerPage ?? $perPage,
        'page' => $newPage ?? $page
    ];
    return '/tp-hub?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null && $v !== 'all'));
};
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="padding: 1.5rem !important; background: #f8fafc; min-height: 100vh;">

        <?php if ($msg): ?>
            <div
                style="background: #dcfce7; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-check-circle"></i> <?= h($msg) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div
                style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- ========================================================================= -->
        <!-- SCHOOL VIEW: PLACEMENT MANAGEMENT CENTER -->
        <!-- ========================================================================= -->
        <?php if ($isSchool): ?>
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; background: white; padding: 1.25rem 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0;">
                <div>
                    <h2 style="margin: 0 0 4px; font-size: 1.35rem; color: #0f172a; font-weight: 800;">🎓 School TP
                        Placement Center</h2>
                    <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                        Offer Teaching Practice placement slots to student teachers, review candidate credentials, and
                        approve semester attachments for <?= h($school->name) ?>.
                    </p>
                </div>
                <div>
                    <button onclick="document.getElementById('postTpModal').style.display='flex'"
                        style="background: #0f766e; color: white; border: none; padding: 9px 18px; border-radius: 8px; font-weight: 700; font-size: 0.85rem; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                        <i class="fa fa-plus-circle"></i> Open TP Placement Slot
                    </button>
                </div>
            </div>

            <!-- School TP KPIs -->
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                <div class="metric-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Open TP Slots
                        Available</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f766e;"><?= $openSlotsCount ?></div>
                </div>
                <div class="metric-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Student Teacher
                        Applications</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #0f172a;"><?= $totalAppsCount ?></div>
                </div>
                <div class="metric-card">
                    <div style="font-size: 0.8rem; font-weight: 600; color: #64748b; margin-bottom: 0.4rem;">Placements
                        Offered / Placed</div>
                    <div style="font-size: 1.8rem; font-weight: 800; color: #16a34a;"><?= $placedCount ?></div>
                </div>
            </div>

            <!-- Incoming TP Applications Table -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 2rem;">
                <div
                    style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f8fafc;">
                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
                        <i class="fa fa-inbox" style="color: #0f766e;"></i> Incoming Student Teacher Placement Requests
                    </h4>
                    <span style="font-size: 0.78rem; color: #64748b; font-weight: 600;"><?= $totalAppsCount ?> total
                        received</span>
                </div>

                <?php if (empty($incomingApps)): ?>
                    <div style="padding: 3rem 1.5rem; text-align: center;">
                        <i class="fa fa-graduation-cap fa-3x" style="color: #cbd5e1; margin-bottom: 0.75rem;"></i>
                        <p style="margin: 0; color: #64748b; font-size: 0.88rem;">No student teacher placement applications
                            received yet.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.85rem; text-align: left;">
                            <thead>
                                <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b;">
                                    <th style="padding: 10px 14px; font-weight: 600;">Student Teacher</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">University / College</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Subjects</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Target Term</th>
                                    <th style="padding: 10px 14px; font-weight: 600;">Letter</th>
                                    <th style="padding: 10px 14px; font-weight: 600; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($incomingApps as $app): ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9;">
                                        <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                            <?= h($app->teacher_name) ?><br>
                                            <span
                                                style="font-size: 0.75rem; color: #64748b; font-weight: 400;"><?= h($app->teacher_email) ?></span>
                                        </td>
                                        <td style="padding: 12px 14px; color: #334155;">
                                            <?= h($app->institution_name) ?><br>
                                            <span style="font-size: 0.75rem; color: #64748b;"><?= h($app->year_of_study) ?></span>
                                        </td>
                                        <td style="padding: 12px 14px; color: #334155; font-weight: 600;"><?= h($app->subjects) ?>
                                        </td>
                                        <td style="padding: 12px 14px; color: #475569;"><?= h($app->desired_term) ?></td>
                                        <td style="padding: 12px 14px;">
                                            <?php if (!empty($app->tp_letter_doc)): ?>
                                                <a href="/<?= h($app->tp_letter_doc) ?>" target="_blank"
                                                    style="background: #f0fdfa; color: #0f766e; border: 1px solid #ccfbf1; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; text-decoration: none; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-file-pdf"></i> View Letter
                                                </a>
                                            <?php else: ?>
                                                <span style="color: #94a3b8; font-size: 0.75rem;">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <?php if ($app->status === 'accepted'): ?>
                                                <span
                                                    style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 12px;">Offer
                                                    Sent ✓</span>
                                            <?php elseif ($app->status === 'declined'): ?>
                                                <span
                                                    style="background: #fee2e2; color: #991b1b; font-size: 0.75rem; font-weight: 600; padding: 3px 8px; border-radius: 12px;">Declined</span>
                                            <?php else: ?>
                                                <div style="display: flex; gap: 6px; justify-content: flex-end;">
                                                    <form method="POST" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action_type" value="update_tp_app_status">
                                                        <input type="hidden" name="app_id" value="<?= $app->id ?>">
                                                        <input type="hidden" name="new_status" value="accepted">
                                                        <button type="submit"
                                                            style="background: #0f766e; color: white; border: none; padding: 5px 12px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer;">
                                                            Accept & Offer
                                                        </button>
                                                    </form>
                                                    <form method="POST" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="action_type" value="update_tp_app_status">
                                                        <input type="hidden" name="app_id" value="<?= $app->id ?>">
                                                        <input type="hidden" name="new_status" value="declined">
                                                        <button type="submit"
                                                            style="background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; padding: 5px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 600; cursor: pointer;">
                                                            Decline
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- School Active TP Slots Posted -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 2rem;">
                <div style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; background: #f8fafc;">
                    <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: #0f172a;">
                        <i class="fa fa-list" style="color: #0f766e;"></i> Your Published TP Placement Slots
                    </h4>
                </div>
                <div style="padding: 1.25rem;">
                    <?php if (empty($myPlacements)): ?>
                        <p style="color: #64748b; font-size: 0.85rem; margin: 0;">No placement slots published yet.</p>
                    <?php else: ?>
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem;">
                            <?php foreach ($myPlacements as $pl): ?>
                                <div
                                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; display: flex; justify-content: space-between; flex-direction: column;">
                                    <div>
                                        <div
                                            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                            <span
                                                style="font-weight: 700; color: #0f766e; font-size: 0.9rem;"><?= h($pl->term) ?></span>
                                            <span
                                                style="background: <?= $pl->status === 'open' ? '#dcfce7' : '#f1f5f9' ?>; color: <?= $pl->status === 'open' ? '#15803d' : '#64748b' ?>; font-size: 0.72rem; font-weight: 800; padding: 2px 6px; border-radius: 4px;">
                                                <?= strtoupper($pl->status) ?>
                                            </span>
                                        </div>
                                        <h5 style="margin: 0 0 6px; font-size: 0.92rem; color: #0f172a;"><?= h($pl->subjects) ?>
                                        </h5>
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
                                            <button type="submit"
                                                style="background: none; border: none; color: #ef4444; font-size: 0.78rem; font-weight: 600; cursor: pointer; padding: 0;">
                                                <i class="fa fa-ban"></i> Close Slot
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ========================================================================= -->
            <!-- TEACHER & GUEST VIEW: STUDENT PLACEMENT & INTERNSHIP HUB -->
            <!-- ========================================================================= -->
        <?php else: ?>

            <!-- Hero Header Strip -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <div
                    style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div>
                        <h2
                            style="margin: 0 0 4px; font-size: 1.35rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-graduation-cap" style="color: #0f766e;"></i> Teaching Practice (TP) & Student
                            Placement Hub
                        </h2>
                        <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                            Discover semester Teaching Practice (TP) openings, student teacher attachments, resident covers,
                            and graduate trainee positions across Kenya.
                        </p>
                    </div>
                    <?php if ($isTeacher): ?>
                        <a href="/teacher/dashboard"
                            style="background: #f1f5f9; color: #334155; font-size: 0.84rem; font-weight: 600; padding: 8px 14px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0;">
                            <i class="fa fa-arrow-left"></i> Dashboard
                        </a>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isTeacher && !empty($myApplications)): ?>
                <!-- Teacher's Applied TP Applications Drawer -->
                <div
                    style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 1.5rem;">
                    <div
                        style="padding: 1rem 1.25rem; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; background: #f0fdfa;">
                        <h4 style="margin: 0; font-size: 0.92rem; font-weight: 700; color: #0f766e;">
                            <i class="fa fa-list-check"></i> My Submitted TP Applications (<?= count($myApplications) ?>)
                        </h4>
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
                                        <td style="padding: 12px 14px; font-weight: 700; color: #0f172a;">
                                            <?= h($myApp->school_name) ?>
                                        </td>
                                        <td style="padding: 12px 14px; color: #334155;"><?= h($myApp->subjects) ?></td>
                                        <td style="padding: 12px 14px; color: #475569;"><?= h($myApp->desired_term) ?></td>
                                        <td style="padding: 12px 14px; color: #64748b; font-size: 0.8rem;">
                                            <?= date('M d, Y', strtotime($myApp->created_at)) ?>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <?php if ($myApp->status === 'accepted'): ?>
                                                <div
                                                    style="display: flex; gap: 6px; justify-content: flex-end; align-items: center; flex-wrap: wrap;">
                                                    <span
                                                        style="background: #dcfce7; color: #15803d; font-size: 0.75rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; border: 1px solid #86efac;">
                                                        🎉 Offered
                                                    </span>
                                                    <button type="button"
                                                        onclick="showPlacementSlip('<?= addslashes($myApp->school_name) ?>', 'Kenya', '<?= addslashes($teacher->name ?: 'Student Teacher') ?>', '<?= addslashes($myApp->reg_number ?: 'N/A') ?>', '<?= addslashes($myApp->institution_name) ?>', '<?= addslashes($myApp->subjects) ?>', '<?= addslashes($myApp->desired_term) ?>', '<?= date('F d, Y', strtotime($myApp->reviewed_at ?: $myApp->created_at)) ?>')"
                                                        style="background: #0f766e; color: white; border: none; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                        <i class="fa fa-print"></i> Print Slip
                                                    </button>
                                                </div>
                                            <?php elseif ($myApp->status === 'declined'): ?>
                                                <span
                                                    style="background: #fee2e2; color: #991b1b; font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 12px;">
                                                    Position Filled
                                                </span>
                                            <?php else: ?>
                                                <span
                                                    style="background: #fef3c7; color: #92400e; font-size: 0.78rem; font-weight: 600; padding: 4px 10px; border-radius: 12px;">
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

            <!-- Category Tabs & Stream Filter Controls -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                <form method="GET" action="/tp-hub" style="margin: 0;">

                    <!-- Top Category Tabs Strip -->
                    <div
                        style="display: flex; gap: 8px; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; flex-wrap: wrap;">
                        <a href="<?= $buildTpQueryUrl(1, null, null, 'all') ?>"
                            style="padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: <?= ($categoryTab === 'all') ? '#0f766e' : '#f1f5f9' ?>; color: <?= ($categoryTab === 'all') ? '#ffffff' : '#475569' ?>;">
                            <i class="fa fa-layer-group"></i> All Opportunities
                            (<?= count($rawPlacements) + count($rawInternships) ?>)
                        </a>
                        <a href="<?= $buildTpQueryUrl(1, null, null, 'tp_slots') ?>"
                            style="padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: <?= ($categoryTab === 'tp_slots') ? '#0f766e' : '#f1f5f9' ?>; color: <?= ($categoryTab === 'tp_slots') ? '#ffffff' : '#475569' ?>;">
                            <i class="fa fa-school"></i> Verified TP Slots (<?= count($rawPlacements) ?>)
                        </a>
                        <a href="<?= $buildTpQueryUrl(1, null, null, 'internships') ?>"
                            style="padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; background: <?= ($categoryTab === 'internships') ? '#0f766e' : '#f1f5f9' ?>; color: <?= ($categoryTab === 'internships') ? '#ffffff' : '#475569' ?>;">
                            <i class="fa fa-graduation-cap"></i> Internships & Trainees (<?= count($rawInternships) ?>)
                        </a>
                    </div>

                    <input type="hidden" name="tab" value="<?= h($categoryTab) ?>">

                    <div
                        style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-bottom: 12px;">
                        <div>
                            <label
                                style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">County
                                Placement</label>
                            <select name="county"
                                style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                                <option value="">All 47 Counties</option>
                                <?php foreach (kenyan_counties() as $c): ?>
                                    <option value="<?= h($c) ?>" <?= ($countyFilter === $c) ? 'selected' : '' ?>><?= h($c) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label
                                style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Subject
                                Combination or Keyword</label>
                            <input type="text" name="subject" value="<?= h($subjectFilter) ?>"
                                placeholder="e.g. ECD, Mathematics / Physics, Kiswahili"
                                style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0; box-sizing: border-box;">
                        </div>
                    </div>

                    <!-- Filter Bottom Bar: Sorting & Per-Page -->
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: #475569;"><i
                                        class="fa fa-arrow-down-wide-short" style="color: #0f766e;"></i> Sort By:</label>
                                <select name="sort" onchange="this.form.submit()"
                                    style="height: 36px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #0f172a; background: #ffffff;">
                                    <option value="kenya_first" <?= ($sortBy === 'kenya_first') ? 'selected' : '' ?>>🇰🇪
                                        Kenyan First (Default)</option>
                                    <option value="newest" <?= ($sortBy === 'newest') ? 'selected' : '' ?>>🕒 Newest Posted
                                    </option>
                                    <option value="closing_soon" <?= ($sortBy === 'closing_soon') ? 'selected' : '' ?>>⏳
                                        Closing Soon</option>
                                    <option value="title_asc" <?= ($sortBy === 'title_asc') ? 'selected' : '' ?>>🔤 Title (A–Z)
                                    </option>
                                </select>
                            </div>
                            <div style="display: flex; align-items: center; gap: 6px;">
                                <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Show:</label>
                                <select name="per_page" onchange="this.form.submit()"
                                    style="height: 36px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #0f172a; background: #ffffff;">
                                    <option value="6" <?= ($perPage === 6) ? 'selected' : '' ?>>6 / page</option>
                                    <option value="12" <?= ($perPage === 12) ? 'selected' : '' ?>>12 / page</option>
                                    <option value="24" <?= ($perPage === 24) ? 'selected' : '' ?>>24 / page</option>
                                    <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50 / page</option>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; gap: 8px; align-items: center;">
                            <button type="submit" class="btn-primary"
                                style="height: 36px; padding: 0 18px; font-size: 0.84rem; font-weight: 700; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-filter"></i> Search Placements
                            </button>
                            <?php if (!empty($countyFilter) || !empty($subjectFilter) || $categoryTab !== 'all' || $sortBy !== 'kenya_first' || $perPage !== 12): ?>
                                <a href="/tp-hub"
                                    style="height: 36px; padding: 0 12px; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                                    Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Total Results Summary -->
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; color: #64748b; font-size: 0.85rem;">
                <div>
                    Showing
                    <strong><?= $totalItems > 0 ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalItems) ?></strong>
                    of <strong><?= $totalItems ?></strong> TP & internship openings
                </div>
                <div>
                    Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
                </div>
            </div>

            <!-- Unified Stream Grid -->
            <?php if (empty($pagedStream)): ?>
                <div
                    style="background: white; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 3.5rem 1.5rem; text-align: center; margin-bottom: 2rem;">
                    <div
                        style="width: 56px; height: 56px; background: #f0fdfa; color: #0f766e; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                        <i class="fa fa-graduation-cap"></i>
                    </div>
                    <h3 style="margin: 0 0 6px; font-size: 1.15rem; font-weight: 800; color: #0f172a;">No Active TP Placements
                        or Internships Matching Criteria</h3>
                    <p style="margin: 0 auto 1.25rem; font-size: 0.88rem; color: #64748b; max-width: 500px;">
                        There are currently no active teaching practice slots or graduate trainee openings
                        <?= $countyFilter ? 'in <strong>' . h($countyFilter) . '</strong>' : 'matching your search' ?>. Try
                        clearing filters to view all openings across Kenya.
                    </p>
                    <a href="/tp-hub"
                        style="display: inline-block; background: #0f766e; color: white; padding: 8px 18px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
                        Clear All Filters
                    </a>
                </div>
            <?php else: ?>
                <div
                    style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
                    <?php foreach ($pagedStream as $item): ?>
                        <?php
                        $isTp = ($item['type'] === 'tp_slot');
                        $schoolName = $item['school_name'];

                        // School Link Lookup
                        $schoolProfileUrl = null;
                        if (!empty($item['school_id'])) {
                            $schoolProfileUrl = '/schools/public/detail?id=' . $item['school_id'];
                        } elseif (!empty($schoolName)) {
                            $foundSchool = R::findOne('public_school', 'name LIKE ? LIMIT 1', ['%' . trim($schoolName) . '%']);
                            if ($foundSchool) {
                                $schoolProfileUrl = '/schools/public/detail?id=' . $foundSchool->id;
                            }
                        }

                        $initials = strtoupper(substr(trim($schoolName), 0, 2));
                        ?>
                        <div
                            style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.35rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); display: flex; flex-direction: column; justify-content: space-between;">
                            <div>
                                <!-- Header Strip: Badge & Avatar -->
                                <div
                                    style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; gap: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <div
                                            style="width: 42px; height: 42px; border-radius: 8px; background: <?= $item['is_kenyan'] ? '#f0fdfa' : '#f8fafc' ?>; color: <?= $item['is_kenyan'] ? '#0f766e' : '#475569' ?>; border: 1px solid <?= $item['is_kenyan'] ? '#ccfbf1' : '#e2e8f0' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.9rem; flex-shrink: 0;">
                                            <?= h($initials) ?>
                                        </div>
                                        <div>
                                            <span
                                                style="background: <?= $item['badge_bg'] ?>; color: <?= $item['badge_color'] ?>; font-size: 0.7rem; font-weight: 800; padding: 2px 8px; border-radius: 6px; display: inline-block;">
                                                <?= $item['badge'] ?>
                                            </span>
                                            <?php if ($item['is_kenyan']): ?>
                                                <span
                                                    style="background: #ecfdf5; color: #047857; font-size: 0.68rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; border: 1px solid #a7f3d0; margin-left: 4px;">
                                                    🇰🇪 Kenya
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($isTp): ?>
                                        <span
                                            style="background: #f1f5f9; color: #0f172a; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; white-space: nowrap; border: 1px solid #e2e8f0;">
                                            <?= h($item['term']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <!-- Title & School Links -->
                                <h4
                                    style="margin: 0 0 4px; font-size: 1.05rem; font-weight: 800; color: #0f172a; line-height: 1.35;">
                                    <?php if ($isTp): ?>
                                        <?= h($item['title']) ?>
                                    <?php else: ?>
                                        <a href="/teacher/apply?job_id=<?= $item['id'] ?>"
                                            style="color: #0f172a; text-decoration: none;">
                                            <?= h($item['title']) ?>
                                        </a>
                                    <?php endif; ?>
                                </h4>

                                <!-- School Dossier Link -->
                                <p style="margin: 0 0 10px; font-size: 0.83rem; color: #64748b;">
                                    <?php if ($schoolProfileUrl): ?>
                                        <a href="<?= $schoolProfileUrl ?>" title="View school profile in directory"
                                            style="color: #0f766e; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-school"></i> <?= h($schoolName) ?> <i
                                                class="fa fa-arrow-up-right-from-square" style="font-size: 0.65rem;"></i>
                                        </a>
                                    <?php else: ?>
                                        <span
                                            style="font-weight: 700; color: #334155; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-school" style="color: #0f766e;"></i> <?= h($schoolName) ?>
                                        </span>
                                    <?php endif; ?>
                                    &bull;
                                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                                        <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($item['location']) ?>
                                    </span>
                                </p>

                                <!-- Details Box -->
                                <?php if ($isTp): ?>
                                    <div
                                        style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 10px; margin-bottom: 12px; font-size: 0.83rem;">
                                        <div style="margin-bottom: 2px;"><strong style="color: #0f172a;">Level:</strong>
                                            <?= h($item['level']) ?> &bull; <strong>Slots:</strong> <?= $item['slots_count'] ?></div>
                                        <div style="color: #047857; font-weight: 600;"><i class="fa fa-hand-holding-dollar"></i>
                                            <?= !empty($item['stipend']) ? h($item['stipend']) : 'Standard TP placement' ?></div>
                                    </div>
                                    <?php if (!empty($item['notes'])): ?>
                                        <p style="margin: 0 0 12px; font-size: 0.8rem; color: #64748b; font-style: italic;">
                                            <?= h($item['notes']) ?>
                                        </p>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <p style="margin: 0 0 12px; font-size: 0.83rem; color: #475569; line-height: 1.5;">
                                        <?= h(mb_substr($item['description'] ?? '', 0, 140)) ?>                <?= strlen($item['description'] ?? '') > 140 ? '...' : '' ?>
                                    </p>
                                    <?php if (!empty($item['curriculum'])): ?>
                                        <div style="margin-bottom: 12px;">
                                            <span
                                                style="background: #e0f2fe; color: #0369a1; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                                                <?= h($item['curriculum']) ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>

                            <!-- Footer Actions -->
                            <div
                                style="border-top: 1px solid #f1f5f9; padding-top: 0.75rem; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-size: 0.75rem; color: #94a3b8;">
                                    <i class="fa fa-clock"></i> <?= date('M d, Y', strtotime($item['posted_date'] ?? 'now')) ?>
                                </span>

                                <div>
                                    <?php if ($isTp): ?>
                                        <?php if ($isTeacher): ?>
                                            <button
                                                onclick="openApplyModal(<?= $item['school_id'] ?>, <?= $item['id'] ?>, '<?= addslashes($item['school_name']) ?>', '<?= addslashes($item['subjects']) ?>', '<?= addslashes($item['term']) ?>')"
                                                style="background: #0f766e; color: white; border: none; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; cursor: pointer; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                                                Apply for TP Slot &rarr;
                                            </button>
                                        <?php else: ?>
                                            <a href="/login/teacher"
                                                style="background: #0f766e; color: white !important; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-block;">
                                                Log In to Apply
                                            </a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?php if ($isTeacher): ?>
                                            <a href="/teacher/apply?job_id=<?= $item['id'] ?>"
                                                style="background: #0f766e; color: white !important; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                                                Apply via MwalimuLink &rarr;
                                            </a>
                                        <?php else: ?>
                                            <a href="/login/teacher"
                                                style="background: #0f766e; color: white !important; padding: 7px 16px; border-radius: 6px; font-size: 0.82rem; font-weight: 700; text-decoration: none; display: inline-block;">
                                                Log In to Apply
                                            </a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Responsive Pagination Bar for TP Hub -->
                <?php if ($totalPages > 1): ?>
                    <div
                        style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 2rem;">
                        <div style="font-size: 0.85rem; color: #64748b;">
                            Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalItems ?>
                            total)
                        </div>

                        <div style="display: flex; gap: 4px; align-items: center;">
                            <?php if ($page > 1): ?>
                                <a href="<?= $buildTpQueryUrl($page - 1) ?>"
                                    style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    &laquo; Prev
                                </a>
                            <?php endif; ?>

                            <?php
                            $startPage = max(1, $page - 2);
                            $endPage = min($totalPages, $page + 2);
                            if ($startPage > 1) {
                                echo '<a href="' . $buildTpQueryUrl(1) . '" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; text-decoration: none;">1</a>';
                                if ($startPage > 2)
                                    echo '<span style="padding: 0 4px; color: #94a3b8;">...</span>';
                            }
                            for ($p = $startPage; $p <= $endPage; $p++):
                                $isActivePage = ($p === $page);
                                ?>
                                <a href="<?= $buildTpQueryUrl($p) ?>"
                                    style="padding: 6px 12px; background: <?= $isActivePage ? '#0f766e' : '#f8fafc' ?>; border: 1px solid <?= $isActivePage ? '#0f766e' : '#cbd5e1' ?>; border-radius: 6px; color: <?= $isActivePage ? '#ffffff' : '#334155' ?>; font-size: 0.84rem; font-weight: <?= $isActivePage ? '700' : '600' ?>; text-decoration: none;">
                                    <?= $p ?>
                                </a>
                            <?php endfor; ?>
                            <?php
                            if ($endPage < $totalPages) {
                                if ($endPage < $totalPages - 1)
                                    echo '<span style="padding: 0 4px; color: #94a3b8;">...</span>';
                                echo '<a href="' . $buildTpQueryUrl($totalPages) . '" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; text-decoration: none;">' . $totalPages . '</a>';
                            }
                            ?>

                            <?php if ($page < $totalPages): ?>
                                <a href="<?= $buildTpQueryUrl($page + 1) ?>"
                                    style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                    Next &raquo;
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        <?php endif; ?>

    </main>
</div>

<!-- ========================================================================= -->
<!-- MODALS -->
<!-- ========================================================================= -->

<!-- Modal 1: School Opens TP Placement Slot -->
<?php if ($isSchool): ?>
    <div id="postTpModal"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
        <div
            style="background: white; border-radius: 12px; max-width: 500px; width: 100%; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a;"><i class="fa fa-plus-circle"
                        style="color: #0f766e;"></i> Open TP Placement Slot</h4>
                <button type="button" onclick="document.getElementById('postTpModal').style.display='none'"
                    style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>

            <form method="POST" action="/tp-hub">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="create_tp_slot">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                    <div>
                        <label
                            style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Target
                            Term <span style="color: red;">*</span></label>
                        <select name="term" required
                            style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                            <option value="Term 1">Term 1 (Jan - April)</option>
                            <option value="Term 2">Term 2 (May - August)</option>
                            <option value="Term 3">Term 3 (Sept - Nov)</option>
                            <option value="Term 1 & 2">Terms 1 & 2 (Extended)</option>
                        </select>
                    </div>
                    <div>
                        <label
                            style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Number
                            of Slots <span style="color: red;">*</span></label>
                        <input type="number" name="slots_count" value="1" min="1" max="20" required
                            style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                </div>

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Subject
                    Combinations Needed <span style="color: red;">*</span></label>
                <input type="text" name="subjects" placeholder="e.g. Mathematics / Physics, Kiswahili / CRE" required
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Stipend
                    / Benefits (Optional)</label>
                <input type="text" name="stipend" placeholder="e.g. Lunch provided, KES 5,000 monthly allowance"
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Additional
                    Requirements / Notes</label>
                <textarea name="notes" rows="2" placeholder="e.g. University introduction letter required upon reporting."
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.5rem; box-sizing: border-box;"></textarea>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="document.getElementById('postTpModal').style.display='none'"
                        style="background: #f1f5f9; color: #475569; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                    <button type="submit"
                        style="background: #0f766e; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">Publish
                        Slot</button>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<!-- Modal 2: Student Teacher Applies for TP Slot -->
<?php if ($isTeacher): ?>
    <div id="applyTpModal"
        style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 2000; align-items: center; justify-content: center; padding: 1rem;">
        <div
            style="background: white; border-radius: 12px; max-width: 520px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 1.75rem; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h4 style="margin: 0; font-size: 1.15rem; color: #0f172a;"><i class="fa fa-graduation-cap"
                        style="color: #0f766e;"></i> Apply for TP Placement</h4>
                <button type="button" onclick="document.getElementById('applyTpModal').style.display='none'"
                    style="background: none; border: none; font-size: 1.4rem; cursor: pointer; color: #64748b;">&times;</button>
            </div>

            <form method="POST" action="/tp-hub" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action_type" value="apply_tp">
                <input type="hidden" name="school_id" id="modalSchoolId">
                <input type="hidden" name="placement_id" id="modalPlacementId">

                <div
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px 12px; margin-bottom: 1rem; font-size: 0.85rem;">
                    <div style="font-weight: 700; color: #0f172a;" id="modalSchoolName"></div>
                    <div style="color: #64748b;" id="modalSlotDetails"></div>
                </div>

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">University
                    / College Name <span style="color: red;">*</span></label>
                <input type="text" name="institution_name"
                    placeholder="e.g. Kenyatta University, Moi University, Machakos TTC" required
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 1rem;">
                    <div>
                        <label
                            style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Student
                            Reg Number</label>
                        <input type="text" name="reg_number" placeholder="e.g. E35/1234/2022"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                    <div>
                        <label
                            style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Year
                            of Study</label>
                        <select name="year_of_study"
                            style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                            <option value="3rd Year">3rd Year</option>
                            <option value="4th Year">4th Year</option>
                            <option value="2nd Year (Diploma/TTC)">2nd Year (Diploma/TTC)</option>
                            <option value="Post-Graduate PGDE">Post-Graduate PGDE</option>
                        </select>
                    </div>
                </div>

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Subject
                    Combinations <span style="color: red;">*</span></label>
                <input type="text" name="subjects" id="modalSubjectsInput" placeholder="e.g. Mathematics / Physics" required
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Target
                    Placement Term</label>
                <input type="text" name="desired_term" id="modalTermInput" value="Term 2"
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1rem; box-sizing: border-box;">

                <!-- University TP Intro Letter Upload -->
                <div
                    style="background: #f0fdfa; border: 1px dashed #0f766e; border-radius: 6px; padding: 10px 12px; margin-bottom: 1rem;">
                    <label
                        style="font-size: 0.85rem; font-weight: 700; color: #0f766e; margin-bottom: 4px; display: block;">
                        <i class="fa fa-file-pdf"></i> University Introduction / Recommendation Letter (Optional)
                    </label>
                    <p style="margin: 0 0 6px; font-size: 0.75rem; color: #64748b;">
                        Upload the official letter from your Faculty / Dean of Education (PDF, JPG, PNG, Max 5MB).
                    </p>
                    <input type="file" name="tp_letter" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                        style="font-size: 0.82rem; color: #475569;">
                </div>

                <label
                    style="font-size: 0.85rem; font-weight: 600; color: #334155; margin-bottom: 4px; display: block;">Introductory
                    Note to School Principal</label>
                <textarea name="notes" rows="2"
                    placeholder="Brief statement regarding your readiness, availability, and university requirements."
                    style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin-bottom: 1.5rem; box-sizing: border-box;"></textarea>

                <div style="display: flex; justify-content: flex-end; gap: 8px;">
                    <button type="button" onclick="document.getElementById('applyTpModal').style.display='none'"
                        style="background: #f1f5f9; color: #475569; border: none; padding: 8px 14px; border-radius: 6px; font-weight: 600; font-size: 0.85rem; cursor: pointer;">Cancel</button>
                    <button type="submit"
                        style="background: #0f766e; color: white; border: none; padding: 8px 18px; border-radius: 6px; font-weight: 700; font-size: 0.85rem; cursor: pointer;">Submit
                        Application</button>
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
<div id="placementSlipModal"
    style="display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.6); z-index: 3000; align-items: center; justify-content: center; padding: 1rem;">
    <div
        style="background: white; border-radius: 12px; max-width: 650px; width: 100%; max-height: 90vh; overflow-y: auto; padding: 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.25);">
        <div class="no-print"
            style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; border-bottom: 1px solid #e2e8f0; padding-bottom: 0.75rem;">
            <div style="display: flex; align-items: center; gap: 8px; color: #0f766e; font-weight: 800;">
                <i class="fa fa-certificate fa-lg"></i> Official TP Placement Confirmation
            </div>
            <div style="display: flex; gap: 8px;">
                <button onclick="window.print()" class="btn-primary" style="padding: 6px 14px; font-size: 0.82rem;">
                    <i class="fa fa-print"></i> Print / Save PDF
                </button>
                <button onclick="document.getElementById('placementSlipModal').style.display='none'"
                    style="background: #f1f5f9; color: #475569; border: none; padding: 6px 12px; border-radius: 6px; font-weight: 700; cursor: pointer;">
                    Close
                </button>
            </div>
        </div>

        <!-- Printable Slip Document Body -->
        <div id="printableSlipContent"
            style="border: 2px solid #0f766e; border-radius: 8px; padding: 2rem; background: #ffffff; color: #0f172a; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
            <div
                style="text-align: center; border-bottom: 2px double #cbd5e1; padding-bottom: 1rem; margin-bottom: 1.5rem;">
                <div style="font-size: 1.3rem; font-weight: 900; color: #0f766e; letter-spacing: 0.05em; text-transform: uppercase;"
                    id="slipSchoolName">SCHOOL NAME</div>
                <div style="font-size: 0.85rem; color: #64748b; margin-top: 2px;">TEACHING PRACTICE & ATTACHMENT
                    COORDINATION OFFICE</div>
                <div style="font-size: 0.8rem; color: #94a3b8;" id="slipSchoolCounty">Kenya</div>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 1.25rem;">
                <div><strong>Ref:</strong> TP/OFFER/<span id="slipRefCode">2026-001</span></div>
                <div><strong>Date:</strong> <span id="slipDate">Date</span></div>
            </div>

            <div style="text-align: center; margin: 1.5rem 0;">
                <span
                    style="font-size: 1.1rem; font-weight: 800; color: #0f172a; text-transform: uppercase; border-bottom: 2px solid #0f766e; padding-bottom: 4px;">
                    TEACHING PRACTICE PLACEMENT ACCEPTANCE SLIP
                </span>
            </div>

            <p style="font-size: 0.9rem; line-height: 1.6; margin-bottom: 1rem;">
                This is to officially confirm that <strong><span id="slipStudentName">STUDENT NAME</span></strong> (Reg.
                No: <strong><span id="slipRegNo">REG_NO</span></strong>) from <strong><span
                        id="slipUniversity">UNIVERSITY</span></strong> has been offered a Teaching Practice placement
                opening at our institution for:
            </p>

            <table style="width: 100%; border-collapse: collapse; margin-bottom: 1.5rem; font-size: 0.88rem;">
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 0; font-weight: 700; color: #475569; width: 40%;">Assigned Teaching Term:
                    </td>
                    <td style="padding: 8px 0; font-weight: 800; color: #0f766e;" id="slipTerm">Term 1</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 0; font-weight: 700; color: #475569;">Approved Subject Combinations:</td>
                    <td style="padding: 8px 0; font-weight: 800; color: #0f172a;" id="slipSubjects">Mathematics /
                        Physics</td>
                </tr>
                <tr style="border-bottom: 1px solid #e2e8f0;">
                    <td style="padding: 8px 0; font-weight: 700; color: #475569;">Institution Status:</td>
                    <td style="padding: 8px 0; color: #166534; font-weight: 700;">✓ Verified Placement Host School</td>
                </tr>
            </table>

            <p style="font-size: 0.85rem; color: #475569; line-height: 1.5; margin-bottom: 2rem;">
                The student teacher is expected to report to the Dean of Studies / TP Coordinator on the official term
                opening date accompanied by original identification, TSC student registration, and institutional TP
                assessment logbooks.
            </p>

            <div
                style="display: flex; justify-content: space-between; align-items: flex-end; margin-top: 2.5rem; padding-top: 1rem; border-top: 1px dashed #cbd5e1;">
                <div>
                    <div style="border-bottom: 1px solid #0f172a; width: 180px; margin-bottom: 4px;"></div>
                    <div style="font-size: 0.78rem; font-weight: 700; color: #0f172a;">Principal / TP Coordinator
                        Signature</div>
                    <div style="font-size: 0.72rem; color: #64748b;" id="slipSignSchool">School Administration</div>
                </div>
                <div style="text-align: right;">
                    <div
                        style="display: inline-block; border: 2px dashed #0f766e; color: #0f766e; padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase;">
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

        #placementSlipModal,
        #placementSlipModal * {
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