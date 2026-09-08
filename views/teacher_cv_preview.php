<?php
// views/teacher_cv_preview.php - Clean Printable Educator CV Preview
require_auth();

$authUser = auth_user();
$isTeacher = (($authUser['role'] ?? '') === 'teacher');
$isSchool = (($authUser['role'] ?? '') === 'school');

$teacherId = intval($_GET['id'] ?? 0);
if (!$teacherId && $isTeacher) {
    $teacherId = $authUser['user_id'];
}

if (!$teacherId) {
    echo "<div style='padding: 2rem; text-align: center; color: #dc2626;'>Teacher ID required.</div>";
    exit();
}

$teacher = R::load('teacher', $teacherId);
if (!$teacher->id) {
    echo "<div style='padding: 2rem; text-align: center; color: #dc2626;'>Teacher profile not found.</div>";
    exit();
}

// Ensure teacher can only view their own CV unless a school is viewing a candidate
if ($isTeacher && $teacher->id != $authUser['user_id']) {
    http_response_code(403);
    echo "<div style='padding: 2rem; text-align: center; color: #dc2626;'>Unauthorized to view this curriculum vitae.</div>";
    exit();
}

$cvData = [];
if (!empty($teacher->cv_data)) {
    $cvData = json_decode($teacher->cv_data, true) ?: [];
}

// Check if dynamic preview data is passed via POST
$isLivePost = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($isLivePost) {
    $fullName = trim($_POST['full_name'] ?? ($teacher->name ?: 'Educator Name'));
    $email = trim($_POST['email'] ?? ($teacher->email ?: ''));
    $phone = trim($_POST['phone'] ?? ($teacher->mobile ?: ''));
    $county = trim($_POST['county'] ?? ($teacher->county ?: 'Kenya'));
    $tscNumber = trim($_POST['tsc_number'] ?? ($teacher->tsc_number ?: ''));
    $idNumber = trim($_POST['id_number'] ?? '');
    $goodConduct = trim($_POST['good_conduct'] ?? ($teacher->good_conduct_cert_no ?? ''));
    $subjDisplay = trim($_POST['subject_combination'] ?? ($teacher->teaching_subjects ?: ''));
    $includePhoto = isset($_POST['include_photo']) && $_POST['include_photo'] == '1';
    $photoPath = !empty($teacher->profile_picture) ? $teacher->profile_picture : '';
    $summary = trim($_POST['summary'] ?? '');

    // Parse Live Experience
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

    // Parse Live Education
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

    // Parse Live Trainings
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

    // Parse Live Co-Curricular
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

    // Parse Live Referees
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
} else {
    // Fallback values from database
    $includePhoto = !empty($cvData['include_photo']);
    $photoPath = !empty($teacher->profile_picture) ? $teacher->profile_picture : '';

    $fullName = $cvData['personal']['name'] ?? ($teacher->name ?: 'Educator Name');
    $email = $cvData['personal']['email'] ?? ($teacher->email ?: '');
    $phone = $cvData['personal']['phone'] ?? ($teacher->mobile ?: '');
    $county = $cvData['personal']['county'] ?? ($teacher->county ?: 'Kenya');
    $tscNumber = $cvData['personal']['tsc_number'] ?? ($teacher->tsc_number ?: '');
    $idNumber = $cvData['personal']['id_number'] ?? '';
    $goodConduct = $cvData['personal']['good_conduct'] ?? (!empty($teacher->good_conduct_cert_no) ? $teacher->good_conduct_cert_no : '');
    $subjDisplay = !empty($cvData['personal']['subject_combination']) ? $cvData['personal']['subject_combination'] : ($teacher->teaching_subjects ?: '');

    $summary = trim($cvData['summary'] ?? ($teacher->brief_profile ?: ''));

    $experience = $cvData['experience'] ?? [];
    $education = $cvData['education'] ?? [];
    $trainings = $cvData['trainings'] ?? [];
    $cocurricular = $cvData['cocurricular'] ?? [];
    $referees = $cvData['referees'] ?? [];
}

$template = 'classic_kenyan';
$isVerified = (($teacher->verification_status ?? '') === 'verified');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Curriculum Vitae - <?= h($fullName) ?> | MwalimuLink</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        :root {
            --primary-teal: #0f766e;
            --primary-dark: #0f172a;
            --border-line: #cbd5e1;
            --text-main: #1e293b;
            --text-muted: #475569;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            background-color: #f1f5f9;
            color: var(--text-main);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.5;
            -webkit-print-color-adjust: exact !important;
            print-color-adjust: exact !important;
        }

        /* Top Action Bar (Hidden in Print) */
        .cv-action-bar {
            background: #ffffff;
            border-bottom: 1px solid #e2e8f0;
            padding: 10px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
            min-height: 58px;
        }

        .btn-action {
            height: 38px !important;
            min-height: 38px !important;
            max-height: 38px !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 8px !important;
            padding: 0 16px !important;
            font-size: 0.84rem !important;
            font-weight: 600 !important;
            border-radius: 6px !important;
            text-decoration: none !important;
            cursor: pointer !important;
            transition: all 0.2s ease !important;
            border: 1px solid transparent !important;
            box-sizing: border-box !important;
            margin: 0 !important;
            line-height: 1 !important;
            vertical-align: middle !important;
            white-space: nowrap !important;
        }

        .btn-print {
            background: #0f766e !important;
            color: #ffffff !important;
            border-color: #0f766e !important;
        }

        .btn-print:hover {
            background: #115e59 !important;
            border-color: #115e59 !important;
        }

        .btn-secondary {
            background: #ffffff !important;
            color: #334155 !important;
            border-color: #cbd5e1 !important;
        }

        .btn-secondary:hover {
            background: #f1f5f9 !important;
            border-color: #94a3b8 !important;
        }

        /* Save Dropdown Menu */
        .save-dropdown {
            position: relative;
            display: inline-flex;
            align-items: center;
            margin: 0;
        }

        .save-dropdown-menu {
            display: none;
            position: absolute;
            right: 0;
            top: calc(100% + 6px);
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.15);
            min-width: 230px;
            z-index: 1100;
            padding: 6px 0;
        }

        .save-dropdown-menu button {
            width: 100%;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 16px;
            font-size: 0.85rem;
            font-weight: 600;
            color: #1e293b;
            background: #ffffff;
            border: none;
            cursor: pointer;
            text-align: left;
            transition: background 0.15s ease;
        }

        .save-dropdown-menu button:hover {
            background: #f1f5f9;
            color: #0f766e;
        }

        /* Printable Paper Container */
        .cv-paper-container {
            max-width: 860px;
            margin: 2rem auto;
            background: #ffffff;
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.06);
            border-radius: 4px;
            padding: 45px 50px;
            min-height: 1050px;
        }

        /* ========================================================= */
        /* TEMPLATE 1: CLASSIC KENYAN ACADEMIC                      */
        /* ========================================================= */
        .template-classic_kenyan {
            font-family: "Times New Roman", Times, Georgia, serif;
            color: #111827;
            line-height: 1.45;
        }

        .template-classic_kenyan .cv-header {
            border-bottom: 2px solid #0f172a;
            padding-bottom: 15px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .template-classic_kenyan .header-text-block {
            flex: 1;
        }

        .template-classic_kenyan .cv-name {
            font-size: 1.85rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .template-classic_kenyan .cv-tagline {
            font-size: 1.05rem;
            font-style: italic;
            color: #334155;
            margin-bottom: 8px;
        }

        .template-classic_kenyan .contact-meta-grid {
            font-size: 0.9rem;
            display: flex;
            flex-wrap: wrap;
            gap: 12px 20px;
            color: #1e293b;
        }

        .template-classic_kenyan .cv-badge-block {
            text-align: right;
            font-size: 0.88rem;
            border-left: 1px solid #e2e8f0;
            padding-left: 16px;
        }

        .template-classic_kenyan .cv-section {
            margin-bottom: 20px;
            page-break-inside: avoid;
        }

        .template-classic_kenyan .section-title {
            font-size: 1.05rem;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            border-bottom: 1.5px solid #334155;
            padding-bottom: 3px;
            margin-bottom: 10px;
            color: #0f172a;
        }

        .template-classic_kenyan .entry-item {
            margin-bottom: 12px;
            page-break-inside: avoid;
        }

        .template-classic_kenyan .entry-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-weight: bold;
            font-size: 0.96rem;
        }

        .template-classic_kenyan .entry-sub {
            font-style: italic;
            font-size: 0.9rem;
            color: #334155;
            margin-bottom: 4px;
        }

        .template-classic_kenyan .bullet-list {
            font-size: 0.92rem;
            padding-left: 20px;
            margin-top: 4px;
        }

        .template-classic_kenyan .bullet-list li {
            margin-bottom: 3px;
        }

        /* ========================================================= */
        /* TEMPLATE 2: MODERN CBC SKILLS-BASED                      */
        /* ========================================================= */
        .template-modern_cbc {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #1e293b;
        }

        .template-modern_cbc .cv-header {
            background: #f8fafc;
            border-left: 5px solid #0f766e;
            padding: 18px 24px;
            margin-bottom: 22px;
            border-radius: 4px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }

        .template-modern_cbc .header-text-block {
            flex: 1;
        }

        .template-modern_cbc .cv-name {
            font-size: 1.75rem;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .template-modern_cbc .cv-tagline {
            font-size: 0.95rem;
            color: #0f766e;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .template-modern_cbc .contact-meta-grid {
            font-size: 0.85rem;
            display: flex;
            flex-wrap: wrap;
            gap: 8px 16px;
            color: #475569;
        }

        .template-modern_cbc .section-title {
            font-size: 0.95rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: #0f766e;
            display: flex;
            align-items: center;
            gap: 8px;
            border-bottom: 2px solid #ccfbf1;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }

        .template-modern_cbc .entry-item {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .template-modern_cbc .entry-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 700;
            font-size: 0.94rem;
            color: #0f172a;
        }

        .template-modern_cbc .entry-sub {
            font-size: 0.86rem;
            color: #0f766e;
            font-weight: 600;
            margin-bottom: 4px;
        }

        .template-modern_cbc .bullet-list {
            font-size: 0.88rem;
            padding-left: 18px;
            color: #334155;
            line-height: 1.55;
        }

        /* ========================================================= */
        /* TEMPLATE 3: INTERNATIONAL PLACEMENT                      */
        /* ========================================================= */
        .template-international {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            color: #0f172a;
        }

        .template-international .cv-header {
            border-bottom: 2px solid #0f766e;
            padding-bottom: 18px;
            margin-bottom: 22px;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
        }

        .template-international .header-text-block {
            flex: 1;
        }

        .template-international .cv-name {
            font-size: 1.85rem;
            font-weight: 800;
            letter-spacing: -0.5px;
            color: #0f172a;
            margin-bottom: 4px;
        }

        .template-international .cv-tagline {
            font-size: 0.96rem;
            color: #0f766e;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .template-international .contact-meta-grid {
            font-size: 0.86rem;
            display: flex;
            flex-wrap: wrap;
            gap: 10px 18px;
            color: #475569;
        }

        .template-international .section-title {
            font-size: 0.92rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #0f172a;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 4px;
            margin-bottom: 12px;
        }

        .template-international .entry-item {
            margin-bottom: 14px;
            page-break-inside: avoid;
        }

        .template-international .entry-header {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            font-weight: 700;
            font-size: 0.94rem;
        }

        .template-international .entry-sub {
            font-size: 0.86rem;
            color: #475569;
            margin-bottom: 4px;
        }

        .template-international .bullet-list {
            font-size: 0.88rem;
            padding-left: 18px;
            color: #334155;
            line-height: 1.55;
        }

        /* Photo styling - Completely hidden with zero gap if not enabled */
        .cv-photo-frame {
            width: 100px;
            height: 120px;
            border-radius: 4px;
            object-fit: cover;
            border: 1px solid #cbd5e1;
            flex-shrink: 0;
        }

        /* Two-column referee layout */
        .referee-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 15px;
            page-break-inside: avoid;
        }

        .referee-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 10px 14px;
            border-radius: 4px;
            font-size: 0.88rem;
            line-height: 1.45;
        }

        /* ========================================================= */
        /* PRINT MEDIA STYLES                                        */
        /* ========================================================= */
        @media print {
            body {
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .cv-action-bar {
                display: none !important;
            }

            .cv-paper-container {
                box-shadow: none !important;
                border-radius: 0 !important;
                padding: 20mm 15mm !important;
                margin: 0 !important;
                max-width: 100% !important;
                min-height: auto !important;
                width: 100% !important;
            }

            a {
                text-decoration: none !important;
                color: inherit !important;
            }

            .cv-section,
            .entry-item,
            .referee-grid {
                page-break-inside: avoid !important;
            }
        }
    </style>
</head>

<body>

    <!-- Action Bar (Hidden in Print) -->
    <header class="cv-action-bar">
        <div style="display: flex; align-items: center; gap: 12px;">
            <?php if ($isTeacher): ?>
                <a href="/teacher/cv-builder" class="btn-action btn-secondary">
                    <i class="fa fa-arrow-left"></i> Return to Editor
                </a>
            <?php else: ?>
                <a href="/school/applicants" class="btn-action btn-secondary">
                    <i class="fa fa-arrow-left"></i> Return to Applicants
                </a>
            <?php endif; ?>
        </div>

        <div style="display: flex; gap: 10px; align-items: center;">
            <button onclick="window.print()" class="btn-action btn-secondary" title="Send directly to printer">
                <i class="fa fa-print"></i> Print CV
            </button>

            <div class="save-dropdown">
                <button type="button" class="btn-action btn-print" onclick="toggleSaveDropdown(event)">
                    <i class="fa fa-download"></i> Save CV <i class="fa fa-chevron-down"
                        style="font-size: 0.72rem; margin-left: 4px;"></i>
                </button>
                <div id="saveDropdownMenu" class="save-dropdown-menu">
                    <button type="button" onclick="exportAsPDF()">
                        <i class="fa fa-file-pdf" style="color: #ef4444; font-size: 1rem; width: 20px;"></i>
                        <div>
                            <div>Save as PDF (.pdf)</div>
                        </div>
                    </button>
                    <button type="button" onclick="exportAsWordDoc()">
                        <i class="fa fa-file-word" style="color: #2563eb; font-size: 1rem; width: 20px;"></i>
                        <div>
                            <div>Save as Word Document (.doc / .docx)</div>
                        </div>
                    </button>
                </div>
            </div>
        </div>
    </header>

    <!-- Printable Paper Body -->
    <main class="cv-paper-container template-<?= h($template) ?>">

        <!-- CV Header -->
        <header class="cv-header">
            <!-- Left Text Content -->
            <div class="header-text-block">
                <h1 class="cv-name"><?= h($fullName) ?></h1>
                <div class="cv-tagline">
                    <?= h($subjDisplay ? $subjDisplay . ' Educator' : 'Professional Educator') ?>
                    <?php if (!empty($teacher->qualification)): ?>
                        &bull; <?= h($teacher->qualification) ?>
                    <?php endif; ?>
                </div>

                <div class="contact-meta-grid">
                    <?php if (!empty($phone)): ?>
                        <span><i class="fa fa-phone" style="font-size: 0.8rem; color: #0f766e;"></i> <?= h($phone) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($email)): ?>
                        <span><i class="fa fa-envelope" style="font-size: 0.8rem; color: #0f766e;"></i>
                            <?= h($email) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($county)): ?>
                        <span><i class="fa fa-map-marker-alt" style="font-size: 0.8rem; color: #0f766e;"></i>
                            <?= h($county) ?>, Kenya</span>
                    <?php endif; ?>
                    <?php if (!empty($tscNumber)): ?>
                        <span style="font-weight: bold; color: #0f766e;">
                            <i class="fa fa-id-card"></i> TSC: <?= h($tscNumber) ?>
                        </span>
                    <?php endif; ?>
                    <?php if (!empty($idNumber)): ?>
                        <span>ID/Pass: <?= h($idNumber) ?></span>
                    <?php endif; ?>
                    <?php if ($isVerified): ?>
                        <span style="color: #166534; font-weight: bold;">
                            <i class="fa fa-shield-check"></i> Verified Educator
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Optional Profile Photo (Seamlessly excluded with no gaps if turned off) -->
            <?php
            $photoSrc = '';
            if ($includePhoto) {
                if (!empty($_POST['data_photo_base64'])) {
                    $photoSrc = $_POST['data_photo_base64'];
                } elseif (!empty($_POST['existing_photo'])) {
                    $clean = ltrim(str_replace('\\', '/', $_POST['existing_photo']), '/');
                    $photoSrc = '/' . $clean;
                } elseif (!empty($photoPath)) {
                    $clean = ltrim(str_replace('\\', '/', $photoPath), '/');
                    $photoSrc = '/' . $clean;
                }
            }
            ?>
            <?php if (!empty($photoSrc)): ?>
                <img src="<?= $photoSrc ?>" alt="<?= h($fullName) ?>" class="cv-photo-frame">
            <?php endif; ?>
        </header>

        <!-- Professional Summary -->
        <?php if (!empty($summary)): ?>
            <section class="cv-section">
                <h2 class="section-title">
                    <?= ($template === 'modern_cbc') ? '<i class="fa fa-user"></i> ' : '' ?>Professional Profile
                </h2>
                <p style="font-size: 0.92rem; line-height: 1.6; text-align: justify;">
                    <?= nl2br(h($summary)) ?>
                </p>
            </section>
        <?php endif; ?>

        <!-- Teaching & Professional Experience -->
        <?php if (!empty($experience)): ?>
            <section class="cv-section">
                <h2 class="section-title">
                    <?= ($template === 'modern_cbc') ? '<i class="fa fa-briefcase"></i> ' : '' ?>Teaching Experience
                </h2>
                <?php foreach ($experience as $exp): ?>
                    <?php if (empty($exp['school']) && empty($exp['role']))
                        continue; ?>
                    <article class="entry-item">
                        <div class="entry-header">
                            <span><?= h($exp['role'] ?: 'Subject Teacher') ?></span>
                            <span
                                style="font-weight: normal; font-size: 0.88rem; color: #475569;"><?= h($exp['dates'] ?? '') ?></span>
                        </div>
                        <div class="entry-sub">
                            <?= h($exp['school'] ?? '') ?>        <?= !empty($exp['county']) ? ' &bull; ' . h($exp['county']) : '' ?>
                        </div>
                        <?php if (!empty($exp['responsibilities'])): ?>
                            <ul class="bullet-list">
                                <?php
                                $lines = preg_split('/[\r\n]+/', $exp['responsibilities']);
                                foreach ($lines as $line):
                                    $line = trim(preg_replace('/^[-*•\d.]+\s*/', '', $line));
                                    if (empty($line))
                                        continue;
                                    ?>
                                    <li><?= h($line) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <!-- Academic Credentials & Qualifications -->
        <?php if (!empty($education)): ?>
            <section class="cv-section">
                <h2 class="section-title">
                    <?= ($template === 'modern_cbc') ? '<i class="fa fa-graduation-cap"></i> ' : '' ?>Academic Background &
                    Education
                </h2>
                <?php foreach ($education as $edu): ?>
                    <?php if (empty($edu['institution']) && empty($edu['award']))
                        continue; ?>
                    <article class="entry-item">
                        <div class="entry-header">
                            <span><?= h($edu['award'] ?? '') ?></span>
                            <span
                                style="font-weight: normal; font-size: 0.88rem; color: #475569;"><?= h($edu['year'] ?? '') ?></span>
                        </div>
                        <div class="entry-sub">
                            <?= h($edu['institution'] ?? '') ?>        <?= !empty($edu['grade']) ? ' &bull; ' . h($edu['grade']) : '' ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>

        <!-- Professional Trainings, CBC & CPD -->
        <?php if (!empty($trainings)): ?>
            <section class="cv-section">
                <h2 class="section-title">
                    <?= ($template === 'modern_cbc') ? '<i class="fa fa-certificate"></i> ' : '' ?>Professional Trainings &
                    CPD
                </h2>
                <ul class="bullet-list">
                    <?php foreach ($trainings as $tr): ?>
                        <?php if (empty($tr['name']))
                            continue; ?>
                        <li>
                            <strong><?= h($tr['name']) ?></strong>
                            <?= !empty($tr['provider']) ? ' &ndash; ' . h($tr['provider']) : '' ?>
                            <?= !empty($tr['year']) ? ' (' . h($tr['year']) . ')' : '' ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <!-- Co-Curricular & Responsibilities -->
        <?php if (!empty($cocurricular)): ?>
            <section class="cv-section">
                <h2 class="section-title">
                    <?= ($template === 'modern_cbc') ? '<i class="fa fa-people-group"></i> ' : '' ?>Co-Curricular &
                    Leadership Roles
                </h2>
                <ul class="bullet-list">
                    <?php foreach ($cocurricular as $cc): ?>
                        <?php if (empty($cc['activity']))
                            continue; ?>
                        <li>
                            <strong><?= h($cc['activity']) ?></strong>:
                            <?= h($cc['details'] ?? 'Active engagement and mentorship.') ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <!-- Referees -->
        <?php if (!empty($referees)): ?>
            <section class="cv-section">
                <h2 class="section-title">
                    <?= ($template === 'modern_cbc') ? '<i class="fa fa-address-book"></i> ' : '' ?>Professional Referees
                </h2>
                <div class="referee-grid">
                    <?php foreach ($referees as $ref): ?>
                        <?php if (empty($ref['name'])) continue; ?>
                        <?php
                        $isEndorsedRef = false;
                        if (!empty($ref['name']) && !empty($ref['institution']) && !empty($teacher->id)) {
                            $endCheck = R::findOne('refereeendorsement', 'teacher_id = ? AND referee_name = ? AND institution = ? AND status = ?', [
                                $teacher->id,
                                $ref['name'],
                                $ref['institution'],
                                'endorsed'
                            ]);
                            $isEndorsedRef = !empty($endCheck);
                        }
                        ?>
                        <div class="referee-box">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 6px;">
                                <strong style="color: #0f172a; display: block; font-size: 0.94rem;"><?= h($ref['name']) ?></strong>
                                <?php if ($isEndorsedRef): ?>
                                    <span style="background: #dcfce7; color: #166534; font-size: 0.7rem; font-weight: 700; padding: 1px 6px; border-radius: 4px; border: 1px solid #86efac; white-space: nowrap;">
                                        <i class="fa fa-check-circle"></i> Endorsed ✓
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div style="color: #0f766e; font-weight: 600; font-size: 0.84rem;">
                                <?= h($ref['title'] ?? 'Educator / Administrator') ?></div>
                            <div style="color: #475569; font-size: 0.84rem;"><?= h($ref['institution'] ?? '') ?></div>
                            <?php if (!empty($ref['phone'])): ?>
                                <div style="font-size: 0.82rem; margin-top: 3px;"><i class="fa fa-phone"></i>
                                    <?= h($ref['phone']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($ref['email'])): ?>
                                <div style="font-size: 0.82rem;"><i class="fa fa-envelope"></i> <?= h($ref['email']) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

    </main>

    <script>
        function toggleSaveDropdown(e) {
            e.stopPropagation();
            const menu = document.getElementById('saveDropdownMenu');
            if (menu) {
                menu.style.display = (menu.style.display === 'block') ? 'none' : 'block';
            }
        }

        document.addEventListener('click', function () {
            const menu = document.getElementById('saveDropdownMenu');
            if (menu) {
                menu.style.display = 'none';
            }
        });

        function exportAsPDF() {
            const element = document.querySelector('.cv-paper-container');
            if (!element) return;

            const teacherName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $fullName) ?: 'Educator') ?>;
            const opt = {
                margin: [10, 10, 10, 10],
                filename: `${teacherName}_CV.pdf`,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, useCORS: true, logging: false },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            };

            if (typeof html2pdf !== 'undefined') {
                html2pdf().set(opt).from(element).save().catch(err => {
                    console.error('PDF export fallback:', err);
                    window.print();
                });
            } else {
                window.print();
            }
        }

        function convertImgSrcToBase64(url) {
            return new Promise((resolve) => {
                const img = new Image();
                img.crossOrigin = 'Anonymous';
                img.onload = function() {
                    try {
                        const canvas = document.createElement('canvas');
                        canvas.width = this.naturalWidth || this.width || 120;
                        canvas.height = this.naturalHeight || this.height || 140;
                        const ctx = canvas.getContext('2d');
                        ctx.drawImage(this, 0, 0);
                        resolve(canvas.toDataURL('image/jpeg', 0.95));
                    } catch(e) {
                        resolve(null);
                    }
                };
                img.onerror = function() {
                    resolve(null);
                };
                img.src = url;
            });
        }

        async function exportAsWordDoc() {
            const paper = document.querySelector('.cv-paper-container');
            if (!paper) return;

            const clone = paper.cloneNode(true);

            // Convert images in the cloned document into Base64 so MS Word can render them locally!
            const images = clone.querySelectorAll('img');
            for (let img of images) {
                if (img.src && !img.src.startsWith('data:')) {
                    try {
                        const base64 = await convertImgSrcToBase64(img.src);
                        if (base64) {
                            img.src = base64;
                        }
                    } catch(e) {
                        console.warn('Word export image convert error:', e);
                    }
                }
            }

            const docContent = `
<html xmlns:o='urn:schemas-microsoft-com:office:office' 
      xmlns:w='urn:schemas-microsoft-com:office:word' 
      xmlns='http://www.w3.org/TR/REC-html40'>
<head>
    <meta charset='utf-8'>
    <title><?= addslashes(h($fullName)) ?> - Curriculum Vitae</title>
    <!--[if gte mso 9]>
    <xml>
        <w:WordDocument>
            <w:View>Print</w:View>
            <w:Zoom>100</w:Zoom>
            <w:DoNotOptimizeForBrowser/>
        </w:WordDocument>
    </xml>
    <![endif]-->
    <style>
        @page {
            size: 21.0cm 29.7cm;
            margin: 2cm 2cm 2cm 2cm;
            mso-page-orientation: portrait;
        }
        body {
            font-family: 'Times New Roman', Times, Georgia, serif;
            font-size: 11pt;
            color: #111827;
            line-height: 1.4;
        }
        h1.cv-name {
            font-size: 18pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0 0 4pt 0;
            color: #0f172a;
        }
        .cv-tagline {
            font-size: 11pt;
            font-style: italic;
            color: #334155;
            margin-bottom: 6pt;
        }
        .contact-meta-grid {
            font-size: 10pt;
            color: #1e293b;
            margin-bottom: 12pt;
        }
        .contact-meta-grid span {
            margin-right: 12pt;
            display: inline-block;
        }
        h2.section-title {
            font-size: 12pt;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1.5pt solid #334155;
            padding-bottom: 2pt;
            margin-top: 14pt;
            margin-bottom: 6pt;
            color: #0f172a;
        }
        .entry-item {
            margin-bottom: 8pt;
        }
        .entry-header {
            font-weight: bold;
            font-size: 10.5pt;
        }
        .entry-sub {
            font-style: italic;
            font-size: 10pt;
            color: #334155;
        }
        ul.bullet-list {
            margin-top: 4pt;
            margin-bottom: 6pt;
            padding-left: 18pt;
        }
        ul.bullet-list li {
            font-size: 10.5pt;
            margin-bottom: 2pt;
        }
        .referee-grid {
            display: table;
            width: 100%;
        }
        .referee-box {
            border: 1pt solid #e2e8f0;
            padding: 8pt;
            margin-bottom: 6pt;
            font-size: 10pt;
        }
        .cv-photo-frame {
            width: 100px;
            height: 120px;
            float: right;
            margin-left: 14pt;
            border: 1pt solid #cbd5e1;
        }
    </style>
</head>
<body>
    ${clone.innerHTML}
</body>
</html>`;

            const blob = new Blob(['\ufeff', docContent], {
                type: 'application/msword;charset=utf-8'
            });

            const teacherName = <?= json_encode(preg_replace('/[^a-zA-Z0-9_\-]/', '_', $fullName) ?: 'Educator') ?>;
            const filename = `${teacherName}_CV.doc`;

            const downloadLink = document.createElement('a');
            downloadLink.href = URL.createObjectURL(blob);
            downloadLink.download = filename;
            document.body.appendChild(downloadLink);
            downloadLink.click();
            document.body.removeChild(downloadLink);
            URL.revokeObjectURL(downloadLink.href);
        }
    </script>
</body>
</html>