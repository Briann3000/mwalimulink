<?php
// private_school_search.php (partial for embedding in master layout)

// --- CONFIGURATION ---
$per_page = 20;

// --- GET PARAMETERS ---
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');

// --- BUILD QUERY ---
$offset = ($page - 1) * $per_page;

if ($search !== '') {
    // Count total for pagination with search
    $total = R::count('private_school',
        'name LIKE ? OR level LIKE ? OR county LIKE ? OR constituency LIKE ?',
        ["%$search%","%$search%", "%$search%", "%$search%"]);

    // Fetch schools with search
    $schools = R::find('private_school',
        'name LIKE ? OR level LIKE ?  OR county LIKE ? OR constituency LIKE ? ORDER BY name LIMIT ? OFFSET ?',
        ["%$search%","%$search%", "%$search%", "%$search%", $per_page, $offset]);
} else {
    // Count total for pagination without search
    $total = R::count('private_school');

    // Fetch schools without search
    $schools = R::find('private_school', 'ORDER BY name LIMIT ? OFFSET ?', [$per_page, $offset]);
}

$total_pages = max(1, ceil($total / $per_page));
?>

<h2>Search Private Schools</h2>
<form method="get" action="index.php">
    <input type="hidden" name="action" value="private_school_search">
    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" 
           placeholder="Search by name, county, or constituency">
    <button type="submit">Search</button>
</form>

<p>Showing <?= count($schools) ?> of <?= $total ?> schools<?= $search ? " for <b>".htmlspecialchars($search)."</b>" : "" ?></p>

<!-- LIST PRIMARY & SECONDARY by kesh -->
<!--
<small><i style="font-size:1vw;"><?php // $numPrimarySchools = R::count('private_school', 'level = ?', ['PRIMARY SCHOOL']);
// $numSecondarySchools = R::count('private_school', 'level = ?', ['SECONDARY SCHOOL']);
// echo "Total Primary Schools ".$numPrimarySchools." | Total Secondary Schools ".$numSecondarySchools;
?></i></small>-->
<!-- END LIST PRIMARY & SECONDARY -->


<style>
    table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
    th, td { padding: 0.5rem; border: 1px solid #ddd; text-align: left; }
    th { font-weight: bold; background: #f5f5f5; }
    .pagination { margin: 1rem 0; }
    .pagination a, .pagination span { 
        display: inline-block; 
        padding: 0.25rem 0.5rem;
        margin: 0 0.25rem;
        border: 1px solid #ddd;
    }
    .pagination .current { background: #007bff; color: white; border-color: #007bff; }
</style>

<table>
    <thead>
        <tr>
            <th>Name</th>
            <th>Level</th>
            <th>Status</th>
            <th>County</th>
            <th>Constituency</th>
            <th>Province</th>
            <th>District</th>
            <th>Division</th>
            <th>Location</th>
            <th>Email</th>
            <th>Latitude</th>
            <th>Longitude</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($schools as $school): ?>
        <tr>
            <td>
                <a href="?action=private_school_detail&id=<?= urlencode($school->id ?? '') ?>">
                    <?= htmlspecialchars($school->name ?? '') ?>
                </a>
            </td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->level ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->status ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->county ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->constituency ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->province ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->district ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->division ?? ''))) ?></td>
            <td><?= ucfirst(strtolower(htmlspecialchars($school->location ?? ''))) ?></td>
            <td><?= strtolower(htmlspecialchars($school->email ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($school->latitude ?? '')) ?></td>
            <td><?= htmlspecialchars((string)($school->longitude ?? '')) ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="pagination">
    <?php if ($page > 1): ?>
        <a href="?action=private_school_search&search=<?= urlencode($search) ?>&page=<?= $page-1 ?>">&laquo; Prev</a>
    <?php endif; ?>
    
    <?php for ($p = 1; $p <= $total_pages; $p++): ?>
        <?php if ($p == $page): ?>
            <span class="current"><?= $p ?></span>
        <?php elseif ($p == 1 || $p == $total_pages || abs($p - $page) <= 2): ?>
            <a href="?action=private_school_search&search=<?= urlencode($search) ?>&page=<?= $p ?>"><?= $p ?></a>
        <?php elseif ($p == $page - 3 || $p == $page + 3): ?>
            <span>...</span>
        <?php endif; ?>
    <?php endfor; ?>
    
    <?php if ($page < $total_pages): ?>
        <a href="?action=private_school_search&search=<?= urlencode($search) ?>&page=<?= $page+1 ?>">Next &raquo;</a>
        [ page <?= $page ?>  of <?= $total_pages ?> | <?= $total_pages * 20 ?> Schools]
    <?php endif; ?>
</div>
