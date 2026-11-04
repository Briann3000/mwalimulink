<?php
// views/jobs_search.php - Public Teaching Vacancies & Opportunities Directory
$authUser = auth_user();
$isLoggedInTeacher = ($authUser && ($authUser['role'] ?? '') === 'teacher');
$teacher_id = $isLoggedInTeacher ? $authUser['user_id'] : null;

$mySubjectTokens = [];
$appsByJob = [];

if ($isLoggedInTeacher && $teacher_id) {
    $teacher = R::load('teacher', $teacher_id);
    $mySubjectsRaw = (string)($teacher->teaching_subjects ?? '');
    if (!empty($mySubjectsRaw)) {
        $rawToks = preg_split('/[\/,\+;&|]|\band\b/i', $mySubjectsRaw);
        foreach ($rawToks as $tok) {
            $tok = trim($tok);
            if (strlen($tok) >= 2) {
                $mySubjectTokens[] = $tok;
            }
        }
        $mySubjectTokens = array_values(array_unique($mySubjectTokens));
    }

    $myApps = R::find('applications', 'teacher_id = ?', [$teacher_id]);
    foreach ($myApps as $app) {
        $appsByJob[$app->job_id] = $app;
    }
}

// Popular quick search chips for guests
$popularKeywords = ['Mathematics', 'English', 'Science', 'CBC Junior School', 'Kiswahili', 'Biology', 'Chemistry', 'Physics', 'Special Needs'];

// Search, sorting & pagination inputs
$keyword = trim($_GET['q'] ?? '');
$countyFilter = trim($_GET['county'] ?? '');
$curriculumFilter = trim($_GET['curriculum'] ?? '');
$sourceFilter = trim($_GET['source'] ?? ''); // 'direct' | 'external' | ''
$sortBy = trim($_GET['sort'] ?? 'kenya_first'); // 'kenya_first' | 'newest' | 'closing_soon' | 'title_asc'
$perPage = max(5, min(100, intval($_GET['per_page'] ?? 10)));
$page = max(1, intval($_GET['page'] ?? 1));

$query = "((source_type = 'external' AND aggregation_status = 'published') OR source_type = 'direct' OR source_type IS NULL OR source_type = '')";
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
    $query .= " AND (source_type = 'direct' OR source_type IS NULL OR source_type = '')";
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
        strpos($src, 'brighter') !== false || strpos($src, 'jobwebkenya') !== false || strpos($src, 'careerpoint') !== false) {
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
    return '/jobs?' . http_build_query(array_filter($params, fn($v) => $v !== '' && $v !== null));
};
?>

<div class="container" style="max-width: 1200px; margin: 0 auto; padding: 1.5rem 1rem;">

    <!-- Breadcrumb Trail -->
    <nav aria-label="breadcrumb" style="margin-bottom: 1rem; font-size: 0.84rem;">
        <ol style="display: flex; gap: 8px; list-style: none; padding: 0; margin: 0; align-items: center; color: #64748b;">
            <li><a href="/" style="color: #0f766e; text-decoration: none;"><i class="fa fa-home"></i> Home</a></li>
            <li><span style="color: #cbd5e1;">/</span></li>
            <li style="color: #0f172a; font-weight: 700;">Teaching Vacancies</li>
        </ol>
    </nav>

    <!-- Hero Header Strip -->
    <div style="background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: white; padding: 2rem 2rem; border-radius: 14px; margin-bottom: 1.75rem; border: 1px solid #334155; box-shadow: 0 4px 12px rgba(15,23,42,0.08);">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1.25rem;">
            <div style="max-width: 720px;">
                <div style="display: inline-flex; align-items: center; gap: 6px; background: rgba(45,212,191,0.15); color: #2dd4bf; border: 1px solid rgba(45,212,191,0.3); padding: 4px 10px; border-radius: 20px; font-size: 0.76rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px;">
                    <i class="fa fa-briefcase"></i> Active Vacancy Directory
                </div>
                <h1 style="color: white; margin: 0 0 8px; font-size: 1.85rem; font-weight: 900; line-height: 1.25;">
                    Teaching Job Vacancies in Kenya
                </h1>
                <p style="color: #94a3b8; margin: 0; font-size: 0.95rem; line-height: 1.5;">
                    Discover verified teaching positions from Kenyan public and private schools, CBC junior secondary schools, international academies, and leading educational portals.
                </p>
            </div>
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <?php if ($isLoggedInTeacher): ?>
                    <a href="/teacher/applications" style="background: #0f766e; color: white !important; font-size: 0.86rem; font-weight: 700; padding: 10px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 4px rgba(15,118,110,0.3);">
                        <i class="fa fa-list-check"></i> My Applications (<?= count($appsByJob) ?>)
                    </a>
                    <a href="/teacher/dashboard" style="background: rgba(255,255,255,0.1); color: white !important; font-size: 0.86rem; font-weight: 600; padding: 10px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fa fa-user-circle"></i> Educator Portal
                    </a>
                <?php else: ?>
                    <a href="/register/teacher" style="background: #0f766e; color: white !important; font-size: 0.86rem; font-weight: 700; padding: 10px 18px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 2px 6px rgba(15,118,110,0.35);">
                        <i class="fa fa-user-plus"></i> Register as Teacher (Free)
                    </a>
                    <a href="/login/teacher?redirect=<?= urlencode('/jobs') ?>" style="background: rgba(255,255,255,0.1); color: white !important; font-size: 0.86rem; font-weight: 600; padding: 10px 16px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(255,255,255,0.2);">
                        <i class="fa fa-sign-in-alt"></i> Teacher Login
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 1.25rem 1.5rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
        <form method="GET" action="/jobs" style="margin: 0;">
            
            <!-- Quick Chips Strip -->
            <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #f1f5f9;">
                <span style="font-size: 0.78rem; font-weight: 700; color: #64748b;">
                    <?= !empty($mySubjectTokens) ? 'Your Specialization Shortcuts:' : 'Popular Searches:' ?>
                </span>
                <?php 
                $chips = !empty($mySubjectTokens) ? $mySubjectTokens : $popularKeywords;
                foreach ($chips as $chip): 
                ?>
                    <a href="/jobs?q=<?= urlencode($chip) ?>" style="background: <?= ($keyword === $chip) ? '#0f766e' : '#f0fdf4' ?>; color: <?= ($keyword === $chip) ? '#ffffff' : '#166534' ?>; border: 1px solid #bbf7d0; font-size: 0.76rem; font-weight: 700; padding: 4px 12px; border-radius: 20px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                        <i class="fa fa-tag" style="font-size: 0.68rem;"></i> <?= h($chip) ?>
                    </a>
                <?php endforeach; ?>

                <?php if (!empty($keyword) || !empty($countyFilter) || !empty($curriculumFilter) || !empty($sourceFilter)): ?>
                    <a href="/jobs" style="font-size: 0.76rem; color: #dc2626; font-weight: 600; text-decoration: none; margin-left: auto;">
                        <i class="fa fa-rotate-left"></i> Reset Filters
                    </a>
                <?php endif; ?>
            </div>

            <!-- Inputs Grid -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px;">
                <div style="grid-column: span 2; min-width: 260px;">
                    <label style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Keywords or Subject</label>
                    <input type="text" name="q" value="<?= h($keyword) ?>"
                        placeholder="e.g. Mathematics, CBC Grade 7, Chemistry, P1, Kiswahili..."
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
                    <label style="font-size: 0.72rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Vacancy Source</label>
                    <select name="source" style="width: 100%; height: 40px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                        <option value="">All Verified Sources</option>
                        <option value="direct" <?= ($sourceFilter === 'direct') ? 'selected' : '' ?>>MwalimuLink Partner Schools</option>
                        <option value="external" <?= ($sourceFilter === 'external') ? 'selected' : '' ?>>Online Aggregated Portals</option>
                    </select>
                </div>
            </div>

            <!-- Sorting & Action Strip -->
            <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 12px; flex-wrap: wrap; gap: 10px;">
                <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <div style="display: flex; align-items: center; gap: 6px;">
                        <label style="font-size: 0.8rem; font-weight: 700; color: #475569;"><i class="fa fa-arrow-down-wide-short" style="color: #0f766e;"></i> Sort By:</label>
                        <select name="sort" onchange="this.form.submit()" style="height: 36px; padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; font-weight: 600; color: #0f172a; background: #ffffff;">
                            <option value="kenya_first" <?= ($sortBy === 'kenya_first') ? 'selected' : '' ?>>🇰🇪 Kenyan Opportunities First</option>
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
                    <button type="submit" class="btn-primary" style="height: 36px; padding: 0 18px; font-size: 0.84rem; font-weight: 700; margin: 0; display: inline-flex; align-items: center; gap: 6px; background: #0f766e; color: white; border: none; border-radius: 6px; cursor: pointer;">
                        <i class="fa fa-filter"></i> Search Vacancies
                    </button>
                    <?php if (!empty($keyword) || !empty($countyFilter) || !empty($curriculumFilter) || !empty($sourceFilter) || $sortBy !== 'kenya_first' || $perPage !== 10): ?>
                        <a href="/jobs" style="height: 36px; padding: 0 12px; background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; border-radius: 6px; font-size: 0.82rem; font-weight: 600; text-decoration: none; display: inline-flex; align-items: center;">
                            Reset
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <!-- Vacancy Feed Count Summary -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; color: #64748b; font-size: 0.88rem;">
        <div>
            Showing <strong><?= $totalJobs > 0 ? ($offset + 1) : 0 ?>–<?= min($offset + $perPage, $totalJobs) ?></strong> of <strong><?= number_format($totalJobs) ?></strong> teaching vacancies
        </div>
        <div>
            Page <strong><?= $page ?></strong> of <strong><?= $totalPages ?></strong>
        </div>
    </div>

    <!-- Vacancy Cards Feed -->
    <?php if (empty($pagedJobs)): ?>
        <div style="background: white; border: 1px dashed #cbd5e1; border-radius: 12px; text-align: center; padding: 3.5rem 1.5rem; margin-bottom: 2rem;">
            <div style="width: 56px; height: 56px; background: #f0fdfa; color: #0f766e; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; font-size: 1.5rem; margin-bottom: 1rem;">
                <i class="fa fa-briefcase"></i>
            </div>
            <h3 style="color: #0f172a; margin: 0 0 6px; font-size: 1.2rem; font-weight: 800;">No Vacancies Found Matching Your Search</h3>
            <p style="color: #64748b; font-size: 0.9rem; margin: 0 auto 1.5rem; max-width: 480px;">
                Try searching for alternate subject terms, selecting "All Counties", or resetting your search to explore all available listings across Kenya.
            </p>
            <a href="/jobs" style="display: inline-block; background: #0f766e; color: white; padding: 9px 20px; border-radius: 6px; font-size: 0.88rem; font-weight: 700; text-decoration: none;">
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

                // Check if matched to internal public or private school for clickable dossier
                $schoolProfileUrl = null;
                if (!$isExternal && $school && $school->id) {
                    $schoolProfileUrl = '/schools/public/detail?id=' . $school->id;
                } elseif (!empty($institutionName)) {
                    $foundSchool = R::findOne('public_school', 'name LIKE ? LIMIT 1', ['%' . trim($institutionName) . '%']);
                    if ($foundSchool) {
                        $schoolProfileUrl = '/schools/public/detail?id=' . $foundSchool->id;
                    } else {
                        $foundPrivate = R::findOne('private_school', 'name LIKE ? LIMIT 1', ['%' . trim($institutionName) . '%']);
                        if ($foundPrivate) {
                            $schoolProfileUrl = '/schools/private/detail?id=' . $foundPrivate->id;
                        }
                    }
                }

                $isExpired = false;
                $deadlineBadge = '';
                if (!empty($job->deadline)) {
                    $deadlineDate = new DateTime($job->deadline);
                    $today = new DateTime('today');
                    if ($deadlineDate < $today) {
                        $isExpired = true;
                        $deadlineBadge = '<span style="background: #fee2e2; color: #991b1b; font-size: 0.74rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-lock"></i> Closed</span>';
                    } else {
                        $diff = $today->diff($deadlineDate)->days;
                        $text = ($diff === 0) ? 'Today' : ($diff === 1 ? 'Tomorrow' : "{$diff} days left");
                        $deadlineBadge = '<span style="background: #eff6ff; color: #1d4ed8; font-size: 0.74rem; font-weight: 700; padding: 3px 8px; border-radius: 12px; display: inline-flex; align-items: center; gap: 4px;"><i class="fa fa-clock"></i> Closes ' . $text . '</span>';
                    }
                }

                $initials = strtoupper(substr(trim($institutionName), 0, 2));
                $isKe = $isKenyanJob($job);

                // Requirements preview
                $rawDesc = strip_tags($job->requirements ?: ($job->description ?: ''));
                $descExcerpt = mb_substr(preg_replace('/\s+/', ' ', $rawDesc), 0, 180);
                if (mb_strlen($rawDesc) > 180) $descExcerpt .= '...';
                ?>
                <div style="background: white; border: 1px solid <?= $app ? '#93c5fd' : ($isExpired ? '#f1f5f9' : '#e2e8f0') ?>; border-radius: 12px; padding: 1.35rem 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); transition: all 0.2s ease; opacity: <?= $isExpired && !$app ? '0.75' : '1' ?>;">
                    <div style="display: flex; gap: 1.25rem; align-items: flex-start;">
                        
                        <!-- Institution Avatar Icon -->
                        <div style="width: 50px; height: 50px; border-radius: 10px; background: <?= $isKe ? '#f0fdfa' : '#f8fafc' ?>; color: <?= $isKe ? '#0f766e' : '#475569' ?>; border: 1px solid <?= $isKe ? '#ccfbf1' : '#e2e8f0' ?>; display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; flex-shrink: 0;">
                            <?= h($initials) ?>
                        </div>

                        <div style="flex: 1; min-width: 0;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                                <div>
                                    <h3 style="margin: 0 0 4px; font-size: 1.2rem; font-weight: 800; color: #0f172a; line-height: 1.3;">
                                        <a href="/jobs/view?id=<?= $job->id ?>" style="color: #0f172a; text-decoration: none;">
                                            <?= h($job->title) ?>
                                        </a>
                                    </h3>

                                    <!-- School / Institution Link & Location -->
                                    <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; font-size: 0.88rem; color: #64748b;">
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

                                <!-- Badges Strip -->
                                <div style="display: flex; align-items: center; gap: 6px; flex-wrap: wrap;">
                                    <?php if ($isKe): ?>
                                        <span style="background: #ecfdf5; color: #047857; font-size: 0.74rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; border: 1px solid #a7f3d0; display: inline-flex; align-items: center; gap: 4px;">
                                            🇰🇪 Kenya
                                        </span>
                                    <?php else: ?>
                                        <span style="background: #f1f5f9; color: #475569; font-size: 0.74rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; border: 1px solid #e2e8f0; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-globe"></i> International
                                        </span>
                                    <?php endif; ?>

                                    <?php if (!empty($job->curriculum)): ?>
                                        <span style="background: #eef2ff; color: #4338ca; font-size: 0.74rem; font-weight: 700; padding: 3px 8px; border-radius: 6px; border: 1px solid #c7d2fe;">
                                            <?= h($job->curriculum) ?>
                                        </span>
                                    <?php endif; ?>

                                    <?= $deadlineBadge ?>

                                    <?php if ($app): ?>
                                        <span style="background: #dbeafe; color: #1e40af; font-size: 0.74rem; font-weight: 800; padding: 3px 8px; border-radius: 6px; border: 1px solid #bfdbfe; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="fa fa-circle-check"></i> Applied
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Excerpt Description -->
                            <?php if (!empty($descExcerpt)): ?>
                                <p style="margin: 8px 0 12px; font-size: 0.88rem; color: #475569; line-height: 1.5;">
                                    <?= h($descExcerpt) ?>
                                </p>
                            <?php endif; ?>

                            <!-- Footer Actions & Metadata -->
                            <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; border-top: 1px solid #f8fafc; padding-top: 10px;">
                                <div style="font-size: 0.8rem; color: #94a3b8; display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                                    <?php if (!empty($job->posted_date)): ?>
                                        <span><i class="fa fa-calendar-alt"></i> Posted <?= date('M d, Y', strtotime($job->posted_date)) ?></span>
                                    <?php endif; ?>
                                    <?php if ($isExternal): ?>
                                        <span><i class="fa fa-share-nodes"></i> via <?= h($job->source_name ?: 'Partner Aggregator') ?></span>
                                    <?php else: ?>
                                        <span><i class="fa fa-certificate" style="color: #0f766e;"></i> Direct School Posting</span>
                                    <?php endif; ?>
                                </div>

                                <div style="display: flex; gap: 8px; align-items: center;">
                                    <?php if ($isExternal && !empty($job->source_url)): ?>
                                        <a href="<?= h($job->source_url) ?>" target="_blank" rel="noopener noreferrer"
                                            style="background: #f8fafc; color: #475569; border: 1px solid #cbd5e1; font-size: 0.82rem; font-weight: 600; padding: 6px 12px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            Original Portal <i class="fa fa-external-link-alt" style="font-size: 0.72rem;"></i>
                                        </a>
                                    <?php endif; ?>

                                    <a href="/jobs/view?id=<?= $job->id ?>"
                                        style="background: #0f766e; color: white !important; font-size: 0.84rem; font-weight: 700; padding: 7px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; box-shadow: 0 1px 3px rgba(15,118,110,0.2);">
                                        View Details & Apply <i class="fa fa-chevron-right" style="font-size: 0.72rem;"></i>
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination Bar -->
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 6px; margin: 2rem 0; flex-wrap: wrap;">
                <?php if ($page > 1): ?>
                    <a href="<?= $buildQueryUrl(1) ?>" style="padding: 6px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                        &laquo; First
                    </a>
                    <a href="<?= $buildQueryUrl($page - 1) ?>" style="padding: 6px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                        Prev
                    </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                for ($p = $startPage; $p <= $endPage; $p++):
                ?>
                    <a href="<?= $buildQueryUrl($p) ?>" style="padding: 6px 14px; background: <?= ($p === $page) ? '#0f766e' : 'white' ?>; border: 1px solid <?= ($p === $page) ? '#0f766e' : '#cbd5e1' ?>; border-radius: 6px; color: <?= ($p === $page) ? 'white' : '#334155' ?>; font-size: 0.85rem; text-decoration: none; font-weight: 700;">
                        <?= $p ?>
                    </a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="<?= $buildQueryUrl($page + 1) ?>" style="padding: 6px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                        Next
                    </a>
                    <a href="<?= $buildQueryUrl($totalPages) ?>" style="padding: 6px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; color: #334155; font-size: 0.85rem; text-decoration: none; font-weight: 600;">
                        Last &raquo;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php endif; ?>

    <!-- Visitor Conversion CTA Strip -->
    <div style="background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2rem; margin-top: 2rem; box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; align-items: center;">
            <div>
                <h3 style="margin: 0 0 6px; font-size: 1.25rem; font-weight: 800; color: #0f172a;">
                    Looking for teaching positions across Kenya?
                </h3>
                <p style="margin: 0; font-size: 0.9rem; color: #64748b; line-height: 1.5;">
                    Create a free MwalimuLink Teacher Profile. Get verified with your TSC credentials, build an instant CV, and apply to opportunities with 1 click.
                </p>
            </div>
            <div style="display: flex; gap: 10px; justify-content: flex-end; flex-wrap: wrap;">
                <a href="/register/teacher" style="background: #0f766e; color: white !important; font-size: 0.88rem; font-weight: 700; padding: 10px 20px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-user-plus"></i> Register Free Educator Profile
                </a>
                <a href="/login/school" style="background: #f1f5f9; color: #334155 !important; font-size: 0.88rem; font-weight: 600; padding: 10px 18px; border-radius: 8px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #cbd5e1;">
                    <i class="fa fa-school"></i> Post a School Vacancy
                </a>
            </div>
        </div>
    </div>

</div>
