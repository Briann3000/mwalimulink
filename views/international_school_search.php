<?php
// Assumes RedBeanPHP is set up and connected in the base layout

if (!function_exists('h')) {
    function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
}

$per_page = 10;
$page = max(1, intval($_GET['page'] ?? 1));
$search = trim($_GET['search'] ?? '');

$where = '';
$params = [];
if ($search !== '') {
    $where = "WHERE name LIKE ? OR city LIKE ? OR country LIKE ? OR address LIKE ?";
    $params = array_fill(0, 4, "%$search%");
}

$total = $where
    ? R::count('international_school', $where, $params)
    : R::count('international_school');
$offset = ($page - 1) * $per_page;

$records = $where
    ? R::findAll('international_school', "$where ORDER BY name LIMIT ? OFFSET ?", array_merge($params, [$per_page, $offset]))
    : R::findAll('international_school', "ORDER BY name LIMIT ? OFFSET ?", [$per_page, $offset]);

if (!function_exists('paginate')) {
    function paginate($total, $per_page, $page, $base_url) {
        $pages = max(1, ceil($total / $per_page));
        if ($pages <= 1) return;

    echo '<nav aria-label="Page navigation" class="mt-4">';
    echo '<ul class="pagination justify-content-center">';

    // First
    echo $page > 1
        ? '<li class="page-item"><a class="page-link" href="' . h($base_url) . '&page=1">First</a></li>'
        : '<li class="page-item disabled"><span class="page-link">First</span></li>';

    // Prev
    echo $page > 1
        ? '<li class="page-item"><a class="page-link" href="' . h($base_url) . '&page=' . ($page - 1) . '">Prev</a></li>'
        : '<li class="page-item disabled"><span class="page-link">Prev</span></li>';

    

    // Next
    echo $page < $pages
        ? '<li class="page-item"><a class="page-link" href="' . h($base_url) . '&page=' . ($page + 1) . '">Next</a></li>'
        : '<li class="page-item disabled"><span class="page-link">Next</span></li>';

    // Last
    echo $page < $pages
        ? '<li class="page-item"><a class="page-link" href="' . h($base_url) . '&page=' . $pages . '">Last</a></li>'
        : '<li class="page-item disabled"><span class="page-link">Last</span></li>';


// Current page info
//$schoolstotal = R::count('international_school');

    echo '<li class="page-item disabled"><span class="page-link">Showing ' . $page * 10 . ' of '. $total . '</span></li>';

    echo '</ul></nav>';
    }
}
?>

    
    <!-- KESH TEST POSITION RAIN -->
<!--
<article class="card" style="max-width: 60%; margin: 0 auto;">
<div class="card shadow-sm my-4">-->
<div class="container">
    <article class="card" style="margin: 2rem auto; max-width: 80%; padding: 1.5rem;">
  <div class="card-body">
    <h2 class="card-title mb-4 text-center">International School Search</h2>

    <form method="get" class="row g-2 align-items-center justify-content-center mb-4" action="">
      <input type="hidden" name="action" value="international_school_search" />
      <div class="col-sm-8 col-md-6">
        <input type="search" name="search" class="form-control" placeholder="Search name, city, country, address..." value="<?= h($search) ?>" aria-label="Search schools" />
      </div>
      <div class="col-auto">
        <button type="submit" class="btn btn-primary px-4">Search</button>
      </div>
      <?php if ($search !== ''): ?>
        <div class="col-auto">
          <a href="?action=international_school_search" class="btn btn-outline-secondary">Clear</a>
        </div>
      <?php endif; ?>
      
      <?php
      $base_url = "?action=international_school_search";
      if ($search !== '') {
          $base_url .= "&search=" . urlencode($search);
      }
      paginate($total, $per_page, $page, $base_url);
      ?>
      
    </form>

    <?php if ($total === 0): ?>
      <p class="text-center text-muted">No international schools found.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table table-striped table-hover align-middle">
          <thead class="table-light">
            <tr>
              <th>Name</th>
              <th>City</th>
              <th>Country</th>
              <th>Address</th>
              <th style="width: 100px;">Locate</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $rec): ?>
              <tr>
                <td><a href="?action=international_school_details&id=<?= $rec->id ?>"><?= h($rec->name) ?></a></td>
                <td><?= h($rec->city) ?></td>
                <td><?= h($rec->country) ?></td>
                <td><?= h($rec->address) ?></td>
                <td>
                  <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($rec->name . ', ' . $rec->city . ', ' . $rec->country) ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary w-100">
                    Locate
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <?php
      $base_url = "?action=international_school_search";
      if ($search !== '') {
          $base_url .= "&search=" . urlencode($search);
      }
      paginate($total, $per_page, $page, $base_url);
      ?>
    <?php endif; ?>
  </div>
</div>
</article>
