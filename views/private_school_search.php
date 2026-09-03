<?php
// private_school_search.php - Modern Private School Directory
$per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$countyFilter = trim($_GET['county'] ?? '');
$levelFilter = trim($_GET['level'] ?? '');

$offset = ($page - 1) * $per_page;

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(name LIKE ? OR constituency LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($countyFilter !== '') {
    $whereClauses[] = "county = ?";
    $params[] = $countyFilter;
}

if ($levelFilter !== '') {
    $whereClauses[] = "level LIKE ?";
    $params[] = "%$levelFilter%";
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$total = !empty($whereSql) 
    ? R::count('private_school', $whereSql, $params)
    : R::count('private_school');

$queryParams = $params;
$queryParams[] = $per_page;
$queryParams[] = $offset;

$schools = !empty($whereSql)
    ? R::find('private_school', "$whereSql ORDER BY name LIMIT ? OFFSET ?", $queryParams)
    : R::find('private_school', 'ORDER BY name LIMIT ? OFFSET ?', [$per_page, $offset]);

$total_pages = max(1, ceil($total / $per_page));
$authUser = auth_user();
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="<?= !$authUser ? 'max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem;' : '' ?>">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a;">🏫 Private Schools & Academies Directory</h2>
            <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                Explore verified private educational institutions, academies, and junior schools across Kenya.
            </p>
        </div>

        <!-- Filter Search Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <form method="GET" action="/schools/private" style="margin: 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: flex-end;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Keywords</label>
                        <input type="text" name="search" value="<?= h($search) ?>" placeholder="Academy name or constituency..." style="width: 100%; box-sizing: border-box; margin: 0;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">County</label>
                        <select name="county" style="width: 100%; box-sizing: border-box; margin: 0;">
                            <option value="">All 47 Counties</option>
                            <?php foreach (kenyan_counties() as $c): ?>
                                <option value="<?= h($c) ?>" <?= ($countyFilter === $c) ? 'selected' : '' ?>><?= h($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Level / Grade</label>
                        <select name="level" style="width: 100%; box-sizing: border-box; margin: 0;">
                            <option value="">All Categories</option>
                            <option value="PRIMARY" <?= ($levelFilter === 'PRIMARY') ? 'selected' : '' ?>>Primary / Academy (CBC)</option>
                            <option value="SECONDARY" <?= ($levelFilter === 'SECONDARY') ? 'selected' : '' ?>>Secondary / High School</option>
                            <option value="JUNIOR" <?= ($levelFilter === 'JUNIOR') ? 'selected' : '' ?>>Junior Secondary</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 6px;">
                        <button type="submit" class="btn-primary" style="height: 40px; flex: 1; margin: 0;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $countyFilter || $levelFilter): ?>
                            <a href="/schools/private" style="height: 40px; padding: 0 14px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; text-decoration: none;">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
            <span style="font-size: 0.85rem; color: #64748b;">
                Showing <strong><?= count($schools) ?></strong> of <strong><?= number_format($total) ?></strong> institutions
            </span>
        </div>

        <!-- Directory Table Card -->
        <div class="mwalimu-table-card">
            <div style="overflow-x: auto;">
                <table class="mwalimu-table">
                    <thead>
                        <tr>
                            <th>School Name</th>
                            <th>Level</th>
                            <th>County</th>
                            <th>Constituency</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schools)): ?>
                            <?php foreach ($schools as $school): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <a href="/schools/private/detail?id=<?= $school->id ?>" style="color: #0f172a; text-decoration: none; font-weight: 700;">
                                            <?= h($school->name) ?>
                                        </a>
                                    </td>
                                    <td>
                                        <span style="background: #f0fdf4; color: #166534; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid #bbf7d0;">
                                            <?= h($school->level ?: 'Academy') ?>
                                        </span>
                                    </td>
                                    <td style="color: #475569;"><?= h($school->county) ?></td>
                                    <td style="color: #475569;"><?= h($school->constituency) ?></td>
                                    <td style="text-align: right;">
                                        <a href="/schools/private/detail?id=<?= $school->id ?>" style="color: #0f766e; font-weight: 700; font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            View <i class="fa fa-arrow-right" style="font-size: 10px;"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                    No private schools found matching your search criteria.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <?php 
            $pageQuery = $_GET;
            unset($pageQuery['page']);
            $queryString = http_build_query($pageQuery);
            $basePaginationUrl = '/schools/private?' . ($queryString ? $queryString . '&' : '');
            ?>
            <div style="display: flex; justify-content: center; gap: 6px; margin-top: 2rem;">
                <?php if ($page > 1): ?>
                    <a href="<?= $basePaginationUrl ?>page=<?= $page - 1 ?>" style="padding: 6px 14px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none;">&laquo; Prev</a>
                <?php endif; ?>

                <span style="padding: 6px 14px; background: #0f766e; color: white; border-radius: 6px; font-size: 0.82rem; font-weight: 700;">
                    Page <?= $page ?> of <?= $total_pages ?>
                </span>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= $basePaginationUrl ?>page=<?= $page + 1 ?>" style="padding: 6px 14px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none;">Next &raquo;</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>
</div>
