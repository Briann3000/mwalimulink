<?php
// views/job_view.php - Public Single Vacancy Overview with Google Jobs Schema.org & Direct Conversion Flow
$jobId = intval($_GET['id'] ?? 0);
if ($jobId <= 0) {
    header("Location: /jobs");
    exit();
}

$job = R::load('job', $jobId);
if (!$job || !$job->id || ($job->aggregation_status === 'archived' || $job->aggregation_status === 'rejected')) {
    http_response_code(404);
    echo '<div class="container" style="max-width: 650px; margin: 3rem auto; text-align: center; padding: 1.5rem;">
        <article style="background: white; padding: 2.5rem 2rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="width: 56px; height: 56px; background: #fef2f2; color: #dc2626; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                <i class="fa fa-circle-exclamation"></i>
            </div>
            <h2 style="color: #0f172a; margin-top: 0; font-size: 1.35rem; font-weight: 800;">Vacancy Not Found or Expired</h2>
            <p style="color: #64748b; font-size: 0.92rem; line-height: 1.5; margin-bottom: 1.5rem;">
                This teaching opportunity is no longer accepting applications or has been closed. You can explore active vacancies across Kenya below.
            </p>
            <a href="/jobs" class="btn-primary" style="background: #0f766e; color: white !important; font-weight: 700; padding: 10px 22px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa fa-briefcase"></i> Explore Active Vacancies
            </a>
        </article>
    </div>';
    return;
}

$authUser = auth_user();
$isLoggedInTeacher = ($authUser && ($authUser['role'] ?? '') === 'teacher');
$teacher_id = $isLoggedInTeacher ? $authUser['user_id'] : null;

// Check if teacher has applied
$existingApplication = null;
if ($isLoggedInTeacher && $teacher_id) {
    $existingApplication = R::findOne('applications', 'teacher_id = ? AND job_id = ?', [$teacher_id, $job->id]);
}

$isExternal = ($job->source_type === 'external');
$school = !$isExternal ? R::load('school', $job->school_id) : null;
$institutionName = $isExternal ? ($job->company_name ?: ($job->source_name ?: 'Education Partner')) : ($school->name ?? 'Registered School');
$institutionLocation = $isExternal ? ($job->location_text ?: 'Kenya') : ($school->county ?? 'Kenya');

// Check if matched to public or private school directory
$schoolProfileUrl = null;
if (!$isExternal && $school && $school->id) {
    $schoolProfileUrl = '/schools/public/detail?id=' . $school->id;
} elseif (!empty($institutionName)) {
    $foundPub = R::findOne('public_school', 'name LIKE ? LIMIT 1', ['%' . trim($institutionName) . '%']);
    if ($foundPub) {
        $schoolProfileUrl = '/schools/public/detail?id=' . $foundPub->id;
    } else {
        $foundPriv = R::findOne('private_school', 'name LIKE ? LIMIT 1', ['%' . trim($institutionName) . '%']);
        if ($foundPriv) {
            $schoolProfileUrl = '/schools/private/detail?id=' . $foundPriv->id;
        }
    }
}

// Deadline calculations
$isDeadlinePassed = false;
$daysRemaining = null;
$deadlineFormatted = null;
if (!empty($job->deadline)) {
    $deadlineDate = new DateTime($job->deadline);
    $today = new DateTime('today');
    $deadlineFormatted = $deadlineDate->format('M d, Y');
    if ($deadlineDate < $today) {
        $isDeadlinePassed = true;
    } else {
        $daysRemaining = $today->diff($deadlineDate)->days;
    }
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$currentUrl = $scheme . ($_SERVER['HTTP_HOST'] ?? 'mwalimu.info') . '/jobs/view?id=' . $job->id;

// Related vacancies in same county or matching subject
$relatedJobs = R::find('job', "id != ? AND ((source_type = 'external' AND aggregation_status = 'published') OR source_type = 'direct' OR source_type IS NULL) ORDER BY id DESC LIMIT 3", [$job->id]);

// Build Google-compliant Schema.org JobPosting JSON-LD
$fullDesc = '';
if (!empty($job->description)) {
    $fullDesc .= '<p>' . nl2br(h($job->description)) . '</p>';
}
if (!empty($job->requirements)) {
    $fullDesc .= '<h3>Requirements & Qualifications</h3><p>' . nl2br(h($job->requirements)) . '</p>';
}
if (empty($fullDesc)) {
    $fullDesc = '<p>' . h($job->title) . ' vacancy at ' . h($institutionName) . ' in ' . h($institutionLocation) . '. Apply on MwalimuLink.</p>';
}

$schemaData = [
    '@context' => 'https://schema.org/',
    '@type' => 'JobPosting',
    'title' => $job->title,
    'description' => $fullDesc,
    'identifier' => [
        '@type' => 'PropertyValue',
        'name' => 'MwalimuLink',
        'value' => (string)$job->id
    ],
    'datePosted' => !empty($job->posted_date) ? date('c', strtotime($job->posted_date)) : date('c'),
    'validThrough' => (!empty($job->deadline) && !$isDeadlinePassed) ? date('c', strtotime($job->deadline)) : date('c', strtotime('+30 days')),
    'employmentType' => 'FULL_TIME',
    'hiringOrganization' => [
        '@type' => 'Organization',
        'name' => $institutionName,
        'sameAs' => $schoolProfileUrl ? ($scheme . ($_SERVER['HTTP_HOST'] ?? 'mwalimu.info') . $schoolProfileUrl) : $currentUrl,
        'logo' => $scheme . ($_SERVER['HTTP_HOST'] ?? 'mwalimu.info') . '/mwalimu-link-logo-clean.png'
    ],
    'jobLocation' => [
        '@type' => 'Place',
        'address' => [
            '@type' => 'PostalAddress',
            'addressLocality' => $institutionLocation,
            'addressCountry' => 'KE'
        ]
    ],
    'applicantLocationRequirements' => [
        '@type' => 'Country',
        'name' => 'Kenya'
    ],
    'directApply' => true
];

if (!empty($job->salary)) {
    $numericSalary = preg_replace('/[^0-9]/', '', (string)$job->salary);
    if (!empty($numericSalary) && is_numeric($numericSalary) && intval($numericSalary) > 1000) {
        $schemaData['baseSalary'] = [
            '@type' => 'MonetaryAmount',
            'currency' => 'KES',
            'value' => [
                '@type' => 'QuantitativeValue',
                'value' => (float)$numericSalary,
                'unitText' => 'MONTH'
            ]
        ];
    }
}

// Format WhatsApp message for viral sharing
$waMessage = "🇰🇪 TEACHING VACANCY: " . $job->title . "\n"
    . "🏫 Institution: " . $institutionName . "\n"
    . "📍 Location: " . $institutionLocation . "\n"
    . "⏳ Deadline: " . ($deadlineFormatted ?: 'Open') . "\n"
    . "👉 View details & apply: " . $currentUrl;
?>

<!-- Google Jobs Schema.org JSON-LD Structured Data -->
<script type="application/ld+json">
<?= json_encode($schemaData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?>
</script>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 1.5rem 1rem;">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" style="margin-bottom: 1.25rem; font-size: 0.84rem;">
        <ol style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center; color: #64748b; flex-wrap: wrap;">
            <li><a href="/" style="color: #0f766e; text-decoration: none;"><i class="fa fa-home"></i> Home</a></li>
            <li><span style="color: #cbd5e1;">/</span></li>
            <li><a href="/jobs" style="color: #0f766e; text-decoration: none;">Teaching Vacancies</a></li>
            <li><span style="color: #cbd5e1;">/</span></li>
            <li style="color: #0f172a; font-weight: 700; max-width: 380px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                <?= h($job->title) ?>
            </li>
        </ol>
    </nav>

    <!-- Header Banner Card -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 1rem;">
            <div style="flex: 1; min-width: 280px;">
                
                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 8px;">
                    <?php if ($isExternal): ?>
                        <span style="background: #f1f5f9; color: #475569; font-size: 0.74rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-share-nodes"></i> Aggregated from <?= h($job->source_name ?: 'Partner') ?>
                        </span>
                    <?php else: ?>
                        <span style="background: #ecfdf5; color: #047857; font-size: 0.74rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; border: 1px solid #a7f3d0; display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-certificate"></i> Verified School Direct Posting
                        </span>
                    <?php endif; ?>

                    <?php if (!empty($job->curriculum)): ?>
                        <span style="background: #eef2ff; color: #4338ca; font-size: 0.74rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; border: 1px solid #c7d2fe;">
                            <?= h($job->curriculum) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <h1 style="margin: 0 0 10px; font-size: 1.75rem; font-weight: 900; color: #0f172a; line-height: 1.3;">
                    <?= h($job->title) ?>
                </h1>

                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap; font-size: 0.95rem; color: #64748b;">
                    <?php if ($schoolProfileUrl): ?>
                        <a href="<?= $schoolProfileUrl ?>" title="View school profile in directory" style="color: #0f766e; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-school"></i> <?= h($institutionName) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.75rem;"></i>
                        </a>
                    <?php else: ?>
                        <span style="font-weight: 700; color: #1e293b; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-school" style="color: #0f766e;"></i> <?= h($institutionName) ?>
                        </span>
                    <?php endif; ?>
                    &bull;
                    <span style="display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($institutionLocation) ?>
                    </span>
                    <?php if (!empty($job->posted_date)): ?>
                        &bull;
                        <span style="display: inline-flex; align-items: center; gap: 4px;">
                            <i class="fa fa-calendar-alt"></i> Posted <?= date('M d, Y', strtotime($job->posted_date)) ?>
                        </span>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Header Quick Badge -->
            <div>
                <?php if ($isDeadlinePassed): ?>
                    <div style="background: #fee2e2; border: 1px solid #fecaca; color: #991b1b; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-lock"></i> Application Closed on <?= $deadlineFormatted ?>
                    </div>
                <?php elseif ($daysRemaining !== null): ?>
                    <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1d4ed8; padding: 8px 16px; border-radius: 8px; font-size: 0.85rem; font-weight: 700; display: flex; align-items: center; gap: 6px;">
                        <i class="fa fa-clock"></i> Closes in <?= $daysRemaining ?> <?= ($daysRemaining === 1) ? 'day' : 'days' ?> (<?= $deadlineFormatted ?>)
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Main Content & Application Sidebar Layout -->
    <div style="display: grid; grid-template-columns: 2fr 1.15fr; gap: 1.75rem; align-items: start;">
        
        <!-- Left: Full Job Details Pane -->
        <div>
            
            <!-- Quick Specifications Strip -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 1rem;">
                <div>
                    <span style="display: block; font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Location</span>
                    <strong style="color: #0f172a; font-size: 0.92rem;"><?= h($institutionLocation) ?></strong>
                </div>
                <div>
                    <span style="display: block; font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Curriculum</span>
                    <strong style="color: #0f172a; font-size: 0.92rem;"><?= h($job->curriculum ?: 'Kenyan / Standard') ?></strong>
                </div>
                <div>
                    <span style="display: block; font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Opportunity</span>
                    <strong style="color: #0f172a; font-size: 0.92rem;"><?= h($job->opportunity_type ?: 'Teaching Vacancy') ?></strong>
                </div>
                <div>
                    <span style="display: block; font-size: 0.72rem; font-weight: 800; color: #94a3b8; text-transform: uppercase;">Compensation</span>
                    <strong style="color: #0f172a; font-size: 0.92rem;"><?= !empty($job->salary) ? h($job->salary) : 'Competitive / Standard Scale' ?></strong>
                </div>
            </div>

            <!-- Job Description Card -->
            <article style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                
                <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                    <i class="fa fa-file-lines" style="color: #0f766e; margin-right: 6px;"></i> Role Overview & Description
                </h2>

                <div style="color: #334155; line-height: 1.7; font-size: 0.95rem;">
                    <?php if (!empty($job->description)): ?>
                        <?= nl2br(h($job->description)) ?>
                    <?php else: ?>
                        <p style="color: #64748b;">
                            <?= h($institutionName) ?> is seeking a qualified, passionate educator to fill the position of <strong><?= h($job->title) ?></strong>. Interested candidates should review the requirements below.
                        </p>
                    <?php endif; ?>
                </div>

                <?php if (!empty($job->requirements)): ?>
                    <h2 style="font-size: 1.25rem; font-weight: 800; color: #0f172a; margin-top: 2rem; margin-bottom: 1rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px;">
                        <i class="fa fa-list-check" style="color: #0f766e; margin-right: 6px;"></i> Qualifications & Requirements
                    </h2>
                    <div style="color: #334155; line-height: 1.7; font-size: 0.95rem;">
                        <?= nl2br(h($job->requirements)) ?>
                    </div>
                <?php endif; ?>

            </article>

            <!-- Hiring Institution Dossier Card -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem 2rem; margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-top: 0; margin-bottom: 0.75rem;">
                    <i class="fa fa-school" style="color: #0f766e; margin-right: 6px;"></i> About <?= h($institutionName) ?>
                </h3>
                <p style="color: #64748b; font-size: 0.9rem; line-height: 1.6; margin: 0 0 1rem;">
                    <?= h($institutionName) ?> is located in <strong><?= h($institutionLocation) ?></strong>. Learn more about the institution, student capacities, and academic facilities in our comprehensive school directory.
                </p>
                <?php if ($schoolProfileUrl): ?>
                    <a href="<?= $schoolProfileUrl ?>" style="background: #f0fdfa; color: #0f766e !important; border: 1px solid #99f6e4; font-size: 0.86rem; font-weight: 700; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        <i class="fa fa-folder-open"></i> View Full Institution Dossier & Analytics
                    </a>
                <?php endif; ?>
            </div>

            <!-- Related Vacancies -->
            <?php if (!empty($relatedJobs)): ?>
                <div style="margin-top: 2rem;">
                    <h3 style="font-size: 1.15rem; font-weight: 800; color: #0f172a; margin-bottom: 1rem;">
                        Similar Teaching Opportunities
                    </h3>
                    <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                        <?php foreach ($relatedJobs as $rj): ?>
                            <?php
                            $rjName = !empty($rj->company_name) ? $rj->company_name : 'Education Partner';
                            $rjLoc = !empty($rj->location_text) ? $rj->location_text : 'Kenya';
                            ?>
                            <a href="/jobs/view?id=<?= $rj->id ?>" style="display: flex; justify-content: space-between; align-items: center; background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; text-decoration: none; transition: border-color 0.2s ease;">
                                <div>
                                    <h4 style="margin: 0 0 4px; font-size: 0.98rem; font-weight: 800; color: #0f172a;"><?= h($rj->title) ?></h4>
                                    <span style="font-size: 0.82rem; color: #64748b;"><?= h($rjName) ?> &bull; <?= h($rjLoc) ?></span>
                                </div>
                                <span style="font-size: 0.82rem; color: #0f766e; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                    View <i class="fa fa-chevron-right" style="font-size: 0.7rem;"></i>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

        </div>

        <!-- Right: Sticky Application Action Sidebar -->
        <div style="position: sticky; top: 85px;">
            
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.75rem; margin-bottom: 1.5rem; box-shadow: 0 4px 12px rgba(15,23,42,0.04);">
                
                <h3 style="margin: 0 0 1rem; font-size: 1.15rem; font-weight: 800; color: #0f172a;">
                    Application Portal
                </h3>

                <?php if ($existingApplication): ?>
                    <div style="background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; text-align: center;">
                        <i class="fa fa-circle-check" style="color: #047857; font-size: 1.8rem; margin-bottom: 6px;"></i>
                        <h4 style="margin: 0 0 4px; font-size: 0.98rem; font-weight: 800; color: #065f46;">Application Submitted</h4>
                        <p style="margin: 0; font-size: 0.82rem; color: #047857;">
                            You applied for this vacancy on <?= date('M d, Y', strtotime($existingApplication->application_date)) ?>.
                        </p>
                        <a href="/teacher/applications" style="display: inline-block; margin-top: 10px; font-size: 0.82rem; font-weight: 700; color: #047857; text-decoration: underline;">
                            View in My Applications &rarr;
                        </a>
                    </div>
                <?php elseif ($isDeadlinePassed): ?>
                    <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 1rem; margin-bottom: 1.25rem; text-align: center;">
                        <i class="fa fa-lock" style="color: #dc2626; font-size: 1.5rem; margin-bottom: 6px;"></i>
                        <h4 style="margin: 0 0 4px; font-size: 0.95rem; font-weight: 800; color: #991b1b;">Applications Closed</h4>
                        <p style="margin: 0; font-size: 0.82rem; color: #b91c1c;">
                            The deadline for this position passed on <?= $deadlineFormatted ?>.
                        </p>
                    </div>
                <?php else: ?>

                    <!-- Active Application Choices -->
                    <div style="display: flex; flex-direction: column; gap: 0.85rem; margin-bottom: 1.25rem;">
                        
                        <?php if ($isExternal && !empty($job->source_url)): ?>
                            <a href="<?= h($job->source_url) ?>" target="_blank" rel="noopener noreferrer"
                                style="background: #0f766e; color: white !important; font-size: 0.92rem; font-weight: 800; padding: 12px 18px; border-radius: 8px; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 2px 6px rgba(15,118,110,0.3);">
                                <i class="fa fa-external-link-alt"></i> Apply on Partner Portal
                            </a>
                        <?php endif; ?>

                        <?php if ($isLoggedInTeacher): ?>
                            <a href="/teacher/apply?job_id=<?= $job->id ?>"
                                style="background: <?= ($isExternal && !empty($job->source_url)) ? '#f0fdfa' : '#0f766e' ?>; color: <?= ($isExternal && !empty($job->source_url)) ? '#0f766e' : 'white' ?> !important; border: 1px solid <?= ($isExternal && !empty($job->source_url)) ? '#0f766e' : 'transparent' ?>; font-size: 0.92rem; font-weight: 800; padding: 12px 18px; border-radius: 8px; text-decoration: none; text-align: center; display: flex; align-items: center; justify-content: center; gap: 8px;">
                                <i class="fa fa-paper-plane"></i> Apply via MwalimuLink Profile
                            </a>
                        <?php else: ?>
                            <!-- Guest Conversion Trigger Box -->
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.15rem; text-align: left;">
                                <div style="font-size: 0.82rem; font-weight: 800; color: #0f172a; text-transform: uppercase; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                                    <i class="fa fa-graduation-cap" style="color: #0f766e;"></i> Direct Educator Application
                                </div>
                                <p style="margin: 0 0 10px; font-size: 0.84rem; color: #64748b; line-height: 1.5;">
                                    Apply with your verified TSC registration and MwalimuLink digital CV package.
                                </p>
                                <div style="display: flex; flex-direction: column; gap: 8px;">
                                    <a href="/login/teacher?redirect=<?= urlencode('/teacher/apply?job_id=' . $job->id) ?>"
                                        style="background: #0f766e; color: white !important; font-size: 0.86rem; font-weight: 700; padding: 9px 14px; border-radius: 6px; text-decoration: none; text-align: center;">
                                        Educator Sign In to Apply
                                    </a>
                                    <a href="/register/teacher"
                                        style="background: white; color: #0f766e !important; border: 1px solid #0f766e; font-size: 0.86rem; font-weight: 700; padding: 8px 14px; border-radius: 6px; text-decoration: none; text-align: center;">
                                        Create Free Teacher Profile
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>

                    </div>

                <?php endif; ?>

                <!-- Vacancy Summary Meta -->
                <div style="border-top: 1px solid #f1f5f9; padding-top: 1rem; font-size: 0.84rem; color: #64748b;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Deadline:</span>
                        <strong style="color: #0f172a;"><?= $deadlineFormatted ?: 'Not specified' ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                        <span>Region:</span>
                        <strong style="color: #0f172a;"><?= h($institutionLocation) ?></strong>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>Job Reference:</span>
                        <strong style="color: #0f172a;">#ML-<?= $job->id ?></strong>
                    </div>
                </div>

            </div>

            <!-- Social Share Card -->
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem;">
                <span style="font-size: 0.78rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 10px;">
                    Share Vacancy With Colleagues
                </span>
                <div style="display: flex; gap: 8px;">
                    <a href="https://api.whatsapp.com/send?text=<?= urlencode($waMessage) ?>" target="_blank" rel="noopener noreferrer"
                        style="flex: 1; background: #25d366; color: white !important; font-size: 0.84rem; font-weight: 700; padding: 8px 12px; border-radius: 6px; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fa-brands fa-whatsapp"></i> WhatsApp
                    </a>
                    <a href="https://twitter.com/intent/tweet?text=<?= urlencode('Teaching Vacancy: ' . $job->title . ' at ' . $institutionName . ' via @MwalimuLink') ?>&url=<?= urlencode($currentUrl) ?>" target="_blank" rel="noopener noreferrer"
                        style="background: #0f172a; color: white !important; font-size: 0.84rem; font-weight: 700; padding: 8px 14px; border-radius: 6px; text-decoration: none; text-align: center; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                        <i class="fa-brands fa-x-twitter"></i>
                    </a>
                    <button type="button" onclick="navigator.clipboard.writeText('<?= $currentUrl ?>'); this.innerText = 'Copied!'; setTimeout(() => this.innerHTML = '<i class=\'fa fa-copy\'></i>', 2000);"
                        style="background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; font-size: 0.84rem; font-weight: 700; padding: 8px 12px; border-radius: 6px; cursor: pointer;">
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
            </div>

            <!-- Fraud / Job Seeking Safety Warning -->
            <div style="background: #fffbeb; border: 1px solid #fef3c7; border-radius: 12px; padding: 1.25rem; font-size: 0.82rem; color: #92400e; line-height: 1.5;">
                <div style="font-weight: 800; margin-bottom: 4px; display: flex; align-items: center; gap: 6px;">
                    <i class="fa fa-shield-alt" style="color: #d97706;"></i> Safe Job Seeking Advisory
                </div>
                MwalimuLink does not charge application fees, verification charges, or interview payments. Never send money or sensitive banking details to any recruiter.
            </div>

        </div>

    </div>

</div>
