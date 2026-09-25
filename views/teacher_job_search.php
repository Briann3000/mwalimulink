<?php
// views/teacher_job_search.php - Unified Teaching Vacancy & Job Discovery Engine
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];

// Get teacher's existing applications indexed by job_id
$myApps = R::find('applications', 'teacher_id = ?', [$teacher_id]);
$appsByJob = [];
foreach ($myApps as $app) {
    $appsByJob[$app->job_id] = $app;
}

// Search, sorting & pagination inputs
$keyword = trim($_GET['q'] ?? '');
$countyFilter = trim($_GET['county'] ?? '');
$curriculumFilter = trim($_GET['curriculum'] ?? '');
$sourceFilter = trim($_GET['source'] ?? ''); // 'direct' | 'external' | ''
$sortBy = trim($_GET['sort'] ?? 'kenya_first'); // 'kenya_first' | 'newest' | 'closing_soon' | 'title_asc'
$perPage = max(5, min(100, intval($_GET['per_page'] ?? 10)));
$page = max(1, intval($_GET['page'] ?? 1));

$query = "((source_type = 'external' AND aggregation_status = 'published') OR source_type = 'direct' OR source_type IS NULL)";
$params = [];

if (!empty($keyword)) {
    $query .= " AND (title LIKE ? OR description LIKE ? OR requirements LIKE ? OR company_name LIKE ?)";
    $kw = "%{$keyword}%";
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
    $params[] = $kw;
}

if (!empty($curriculumFilter)) {
    $query .= " AND curriculum = ?";
    $params[] = $curriculumFilter;
}

if ($sourceFilter === 'direct') {
    $query .= " AND (source_type = 'direct' OR source_type IS NULL)";
} elseif ($sourceFilter === 'external') {
    $query .= " AND source_type = 'external'";
}

$rawJobs = R::find('job', "$query", $params);

// Filter by county / location if specified
$allJobs = [];
foreach ($rawJobs as $job) {
    if (!empty($countyFilter)) {
        $matched = false;
        if ($job->source_type === 'external') {
            if (stripos($job->location_text ?? '', $countyFilter) !== false || stripos($job->description ?? '', $countyFilter) !== false) {
                $matched = true;
            }
        } else {
            $school = R::load('school', $job->school_id);
            if ($school && stripos($school->county ?? '', $countyFilter) !== false) {
                $matched = true;
            }
        }
        if (!$matched) {
            continue;
        }
    }
    $allJobs[] = $job;
}

// Smart Sorting Engine (Kenyan First by Default)
$isKenyanJob = function($job) {
    if ($job->source_type === 'direct' || empty($job->source_type)) {
        return true;
    }
    $loc = strtolower($job->location_text ?? '');
    $src = strtolower($job->source_name ?? '');
    if (strpos($loc, 'kenya') !== false || strpos($loc, 'nairobi') !== false || strpos($loc, 'mombasa') !== false || 
        strpos($loc, 'kisumu') !== false || strpos($loc, 'nakuru') !== false || strpos($loc, 'eldoret') !== false ||
        strpos($loc, 'kiambu') !== false || strpos($loc, 'machakos') !== false || strpos($src, 'myjobmag') !== false ||
        strpos($src, 'brighter') !== false) {
        return true;
    }
    return false;
};

usort($allJobs, function($a, $b) use ($sortBy, $isKenyanJob) {
    if ($sortBy === 'kenya_first') {
        $aKe = $isKenyanJob($a) ? 1 : 0;
        $bKe = $isKenyanJob($b) ? 1 : 0;
        if ($aKe !== $bKe) {
            return $bKe - $aKe; // Kenyan jobs first
        }
        // Then by newest
        $aDate = strtotime($a->posted_date ?? 'now');
        $bDate = strtotime($b->posted_date ?? 'now');
        return $bDate - $aDate;
    } elseif ($sortBy === 'closing_soon') {
        $aDeadline = !empty($a->deadline) ? strtotime($a->deadline) : 9999999999;
        $bDeadline = !empty($b->deadline) ? strtotime($b->deadline) : 9999999999;
        return $aDeadline - $bDeadline;
    } elseif ($sortBy === 'title_asc') {
        return strcasecmp($a->title ?? '', $b->title ?? '');
    } else { // 'newest'
        $aDate = strtotime($a->posted_date ?? 'now');
        $bDate = strtotime($b->posted_date ?? 'now');
        return $bDate - $aDate;
    }
});

// Pagination Calculations
$totalJobs = count($allJobs);
$totalPages = max(1, ceil($totalJobs / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;
$pagedJobs = array_slice($allJobs, $offset, $perPage);

// Helper for pagination query string
$buildQueryUrl = function($newPage = null, $newPerPage = null, $newSort = null) use ($keyword, $countyFilter, $curriculumFilter, $sourceFilter, $sortBy, $perPage, $page) {
    $params = [
        'q' => $keyword,
        'county' => $countyFilter,
        'curriculum' => $curriculumFilter,
        'source' => $sourceFilter,
        'sort' => $newSort ?? $sortBy,
        'per_page' => $newPerPage ?? $perPage,
        'page' => $newPage ?? $page
    ];
    return '/teacher/jobs?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
};
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="padding: 1.5rem !important; background: #f8fafc; min-height: 100vh;">
        
        <!-- Header Strip -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem; background: white; padding: 1.25rem 1.5rem; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <div>
                <h2 style="margin: 0; font-size: 1.35rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-briefcase" style="color: #0f766e;"></i> Teaching Job Vacancies
                </h2>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                    Discover active teaching opportunities from verified Kenyan institutions and leading educational job boards.
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center;">
                <a href="/teacher/applications"
                    style="background: #0f766e; color: white !important; font-size: 0.84rem; font-weight: 700; padding: 8px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                    <i class="fa fa-list-check"></i> My Applications (<?= count($appsByJob) ?>)
                </a>
                <a href="/teacher/dashboard"
                    style="background: #f1f5f9; color: #334155; font-size: 0.84rem; font-weight: 600; padding: 8px 14px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #e2e8f0;">
                    <i class="fa fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Filter & Search Toolbar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <form method="GET" action="/teacher/jobs" style="margin: 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
                    <div style="grid-column: span 2; min-width: 260px;">
                        <label style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Keywords</label>
                        <input type="text" name="q" value="<?= h($keyword) ?>"
                            placeholder="e.g. Mathematics, CBC Grade 7, Chemistry, ECD..."
                            style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0; box-sizing: border-box;">
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">County</label>
                        <select name="county" style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                            <option value="">All Counties</option>
                            <?php foreach (kenyan_counties() as $c): ?>
                                <option value="<?= h($c) ?>" <?= ($countyFilter === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Curriculum</label>
                        <select name="curriculum" style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                            <option value="">All Curriculums</option>
                            <option value="CBC" <?= ($curriculumFilter === 'CBC') ? 'selected' : '' ?>>CBC</option>
                            <option value="IGCSE" <?= ($curriculumFilter === 'IGCSE') ? 'selected' : '' ?>>IGCSE / Cambridge</option>
                            <option value="IB" <?= ($curriculumFilter === 'IB') ? 'selected' : '' ?>>IB (International)</option>
                            <option value="8-4-4" <?= ($curriculumFilter === '8-4-4') ? 'selected' : '' ?>>8-4-4</option>
                        </select>
                    </div>
                    <div>
                        <label style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Source</label>
                        <select name="source" style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                            <option value="">All Sources</option>
                            <option value="direct" <?= ($sourceFilter === 'direct') ? 'selected' : '' ?>>MwalimuLink Schools</option>
                            <option value="external" <?= ($sourceFilter === 'external') ? 'selected' : '' ?>>Online Aggregated</option>
                        </select>
                    </div>
                </div>

                <!-- Controls Strip: Sorting, Per-Page, and Actions -->
                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #475569;"><i class="fa fa-arrow-down-wide-short" style="color: #0f766e;"></i> Sort By:</label>
                            <select name="sort" onchange="this.form.submit()" style="height: 36px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #0f172a; background: #ffffff;">
                                <option value="kenya_first" <?= ($sortBy === 'kenya_first') ? 'selected' : '' ?>>🇰🇪 Kenyan First (Default)</option>
                                <option value="newest" <?= ($sortBy === 'newest') ? 'selected' : '' ?>>🕒 Newest Posted</option>
                                <option value="closing_soon" <?= ($sortBy === 'closing_soon') ? 'selected' : '' ?>>⏳ Closing Soonest</option>
                                <option value="title_asc" <?= ($sortBy === 'title_asc') ? 'selected' : '' ?>>🔤 Job Title (A–Z)</option>
                            </select>
                        </div>
                        <div style="display: flex; align-items: center; gap: 6px;">
                            <label style="font-size: 0.8rem; font-weight: 700; color: #475569;">Show:</label>
                            <select name="per_page" onchange="this.form.submit()" style="height: 36px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #0f172a; background: #ffffff;">
                                <option value="10" <?= ($perPage === 10) ? 'selected' : '' ?>>10 / page</option>
                                <option value="25" <?= ($perPage === 25) ? 'selected' : '' ?>>25 / page</option>
                                <option value="50" <?= ($perPage === 50) ? 'selected' : '' ?>>50 / page</option>
                                <option value="100" <?= ($perPage === 100) ? 'selected' : '' ?>>100 / page</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: flex; gap: 8px; align-items: center;">
                        <button type="submit" class="btn-primary" style="height: 36px; padding: 0 18px; font-size: 0.84rem; font-weight: 700; margin: 0; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Apply Filters
                        </button>
                        <?php if (!empty($keyword) || !empty($countyFilter) || !empty($curriculumFilter) || !empty($sourceFilter) || $sortBy !== 'kenya_first' || $perPage !== 10): ?>
                            <a href="/teacher/jobs" style="height: 36px; padding: 0 12px; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                                Reset
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Vacancy Feed Count Summary -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; color: #64748b; font-size: 0.85rem;">
            <div>
                Showing <strong><?= $totalJobs > 0 ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalJobs) ?></strong> of <strong><?= $totalJobs ?></strong> teaching vacancies
            </div>
            <div>
                Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
            </div>
        </div>

        <?php if (empty($pagedJobs)): ?>
            <div style="background: white; border: 1px dashed #cbd5e1; border-radius: 12px; text-align: center; padding: 3.5rem 1.5rem; margin-bottom: 2rem;">
                <div style="width: 56px; height: 56px; background: #f0fdfa; color: #0f766e; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                    <i class="fa fa-briefcase"></i>
                </div>
                <h3 style="color: #0f172a; margin: 0 0 6px; font-size: 1.15rem; font-weight: 800;">No Vacancies Found Matching Your Search</h3>
                <p style="color: #64748b; font-size: 0.88rem; margin: 0 auto 1.5rem; max-width: 480px;">
                    Try adjusting your filters, searching for alternate subject terms, or reset to explore all available listings across Kenya.
                </p>
                <a href="/teacher/jobs" style="display: inline-block; background: #0f766e; color: white; padding: 8px 18px; border-radius: 6px; font-size: 0.85rem; font-weight: 700; text-decoration: none;">
                    View All Active Vacancies
                </a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                <?php foreach ($pagedJobs as $job): ?>
                    <?php
                    $isExternal = ($job->source_type === 'external');
                    $school = !$isExternal ? R::load('school', $job->school_id) : null;
                    $app = $appsByJob[$job->id] ?? null;

                    $institutionName = $isExternal ? ($job->company_name ?: 'Education Partner') : ($school->name ?? 'Registered School');
                    $institutionLocation = $isExternal ? ($job->location_text ?: 'Kenya') : ($school->county ?? 'Kenya');

                    // Check if matched to internal public/private school for clickable dossier
                    $schoolProfileUrl = null;
                    if (!$isExternal && $school && $school->id) {
                        $schoolProfileUrl = '/schools/public/detail?id=' . $school->id;
                    } elseif (!empty($institutionName)) {
                        // Quick lookup in public/private directory
                        $foundSchool = R::findOne('public_school', 'name LIKE ? LIMIT 1', ['%' . trim($institutionName) . '%']);
                        if ($foundSchool) {
                            $schoolProfileUrl = '/schools/public/detail?id=' . $foundSchool->id;
                        }
                    }

                    $isExpired = false;
                    $deadlineBadge = '';
                    if (!empty($job->deadline)) {
                        $deadlineDate = new DateTime($job->deadline);
                        $today = new DateTime('today');
                        if ($deadlineDate < $today) {
                            $isExpired = true;
                            $deadlineBadge = '<span style="background: #fee2e2; color: #991b1b; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-lock"></i> Closed</span>';
                        } else {
                            $diff = $today->diff($deadlineDate)->days;
                            $text = ($diff === 0) ? 'Today' : ($diff === 1 ? 'Tomorrow' : "{$diff} days left");
                            $deadlineBadge = '<span style="background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-clock"></i> Closes ' . $text . '</span>';
                        }
                    }

                    $initials = strtoupper(substr(trim($institutionName), 0, 2));
                    $isKe = $isKenyanJob($job);
                    ?>
                    <div style="background: white; border: 1px solid <?= $app ? '#93c5fd' : ($isExpired ? '#f1f5f9' : '#e2e8f0') ?>; border-radius: 12px; padding: 1.35rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s ease; opacity: <?= $isExpired && !$app ? '0.75' : '1' ?>;">
                        <div style="display: flex; gap: 1rem; align-items: flex-start;">
                            
                            <!-- School Avatar Icon -->
                            <div style="width: 46px; height: 46px; border-radius: 10px; background: <?= $isKe ? '#f0fdfa' : '#f8fafc' ?>; color: <?= $isKe ? '#0f766e' : '#475569' ?>; border: 1px solid <?= $isKe ? '#ccfbf1' : '#e2e8f0' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 0.95rem; flex-shrink: 0;">
                                <?= h($initials) ?>
                            </div>

                            <div style="flex: 1; min-width: 0;">
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                    <div>
                                        <h3 style="margin: 0 0 4px; font-size: 1.15rem; font-weight: 800; color: #0f172a; line-height: 1.3;">
                                            <?php if ($isExpired && !$app): ?>
                                                <span style="color: #64748b;"><?= h($job->title) ?></span>
                                            <?php else: ?>
                                                <a href="/teacher/apply?job_id=<?= $job->id ?>" style="color: #0f172a; text-decoration: none;"><?= h($job->title) ?></a>
                                            <?php endif; ?>
                                        </h3>

                                        <!-- Clickable School / Organization Link -->
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.85rem; color: #64748b;">
                                            <?php if ($schoolProfileUrl): ?>
                                                <a href="<?= $schoolProfileUrl ?>" title="View official school profile dossier" style="color: #0f766e; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-school"></i> <?= h($institutionName) ?> <i class="fa fa-arrow-up-right-from-square" style="font-size: 0.7rem;"></i>
                                                </a>
                                            <?php else: ?>
                                                <span style="font-weight: 700; color: #334155; display: inline-flex; align-items: center; gap: 4px;">
                                                    <i class="fa fa-school" style="color: #0f766e;"></i> <?= h($institutionName) ?>
                                                </span>
                                            <?php endif; ?>
                                            &bull;
                                            <span style="display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($institutionLocation) ?>
                                            </span>
                                        </div>
                                    </div>

                                    <!-- Status & Source Badges Strip -->
                                    <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                        <?php if ($isKe): ?>
                                            <span style="background: #ecfdf5; color: #047857; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 6px; border: 1px solid #a7f3d0; display: inline-flex; align-items: center; gap: 4px;">
                                                🇰🇪 Kenya
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #f1f5f9; color: #475569; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="fa fa-globe"></i> International
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($isExternal): ?>
                                            <span style="background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 6px;">
                                                Via <?= h($job->source_name) ?>
                                            </span>
                                        <?php else: ?>
                                            <span style="background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 6px;">
                                                <i class="fa fa-shield-halved"></i> Direct School
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!empty($job->curriculum)): ?>
                                            <span style="background: #e0f2fe; color: #0369a1; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 6px;">
                                                <?= h($job->curriculum) ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($app): ?>
                                            <?php
                                            $st = $app->status ?: 'applied';
                                            $pillMap = [
                                                'applied' => ['text' => 'Applied ✓', 'bg' => '#e0f2fe', 'color' => '#0369a1'],
                                                'reviewing' => ['text' => 'Under Review 👁️', 'bg' => '#fef9c3', 'color' => '#854d0e'],
                                                'shortlisted' => ['text' => 'Shortlisted ⭐', 'bg' => '#dcfce7', 'color' => '#15803d'],
                                                'interview_scheduled' => ['text' => 'Interview Invited 📅', 'bg' => '#ede9fe', 'color' => '#6d28d9'],
                                                'hired' => ['text' => 'Hired 🎉', 'bg' => '#bbf7d0', 'color' => '#166534'],
                                                'rejected' => ['text' => 'Not Selected', 'bg' => '#fee2e2', 'color' => '#991b1b']
                                            ];
                                            $pill = $pillMap[$st] ?? $pillMap['applied'];
                                            ?>
                                            <span style="background: <?= $pill['bg'] ?>; color: <?= $pill['color'] ?>; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 6px;">
                                                <?= $pill['text'] ?>
                                            </span>
                                        <?php endif; ?>
                                        <?= $deadlineBadge ?>
                                    </div>
                                </div>

                                <p style="color: #475569; font-size: 0.87rem; line-height: 1.55; margin: 0 0 10px;">
                                    <?= nl2br(h(mb_substr($job->description ?? '', 0, 240))) ?><?= strlen($job->description ?? '') > 240 ? '...' : '' ?>
                                </p>

                                <?php
                                $hasRealRequirements = !empty($job->requirements) && !preg_match('/refer to (the full|original|listing)|check listing on/i', $job->requirements);
                                ?>
                                <?php if ($hasRealRequirements): ?>
                                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 8px 12px; margin-bottom: 12px;">
                                        <span style="font-size: 0.72rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 3px;">Requirements Preview:</span>
                                        <p style="margin: 0; font-size: 0.8rem; color: #475569; line-height: 1.4;">
                                            <?= h(mb_substr($job->requirements, 0, 180)) ?><?= strlen($job->requirements) > 180 ? '...' : '' ?>
                                        </p>
                                    </div>
                                <?php endif; ?>

                                <!-- Footer Strip with Action Buttons -->
                                <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 10px; flex-wrap: wrap; gap: 8px;">
                                    <div style="display: flex; align-items: center; gap: 10px;">
                                        <span style="font-size: 0.78rem; color: #94a3b8;">
                                            <i class="fa fa-clock"></i> Posted <?= date('M d, Y', strtotime($job->posted_date ?? 'now')) ?>
                                        </span>
                                        <?php if (!empty($job->salary)): ?>
                                            <span style="background: #dcfce7; color: #166534; font-size: 0.78rem; font-weight: 700; padding: 2px 8px; border-radius: 4px;">
                                                KES <?= number_format($job->salary) ?> / mo
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div style="display: flex; gap: 8px; align-items: center;">
                                        <?php if ($app): ?>
                                            <a href="/teacher/applications"
                                                style="background: #f0fdf4; color: #166534 !important; border: 1px solid #bbf7d0; font-size: 0.82rem; font-weight: 700; padding: 6px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                                <i class="fa fa-eye"></i> View Application &rarr;
                                            </a>
                                        <?php elseif ($isExpired): ?>
                                            <button disabled
                                                style="background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; font-size: 0.82rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; cursor: not-allowed;">
                                                <i class="fa fa-lock"></i> Applications Closed
                                            </button>
                                        <?php else: ?>
                                            <a href="/teacher/apply?job_id=<?= $job->id ?>"
                                                style="background: #0f766e; color: white !important; font-size: 0.84rem; font-weight: 700; padding: 7px 18px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                                                Easy Apply via MwalimuLink &rarr;
                                            </a>
                                            <?php if ($isExternal && !empty($job->source_url)): ?>
                                                <a href="<?= h($job->source_url) ?>" target="_blank" rel="noopener noreferrer"
                                                    title="View original portal listing"
                                                    style="background: #f8fafc; color: #64748b; border: 1px solid #cbd5e1; font-size: 0.8rem; font-weight: 600; padding: 7px 10px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center;">
                                                    <i class="fa fa-arrow-up-right-from-square"></i>
                                                </a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Responsive Pagination Toolbar -->
            <?php if ($totalPages > 1): ?>
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); margin-bottom: 2rem;">
                    <div style="font-size: 0.85rem; color: #64748b;">
                        Showing page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong> (<?= $totalJobs ?> total)
                    </div>

                    <div style="display: flex; gap: 4px; align-items: center;">
                        <!-- Previous Page -->
                        <?php if ($page > 1): ?>
                            <a href="<?= $buildQueryUrl($page - 1) ?>" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                &laquo; Prev
                            </a>
                        <?php endif; ?>

                        <!-- Page Numbers Window -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);
                        if ($startPage > 1) {
                            echo '<a href="' . $buildQueryUrl(1) . '" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; text-decoration: none;">1</a>';
                            if ($startPage > 2) echo '<span style="padding: 0 4px; color: #94a3b8;">...</span>';
                        }
                        for ($p = $startPage; $p <= $endPage; $p++):
                            $isActivePage = ($p === $page);
                        ?>
                            <a href="<?= $buildQueryUrl($p) ?>" style="padding: 6px 12px; background: <?= $isActivePage ? '#0f766e' : '#f8fafc' ?>; border: 1px solid <?= $isActivePage ? '#0f766e' : '#cbd5e1' ?>; border-radius: 6px; color: <?= $isActivePage ? '#ffffff' : '#334155' ?>; font-size: 0.84rem; font-weight: <?= $isActivePage ? '700' : '600' ?>; text-decoration: none;">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                        <?php
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) echo '<span style="padding: 0 4px; color: #94a3b8;">...</span>';
                            echo '<a href="' . $buildQueryUrl($totalPages) . '" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; text-decoration: none;">' . $totalPages . '</a>';
                        }
                        ?>

                        <!-- Next Page -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?= $buildQueryUrl($page + 1) ?>" style="padding: 6px 12px; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.84rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                Next &raquo;
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</div>