<?php
// views/admin_job_aggregation.php - Job Aggregation & Online Vacancy Moderation Manager
require_auth('admin');
require_once __DIR__ . '/../services/JobIngestionService.php';
require_once __DIR__ . '/../scripts/job_scraper_worker.php';

$msg = '';
$error = '';

// Handle Admin Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        $action = $_POST['admin_action'] ?? '';
        $jobId = intval($_POST['job_id'] ?? 0);

        if ($action === 'publish_job' && $jobId > 0) {
            $job = R::load('job', $jobId);
            if ($job && $job->id) {
                $job->aggregation_status = 'published';
                R::store($job);
                require_once __DIR__ . '/../services/JobAlertService.php';
                $alertRes = JobAlertService::notifyMatchingTeachers($job);
                log_admin_audit('job_aggregation', 'JOB_PUBLISHED', 'job', $jobId, $job->title, "Aggregated job '{$job->title}' approved and published live. Alerts sent: {$alertRes['alerts_dispatched']}");
                $msg = "Vacancy '{$job->title}' is now live for teachers (notified {$alertRes['alerts_dispatched']} matching educators).";
            }
        } elseif ($action === 'reject_job' && $jobId > 0) {
            $job = R::load('job', $jobId);
            if ($job && $job->id) {
                $job->aggregation_status = 'rejected';
                R::store($job);
                log_admin_audit('job_aggregation', 'JOB_REJECTED', 'job', $jobId, $job->title, "Aggregated job '{$job->title}' rejected.");
                $msg = "Vacancy '{$job->title}' has been unlisted.";
            }
        } elseif ($action === 'delete_aggregated_job' && $jobId > 0) {
            $job = R::load('job', $jobId);
            if ($job && $job->id) {
                $title = $job->title;
                R::trash($job);
                log_admin_audit('job_aggregation', 'JOB_DELETED', 'job', $jobId, $title, "Aggregated job '{$title}' permanently deleted.");
                $msg = "Job '{$title}' permanently removed.";
            }
        } elseif ($action === 'publish_all_pending') {
            $pendingJobs = R::find('job', "source_type = 'external' AND aggregation_status = 'pending_review'");
            $count = 0;
            foreach ($pendingJobs as $pj) {
                $pj->aggregation_status = 'published';
                R::store($pj);
                $count++;
            }
            log_admin_audit('job_aggregation', 'BULK_PUBLISHED', 'job', 0, 'Bulk Action', "{$count} pending aggregated vacancies approved in bulk.");
            $msg = "Successfully approved and published {$count} pending vacancies.";
        } elseif ($action === 'run_scraper_now') {
            $sourceChoice = trim($_POST['scraper_source'] ?? '');
            $runResults = JobScraperWorker::run([
                'source' => !empty($sourceChoice) ? $sourceChoice : null,
                'dry_run' => false
            ]);
            $newCount = $runResults['total_inserted'] ?? 0;
            $dupCount = $runResults['total_skipped_duplicates'] ?? 0;
            $msg = "Aggregation pipeline completed! {$newCount} new vacancies ingested, {$dupCount} duplicates skipped.";
        }
    }
}

// Search & Filter Parameters
$search = trim($_GET['q'] ?? '');
$sourceFilter = trim($_GET['source'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$curriculumFilter = trim($_GET['curriculum'] ?? '');

$page = max(1, intval($_GET['p'] ?? 1));
$limit = 25;
$offset = ($page - 1) * $limit;

$whereClauses = ["source_type = 'external'"];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(title LIKE ? OR company_name LIKE ? OR description LIKE ?)";
    $st = "%{$search}%";
    $params[] = $st;
    $params[] = $st;
    $params[] = $st;
}

if (!empty($sourceFilter)) {
    $whereClauses[] = "source_name = ?";
    $params[] = $sourceFilter;
}

if (!empty($statusFilter)) {
    $whereClauses[] = "aggregation_status = ?";
    $params[] = $statusFilter;
}

if (!empty($curriculumFilter)) {
    $whereClauses[] = "curriculum = ?";
    $params[] = $curriculumFilter;
}

$whereSql = implode(' AND ', $whereClauses);
$totalJobs = R::count('job', $whereSql, $params);
$jobs = R::find('job', "{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

// Summary Statistics
$statTotalExternal = R::count('job', "source_type = 'external'");
$statPublished = R::count('job', "source_type = 'external' AND aggregation_status = 'published'");
$statPending = R::count('job', "source_type = 'external' AND aggregation_status = 'pending_review'");
$statRejected = R::count('job', "source_type = 'external' AND aggregation_status = 'rejected'");
$statExpired = R::count('job', "source_type = 'external' AND aggregation_status = 'expired'");

// Distinct sources list
$sourcesList = R::getCol("SELECT DISTINCT source_name FROM job WHERE source_type = 'external' AND source_name IS NOT NULL");
$totalPages = ceil($totalJobs / $limit);
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 1200px; margin: 0 auto; padding-bottom: 3rem;">

            <!-- Breadcrumb & Header -->
            <div
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; gap: 1rem;">
                <div>
                    <a href="/admin/dashboard"
                        style="color: #64748b; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 6px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; display: flex; align-items: center; gap: 10px;">
                        <i class="fa fa-satellite-dish" style="color: #0f766e;"></i> External Job Aggregator & Crawler
                    </h2>
                    <p style="margin: 4px 0 0; font-size: 0.85rem; color: #64748b;">
                        Aggregate, review, and syndicate online teaching opportunities from Kenyan & international job
                        boards.
                    </p>
                </div>
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <form method="POST" action="/admin/aggregation" style="margin: 0; display: inline-flex; gap: 8px;">
                        <?= csrf_field() ?>
                        <input type="hidden" name="admin_action" value="run_scraper_now">
                        <select name="scraper_source"
                            style="padding: 6px 10px; font-size: 0.82rem; border: 1px solid #cbd5e1; border-radius: 6px; background: white;">
                            <option value="">All Scrapers</option>
                            <option value="myjobmag">MyJobMag Kenya</option>
                            <option value="brightermonday">BrighterMonday Kenya</option>
                            <option value="teachaway">TeachAway International</option>
                        </select>
                        <button type="submit"
                            style="background: #0f766e; color: white !important; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-rotate"></i> Ingest Now
                        </button>
                    </form>
                    <?php if ($statPending > 0): ?>
                        <form method="POST" action="/admin/aggregation" style="margin: 0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="admin_action" value="publish_all_pending">
                            <button type="submit"
                                onclick="return confirm('Approve and publish all <?= $statPending ?> pending vacancies?')"
                                style="background: #16a34a; color: white !important; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                                <i class="fa fa-check-double"></i> Publish All Pending (<?= $statPending ?>)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($msg): ?>
                <div
                    style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-check"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-exclamation"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 1rem; margin-bottom: 1.5rem;">
                <div
                    style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Total
                        Ingested</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #0f172a; margin-top: 4px;">
                        <?= number_format($statTotalExternal) ?></div>
                </div>
                <div
                    style="background: white; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #166534; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Live
                        for Teachers</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a; margin-top: 4px;">
                        <?= number_format($statPublished) ?></div>
                </div>
                <div
                    style="background: white; border: 1px solid #fef08a; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #854d0e; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">
                        Pending Review</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #ca8a04; margin-top: 4px;">
                        <?= number_format($statPending) ?></div>
                </div>
                <div
                    style="background: white; border: 1px solid #e0e7ff; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #3730a3; font-size: 0.78rem; text-transform: uppercase; font-weight: 600;">Active
                        Crawlers</div>
                    <div style="font-size: 1.5rem; font-weight: 800; color: #4338ca; margin-top: 4px;">
                        <?= count($sourcesList) ?> Sources</div>
                </div>
            </div>

            <!-- Filter and Search Bar -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/aggregation"
                    style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center; margin: 0;">
                    <div style="flex: 2; min-width: 200px;">
                        <input type="text" name="q" value="<?= h($search) ?>"
                            placeholder="Search vacancy title, school, or subjects..."
                            style="width: 100%; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="status"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                            <option value="">All Statuses</option>
                            <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Published
                                (Live)</option>
                            <option value="pending_review" <?= $statusFilter === 'pending_review' ? 'selected' : '' ?>>
                                Pending Review</option>
                            <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected
                            </option>
                            <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Expired</option>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="source"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                            <option value="">All Sources</option>
                            <?php foreach ($sourcesList as $s): ?>
                                <option value="<?= h($s) ?>" <?= $sourceFilter === $s ? 'selected' : '' ?>><?= h($s) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div style="flex: 1; min-width: 120px;">
                        <select name="curriculum"
                            style="width: 100%; padding: 8px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.85rem; background: white; box-sizing: border-box;">
                            <option value="">All Curriculums</option>
                            <option value="CBC" <?= $curriculumFilter === 'CBC' ? 'selected' : '' ?>>CBC</option>
                            <option value="IGCSE" <?= $curriculumFilter === 'IGCSE' ? 'selected' : '' ?>>IGCSE / Cambridge
                            </option>
                            <option value="IB" <?= $curriculumFilter === 'IB' ? 'selected' : '' ?>>IB</option>
                            <option value="8-4-4" <?= $curriculumFilter === '8-4-4' ? 'selected' : '' ?>>8-4-4</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit"
                            style="background: #0f766e; color: white !important; padding: 8px 16px; border: none; border-radius: 6px; font-size: 0.85rem; font-weight: 600; cursor: pointer;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $statusFilter || $sourceFilter || $curriculumFilter): ?>
                            <a href="/admin/aggregation"
                                style="margin-left: 6px; font-size: 0.82rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Ingested Jobs Table -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; box-shadow: 0 1px 3px rgba(0,0,0,0.03); overflow: hidden;">
                <?php if (empty($jobs)): ?>
                    <div style="padding: 3.5rem 1.5rem; text-align: center; color: #64748b;">
                        <i class="fa fa-satellite-dish" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <h4 style="margin: 0 0 6px; color: #0f172a;">No Aggregated Jobs Found</h4>
                        <p style="margin: 0; font-size: 0.88rem;">Try adjusting your filters or trigger a crawler run using
                            the 'Ingest Now' button above.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse; font-size: 0.86rem;">
                            <thead>
                                <tr
                                    style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569;">
                                    <th style="padding: 12px 14px;">Vacancy & Employer</th>
                                    <th style="padding: 12px 14px;">Source & Category</th>
                                    <th style="padding: 12px 14px;">Curriculum & County</th>
                                    <th style="padding: 12px 14px;">Status</th>
                                    <th style="padding: 12px 14px; text-align: right;">Moderation</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $j): ?>
                                    <?php
                                    $st = $j->aggregation_status ?: 'published';
                                    $statusBadges = [
                                        'published' => ['label' => 'Live ✓', 'bg' => '#dcfce7', 'color' => '#166534'],
                                        'pending_review' => ['label' => 'Pending Review ⏳', 'bg' => '#fef9c3', 'color' => '#854d0e'],
                                        'rejected' => ['label' => 'Unlisted / Rejected', 'bg' => '#fee2e2', 'color' => '#991b1b'],
                                        'expired' => ['label' => 'Expired 🔒', 'bg' => '#f1f5f9', 'color' => '#475569']
                                    ];
                                    $badge = $statusBadges[$st] ?? $statusBadges['published'];
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;"
                                        onmouseover="this.style.background='#fbfcfd'"
                                        onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 14px; max-width: 320px;">
                                            <div
                                                style="font-weight: 700; color: #0f172a; font-size: 0.92rem; line-height: 1.3;">
                                                <?= h($j->title) ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #0f766e; margin-top: 3px; font-weight: 600;">
                                                <i class="fa fa-school"></i> <?= h($j->company_name ?: 'Education Partner') ?>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #94a3b8; margin-top: 2px;">
                                                Scraped:
                                                <?= date('M d, Y H:i', strtotime($j->scraped_at ?? ($j->posted_date ?? 'now'))) ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.8rem; font-weight: 700; color: #334155;">
                                                <i class="fa fa-globe" style="color: #0f766e;"></i> <?= h($j->source_name) ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                                <?= h($j->subject_category ?: 'General Teaching') ?>
                                            </div>
                                            <div style="margin-top: 4px;">
                                                <a href="<?= h($j->source_url) ?>" target="_blank" rel="noopener noreferrer"
                                                    style="font-size: 0.74rem; color: #0f766e; text-decoration: underline; display: inline-flex; align-items: center; gap: 3px;">
                                                    Original Post <i class="fa fa-arrow-up-right-from-square"
                                                        style="font-size: 0.68rem;"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div>
                                                <span
                                                    style="background: #e0f2fe; color: #0369a1; font-weight: 700; font-size: 0.74rem; padding: 2px 7px; border-radius: 4px;">
                                                    <?= h($j->curriculum ?: 'CBC') ?>
                                                </span>
                                            </div>
                                            <div style="font-size: 0.76rem; color: #64748b; margin-top: 4px;">
                                                <i class="fa fa-map-marker-alt"></i> <?= h($j->location_text ?: 'Kenya') ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <span
                                                style="background: <?= $badge['bg'] ?>; color: <?= $badge['color'] ?>; font-weight: 700; font-size: 0.74rem; padding: 3px 8px; border-radius: 12px; display: inline-block;">
                                                <?= $badge['label'] ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: right;">
                                            <div
                                                style="display: inline-flex; gap: 6px; flex-wrap: wrap; justify-content: flex-end;">
                                                <?php if ($st !== 'published'): ?>
                                                    <form method="POST" action="/admin/aggregation"
                                                        style="margin: 0; display: inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="job_id" value="<?= $j->id ?>">
                                                        <input type="hidden" name="admin_action" value="publish_job">
                                                        <button type="submit" title="Publish live for teachers"
                                                            style="background: #dcfce7; color: #166534; border: 1px solid #86efac; font-size: 0.74rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                                            <i class="fa fa-check"></i> Approve
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <?php if ($st !== 'rejected'): ?>
                                                    <form method="POST" action="/admin/aggregation"
                                                        style="margin: 0; display: inline;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="job_id" value="<?= $j->id ?>">
                                                        <input type="hidden" name="admin_action" value="reject_job">
                                                        <button type="submit" title="Unlist this vacancy"
                                                            style="background: #fef08a; color: #854d0e; border: 1px solid #fde047; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                                            <i class="fa fa-eye-slash"></i> Unlist
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                                <form method="POST" action="/admin/aggregation"
                                                    style="margin: 0; display: inline;">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="job_id" value="<?= $j->id ?>">
                                                    <input type="hidden" name="admin_action" value="delete_aggregated_job">
                                                    <button type="submit"
                                                        onclick="return confirm('Permanently delete this aggregated job?')"
                                                        title="Delete permanently"
                                                        style="background: #fee2e2; color: #dc2626; border: 1px solid #fca5a5; font-size: 0.74rem; font-weight: 600; padding: 4px 8px; border-radius: 4px; cursor: pointer;">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div
                            style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; font-size: 0.82rem; color: #64748b;">
                            <span>Page <?= $page ?> of <?= $totalPages ?> (Total: <?= number_format($totalJobs) ?>
                                vacancies)</span>
                            <div style="display: flex; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="/admin/aggregation?p=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&source=<?= urlencode($sourceFilter) ?>&curriculum=<?= urlencode($curriculumFilter) ?>"
                                        style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; text-decoration: none;">
                                        &laquo; Prev
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="/admin/aggregation?p=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&source=<?= urlencode($sourceFilter) ?>&curriculum=<?= urlencode($curriculumFilter) ?>"
                                        style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; color: #334155; text-decoration: none;">
                                        Next &raquo;
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>