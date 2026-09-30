<?php
// views/admin_jobs.php - Admin Vacancy & Job Listing Management
require_auth('admin');

$msg = '';
$error = '';

// Handle Delete Action
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $job_id = intval($_POST['job_id'] ?? 0);
        $action = $_POST['admin_action'] ?? '';

        if ($action === 'delete_job') {
            $job = R::load('job', $job_id);
            if ($job && $job->id) {
                $jobTitle = $job->title;
                // Delete associated applications
                $applications = R::find('application', 'job_id = ?', [$job->id]);
                foreach ($applications as $app) {
                    R::trash($app);
                }
                R::trash($job);
                log_admin_audit('job', 'JOB_DELETED', 'job', $job_id, $jobTitle, "Job vacancy '{$jobTitle}' and associated applicant records deleted by admin");
                $msg = "Job listing '{$jobTitle}' and its applicant records have been deleted.";
            } else {
                $error = "Job listing not found.";
            }
        }
    }
}

// Search & Filter
$search = trim($_GET['q'] ?? '');
$filterSchoolId = intval($_GET['school_id'] ?? 0);
$statusFilter = trim($_GET['status'] ?? '');
$page = max(1, intval($_GET['p'] ?? 1));
$limit = 30;
$offset = ($page - 1) * $limit;

$params = [];
$whereClauses = ['1=1'];

if ($search !== '') {
    $whereClauses[] = '(title LIKE ? OR description LIKE ? OR requirements LIKE ?)';
    $searchTerm = "%{$search}%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
}

if ($filterSchoolId > 0) {
    $whereClauses[] = 'school_id = ?';
    $params[] = $filterSchoolId;
}

$today = date('Y-m-d');
if ($statusFilter === 'active') {
    $whereClauses[] = '(deadline IS NULL OR deadline >= ?)';
    $params[] = $today;
} elseif ($statusFilter === 'expired') {
    $whereClauses[] = 'deadline < ?';
    $params[] = $today;
}

$whereSql = implode(' AND ', $whereClauses);
$totalJobs = R::count('job', $whereSql, $params);
$jobs = R::find('job', "{$whereSql} ORDER BY id DESC LIMIT ? OFFSET ?", array_merge($params, [$limit, $offset]));

// Aggregate statistics
$statTotal = R::count('job');
$statActive = R::count('job', 'deadline IS NULL OR deadline >= ?', [$today]);
$statExpired = R::count('job', 'deadline < ?', [$today]);
$statTotalApplications = R::count('application');

$filterSchool = $filterSchoolId > 0 ? R::load('school', $filterSchoolId) : null;
$totalPages = ceil($totalJobs / $limit);
?>

<style>
.admin-table-container {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    box-shadow: 0 1px 3px rgba(0,0,0,0.03);
}
.admin-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.86rem;
}
.admin-table th, .admin-table td {
    padding: 10px 12px;
    vertical-align: middle;
}
@media (max-width: 768px) {
    .admin-header-flex {
        flex-direction: column !important;
        align-items: flex-start !important;
    }
    .admin-filter-form {
        flex-direction: column !important;
    }
    .admin-filter-form > div, .admin-filter-form button {
        width: 100% !important;
    }
    .stat-grid-responsive {
        grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)) !important;
        gap: 0.5rem !important;
    }
}
</style>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="max-width: 1200px; margin: 0 auto; padding: 1.25rem 1rem;">
        <div style="padding-bottom: 3rem;">

            <!-- Breadcrumb & Header -->
            <div class="admin-header-flex"
                style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <a href="/admin/dashboard"
                        style="color: #64748b; font-size: 0.82rem; display: inline-flex; align-items: center; gap: 6px; margin-bottom: 4px; text-decoration: none;">
                        <i class="fa fa-arrow-left"></i> Back to Dashboard
                    </a>
                    <h2 style="margin: 0; color: #0f172a; font-size: 1.35rem; display: flex; align-items: center; gap: 8px;">
                        <i class="fa fa-briefcase" style="color: #0f766e;"></i> Job Vacancies & Listings
                        <?php if ($filterSchool && $filterSchool->id): ?>
                            <span
                                style="font-size: 0.8rem; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 6px; font-weight: 600;">
                                Filtered: <?= h($filterSchool->name) ?>
                            </span>
                        <?php endif; ?>
                    </h2>
                </div>
                <div>
                    <span
                        style="background: #f1f5f9; color: #475569; padding: 5px 10px; border-radius: 6px; font-size: 0.8rem; font-weight: 600;">
                        Showing <?= count($jobs) ?> of <?= number_format($totalJobs) ?> Vacancies
                    </span>
                </div>
            </div>

            <?php if ($msg): ?>
                <div
                    style="background: #f0fdf4; border: 1px solid #86efac; color: #166534; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-check"></i> <?= h($msg) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div
                    style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.86rem; display: flex; align-items: center; gap: 8px;">
                    <i class="fa fa-circle-exclamation"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <!-- Metric Summary Cards -->
            <div class="stat-grid-responsive"
                style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)); gap: 0.75rem; margin-bottom: 1.25rem;">
                <div
                    style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #64748b; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Total Posted</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #0f172a; margin-top: 2px;">
                        <?= number_format($statTotal) ?></div>
                </div>
                <div
                    style="background: white; border: 1px solid #bbf7d0; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #166534; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Active / Open</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #16a34a; margin-top: 2px;">
                        <?= number_format($statActive) ?></div>
                </div>
                <div
                    style="background: white; border: 1px solid #fee2e2; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #991b1b; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Closed / Expired</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #dc2626; margin-top: 2px;">
                        <?= number_format($statExpired) ?></div>
                </div>
                <div
                    style="background: white; border: 1px solid #e0e7ff; border-radius: 8px; padding: 0.88rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="color: #3730a3; font-size: 0.74rem; text-transform: uppercase; font-weight: 600;">Total Applications</div>
                    <div style="font-size: 1.4rem; font-weight: 800; color: #4338ca; margin-top: 2px;">
                        <?= number_format($statTotalApplications) ?></div>
                </div>
            </div>

            <!-- Filter & Search Controls -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 8px; padding: 0.88rem 1rem; margin-bottom: 1.25rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <form method="GET" action="/admin/jobs" class="admin-filter-form"
                    style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center; margin: 0;">
                    <div style="flex: 2; min-width: 200px;">
                        <input type="text" name="q" value="<?= h($search) ?>"
                            placeholder="Search job title, description..."
                            style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; box-sizing: border-box;">
                    </div>
                    <div style="flex: 1; min-width: 140px;">
                        <select name="status"
                            style="width: 100%; padding: 7px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.84rem; background: white; box-sizing: border-box;">
                            <option value="">All Job Statuses</option>
                            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active / Open
                            </option>
                            <option value="expired" <?= $statusFilter === 'expired' ? 'selected' : '' ?>>Closed / Expired
                            </option>
                        </select>
                    </div>
                    <div>
                        <button type="submit"
                            style="background: #0f766e; color: white !important; padding: 7px 14px; border: none; border-radius: 6px; font-size: 0.84rem; font-weight: 600; cursor: pointer; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $statusFilter || $filterSchoolId): ?>
                            <a href="/admin/jobs"
                                style="margin-left: 6px; font-size: 0.82rem; color: #64748b; text-decoration: underline;">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Jobs List Table -->
            <div class="admin-table-container">
                <?php if (empty($jobs)): ?>
                    <div style="padding: 3rem; text-align: center; color: #64748b;">
                        <i class="fa fa-folder-open" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0; font-size: 1rem;">No job vacancies found matching your query.</p>
                    </div>
                <?php else: ?>
                    <table class="admin-table">
                        <thead>
                            <tr
                                style="background: #f8fafc; border-bottom: 2px solid #e2e8f0; text-align: left; color: #475569; font-size: 0.76rem; text-transform: uppercase;">
                                    <th style="padding: 12px 14px; min-width: 200px;">Job Title & School</th>
                                    <th style="padding: 12px 14px; min-width: 140px;">Compensation / Terms</th>
                                    <th style="padding: 12px 14px; min-width: 140px;">Timeline & Deadline</th>
                                    <th style="padding: 12px 14px; min-width: 100px;">Applications</th>
                                    <th style="padding: 12px 14px; text-align: center; width: 60px;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($jobs as $j): ?>
                                    <?php
                                    $schoolObj = R::load('school', $j->school_id);
                                    $appCount = R::count('application', 'job_id = ?', [$j->id]);
                                    $isExpired = (!empty($j->deadline) && $j->deadline < $today);
                                    ?>
                                    <tr style="border-bottom: 1px solid #f1f5f9; transition: background 0.15s ease;"
                                        onmouseover="this.style.background='#fbfcfd'"
                                        onmouseout="this.style.background='white'">
                                        <td style="padding: 12px 14px;">
                                            <div style="font-weight: 700; color: #0f172a; font-size: 0.95rem;">
                                                <?= h($j->title) ?>
                                            </div>
                                            <div style="font-size: 0.78rem; color: #0f766e; margin-top: 2px;">
                                                <?php if ($j->source_type === 'external'): ?>
                                                    <span
                                                        style="background: #fef3c7; color: #92400e; font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-right: 4px;">
                                                        <i class="fa fa-globe"></i> <?= h($j->source_name) ?>
                                                    </span>
                                                    <strong><?= h($j->company_name ?: 'Education Partner') ?></strong>
                                                    (<?= h($j->location_text ?: 'Kenya') ?>)
                                                <?php elseif ($schoolObj && $schoolObj->id): ?>
                                                    <span
                                                        style="background: #dcfce7; color: #166534; font-size: 0.72rem; font-weight: 700; padding: 2px 6px; border-radius: 4px; margin-right: 4px;">
                                                        <i class="fa fa-school"></i> Direct School
                                                    </span>
                                                    <a href="/admin/jobs?school_id=<?= $schoolObj->id ?>" style="color: #0f766e; text-decoration: underline;">
                                                        <?= h($schoolObj->name) ?>
                                                    </a> (<?= h($schoolObj->county ?: 'Kenya') ?>)
                                                <?php else: ?>
                                                    <span style="color: #94a3b8;">Direct Listing</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div style="font-size: 0.82rem; color: #334155; font-weight: 600;">
                                                <?= !empty($j->salary) ? 'KES ' . number_format($j->salary) . ' / mo' : 'Negotiable' ?>
                                            </div>
                                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 2px;">
                                                <?= h(substr($j->requirements ?? '', 0, 45)) ?>        <?= strlen($j->requirements ?? '') > 45 ? '...' : '' ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <div>
                                                <?php if ($isExpired): ?>
                                                    <span
                                                        style="background: #fee2e2; color: #991b1b; font-weight: 700; font-size: 0.74rem; padding: 3px 7px; border-radius: 4px;">
                                                        Closed (<?= date('M d, Y', strtotime($j->deadline)) ?>)
                                                    </span>
                                                <?php elseif (!empty($j->deadline)): ?>
                                                    <span
                                                        style="background: #dcfce7; color: #166534; font-weight: 700; font-size: 0.74rem; padding: 3px 7px; border-radius: 4px;">
                                                        Open until <?= date('M d, Y', strtotime($j->deadline)) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span
                                                        style="background: #f1f5f9; color: #475569; font-weight: 600; font-size: 0.74rem; padding: 3px 7px; border-radius: 4px;">
                                                        Open (No Deadline)
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <div style="font-size: 0.74rem; color: #64748b; margin-top: 3px;">
                                                Posted:
                                                <?= !empty($j->posted_date) ? date('M d, Y', strtotime($j->posted_date)) : 'N/A' ?>
                                            </div>
                                        </td>
                                        <td style="padding: 12px 14px;">
                                            <span
                                                style="background: #e0e7ff; color: #3730a3; font-weight: 700; font-size: 0.76rem; padding: 3px 8px; border-radius: 4px;">
                                                <?= $appCount ?> Applicant<?= $appCount !== 1 ? 's' : '' ?>
                                            </span>
                                        </td>
                                        <td style="padding: 12px 14px; text-align: center; position: relative;">
                                            <!-- 3-Dot Dropdown Menu -->
                                            <div style="position: relative; display: inline-block;">
                                                <button type="button" onclick="toggleJobMenu(event, <?= $j->id ?>)" style="background: transparent; border: 1px solid transparent; border-radius: 6px; padding: 6px 10px; cursor: pointer; color: #64748b; font-size: 1rem; transition: all 0.15s;" onmouseover="this.style.background='#f1f5f9'; this.style.borderColor='#cbd5e1'" onmouseout="this.style.background='transparent'; this.style.borderColor='transparent'" title="Actions">
                                                    <i class="fa fa-ellipsis-v"></i>
                                                </button>

                                                <div id="job-menu-<?= $j->id ?>" class="job-dropdown-menu" style="display: none; position: absolute; right: 0; top: 100%; z-index: 100; min-width: 180px; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.1); padding: 4px 0; text-align: left;">
                                                    <a href="/job/view?id=<?= $j->id ?>" target="_blank" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #334155; text-decoration: none;" onmouseover="this.style.background='#f8fafc'" onmouseout="this.style.background='transparent'">
                                                        <i class="fa fa-eye" style="width: 16px; color: #64748b;"></i> View Vacancy
                                                    </a>

                                                    <?php if ($schoolObj && $schoolObj->id): ?>
                                                        <a href="/admin/jobs?school_id=<?= $schoolObj->id ?>" style="display: flex; align-items: center; gap: 8px; padding: 8px 14px; font-size: 0.82rem; color: #0284c7; text-decoration: none;" onmouseover="this.style.background='#f0f9ff'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-school" style="width: 16px;"></i> View School Profile
                                                        </a>
                                                    <?php endif; ?>

                                                    <hr style="margin: 4px 0; border: none; border-top: 1px solid #f1f5f9;">

                                                    <form method="POST" action="/admin/jobs" style="margin: 0;">
                                                        <?= csrf_field() ?>
                                                        <input type="hidden" name="job_id" value="<?= $j->id ?>">
                                                        <input type="hidden" name="admin_action" value="delete_job">
                                                        <button type="submit"
                                                            onclick="return confirm('Are you sure you want to delete this job vacancy and all its application records? This action cannot be undone.')"
                                                            style="display: flex; align-items: center; gap: 8px; width: 100%; border: none; background: transparent; padding: 8px 14px; font-size: 0.82rem; color: #dc2626; cursor: pointer; text-align: left;" onmouseover="this.style.background='#fef2f2'" onmouseout="this.style.background='transparent'">
                                                            <i class="fa fa-trash" style="width: 16px;"></i> Delete Vacancy
                                                        </button>
                                                    </form>
                                                </div>
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
                            style="padding: 1rem 1.5rem; background: #f8fafc; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-size: 0.82rem; color: #64748b;">
                                Page <?= $page ?> of <?= $totalPages ?>
                            </span>
                            <div style="display: flex; gap: 6px;">
                                <?php if ($page > 1): ?>
                                    <a href="/admin/jobs?p=<?= $page - 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&school_id=<?= $filterSchoolId ?>"
                                        style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
                                        &laquo; Prev
                                    </a>
                                <?php endif; ?>
                                <?php if ($page < $totalPages): ?>
                                    <a href="/admin/jobs?p=<?= $page + 1 ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($statusFilter) ?>&school_id=<?= $filterSchoolId ?>"
                                        style="padding: 5px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 4px; font-size: 0.82rem; color: #334155; text-decoration: none;">
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

<script>
function toggleJobMenu(event, jobId) {
    event.stopPropagation();
    var targetMenu = document.getElementById('job-menu-' + jobId);
    var isVisible = targetMenu && targetMenu.style.display === 'block';

    // Close all open job menus first
    document.querySelectorAll('.job-dropdown-menu').forEach(function(menu) {
        menu.style.display = 'none';
    });

    if (targetMenu && !isVisible) {
        targetMenu.style.display = 'block';
    }
}

// Global click-outside listener
document.addEventListener('click', function(event) {
    if (!event.target.closest('.job-dropdown-menu') && !event.target.closest('button')) {
        document.querySelectorAll('.job-dropdown-menu').forEach(function(menu) {
            menu.style.display = 'none';
        });
    }
});
</script>