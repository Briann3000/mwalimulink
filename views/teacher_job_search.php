<?php
require_auth('teacher');

$authUser = auth_user();
$teacher_id = $authUser['user_id'];

// Get teacher's existing applications indexed by job_id
$myApps = R::find('applications', 'teacher_id = ?', [$teacher_id]);
$appsByJob = [];
foreach ($myApps as $app) {
    $appsByJob[$app->job_id] = $app;
}

// Search & filter handling
$keyword = trim($_GET['q'] ?? '');
$countyFilter = trim($_GET['county'] ?? '');

$query = "1=1";
$params = [];

if (!empty($keyword)) {
    $query .= " AND (title LIKE ? OR description LIKE ? OR requirements LIKE ?)";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
    $params[] = "%$keyword%";
}

$jobs = R::find('job', "$query ORDER BY posted_date DESC", $params);

// Filter by school county if specified
if (!empty($countyFilter)) {
    $filteredJobs = [];
    foreach ($jobs as $job) {
        $school = R::load('school', $job->school_id);
        if ($school && stripos($school->county ?? '', $countyFilter) !== false) {
            $filteredJobs[] = $job;
        }
    }
    $jobs = $filteredJobs;
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
            <div>
                <h2 style="margin: 0; font-size: 1.4rem; color: #0f172a;">Teaching Job Vacancies</h2>
                <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                    Browse active teaching opportunities posted by verified institutions across Kenya.
                </p>
            </div>
            <div style="display: flex; gap: 10px;">
                <a href="/teacher/applications" style="background: #0f766e; color: white !important; font-size: 0.85rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-list-check"></i> My Applications (<?= count($appsByJob) ?>)
                </a>
                <a href="/teacher/dashboard" style="background: #e2e8f0; color: #334155; font-size: 0.85rem; font-weight: 600; padding: 8px 14px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Filter Search Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
            <form method="GET" action="/teacher/jobs" style="margin: 0; display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                <div style="flex: 2; min-width: 200px;">
                    <input type="text" name="q" value="<?= h($keyword) ?>" placeholder="Search job title, subject, or keywords..." style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; margin: 0; box-sizing: border-box;">
                </div>
                <div style="flex: 1; min-width: 160px;">
                    <select name="county" style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; margin: 0; box-sizing: border-box;">
                        <option value="">All Counties</option>
                        <?php foreach (kenyan_counties() as $c): ?>
                            <option value="<?= h($c) ?>" <?= ($countyFilter === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary" style="padding: 8px 18px; font-size: 0.85rem; margin: 0;">
                    <i class="fa fa-search"></i> Search
                </button>
                <?php if (!empty($keyword) || !empty($countyFilter)): ?>
                    <a href="/teacher/jobs" style="color: #64748b; font-size: 0.85rem; text-decoration: none; padding: 8px;">Clear</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (empty($jobs)): ?>
            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; text-align: center; padding: 3rem 1.5rem;">
                <i class="fa fa-briefcase fa-3x" style="color: #cbd5e1; margin-bottom: 1rem;"></i>
                <h4 style="color: #0f172a; margin: 0 0 6px;">No Vacancies Found</h4>
                <p style="color: #64748b; font-size: 0.85rem; margin-bottom: 1.25rem;">
                    Try adjusting your search criteria or explore our school directory!
                </p>
                <a href="/schools/public" class="btn-primary">Explore School Directory</a>
            </div>
        <?php else: ?>
            <div style="display: flex; flex-direction: column; gap: 1rem;">
                <?php foreach ($jobs as $job): ?>
                    <?php 
                        $school = R::load('school', $job->school_id);
                        $app = $appsByJob[$job->id] ?? null;

                        $isExpired = false;
                        $deadlineBadge = '';
                        if (!empty($job->deadline)) {
                            $deadlineDate = new DateTime($job->deadline);
                            $today = new DateTime('today');
                            if ($deadlineDate < $today) {
                                $isExpired = true;
                                $deadlineBadge = '<span style="background: #fee2e2; color: #991b1b; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 12px;"><i class="fa fa-lock"></i> Closed</span>';
                            } else {
                                $diff = $today->diff($deadlineDate)->days;
                                $text = ($diff === 0) ? 'Today' : ($diff === 1 ? 'Tomorrow' : "{$diff} days left");
                                $deadlineBadge = '<span style="background: #eff6ff; color: #1d4ed8; font-size: 0.72rem; font-weight: 700; padding: 2px 8px; border-radius: 12px;"><i class="fa fa-clock"></i> Closes ' . $text . '</span>';
                            }
                        }
                    ?>
                    <div style="background: white; border: 1px solid <?= $app ? '#93c5fd' : ($isExpired ? '#f1f5f9' : '#e2e8f0') ?>; border-radius: 8px; padding: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02); opacity: <?= $isExpired && !$app ? '0.75' : '1' ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 8px;">
                            <div>
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 4px;">
                                    <h3 style="margin: 0; font-size: 1.15rem; color: #0f172a;">
                                        <?php if ($isExpired && !$app): ?>
                                            <span style="color: #64748b; font-weight: 700;"><?= h($job->title) ?></span>
                                        <?php else: ?>
                                            <a href="/teacher/apply?job_id=<?= $job->id ?>" style="color: #0f172a; text-decoration: none; font-weight: 700;"><?= h($job->title) ?></a>
                                        <?php endif; ?>
                                    </h3>
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
                                        <span style="background: <?= $pill['bg'] ?>; color: <?= $pill['color'] ?>; font-size: 0.72rem; font-weight: 800; padding: 2px 8px; border-radius: 12px;">
                                            <?= $pill['text'] ?>
                                        </span>
                                    <?php endif; ?>
                                    <?= $deadlineBadge ?>
                                </div>
                                <p style="margin: 0; font-size: 0.84rem; color: #64748b;">
                                    <i class="fa fa-school" style="color: #0f766e;"></i> <strong><?= h($school->name ?? 'Registered School') ?></strong> &bull; 
                                    <i class="fa fa-map-marker-alt" style="color: #0f766e;"></i> <?= h($school->county ?? 'Kenya') ?>
                                </p>
                            </div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <?php if (!empty($job->salary)): ?>
                                    <span style="background: #dcfce7; color: #166534; font-size: 0.8rem; font-weight: 700; padding: 4px 10px; border-radius: 12px;">
                                        KES <?= number_format($job->salary) ?> / mo
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <p style="color: #334155; font-size: 0.88rem; line-height: 1.6; margin: 0 0 1rem;">
                            <?= nl2br(h($job->description)) ?>
                        </p>

                        <?php if (!empty($job->requirements)): ?>
                            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 6px; padding: 0.75rem 1rem; margin-bottom: 1.25rem;">
                                <span style="font-size: 0.75rem; font-weight: 800; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em; display: block; margin-bottom: 4px;">Requirements:</span>
                                <p style="margin: 0; font-size: 0.82rem; color: #475569; line-height: 1.5;"><?= nl2br(h($job->requirements)) ?></p>
                            </div>
                        <?php endif; ?>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #f1f5f9; padding-top: 1rem; flex-wrap: wrap; gap: 8px;">
                            <span style="font-size: 0.78rem; color: #94a3b8;">
                                <i class="fa fa-clock"></i> Posted <?= date('M d, Y', strtotime($job->posted_date ?? 'now')) ?>
                            </span>
                            <div style="display: flex; gap: 8px;">
                                <?php if ($app): ?>
                                    <a href="/teacher/applications" style="background: #f0fdf4; color: #166534 !important; border: 1px solid #bbf7d0; font-size: 0.84rem; font-weight: 700; padding: 7px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                                        <i class="fa fa-eye"></i> View Application & Messages &rarr;
                                    </a>
                                <?php elseif ($isExpired): ?>
                                    <button disabled style="background: #f1f5f9; color: #94a3b8; border: 1px solid #e2e8f0; font-size: 0.82rem; font-weight: 600; padding: 7px 14px; border-radius: 6px; cursor: not-allowed;">
                                        <i class="fa fa-lock"></i> Applications Closed
                                    </button>
                                <?php else: ?>
                                    <a href="/teacher/apply?job_id=<?= $job->id ?>" style="background: #0f766e; color: white !important; font-size: 0.84rem; font-weight: 600; padding: 7px 18px; border-radius: 6px; text-decoration: none;">
                                        Easy Apply &rarr;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </main>
</div>

