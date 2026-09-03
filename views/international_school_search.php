<?php
// views/international_school_search.php - Modern International School Directory
$per_page = 20;
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$countryFilter = trim($_GET['country'] ?? '');
$cityFilter = trim($_GET['city'] ?? '');

$offset = ($page - 1) * $per_page;

$whereClauses = [];
$params = [];

if ($search !== '') {
    $whereClauses[] = "(name LIKE ? OR address LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($countryFilter !== '') {
    $whereClauses[] = "country LIKE ?";
    $params[] = "%$countryFilter%";
}

if ($cityFilter !== '') {
    $whereClauses[] = "city LIKE ?";
    $params[] = "%$cityFilter%";
}

$whereSql = !empty($whereClauses) ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

$total = !empty($whereSql) 
    ? R::count('international_school', $whereSql, $params)
    : R::count('international_school');

$queryParams = $params;
$queryParams[] = $per_page;
$queryParams[] = $offset;

$schools = !empty($whereSql)
    ? R::findAll('international_school', "$whereSql ORDER BY name LIMIT ? OFFSET ?", $queryParams)
    : R::findAll('international_school', 'ORDER BY name LIMIT ? OFFSET ?', [$per_page, $offset]);

$total_pages = max(1, ceil($total / $per_page));
$authUser = auth_user();
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="<?= !$authUser ? 'max-width: 1200px; margin: 0 auto; padding: 2rem 1.5rem;' : '' ?>">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a;">🌍 International Schools Directory</h2>
            <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                Directory of accredited international, IB, and British curriculum institutions globally and across East Africa.
            </p>
        </div>

        <!-- Filter Search Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <form method="GET" action="/schools/international" style="margin: 0;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; align-items: flex-end;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">School / Keyword</label>
                        <input type="text" name="search" value="<?= h($search) ?>" placeholder="School name or address..." style="width: 100%; box-sizing: border-box; margin: 0;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Country</label>
                        <input type="text" name="country" value="<?= h($countryFilter) ?>" placeholder="e.g. Kenya, UK, UAE, USA..." style="width: 100%; box-sizing: border-box; margin: 0;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">City / Town</label>
                        <input type="text" name="city" value="<?= h($cityFilter) ?>" placeholder="e.g. Nairobi, London, Dubai..." style="width: 100%; box-sizing: border-box; margin: 0;">
                    </div>

                    <div style="display: flex; gap: 6px;">
                        <button type="submit" class="btn-primary" style="height: 40px; flex: 1; margin: 0;">
                            <i class="fa fa-filter"></i> Filter
                        </button>
                        <?php if ($search || $countryFilter || $cityFilter): ?>
                            <a href="/schools/international" style="height: 40px; padding: 0 14px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; text-decoration: none;">Reset</a>
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
                            <th>Institution Name</th>
                            <th>City</th>
                            <th>Country</th>
                            <th>Address</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($schools)): ?>
                            <?php foreach ($schools as $school): ?>
                                <tr>
                                    <td style="font-weight: 600;">
                                        <a href="/schools/international/detail?id=<?= $school->id ?>" style="color: #0f172a; text-decoration: none; font-weight: 700;">
                                            <?= h($school->name) ?>
                                        </a>
                                    </td>
                                    <td style="color: #475569;"><?= h($school->city ?: 'N/A') ?></td>
                                    <td>
                                        <span style="background: #f0fdfa; color: #0f766e; padding: 3px 8px; border-radius: 4px; font-size: 0.75rem; font-weight: 700; border: 1px solid #ccfbf1;">
                                            <?= h($school->country ?: 'Global') ?>
                                        </span>
                                    </td>
                                    <td style="color: #64748b; font-size: 0.82rem; max-width: 250px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?= h($school->address ?: 'N/A') ?></td>
                                    <td style="text-align: right;">
                                        <a href="/schools/international/detail?id=<?= $school->id ?>" style="color: #0f766e; font-weight: 700; font-size: 0.82rem; text-decoration: none; display: inline-flex; align-items: center; gap: 4px;">
                                            View <i class="fa fa-arrow-right" style="font-size: 10px;"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" style="text-align: center; padding: 3rem; color: #94a3b8;">
                                    No international schools found matching your search criteria.
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
            $basePaginationUrl = '/schools/international?' . ($queryString ? $queryString . '&' : '');
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
