<?php
// views/teacher_cv_builder.php - Professional Educator CV Builder & Polisher
require_auth();

$authUser = auth_user();
if (($authUser['role'] ?? '') !== 'teacher') {
    header("Location: /login");
    exit();
}

$teacherId = $authUser['user_id'];
$teacher = R::load('teacher', $teacherId);
if (!$teacher->id) {
    echo "<div class='container' style='padding: 2rem;'><div style='background: #fee2e2; padding: 1.5rem; border-radius: 8px; color: #991b1b;'>Educator account not found.</div></div>";
    exit();
}

$savedMessage = '';
$errorMessage = '';

// Existing or saved CV data
$cvData = [];
if (!empty($teacher->cv_data)) {
    $cvData = json_decode($teacher->cv_data, true) ?: [];
}

// Handle Photo Removal Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_remove_photo'])) {
    validate_csrf();
    $teacher->profile_picture = null;
    
    // Also update saved CV data include_photo to 0
    if (!empty($cvData)) {
        $cvData['include_photo'] = 0;
        $teacher->cv_data = json_encode($cvData, JSON_UNESCAPED_UNICODE);
    }
    
    R::store($teacher);
    $savedMessage = 'Passport photo has been removed successfully.';
}

// Handle Form Submission / Save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_save_cv'])) {
    validate_csrf();

    $template = 'classic_kenyan';
    $includePhoto = isset($_POST['include_photo']) ? 1 : 0;

    // Handle photo upload if a new file is provided
    if (!empty($_FILES['profile_photo']['name'])) {
        $uploadResult = secure_validate_and_upload(
            $_FILES['profile_photo'],
            'uploads/photos/',
            ['jpg', 'jpeg', 'png', 'webp'],
            5 * 1024 * 1024
        );
        if ($uploadResult['success']) {
            $teacher->profile_picture = $uploadResult['relative_path'];
        } else {
            $errorMessage = $uploadResult['error'];
        }
    }

    $subjectCombo = trim($_POST['subject_combination'] ?? ($teacher->teaching_subjects ?? ''));
    if (!empty($subjectCombo)) {
        $teacher->teaching_subjects = $subjectCombo;
    }

    $personal = [
        'name' => trim($_POST['full_name'] ?? $teacher->name),
        'email' => trim($_POST['email'] ?? $teacher->email),
        'phone' => trim($_POST['phone'] ?? $teacher->mobile),
        'county' => trim($_POST['county'] ?? $teacher->county),
        'tsc_number' => trim($_POST['tsc_number'] ?? $teacher->tsc_number),
        'subject_combination' => $subjectCombo,
        'id_number' => trim($_POST['id_number'] ?? ''),
        'good_conduct' => trim($_POST['good_conduct'] ?? ($teacher->good_conduct_cert_no ?? ''))
    ];

    $summary = trim($_POST['summary'] ?? '');

    // Process Experience
    $expSchools = $_POST['exp_school'] ?? [];
    $expRoles = $_POST['exp_role'] ?? [];
    $expCounties = $_POST['exp_county'] ?? [];
    $expDates = $_POST['exp_dates'] ?? [];
    $expResp = $_POST['exp_responsibilities'] ?? [];
    $experience = [];

    for ($i = 0; $i < count($expSchools); $i++) {
        $sch = trim($expSchools[$i] ?? '');
        $rol = trim($expRoles[$i] ?? '');
        if (!empty($sch) || !empty($rol)) {
            $experience[] = [
                'school' => $sch,
                'role' => $rol,
                'county' => trim($expCounties[$i] ?? ''),
                'dates' => trim($expDates[$i] ?? ''),
                'responsibilities' => trim($expResp[$i] ?? '')
            ];
        }
    }

    // Process Education
    $eduInst = $_POST['edu_institution'] ?? [];
    $eduAward = $_POST['edu_award'] ?? [];
    $eduYear = $_POST['edu_year'] ?? [];
    $eduGrade = $_POST['edu_grade'] ?? [];
    $education = [];

    for ($i = 0; $i < count($eduInst); $i++) {
        $inst = trim($eduInst[$i] ?? '');
        $aw = trim($eduAward[$i] ?? '');
        if (!empty($inst) || !empty($aw)) {
            $education[] = [
                'institution' => $inst,
                'award' => $aw,
                'year' => trim($eduYear[$i] ?? ''),
                'grade' => trim($eduGrade[$i] ?? '')
            ];
        }
    }

    // Process Trainings
    $trainNames = $_POST['train_name'] ?? [];
    $trainProviders = $_POST['train_provider'] ?? [];
    $trainYears = $_POST['train_year'] ?? [];
    $trainings = [];

    for ($i = 0; $i < count($trainNames); $i++) {
        $tn = trim($trainNames[$i] ?? '');
        if (!empty($tn)) {
            $trainings[] = [
                'name' => $tn,
                'provider' => trim($trainProviders[$i] ?? ''),
                'year' => trim($trainYears[$i] ?? '')
            ];
        }
    }

    // Process Co-Curricular
    $coActivities = $_POST['co_activity'] ?? [];
    $coDetails = $_POST['co_details'] ?? [];
    $cocurricular = [];

    for ($i = 0; $i < count($coActivities); $i++) {
        $act = trim($coActivities[$i] ?? '');
        if (!empty($act)) {
            $cocurricular[] = [
                'activity' => $act,
                'details' => trim($coDetails[$i] ?? '')
            ];
        }
    }

    // Process Referees
    $refNames = $_POST['ref_name'] ?? [];
    $refTitles = $_POST['ref_title'] ?? [];
    $refInst = $_POST['ref_institution'] ?? [];
    $refPhones = $_POST['ref_phone'] ?? [];
    $refEmails = $_POST['ref_email'] ?? [];
    $referees = [];

    for ($i = 0; $i < count($refNames); $i++) {
        $rn = trim($refNames[$i] ?? '');
        if (!empty($rn)) {
            $referees[] = [
                'name' => $rn,
                'title' => trim($refTitles[$i] ?? ''),
                'institution' => trim($refInst[$i] ?? ''),
                'phone' => trim($refPhones[$i] ?? ''),
                'email' => trim($refEmails[$i] ?? '')
            ];
        }
    }

    // Package payload
    $cvPayload = [
        'template' => 'classic_kenyan',
        'include_photo' => $includePhoto,
        'personal' => $personal,
        'summary' => $summary,
        'experience' => $experience,
        'education' => $education,
        'trainings' => $trainings,
        'cocurricular' => $cocurricular,
        'referees' => $referees,
        'updated_at' => date('Y-m-d H:i:s')
    ];

    $teacher->cv_data = json_encode($cvPayload, JSON_UNESCAPED_UNICODE);

    // Sync summary back to brief_profile if empty
    if (empty($teacher->brief_profile) && !empty($summary)) {
        $teacher->brief_profile = $summary;
    }

    R::store($teacher);
    $cvData = $cvPayload;
    $savedMessage = 'Your curriculum vitae has been updated and saved successfully.';
}

// Pre-fill values
$activePhoto = isset($cvData['include_photo']) ? (!empty($cvData['include_photo'])) : (!empty($teacher->profile_picture));
$personal = $cvData['personal'] ?? [
    'name' => $teacher->name,
    'email' => $teacher->email,
    'phone' => $teacher->mobile,
    'county' => $teacher->county,
    'tsc_number' => $teacher->tsc_number,
    'subject_combination' => $teacher->teaching_subjects ?? '',
    'id_number' => '',
    'good_conduct' => $teacher->good_conduct_cert_no ?? ''
];

$summaryText = $cvData['summary'] ?? ($teacher->brief_profile ?: '');
$experiences = $cvData['experience'] ?? [];
$educations = $cvData['education'] ?? [];

// If education empty but teacher has profile qualification, add as initial clean entry
if (empty($educations) && !empty($teacher->qualification)) {
    $educations[] = [
        'institution' => $teacher->institutions_attended ?: '',
        'award' => $teacher->qualification ?: '',
        'year' => '',
        'grade' => ''
    ];
}

$trainings = $cvData['trainings'] ?? [];
$cocurricular = $cvData['cocurricular'] ?? [];
$referees = $cvData['referees'] ?? [];
?>

<style>
    /* Modern Clean Dashboard Density for CV Builder (KDAnalytiks Style) */
    .cv-builder-scope {
        font-size: 13.5px;
        color: #1e293b;
    }

    .cv-builder-scope h1,
    .cv-builder-scope h2,
    .cv-builder-scope h3,
    .cv-builder-scope h4 {
        text-transform: none !important;
        font-family: 'Inter', -apple-system, sans-serif !important;
    }

    .cv-builder-scope input[type="text"],
    .cv-builder-scope input[type="email"],
    .cv-builder-scope input[type="number"],
    .cv-builder-scope input[type="file"],
    .cv-builder-scope select {
        height: 36px !important;
        min-height: 36px !important;
        padding: 6px 12px !important;
        font-size: 0.84rem !important;
        line-height: 1.3 !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        margin-bottom: 0 !important;
        background-color: #ffffff !important;
        color: #0f172a !important;
        box-sizing: border-box !important;
    }

    .cv-builder-scope input[type="file"] {
        padding: 4px 8px !important;
        height: 34px !important;
    }

    .cv-builder-scope input:focus,
    .cv-builder-scope select:focus,
    .cv-builder-scope textarea:focus {
        border-color: #0f766e !important;
        outline: none !important;
        box-shadow: 0 0 0 2px rgba(15, 118, 110, 0.15) !important;
    }

    .cv-builder-scope textarea {
        font-size: 0.84rem !important;
        line-height: 1.55 !important;
        padding: 8px 12px !important;
        border: 1px solid #cbd5e1 !important;
        border-radius: 6px !important;
        margin-bottom: 0 !important;
        background-color: #ffffff !important;
        color: #0f172a !important;
        box-sizing: border-box !important;
    }

    .cv-builder-scope .field-label {
        font-size: 0.76rem !important;
        font-weight: 600 !important;
        color: #475569 !important;
        text-transform: none !important;
        letter-spacing: normal !important;
        margin-bottom: 4px !important;
        display: block !important;
    }

    /* Action Header Banner */
    .top-action-header {
        background: rgba(248, 250, 252, 0.98);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        padding: 0.85rem 1.15rem;
        margin-bottom: 0.5rem;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        box-shadow: 0 2px 8px rgba(15, 23, 42, 0.04);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.25rem;
    }

    /* Buttons */
    .cv-btn-preview {
        height: 36px !important;
        background: #ffffff !important;
        color: #0f766e !important;
        border: 1.5px solid #0f766e !important;
        font-size: 0.82rem !important;
        font-weight: 600 !important;
        padding: 0 16px !important;
        border-radius: 6px !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        box-sizing: border-box !important;
        line-height: 1 !important;
        margin: 0 !important;
        transition: all 0.2s ease !important;
        white-space: nowrap !important;
        text-transform: none !important;
        cursor: pointer !important;
    }

    .cv-btn-preview:hover {
        background: #f0fdfa !important;
        color: #115e59 !important;
    }

    .cv-btn-save {
        height: 36px !important;
        background: #0f766e !important;
        color: #ffffff !important;
        border: 1.5px solid #0f766e !important;
        font-size: 0.82rem !important;
        font-weight: 600 !important;
        padding: 0 18px !important;
        border-radius: 6px !important;
        text-decoration: none !important;
        display: inline-flex !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 6px !important;
        cursor: pointer !important;
        box-sizing: border-box !important;
        line-height: 1 !important;
        margin: 0 !important;
        transition: all 0.2s ease !important;
        white-space: nowrap !important;
        text-transform: none !important;
    }

    .cv-btn-save:hover {
        background: #115e59 !important;
        border-color: #115e59 !important;
    }

    .btn-polish-primary {
        background: #0f766e !important;
        color: #ffffff !important;
        border: none !important;
        font-size: 0.78rem !important;
        font-weight: 600 !important;
        padding: 6px 12px !important;
        border-radius: 5px !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        text-transform: none !important;
        transition: background 0.15s ease !important;
    }

    .btn-polish-primary:hover {
        background: #115e59 !important;
    }

    .btn-polish-secondary {
        background: #f8fafc !important;
        color: #0f766e !important;
        border: 1px solid #cbd5e1 !important;
        font-size: 0.78rem !important;
        font-weight: 600 !important;
        padding: 6px 12px !important;
        border-radius: 5px !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        text-transform: none !important;
        transition: all 0.15s ease !important;
    }

    .btn-polish-secondary:hover {
        background: #f1f5f9 !important;
        border-color: #0f766e !important;
    }

    .btn-add-item {
        background: #f8fafc !important;
        color: #0f766e !important;
        border: 1px dashed #cbd5e1 !important;
        padding: 6px 12px !important;
        border-radius: 5px !important;
        font-size: 0.78rem !important;
        font-weight: 600 !important;
        cursor: pointer !important;
        display: inline-flex !important;
        align-items: center !important;
        gap: 6px !important;
        text-transform: none !important;
        transition: all 0.15s ease !important;
    }

    .btn-add-item:hover {
        background: #f0fdfa !important;
        border-color: #0f766e !important;
    }

    /* Card Entry Container */
    .card-entry {
        background: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 1.15rem;
        margin-bottom: 0.75rem;
        transition: border-color 0.15s ease;
    }

    .card-entry:hover {
        border-color: #cbd5e1;
    }

    /* Sticky Controls Container: Wraps both Top Action and Quality Score */
    .cv-sticky-controls-wrapper {
        position: -webkit-sticky !important;
        position: sticky !important;
        top: -1.75rem !important;
        z-index: 100 !important;
        margin-bottom: 1.25rem !important;
        background: transparent !important;
    }

    /* Independent Quality Checklist Strip */
    .checklist-strip {
        background: rgba(255, 255, 255, 0.98);
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);
        border: 1px solid #cbd5e1;
        border-radius: 8px;
        padding: 8px 14px;
        margin-bottom: 0;
        box-shadow: 0 4px 12px rgba(15, 23, 42, 0.06);
        transition: all 0.2s ease;
    }

    .checklist-item {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        font-size: 0.78rem;
        color: #64748b;
        transition: all 0.2s ease;
    }

    .checklist-item.completed {
        color: #0f172a;
        font-weight: 500;
    }

    .checklist-item.completed i {
        color: #16a34a !important;
    }

    .checklist-item i {
        color: #cbd5e1;
        font-size: 0.82rem;
    }

    /* Mobile Responsiveness */
    @media (max-width: 900px) {
        .cv-sticky-controls-wrapper {
            top: -1.25rem !important;
            margin-bottom: 0.85rem !important;
        }

        .top-action-header {
            padding: 0.65rem 0.85rem !important;
            margin-bottom: 0.35rem !important;
            flex-direction: column !important;
            align-items: stretch !important;
            gap: 0.5rem !important;
        }

        .top-action-header h1 {
            font-size: 1.1rem !important;
        }

        .top-action-header p {
            display: none !important;
        }

        .top-action-buttons {
            display: flex !important;
            width: 100% !important;
            gap: 8px !important;
        }

        .top-action-buttons .cv-btn-preview,
        .top-action-buttons .cv-btn-save {
            flex: 1 1 50% !important;
            justify-content: center !important;
            height: 34px !important;
            font-size: 0.76rem !important;
            padding: 0 6px !important;
            text-align: center !important;
        }

        .checklist-strip {
            padding: 6px 10px !important;
        }
    }

    @media (max-width: 480px) {
        .cv-sticky-controls-wrapper {
            top: -1rem !important;
        }
    }
</style>

<div class="workspace-wrapper cv-builder-scope">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1100px; margin: 0 auto; padding: 1.75rem 1.5rem 3rem;">

        <!-- Sticky Controls Wrapper (Action Header + Independent Quality Checklist Card) -->
        <div class="cv-sticky-controls-wrapper">
            <!-- Top Action Header Card -->
            <div class="top-action-header">
                <div class="header-meta-text" style="flex: 1; min-width: 260px;">
                    <a href="/teacher/dashboard" style="color: #64748b; font-size: 0.8rem; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; margin-bottom: 4px;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h1 style="margin: 0; font-size: 1.35rem; color: #0f172a; font-weight: 800; display: flex; align-items: center; gap: 10px; line-height: 1.2;">
                        <i class="fa fa-file-lines" style="color: #0f766e;"></i> Educator CV Builder & Polisher
                    </h1>
                    <p style="margin: 3px 0 0; font-size: 0.82rem; color: #64748b;">
                        Create, polish, and export a clean, TSC & BOM-compliant academic curriculum vitae.
                    </p>
                </div>

                <div class="top-action-buttons" style="display: flex; gap: 10px; align-items: center; flex-shrink: 0;">
                    <button type="button" onclick="previewLiveCV()" class="cv-btn-preview" id="btnPreviewTop">
                        <i class="fa fa-eye"></i> Full Screen Preview & Print
                    </button>
                    <button type="button" onclick="document.getElementById('cvBuilderForm').submit()" class="cv-btn-save">
                        <i class="fa fa-floppy-disk"></i> Save Progress
                    </button>
                </div>
            </div>

            <!-- Quality Checklist Compact Strip (Separate Independent Card) -->
            <div class="checklist-strip">
                <div style="display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 10px; flex: 1; min-width: 200px;">
                        <span style="font-weight: 700; font-size: 0.84rem; color: #0f172a; display: flex; align-items: center; gap: 6px;">
                            <i class="fa fa-list-check" style="color: #0f766e;"></i> Quality Score:
                        </span>
                        <span id="checklistPercentage" style="font-size: 0.78rem; font-weight: 700; color: #0f766e; background: #f0fdfa; padding: 2px 8px; border-radius: 4px; border: 1px solid #ccfbf1;">
                            0%
                        </span>
                        <div style="background: #e2e8f0; border-radius: 10px; height: 6px; width: 140px; overflow: hidden;">
                            <div id="checklistProgressBar" style="background: #16a34a; height: 100%; width: 0%; transition: width 0.3s ease;"></div>
                        </div>
                    </div>

                    <button type="button" onclick="toggleChecklistDetails()" style="background: transparent; border: none; color: #0f766e; font-size: 0.78rem; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 4px;">
                        <span id="checklistToggleLabel">View Details</span> <i class="fa fa-chevron-down" id="checklistChevron" style="font-size: 0.7rem;"></i>
                    </button>
                </div>

                <div id="checklistDetailsGrid" style="display: none; margin-top: 10px; padding-top: 10px; border-top: 1px solid #f1f5f9; display: none; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 8px;">
                    <div class="checklist-item" id="check_contact">
                        <i class="fa fa-circle-dot"></i>
                        <span>Contact Info</span>
                    </div>
                    <div class="checklist-item" id="check_tsc">
                        <i class="fa fa-circle-dot"></i>
                        <span>TSC or ID Number</span>
                    </div>
                    <div class="checklist-item" id="check_subjects">
                        <i class="fa fa-circle-dot"></i>
                        <span>Subject Combination</span>
                    </div>
                    <div class="checklist-item" id="check_summary">
                        <i class="fa fa-circle-dot"></i>
                        <span>Profile Summary</span>
                    </div>
                    <div class="checklist-item" id="check_experience">
                        <i class="fa fa-circle-dot"></i>
                        <span>Teaching Experience</span>
                    </div>
                    <div class="checklist-item" id="check_education">
                        <i class="fa fa-circle-dot"></i>
                        <span>Academic Qualification</span>
                    </div>
                    <div class="checklist-item" id="check_referees">
                        <i class="fa fa-circle-dot"></i>
                        <span>2+ Referees</span>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($savedMessage)): ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-circle-check"></i> <?= h($savedMessage) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMessage)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                <i class="fa fa-circle-exclamation"></i> <?= h($errorMessage) ?>
            </div>
        <?php endif; ?>

        <!-- Main CV Form -->
        <form id="cvBuilderForm" method="POST" action="/teacher/cv-builder" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <input type="hidden" name="action_save_cv" value="1">

            <div style="display: flex; flex-direction: column; gap: 1.5rem;">

                <!-- Section 1: Personal & Contact Information (With Integrated Passport Photo) -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <h3 style="margin: 0 0 1.25rem; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-address-card" style="color: #0f766e;"></i> 1. Personal & Contact Information
                    </h3>

                    <input type="hidden" name="data_photo_base64" id="data_photo_base64" value="">
                    <input type="hidden" name="existing_photo" value="<?= h($teacher->profile_picture ?? '') ?>">

                    <!-- Passport Photo Box -->
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 0.84rem; font-weight: 600; color: #1e293b; margin: 0;">
                                <input type="checkbox" name="include_photo" value="1" id="includePhotoCheck" <?= $activePhoto ? 'checked' : '' ?> onchange="togglePhotoBox()">
                                Include Passport / Profile Photo on CV
                            </label>

                            <?php if (!empty($teacher->profile_picture)): ?>
                                <span style="font-size: 0.75rem; background: #dcfce7; color: #166534; padding: 3px 8px; border-radius: 4px; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;">
                                    <i class="fa fa-check"></i> Photo Saved on Profile
                                </span>
                            <?php endif; ?>
                        </div>

                        <div id="photoUploadBox" style="display: <?= $activePhoto ? 'block' : 'none' ?>; padding-top: 10px; border-top: 1px solid #e2e8f0;">
                            <div style="display: flex; align-items: center; gap: 14px; flex-wrap: wrap;">
                                <?php if (!empty($teacher->profile_picture)): ?>
                                    <div style="display: flex; align-items: center; gap: 10px; background: #ffffff; border: 1px solid #cbd5e1; padding: 4px 8px 4px 4px; border-radius: 6px;">
                                        <img src="/<?= h($teacher->profile_picture) ?>" alt="Passport Photo" style="width: 48px; height: 48px; border-radius: 4px; object-fit: cover; flex-shrink: 0;">
                                        <button type="button" onclick="submitRemovePhoto()" style="background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; font-size: 0.78rem; font-weight: 600; padding: 5px 10px; border-radius: 5px; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="fa fa-trash"></i> Remove Saved Photo
                                        </button>
                                    </div>
                                <?php endif; ?>

                                <div style="flex: 1; min-width: 220px;">
                                    <label class="field-label"><?= !empty($teacher->profile_picture) ? 'Upload New / Replace Photo' : 'Upload Passport Photo' ?> (JPG, PNG, max 5MB)</label>
                                    <input type="file" id="profilePhotoInput" name="profile_photo" accept="image/jpeg,image/png,image/webp" onchange="handleClientPhotoSelect(this)">
                                </div>

                                <!-- Dynamic Client-Side Preview when a new file is chosen -->
                                <div id="clientPhotoPreview" style="display: none; align-items: center; gap: 8px; background: #f0fdf4; border: 1px solid #bbf7d0; padding: 4px 8px; border-radius: 6px;">
                                    <img id="clientPhotoImg" src="" alt="Selected Photo" style="width: 44px; height: 44px; border-radius: 4px; object-fit: cover; border: 1px solid #86efac;">
                                    <div style="display: flex; flex-direction: column; gap: 2px;">
                                        <span style="font-size: 0.72rem; color: #166534; font-weight: 600;">Selected for Upload</span>
                                        <button type="button" onclick="clearSelectedPhoto()" style="background: #ffffff; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.72rem; padding: 2px 6px; border-radius: 4px; cursor: pointer;">
                                            <i class="fa fa-xmark"></i> Clear
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Personal Information Inputs -->
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem;">
                        <div>
                            <label class="field-label">Full Name</label>
                            <input type="text" name="full_name" id="field_full_name" value="<?= h($personal['name']) ?>" required placeholder="e.g. Samuel Kiprono">
                        </div>
                        <div>
                            <label class="field-label">Email Address</label>
                            <input type="email" name="email" id="field_email" value="<?= h($personal['email']) ?>" required placeholder="e.g. samuel@example.com">
                        </div>
                        <div>
                            <label class="field-label">Phone Number</label>
                            <input type="text" name="phone" id="field_phone" value="<?= h($personal['phone']) ?>" required placeholder="e.g. 0712 345 678">
                        </div>
                        <div>
                            <label class="field-label">County of Residence</label>
                            <input type="text" name="county" id="field_county" value="<?= h($personal['county']) ?>" placeholder="e.g. Nakuru">
                        </div>
                        <div>
                            <label class="field-label">TSC Registration Number (Optional)</label>
                            <input type="text" name="tsc_number" id="field_tsc_number" value="<?= h($personal['tsc_number']) ?>" placeholder="e.g. 765432">
                        </div>
                        <div>
                            <label class="field-label">Teaching Subject Combination</label>
                            <input type="text" name="subject_combination" id="field_subject_combination" value="<?= h($personal['subject_combination'] ?? '') ?>" placeholder="e.g. Mathematics / Physics">
                        </div>
                        <div>
                            <label class="field-label">National ID / Passport Number (Optional)</label>
                            <input type="text" name="id_number" id="field_id_number" value="<?= h($personal['id_number'] ?? '') ?>" placeholder="e.g. 31234567">
                        </div>
                        <div>
                            <label class="field-label">Police Clearance / Good Conduct Ref (Optional)</label>
                            <input type="text" name="good_conduct" id="field_good_conduct" value="<?= h($personal['good_conduct'] ?? '') ?>" placeholder="e.g. PCC-2024-ABC123">
                        </div>
                    </div>
                </div>

                <!-- Section 2: Professional Profile Summary -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-feather-pointed" style="color: #0f766e;"></i> 2. Professional Profile Summary
                        </h3>

                        <button type="button" onclick="triggerSmartSummary('summaryField')" class="btn-polish-primary">
                            <i class="fa fa-wand-magic-sparkles"></i> Generate & Polish Summary
                        </button>
                    </div>

                    <p style="font-size: 0.78rem; color: #64748b; margin: 0 0 8px;">
                        Provide 2–3 sentences summarizing your subject specialty, classroom achievements, and teaching values.
                    </p>
                    <textarea id="summaryField" name="summary" rows="4" placeholder="Dedicated and TSC-registered Mathematics and Physics teacher with 5+ years of classroom experience delivering competency-based curriculum lessons and improving mean grades..."><?= h($summaryText) ?></textarea>
                </div>

                <!-- Section 3: Teaching Experience -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-briefcase" style="color: #0f766e;"></i> 3. Teaching Experience
                        </h3>
                        <button type="button" onclick="addExperienceRow()" class="btn-add-item">
                            <i class="fa fa-plus"></i> Add Teaching Position
                        </button>
                    </div>

                    <div id="experienceContainer" style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if (empty($experiences)): ?>
                            <div class="card-entry">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                    <strong style="font-size: 0.84rem; color: #0f172a;">Teaching Position #1</strong>
                                    <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div>
                                        <label class="field-label">School / Institution</label>
                                        <input type="text" name="exp_school[]" value="" placeholder="e.g. Alliance High School">
                                    </div>
                                    <div>
                                        <label class="field-label">Role / Title</label>
                                        <input type="text" name="exp_role[]" value="" placeholder="e.g. Mathematics & Physics Teacher">
                                    </div>
                                    <div>
                                        <label class="field-label">County</label>
                                        <input type="text" name="exp_county[]" value="" placeholder="e.g. Kiambu">
                                    </div>
                                    <div>
                                        <label class="field-label">Dates / Period</label>
                                        <input type="text" name="exp_dates[]" value="" placeholder="e.g. 2021 - Present">
                                    </div>
                                </div>
                                <div>
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                        <label class="field-label">Key Responsibilities & Achievements</label>
                                        <button type="button" onclick="triggerBulletOptimize(this)" class="btn-polish-secondary" style="padding: 3px 8px !important; font-size: 0.72rem !important;">
                                            <i class="fa fa-check-double"></i> Optimize Bullets
                                        </button>
                                    </div>
                                    <textarea name="exp_responsibilities[]" rows="3" placeholder="• State key achievements and lesson delivery responsibilities..."></textarea>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($experiences as $idx => $exp): ?>
                                <div class="card-entry">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                                        <strong style="font-size: 0.84rem; color: #0f172a;">Teaching Position #<?= $idx + 1 ?></strong>
                                        <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                                            <i class="fa fa-trash"></i> Remove
                                        </button>
                                    </div>
                                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem;">
                                        <div>
                                            <label class="field-label">School / Institution</label>
                                            <input type="text" name="exp_school[]" value="<?= h($exp['school'] ?? '') ?>" placeholder="e.g. Alliance High School">
                                        </div>
                                        <div>
                                            <label class="field-label">Role / Title</label>
                                            <input type="text" name="exp_role[]" value="<?= h($exp['role'] ?? '') ?>" placeholder="e.g. Subject Teacher">
                                        </div>
                                        <div>
                                            <label class="field-label">County</label>
                                            <input type="text" name="exp_county[]" value="<?= h($exp['county'] ?? '') ?>" placeholder="e.g. Kiambu">
                                        </div>
                                        <div>
                                            <label class="field-label">Dates / Period</label>
                                            <input type="text" name="exp_dates[]" value="<?= h($exp['dates'] ?? '') ?>" placeholder="e.g. 2021 - Present">
                                        </div>
                                    </div>
                                    <div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <label class="field-label">Key Responsibilities & Achievements</label>
                                            <button type="button" onclick="triggerBulletOptimize(this)" class="btn-polish-secondary" style="padding: 3px 8px !important; font-size: 0.72rem !important;">
                                                <i class="fa fa-check-double"></i> Optimize Bullets
                                            </button>
                                        </div>
                                        <textarea name="exp_responsibilities[]" rows="3" placeholder="• State key achievements and lesson delivery responsibilities..."><?= h($exp['responsibilities'] ?? '') ?></textarea>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 4: Academic Background & Qualifications -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-graduation-cap" style="color: #0f766e;"></i> 4. Academic Background & Qualifications
                        </h3>
                        <button type="button" onclick="addEducationRow()" class="btn-add-item">
                            <i class="fa fa-plus"></i> Add Qualification
                        </button>
                    </div>

                    <div id="educationContainer" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if (empty($educations)): ?>
                            <div class="card-entry">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <strong style="font-size: 0.84rem; color: #0f172a;">Qualification Entry #1</strong>
                                    <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                                    <div>
                                        <label class="field-label">Institution / University / College</label>
                                        <input type="text" name="edu_institution[]" value="" placeholder="e.g. Kenyatta University">
                                    </div>
                                    <div>
                                        <label class="field-label">Award / Degree / Diploma</label>
                                        <input type="text" name="edu_award[]" value="" placeholder="e.g. Bachelor of Education (Science)">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                    <div>
                                        <label class="field-label">Graduation Year</label>
                                        <input type="text" name="edu_year[]" value="" placeholder="e.g. 2021">
                                    </div>
                                    <div>
                                        <label class="field-label">Classification / Grade</label>
                                        <input type="text" name="edu_grade[]" value="" placeholder="e.g. Second Class Upper / Credit">
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($educations as $idx => $edu): ?>
                                <div class="card-entry">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <strong style="font-size: 0.84rem; color: #0f172a;">Qualification Entry #<?= $idx + 1 ?></strong>
                                        <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                                            <i class="fa fa-trash"></i> Remove
                                        </button>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                                        <div>
                                            <label class="field-label">Institution / University / College</label>
                                            <input type="text" name="edu_institution[]" value="<?= h($edu['institution'] ?? '') ?>" placeholder="e.g. Kenyatta University">
                                        </div>
                                        <div>
                                            <label class="field-label">Award / Degree / Diploma</label>
                                            <input type="text" name="edu_award[]" value="<?= h($edu['award'] ?? '') ?>" placeholder="e.g. Bachelor of Education (Science)">
                                        </div>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                        <div>
                                            <label class="field-label">Graduation Year</label>
                                            <input type="text" name="edu_year[]" value="<?= h($edu['year'] ?? '') ?>" placeholder="e.g. 2021">
                                        </div>
                                        <div>
                                            <label class="field-label">Classification / Grade</label>
                                            <input type="text" name="edu_grade[]" value="<?= h($edu['grade'] ?? '') ?>" placeholder="e.g. Second Class Upper / Credit">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 5: Professional Trainings & Certifications -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-certificate" style="color: #0f766e;"></i> 5. Professional Trainings & Certifications
                        </h3>
                        <button type="button" onclick="addTrainingRow()" class="btn-add-item">
                            <i class="fa fa-plus"></i> Add Training
                        </button>
                    </div>

                    <div id="trainingsContainer" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if (empty($trainings)): ?>
                            <div class="card-entry" style="padding: 0.85rem;">
                                <div style="display: grid; grid-template-columns: 3fr 2fr 1fr 30px; gap: 0.5rem; align-items: center;">
                                    <div>
                                        <label class="field-label">Training / Workshop</label>
                                        <input type="text" name="train_name[]" value="" placeholder="e.g. CBC Assessment Facilitation">
                                    </div>
                                    <div>
                                        <label class="field-label">Provider</label>
                                        <input type="text" name="train_provider[]" value="" placeholder="e.g. KICD">
                                    </div>
                                    <div>
                                        <label class="field-label">Year</label>
                                        <input type="text" name="train_year[]" value="" placeholder="e.g. 2023">
                                    </div>
                                    <div style="padding-top: 18px;">
                                        <button type="button" onclick="this.closest('.card-entry').remove()" style="color: #dc2626; background: none; border: none; font-size: 0.85rem; cursor: pointer;">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($trainings as $tr): ?>
                                <div class="card-entry" style="padding: 0.85rem;">
                                    <div style="display: grid; grid-template-columns: 3fr 2fr 1fr 30px; gap: 0.5rem; align-items: center;">
                                        <div>
                                            <label class="field-label">Training / Workshop</label>
                                            <input type="text" name="train_name[]" value="<?= h($tr['name'] ?? '') ?>" placeholder="e.g. CBC Assessment Facilitation">
                                        </div>
                                        <div>
                                            <label class="field-label">Provider</label>
                                            <input type="text" name="train_provider[]" value="<?= h($tr['provider'] ?? '') ?>" placeholder="e.g. KICD">
                                        </div>
                                        <div>
                                            <label class="field-label">Year</label>
                                            <input type="text" name="train_year[]" value="<?= h($tr['year'] ?? '') ?>" placeholder="e.g. 2023">
                                        </div>
                                        <div style="padding-top: 18px;">
                                            <button type="button" onclick="this.closest('.card-entry').remove()" style="color: #dc2626; background: none; border: none; font-size: 0.85rem; cursor: pointer;">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 6: Co-Curricular & Responsibilities -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-people-group" style="color: #0f766e;"></i> 6. Co-Curricular & Responsibilities
                        </h3>
                        <div style="display: flex; gap: 8px;">
                            <button type="button" onclick="suggestCocurricular()" class="btn-polish-secondary">
                                <i class="fa fa-lightbulb"></i> Suggest Activities
                            </button>
                            <button type="button" onclick="addCocurricularRow()" class="btn-add-item">
                                <i class="fa fa-plus"></i> Add Activity
                            </button>
                        </div>
                    </div>

                    <div id="cocurricularContainer" style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php if (empty($cocurricular)): ?>
                            <div class="card-entry" style="padding: 0.85rem;">
                                <div style="display: grid; grid-template-columns: 2fr 4fr 30px; gap: 0.5rem; align-items: center;">
                                    <div>
                                        <label class="field-label">Activity / Capacity</label>
                                        <input type="text" name="co_activity[]" value="" placeholder="e.g. Science & Innovation Club Patron">
                                    </div>
                                    <div>
                                        <label class="field-label">Role Details / Outcomes</label>
                                        <input type="text" name="co_details[]" value="" placeholder="e.g. Mentored students for sub-county STEM congress exhibitions.">
                                    </div>
                                    <div style="padding-top: 18px;">
                                        <button type="button" onclick="this.closest('.card-entry').remove()" style="color: #dc2626; background: none; border: none; font-size: 0.85rem; cursor: pointer;">
                                            <i class="fa fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($cocurricular as $cc): ?>
                                <div class="card-entry" style="padding: 0.85rem;">
                                    <div style="display: grid; grid-template-columns: 2fr 4fr 30px; gap: 0.5rem; align-items: center;">
                                        <div>
                                            <label class="field-label">Activity / Capacity</label>
                                            <input type="text" name="co_activity[]" value="<?= h($cc['activity'] ?? '') ?>" placeholder="e.g. Science & Innovation Club Patron">
                                        </div>
                                        <div>
                                            <label class="field-label">Role Details / Outcomes</label>
                                            <input type="text" name="co_details[]" value="<?= h($cc['details'] ?? '') ?>" placeholder="e.g. Mentored students for STEM competitions.">
                                        </div>
                                        <div style="padding-top: 18px;">
                                            <button type="button" onclick="this.closest('.card-entry').remove()" style="color: #dc2626; background: none; border: none; font-size: 0.85rem; cursor: pointer;">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Section 7: Professional Referees -->
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.5rem;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 8px;">
                        <h3 style="margin: 0; font-size: 0.98rem; color: #0f172a; font-weight: 700; display: flex; align-items: center; gap: 8px;">
                            <i class="fa fa-address-book" style="color: #0f766e;"></i> 7. Professional Referees (2–3 Required)
                        </h3>
                        <button type="button" onclick="addRefereeRow()" class="btn-add-item">
                            <i class="fa fa-plus"></i> Add Referee
                        </button>
                    </div>

                    <div id="refereesContainer" style="display: flex; flex-direction: column; gap: 1rem;">
                        <?php if (empty($referees)): ?>
                            <div class="card-entry">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                    <strong style="font-size: 0.84rem; color: #0f172a;">Referee #1</strong>
                                    <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                                        <i class="fa fa-trash"></i> Remove
                                    </button>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.65rem; margin-bottom: 0.65rem;">
                                    <div>
                                        <label class="field-label">Full Name</label>
                                        <input type="text" name="ref_name[]" value="" placeholder="e.g. Mr. John Ochieng">
                                    </div>
                                    <div>
                                        <label class="field-label">Designation / Title</label>
                                        <input type="text" name="ref_title[]" value="" placeholder="e.g. Principal">
                                    </div>
                                    <div>
                                        <label class="field-label">Institution / School</label>
                                        <input type="text" name="ref_institution[]" value="" placeholder="e.g. Nairobi School">
                                    </div>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
                                    <div>
                                        <label class="field-label">Phone Number</label>
                                        <input type="text" name="ref_phone[]" value="" placeholder="e.g. 0722 000 000">
                                    </div>
                                    <div>
                                        <label class="field-label">Email Address</label>
                                        <input type="email" name="ref_email[]" value="" placeholder="e.g. principal@nairobischool.ac.ke">
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($referees as $idx => $rf): ?>
                                <div class="card-entry">
                                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                        <strong style="font-size: 0.84rem; color: #0f172a;">Referee #<?= $idx + 1 ?></strong>
                                        <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                                            <i class="fa fa-trash"></i> Remove
                                        </button>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.65rem; margin-bottom: 0.65rem;">
                                        <div>
                                            <label class="field-label">Full Name</label>
                                            <input type="text" name="ref_name[]" value="<?= h($rf['name'] ?? '') ?>" placeholder="e.g. Mr. John Ochieng">
                                        </div>
                                        <div>
                                            <label class="field-label">Designation / Title</label>
                                            <input type="text" name="ref_title[]" value="<?= h($rf['title'] ?? '') ?>" placeholder="e.g. Principal">
                                        </div>
                                        <div>
                                            <label class="field-label">Institution / School</label>
                                            <input type="text" name="ref_institution[]" value="<?= h($rf['institution'] ?? '') ?>" placeholder="e.g. Nairobi School">
                                        </div>
                                    </div>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
                                        <div>
                                            <label class="field-label">Phone Number</label>
                                            <input type="text" name="ref_phone[]" value="<?= h($rf['phone'] ?? '') ?>" placeholder="e.g. 0722 000 000">
                                        </div>
                                        <div>
                                            <label class="field-label">Email Address</label>
                                            <input type="email" name="ref_email[]" value="<?= h($rf['email'] ?? '') ?>" placeholder="e.g. principal@nairobischool.ac.ke">
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Bottom Save & Preview Bar -->
                <div style="display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 0; border-top: 1px solid #e2e8f0; flex-wrap: wrap; gap: 10px;">
                    <div style="font-size: 0.8rem; color: #64748b;">
                        <i class="fa fa-clock-rotate-left"></i> Last updated:
                        <strong><?= !empty($cvData['updated_at']) ? date('M d, Y H:i', strtotime($cvData['updated_at'])) : 'Draft not yet saved' ?></strong>
                    </div>

                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" onclick="previewLiveCV()" class="cv-btn-preview">
                            <i class="fa fa-eye"></i> Full Screen Preview & Print
                        </button>
                        <button type="submit" class="cv-btn-save">
                            <i class="fa fa-floppy-disk"></i> Save Progress
                        </button>
                    </div>
                </div>

            </div>
        </form>

        <!-- Hidden Standalone Form for Removing Photo -->
        <form id="removePhotoForm" method="POST" action="/teacher/cv-builder" style="display: none;">
            <?= csrf_field() ?>
            <input type="hidden" name="action_remove_photo" value="1">
        </form>

    </main>
</div>

<!-- Dynamic JavaScript -->
<script>
    const teacherContext = {
        name: <?= json_encode($teacher->name) ?>,
        subjects: <?= json_encode($teacher->teaching_subjects) ?>,
        qualification: <?= json_encode($teacher->qualification) ?>,
        years_of_experience: <?= json_encode($teacher->years_of_experience) ?>,
        grade_levels: <?= json_encode($teacher->grade_levels) ?>,
        county: <?= json_encode($teacher->county) ?>
    };

    // Handle dynamic local photo selection preview
    function handleClientPhotoSelect(input) {
        const previewBox = document.getElementById('clientPhotoPreview');
        const previewImg = document.getElementById('clientPhotoImg');
        const hiddenBase64 = document.getElementById('data_photo_base64');
        
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                if (previewImg) previewImg.src = e.target.result;
                if (previewBox) previewBox.style.display = 'inline-flex';
                if (hiddenBase64) hiddenBase64.value = e.target.result;
            };
            reader.readAsDataURL(file);
        }
    }

    // Clear client-side chosen photo
    function clearSelectedPhoto() {
        const input = document.getElementById('profilePhotoInput');
        const previewBox = document.getElementById('clientPhotoPreview');
        const previewImg = document.getElementById('clientPhotoImg');
        const hiddenBase64 = document.getElementById('data_photo_base64');
        
        if (input) input.value = '';
        if (previewImg) previewImg.src = '';
        if (previewBox) previewBox.style.display = 'none';
        if (hiddenBase64) hiddenBase64.value = '';
    }

    // Live Dynamic Preview Submission (Previews whatever is typed on the page!)
    async function previewLiveCV() {
        const form = document.getElementById('cvBuilderForm');
        const photoInput = document.getElementById('profilePhotoInput');
        const hiddenBase64 = document.getElementById('data_photo_base64');

        // If a new local photo is selected, ensure it's encoded to base64 before submitting
        if (photoInput && photoInput.files && photoInput.files[0]) {
            await new Promise((resolve) => {
                const reader = new FileReader();
                reader.onload = function(e) {
                    if (hiddenBase64) hiddenBase64.value = e.target.result;
                    resolve();
                };
                reader.onerror = resolve;
                reader.readAsDataURL(photoInput.files[0]);
            });
        }

        const originalAction = form.action;
        const originalTarget = form.target;

        form.target = '_blank';
        form.action = '/teacher/cv-preview?id=' + encodeURIComponent(<?= json_encode($teacher->id) ?>);
        form.submit();

        // Restore original target & action for normal saves
        form.target = originalTarget;
        form.action = originalAction;
    }

    // Photo Box Toggle
    function togglePhotoBox() {
        const check = document.getElementById('includePhotoCheck');
        const box = document.getElementById('photoUploadBox');
        if (box && check) {
            box.style.display = check.checked ? 'block' : 'none';
        }
    }

    // Submit Photo Removal
    function submitRemovePhoto() {
        if (confirm('Are you sure you want to remove your saved passport photo from your profile?')) {
            document.getElementById('removePhotoForm').submit();
        }
    }

    // Toggle Checklist Details
    function toggleChecklistDetails() {
        const grid = document.getElementById('checklistDetailsGrid');
        const label = document.getElementById('checklistToggleLabel');
        const chevron = document.getElementById('checklistChevron');
        
        if (grid.style.display === 'none' || !grid.style.display) {
            grid.style.display = 'grid';
            label.textContent = 'Hide Details';
            chevron.className = 'fa fa-chevron-up';
        } else {
            grid.style.display = 'none';
            label.textContent = 'View Details';
            chevron.className = 'fa fa-chevron-down';
        }
    }

    // Dynamic Quality Checklist Validation
    function updateChecklist() {
        const fullName = (document.getElementById('field_full_name')?.value || '').trim();
        const email = (document.getElementById('field_email')?.value || '').trim();
        const phone = (document.getElementById('field_phone')?.value || '').trim();
        const tsc = (document.getElementById('field_tsc_number')?.value || '').trim();
        const idNumber = (document.getElementById('field_id_number')?.value || '').trim();
        const subjects = (document.getElementById('field_subject_combination')?.value || '').trim();
        const summary = (document.getElementById('summaryField')?.value || '').trim();

        // Count experiences
        const expSchools = document.querySelectorAll('input[name="exp_school[]"]');
        let hasExp = false;
        expSchools.forEach(el => {
            if (el.value.trim().length > 0) hasExp = true;
        });

        // Count educations
        const eduInst = document.querySelectorAll('input[name="edu_institution[]"]');
        let hasEdu = false;
        eduInst.forEach(el => {
            if (el.value.trim().length > 0) hasEdu = true;
        });

        // Count referees
        const refNames = document.querySelectorAll('input[name="ref_name[]"]');
        let refCount = 0;
        refNames.forEach(el => {
            if (el.value.trim().length > 0) refCount++;
        });

        const hasValidIdentification = (tsc.length >= 4 || idNumber.length >= 5);

        const checks = [
            { id: 'check_contact', valid: (fullName.length > 0 && email.length > 0 && phone.length > 0) },
            { id: 'check_tsc', valid: hasValidIdentification },
            { id: 'check_subjects', valid: (subjects.length > 0) },
            { id: 'check_summary', valid: (summary.length >= 25) },
            { id: 'check_experience', valid: hasExp },
            { id: 'check_education', valid: hasEdu },
            { id: 'check_referees', valid: (refCount >= 2) }
        ];

        let passedCount = 0;
        checks.forEach(c => {
            const item = document.getElementById(c.id);
            if (item) {
                const icon = item.querySelector('i');
                if (c.valid) {
                    passedCount++;
                    item.classList.add('completed');
                    if (icon) {
                        icon.className = 'fa fa-circle-check';
                    }
                } else {
                    item.classList.remove('completed');
                    if (icon) {
                        icon.className = 'fa fa-circle-dot';
                    }
                }
            }
        });

        const percent = Math.round((passedCount / checks.length) * 100);
        const percentBadge = document.getElementById('checklistPercentage');
        const progressBar = document.getElementById('checklistProgressBar');

        if (percentBadge) {
            percentBadge.textContent = `${passedCount}/${checks.length} (${percent}%)`;
        }
        if (progressBar) {
            progressBar.style.width = `${percent}%`;
            progressBar.style.backgroundColor = percent === 100 ? '#16a34a' : (percent >= 60 ? '#0f766e' : '#f59e0b');
        }
    }

    // Attach real-time input listeners
    document.addEventListener('DOMContentLoaded', function() {
        updateChecklist();
        const form = document.getElementById('cvBuilderForm');
        if (form) {
            form.addEventListener('input', updateChecklist);
            form.addEventListener('change', updateChecklist);
        }
    });

    // Smart Single Button: Generate & Polish Summary
    async function triggerSmartSummary(targetFieldId) {
        const field = document.getElementById(targetFieldId);
        if (!field) return;

        const val = field.value.trim();
        const action = (val.length > 15) ? 'polish_summary' : 'generate_summary';

        const btn = event.currentTarget;
        const originalBtnText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating & Polishing...';

        const subjectVal = document.getElementById('field_subject_combination')?.value || teacherContext.subjects;

        try {
            const response = await fetch('/api/cv-polish', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    raw_text: val,
                    context: {
                        ...teacherContext,
                        subjects: subjectVal
                    }
                })
            });

            const data = await response.json();
            if (data && data.success && data.polished_text) {
                field.value = data.polished_text;
                field.style.backgroundColor = '#f0fdf4';
                setTimeout(() => { field.style.backgroundColor = '#ffffff'; }, 1200);
                updateChecklist();
            } else {
                alert('Summary generation response received. Please review your summary.');
            }
        } catch (err) {
            console.error(err);
            alert('Could not complete request at this time.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
        }
    }

    // Polish Summary / Generate Summary AJAX Trigger
    async function triggerPolish(action, targetFieldId) {
        const field = document.getElementById(targetFieldId);
        if (!field) return;

        const originalValue = field.value;
        const btn = event.currentTarget;
        const originalBtnText = btn.innerHTML;

        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Polishing...';

        const subjectVal = document.getElementById('field_subject_combination')?.value || teacherContext.subjects;

        try {
            const response = await fetch('/api/cv-polish', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: action,
                    raw_text: originalValue,
                    context: {
                        ...teacherContext,
                        subjects: subjectVal
                    }
                })
            });

            const data = await response.json();
            if (data && data.success && data.polished_text) {
                field.value = data.polished_text;
                field.style.backgroundColor = '#f0fdf4';
                setTimeout(() => { field.style.backgroundColor = '#ffffff'; }, 1200);
                updateChecklist();
            } else {
                alert('Polishing service response received. Please review your summary.');
            }
        } catch (err) {
            console.error(err);
            alert('Could not complete request at this time.');
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
        }
    }

    // Optimize Bullets inside Experience Entry
    async function triggerBulletOptimize(btn) {
        const card = btn.closest('.card-entry');
        const textarea = card.querySelector('textarea[name="exp_responsibilities[]"]');
        const schoolInput = card.querySelector('input[name="exp_school[]"]');
        const roleInput = card.querySelector('input[name="exp_role[]"]');

        if (!textarea) return;

        const originalBtnText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Optimizing...';

        try {
            const response = await fetch('/api/cv-polish', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'optimize_bullets',
                    raw_text: textarea.value,
                    context: {
                        ...teacherContext,
                        school_name: schoolInput ? schoolInput.value : '',
                        role_title: roleInput ? roleInput.value : ''
                    }
                })
            });

            const data = await response.json();
            if (data && data.success && data.polished_text) {
                textarea.value = data.polished_text;
                textarea.style.backgroundColor = '#f0fdf4';
                setTimeout(() => { textarea.style.backgroundColor = '#ffffff'; }, 1200);
            }
        } catch (err) {
            console.error(err);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
        }
    }

    // Add Experience Row
    function addExperienceRow() {
        const container = document.getElementById('experienceContainer');
        const count = container.querySelectorAll('.card-entry').length + 1;
        const row = document.createElement('div');
        row.className = 'card-entry';
        row.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem;">
                <strong style="font-size: 0.84rem; color: #0f172a;">Teaching Position #${count}</strong>
                <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                    <i class="fa fa-trash"></i> Remove
                </button>
            </div>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 0.75rem; margin-bottom: 0.75rem;">
                <div>
                    <label class="field-label">School / Institution</label>
                    <input type="text" name="exp_school[]" placeholder="e.g. Alliance High School">
                </div>
                <div>
                    <label class="field-label">Role / Title</label>
                    <input type="text" name="exp_role[]" placeholder="e.g. Subject Teacher">
                </div>
                <div>
                    <label class="field-label">County</label>
                    <input type="text" name="exp_county[]" placeholder="e.g. Kiambu">
                </div>
                <div>
                    <label class="field-label">Dates / Period</label>
                    <input type="text" name="exp_dates[]" placeholder="e.g. 2022 - Present">
                </div>
            </div>
            <div>
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                    <label class="field-label">Key Responsibilities & Achievements</label>
                    <button type="button" onclick="triggerBulletOptimize(this)" class="btn-polish-secondary" style="padding: 3px 8px !important; font-size: 0.72rem !important;">
                        <i class="fa fa-check-double"></i> Optimize Bullets
                    </button>
                </div>
                <textarea name="exp_responsibilities[]" rows="3" placeholder="• State key achievements and lesson delivery responsibilities..."></textarea>
            </div>
        `;
        container.appendChild(row);
        updateChecklist();
    }

    // Add Education Row (Stacked 2-Column Card)
    function addEducationRow() {
        const container = document.getElementById('educationContainer');
        const count = container.querySelectorAll('.card-entry').length + 1;
        const row = document.createElement('div');
        row.className = 'card-entry';
        row.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <strong style="font-size: 0.84rem; color: #0f172a;">Qualification Entry #${count}</strong>
                <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                    <i class="fa fa-trash"></i> Remove
                </button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 0.75rem;">
                <div>
                    <label class="field-label">Institution / University / College</label>
                    <input type="text" name="edu_institution[]" placeholder="e.g. Kenyatta University">
                </div>
                <div>
                    <label class="field-label">Award / Degree / Diploma</label>
                    <input type="text" name="edu_award[]" placeholder="e.g. Bachelor of Education (Science)">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                <div>
                    <label class="field-label">Graduation Year</label>
                    <input type="text" name="edu_year[]" placeholder="e.g. 2021">
                </div>
                <div>
                    <label class="field-label">Classification / Grade</label>
                    <input type="text" name="edu_grade[]" placeholder="e.g. Second Class Upper / Credit">
                </div>
            </div>
        `;
        container.appendChild(row);
        updateChecklist();
    }

    // Add Training Row
    function addTrainingRow() {
        const container = document.getElementById('trainingsContainer');
        const row = document.createElement('div');
        row.className = 'card-entry';
        row.style = 'padding: 0.85rem;';
        row.innerHTML = `
            <div style="display: grid; grid-template-columns: 3fr 2fr 1fr 30px; gap: 0.5rem; align-items: center;">
                <div>
                    <label class="field-label">Training / Workshop</label>
                    <input type="text" name="train_name[]" placeholder="e.g. CBC Assessment Facilitation">
                </div>
                <div>
                    <label class="field-label">Provider</label>
                    <input type="text" name="train_provider[]" placeholder="e.g. KICD">
                </div>
                <div>
                    <label class="field-label">Year</label>
                    <input type="text" name="train_year[]" placeholder="e.g. 2023">
                </div>
                <div style="padding-top: 18px;">
                    <button type="button" onclick="this.closest('.card-entry').remove()" style="color: #dc2626; background: none; border: none; font-size: 0.85rem; cursor: pointer;">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(row);
    }

    // Add Co-Curricular Row with data helper
    function addCocurricularRowWithData(activity, details) {
        const container = document.getElementById('cocurricularContainer');
        const row = document.createElement('div');
        row.className = 'card-entry';
        row.style = 'padding: 0.85rem;';
        row.innerHTML = `
            <div style="display: grid; grid-template-columns: 2fr 4fr 30px; gap: 0.5rem; align-items: center;">
                <div>
                    <label class="field-label">Activity / Capacity</label>
                    <input type="text" name="co_activity[]" value="${escapeHtml(activity)}" placeholder="e.g. Drama Club Patron">
                </div>
                <div>
                    <label class="field-label">Role Details / Outcomes</label>
                    <input type="text" name="co_details[]" value="${escapeHtml(details)}" placeholder="e.g. Directed regional drama festival entries.">
                </div>
                <div style="padding-top: 18px;">
                    <button type="button" onclick="this.closest('.card-entry').remove()" style="color: #dc2626; background: none; border: none; font-size: 0.85rem; cursor: pointer;">
                        <i class="fa fa-trash"></i>
                    </button>
                </div>
            </div>
        `;
        container.appendChild(row);
    }

    // Add empty Co-Curricular Row
    function addCocurricularRow() {
        addCocurricularRowWithData('', '');
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // Suggest Co-Curricular Activities (Smart populate: fills blank first row if empty)
    async function suggestCocurricular() {
        const subjectVal = document.getElementById('field_subject_combination')?.value || teacherContext.subjects;
        const btn = event.currentTarget;
        const originalBtnText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Suggesting...';

        try {
            const response = await fetch('/api/cv-polish', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'suggest_cocurricular',
                    context: {
                        ...teacherContext,
                        subjects: subjectVal
                    }
                })
            });
            const data = await response.json();
            if (data && data.success && data.polished_text) {
                const lines = data.polished_text.split('\n')
                    .map(l => l.replace(/^[•\-\*]\s*/, '').trim())
                    .filter(l => l.length > 0);

                if (lines.length > 0) {
                    const container = document.getElementById('cocurricularContainer');
                    const cards = container.querySelectorAll('.card-entry');
                    let startIdx = 0;

                    // If first card exists and has empty inputs, populate it directly
                    if (cards.length >= 1) {
                        const actInput = cards[0].querySelector('input[name="co_activity[]"]');
                        const detInput = cards[0].querySelector('input[name="co_details[]"]');
                        if (actInput && detInput && !actInput.value.trim() && !detInput.value.trim()) {
                            actInput.value = 'Club Patron / Leadership';
                            detInput.value = lines[0];
                            startIdx = 1;
                        }
                    }

                    for (let i = startIdx; i < lines.length; i++) {
                        addCocurricularRowWithData('Club Patron / Leadership', lines[i]);
                    }
                    updateChecklist();
                }
            }
        } catch (err) {
            console.error(err);
        } finally {
            btn.disabled = false;
            btn.innerHTML = originalBtnText;
        }
    }

    // Add Referee Row (2-Column Card)
    function addRefereeRow() {
        const container = document.getElementById('refereesContainer');
        const count = container.querySelectorAll('.card-entry').length + 1;
        const row = document.createElement('div');
        row.className = 'card-entry';
        row.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                <strong style="font-size: 0.84rem; color: #0f172a;">Referee #${count}</strong>
                <button type="button" onclick="this.closest('.card-entry').remove(); updateChecklist();" style="color: #dc2626; background: none; border: none; font-size: 0.78rem; cursor: pointer;">
                    <i class="fa fa-trash"></i> Remove
                </button>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.65rem; margin-bottom: 0.65rem;">
                <div>
                    <label class="field-label">Full Name</label>
                    <input type="text" name="ref_name[]" placeholder="e.g. Mr. John Ochieng">
                </div>
                <div>
                    <label class="field-label">Designation / Title</label>
                    <input type="text" name="ref_title[]" placeholder="e.g. Principal">
                </div>
                <div>
                    <label class="field-label">Institution / School</label>
                    <input type="text" name="ref_institution[]" placeholder="e.g. Nairobi School">
                </div>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.65rem;">
                <div>
                    <label class="field-label">Phone Number</label>
                    <input type="text" name="ref_phone[]" placeholder="e.g. 0722 000 000">
                </div>
                <div>
                    <label class="field-label">Email Address</label>
                    <input type="email" name="ref_email[]" placeholder="e.g. principal@nairobischool.ac.ke">
                </div>
            </div>
        `;
        container.appendChild(row);
        updateChecklist();
    }
</script>