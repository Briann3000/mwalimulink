<?php
// views/international_school_search.php - Modern International School Directory with Multi-Parameter Sorting
$per_page = intval($_GET['per_page'] ?? 20);
if (!in_array($per_page, [20, 50, 100])) $per_page = 20;

$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');
$countryFilter = trim($_GET['country'] ?? '');
$cityFilter = trim($_GET['city'] ?? '');
$sort = trim($_GET['sort'] ?? 'name_asc');

$allowedSorts = [
    'name_asc'     => 'name ASC',
    'name_desc'    => 'name DESC',
    'country_asc'  => 'country ASC, name ASC',
    'country_desc' => 'country DESC, name ASC',
    'city_asc'     => 'city ASC, name ASC',
    'city_desc'    => 'city DESC, name ASC'
];

$orderBySql = $allowedSorts[$sort] ?? 'name ASC';
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
    ? R::findAll('international_school', "$whereSql ORDER BY $orderBySql LIMIT ? OFFSET ?", $queryParams)
    : R::findAll('international_school', "ORDER BY $orderBySql LIMIT ? OFFSET ?", [$per_page, $offset]);

$total_pages = max(1, ceil($total / $per_page));
$from_count = ($total > 0) ? $offset + 1 : 0;
$to_count = min($offset + count($schools), $total);

$pageQuery = $_GET;
unset($pageQuery['page']);
$queryString = http_build_query($pageQuery);
$basePaginationUrl = '/schools/international?' . ($queryString ? $queryString . '&' : '');

$perPageQuery = $_GET;
unset($perPageQuery['per_page'], $perPageQuery['page']);
$perPageQueryString = http_build_query($perPageQuery);
$basePerPageUrl = '/schools/international?' . ($perPageQueryString ? $perPageQueryString . '&' : '');

$authUser = auth_user();

function intl_sort_link($colKey, $label, $currentSort) {
    $params = $_GET;
    unset($params['page']);
    
    $isCurrent = str_starts_with($currentSort, $colKey);
    $isAsc = $currentSort === $colKey . '_asc';
    
    if ($isCurrent && $isAsc) {
        $nextSort = $colKey . '_desc';
        $icon = '<i class="fa fa-sort-up" style="color: #0f766e; margin-left: 4px;"></i>';
    } elseif ($isCurrent && !$isAsc) {
        $nextSort = $colKey . '_asc';
        $icon = '<i class="fa fa-sort-down" style="color: #0f766e; margin-left: 4px;"></i>';
    } else {
        $nextSort = $colKey . '_asc';
        $icon = '<i class="fa fa-sort" style="color: #94a3b8; margin-left: 4px; opacity: 0.6;"></i>';
    }
    
    $params['sort'] = $nextSort;
    $url = '/schools/international?' . http_build_query($params);
    return '<a href="' . h($url) . '" style="color: inherit; text-decoration: none; display: inline-flex; align-items: center;">' . h($label) . ' ' . $icon . '</a>';
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <!-- Main Content Pane -->
    <main class="content-pane" style="<?= !$authUser ? 'max-width: 1400px; width: 100%; margin: 0 auto; padding: 2rem 1.5rem; box-sizing: border-box;' : '' ?>">
        <div style="margin-bottom: 1.5rem;">
            <h2 style="margin: 0 0 4px; font-size: 1.4rem; color: #0f172a;">🌍 International Schools Directory</h2>
            <p style="margin: 0; font-size: 0.85rem; color: #64748b;">
                Directory of accredited international, IB, and British curriculum institutions globally and across East Africa.
            </p>
        </div>

        <!-- Filter Search Bar -->
        <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.25rem; margin-bottom: 1.5rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
            <form method="GET" action="/schools/international" style="margin: 0;">
                <input type="hidden" name="per_page" value="<?= $per_page ?>">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 10px; align-items: flex-end;">
                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">School / Keyword</label>
                        <input type="text" name="search" value="<?= h($search) ?>" placeholder="School name or address..." style="width: 100%; box-sizing: border-box; margin: 0; height: 38px;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Country</label>
                        <input type="text" name="country" value="<?= h($countryFilter) ?>" placeholder="e.g. Kenya, UK, UAE, USA..." style="width: 100%; box-sizing: border-box; margin: 0; height: 38px;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">City / Town</label>
                        <input type="text" name="city" value="<?= h($cityFilter) ?>" placeholder="e.g. Nairobi, London, Dubai..." style="width: 100%; box-sizing: border-box; margin: 0; height: 38px;">
                    </div>

                    <div>
                        <label style="font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; display: block;">Sort By</label>
                        <select name="sort" style="width: 100%; box-sizing: border-box; margin: 0; height: 38px;">
                            <option value="name_asc" <?= ($sort === 'name_asc') ? 'selected' : '' ?>>School Name (A &rarr; Z)</option>
                            <option value="name_desc" <?= ($sort === 'name_desc') ? 'selected' : '' ?>>School Name (Z &rarr; A)</option>
                            <option value="country_asc" <?= ($sort === 'country_asc') ? 'selected' : '' ?>>Country (A &rarr; Z)</option>
                            <option value="country_desc" <?= ($sort === 'country_desc') ? 'selected' : '' ?>>Country (Z &rarr; A)</option>
                            <option value="city_asc" <?= ($sort === 'city_asc') ? 'selected' : '' ?>>City (A &rarr; Z)</option>
                        </select>
                    </div>

                    <div style="display: flex; gap: 6px;">
                        <button type="submit" class="btn-primary" style="height: 38px; flex: 1; margin: 0; display: inline-flex; align-items: center; justify-content: center; gap: 6px;">
                            <i class="fa fa-filter"></i> Apply
                        </button>
                        <?php if ($search || $countryFilter || $cityFilter || $sort !== 'name_asc'): ?>
                            <a href="/schools/international?per_page=<?= $per_page ?>" style="height: 38px; padding: 0 14px; background: #f1f5f9; color: #475569; border-radius: 6px; font-weight: 600; font-size: 0.82rem; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; border: 1px solid #cbd5e1; box-sizing: border-box;">Reset</a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- Summary & Per-Page Controls -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 10px;">
            <span style="font-size: 0.85rem; color: #64748b;">
                Showing <strong><?= number_format($from_count) ?></strong> to <strong><?= number_format($to_count) ?></strong> of <strong><?= number_format($total) ?></strong> institutions
            </span>

            <div style="display: flex; align-items: center; gap: 8px;">
                <label style="font-size: 0.82rem; color: #475569; font-weight: 600; margin: 0;">Show:</label>
                <select onchange="location.href=this.value" style="padding: 4px 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; background: white; margin: 0; width: auto; height: 34px;">
                    <option value="<?= $basePerPageUrl ?>per_page=20" <?= ($per_page == 20) ? 'selected' : '' ?>>20 per page</option>
                    <option value="<?= $basePerPageUrl ?>per_page=50" <?= ($per_page == 50) ? 'selected' : '' ?>>50 per page</option>
                    <option value="<?= $basePerPageUrl ?>per_page=100" <?= ($per_page == 100) ? 'selected' : '' ?>>100 per page</option>
                </select>
            </div>
        </div>

        <!-- Directory Table Card -->
        <div class="mwalimu-table-card">
            <div style="overflow-x: auto;">
                <table class="mwalimu-table">
                    <thead>
                        <tr>
                            <th><?= intl_sort_link('name', 'Institution Name', $sort) ?></th>
                            <th><?= intl_sort_link('city', 'City', $sort) ?></th>
                            <th><?= intl_sort_link('country', 'Country', $sort) ?></th>
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

        <!-- Full Multi-Page Pagination Control -->
        <?php if ($total_pages > 1): ?>
            <?php
                $start_page = max(1, $page - 2);
                $end_page = min($total_pages, $page + 2);
            ?>
            <div style="display: flex; justify-content: center; align-items: center; gap: 6px; margin-top: 2rem; flex-wrap: wrap;">
                <?php if ($page > 1): ?>
                    <a href="<?= $basePaginationUrl ?>page=1" style="padding: 7px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none; font-weight: 600;">
                        &laquo; First
                    </a>
                    <a href="<?= $basePaginationUrl ?>page=<?= $page - 1 ?>" style="padding: 7px 14px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none; font-weight: 600;">
                        &lsaquo; Prev
                    </a>
                <?php endif; ?>

                <?php if ($start_page > 1): ?>
                    <span style="padding: 7px 6px; color: #94a3b8;">...</span>
                <?php endif; ?>

                <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <?php if ($i == $page): ?>
                        <span style="padding: 7px 14px; background: #0f766e; color: white; border-radius: 6px; font-size: 0.82rem; font-weight: 800; border: 1px solid #0f766e;">
                            <?= $i ?>
                        </span>
                    <?php else: ?>
                        <a href="<?= $basePaginationUrl ?>page=<?= $i ?>" style="padding: 7px 14px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none; font-weight: 600;">
                            <?= $i ?>
                        </a>
                    <?php endif; ?>
                <?php endfor; ?>

                <?php if ($end_page < $total_pages): ?>
                    <span style="padding: 7px 6px; color: #94a3b8;">...</span>
                <?php endif; ?>

                <?php if ($page < $total_pages): ?>
                    <a href="<?= $basePaginationUrl ?>page=<?= $page + 1 ?>" style="padding: 7px 14px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none; font-weight: 600;">
                        Next &rsaquo;
                    </a>
                    <a href="<?= $basePaginationUrl ?>page=<?= $total_pages ?>" style="padding: 7px 12px; background: white; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.82rem; color: #334155; text-decoration: none; font-weight: 600;">
                        Last &raquo;
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    </main>
</div>

