<?php
// views/elibrary_catalog.php - E-Library & Teaching Resources Catalog
if (session_status() === PHP_SESSION_NONE) {
  init_session();
}
$authUser = auth_user();
$userType = $authUser['role'] ?? null;
$userId = $authUser['user_id'] ?? null;

// Get available unlock credits if logged in
$availableUnlocks = 0;
if ($authUser && in_array($userType, ['teacher', 'school'])) {
  $availableUnlocks = get_user_elibrary_credits($userType, $userId);
}

// Cart items count
$cartCount = count($_SESSION['elibrary_cart'] ?? []);

// Request parameters
$searchQ = trim($_GET['q'] ?? '');
$filterTier = trim($_GET['tier'] ?? '');
$filterGrade = trim($_GET['grade'] ?? '');
$filterCategory = trim($_GET['category'] ?? '');
$filterSubject = trim($_GET['subject'] ?? '');
$filterTerm = trim($_GET['term'] ?? '');
$filterPrice = trim($_GET['price'] ?? '');
$sort = trim($_GET['sort'] ?? 'latest');

// Build query
$where = ['status = ?'];
$bindings = ['approved'];

if (!empty($searchQ)) {
  $where[] = '(title LIKE ? OR description LIKE ? OR subject LIKE ?)';
  $searchTerm = "%{$searchQ}%";
  $bindings[] = $searchTerm;
  $bindings[] = $searchTerm;
  $bindings[] = $searchTerm;
}

if (!empty($filterTier)) {
  $where[] = 'curriculum_tier = ?';
  $bindings[] = $filterTier;
}

if (!empty($filterGrade)) {
  $where[] = 'grade_level = ?';
  $bindings[] = $filterGrade;
}

if (!empty($filterCategory)) {
  $where[] = 'category = ?';
  $bindings[] = $filterCategory;
}

if (!empty($filterSubject)) {
  $where[] = 'subject = ?';
  $bindings[] = $filterSubject;
}

if (!empty($filterTerm)) {
  $where[] = 'term = ?';
  $bindings[] = $filterTerm;
}

if ($filterPrice === 'free') {
  $where[] = 'price <= 0';
} elseif ($filterPrice === 'paid') {
  $where[] = 'price > 0';
}

$orderBy = 'created_at DESC';
if ($sort === 'popular') {
  $orderBy = 'download_count DESC, views_count DESC';
} elseif ($sort === 'price_low') {
  $orderBy = 'price ASC';
} elseif ($sort === 'price_high') {
  $orderBy = 'price DESC';
}

$whereSql = implode(' AND ', $where);
$totalResources = R::count('resource', $whereSql, $bindings);

// Pagination
$page = max(1, (int) ($_GET['page'] ?? 1));
$limit = 18;
$offset = ($page - 1) * $limit;
$totalPages = max(1, ceil($totalResources / $limit));

$resources = R::findAll('resource', "{$whereSql} ORDER BY {$orderBy} LIMIT ? OFFSET ?", array_merge($bindings, [$limit, $offset]));

$grades = ['PP1', 'PP2', 'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6', 'Grade 7', 'Grade 8', 'Grade 9', 'Form 1', 'Form 2', 'Form 3', 'Form 4', 'General'];
$categories = ['Notes', 'Revision Papers', 'Schemes of Work', 'Lesson Plans', 'Curriculum Designs', 'Set Book Guides', 'Ebooks'];
$tiers = ['Junior School', 'Primary', 'Secondary', 'Pre-Primary', 'Teacher Tools'];
?>

<style>
  .mwalimu-elibrary-container {
    max-width: 1320px;
    margin: 0 auto;
    padding: 1.5rem 1rem 3rem 1rem;
    color: #1e293b;
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
  }

  .mwalimu-elibrary-container form,
  .mwalimu-elibrary-container input,
  .mwalimu-elibrary-container select,
  .mwalimu-elibrary-container button,
  .mwalimu-elibrary-container label {
    margin: 0;
    line-height: normal;
    font-family: inherit;
    box-sizing: border-box;
  }

  .elib-hero {
    background: linear-gradient(135deg, #0f172a 0%, #0f766e 100%);
    border-radius: 16px;
    padding: 2.2rem 2rem;
    color: #ffffff;
    box-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.25);
    margin-bottom: 2rem;
  }

  .elib-hero h1 {
    font-size: 1.85rem;
    font-weight: 900;
    color: #ffffff !important;
    margin: 0 0 0.5rem 0;
  }

  .elib-hero p {
    color: #cbd5e1;
    font-size: 0.95rem;
    max-width: 700px;
    margin: 0 0 1.5rem 0;
    line-height: 1.5;
  }

  .elib-search-bar {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    background: #ffffff;
    padding: 6px;
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  .elib-search-input {
    flex: 1 1 240px;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 10px 14px !important;
    font-size: 0.88rem !important;
    color: #0f172a !important;
    background: #ffffff !important;
    height: 42px !important;
  }

  .elib-select {
    flex: 0 1 160px;
    border: 1px solid #e2e8f0 !important;
    border-radius: 8px !important;
    padding: 8px 12px !important;
    font-size: 0.84rem !important;
    color: #0f172a !important;
    background: #ffffff !important;
    height: 42px !important;
  }

  .elib-btn-search {
    background: #0f766e !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 8px !important;
    padding: 0 20px !important;
    font-weight: 700 !important;
    font-size: 0.88rem !important;
    height: 42px !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
  }

  .elib-btn-search:hover {
    background: #0d9488 !important;
  }

  .elib-layout {
    display: flex;
    flex-direction: column;
    gap: 1.5rem;
  }

  @media (min-width: 992px) {
    .elib-layout {
      flex-direction: row;
      align-items: flex-start;
    }
  }

  .elib-sidebar {
    width: 100%;
    flex-shrink: 0;
  }

  @media (min-width: 992px) {
    .elib-sidebar {
      width: 280px;
      position: sticky;
      top: 80px;
    }
  }

  .elib-filter-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    margin-bottom: 1rem;
  }

  .elib-filter-title {
    font-size: 0.85rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    margin-bottom: 0.85rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .elib-filter-list {
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 240px;
    overflow-y: auto;
    padding-right: 4px;
  }

  .elib-filter-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 6px 10px;
    border-radius: 6px;
    font-size: 0.82rem;
    color: #334155;
    text-decoration: none;
    border: 1px solid transparent;
  }

  .elib-filter-item:hover {
    background: #f1f5f9;
    color: #0f766e;
  }

  .elib-filter-item.active {
    background: #f0fdf4;
    color: #15803d;
    font-weight: 700;
    border-color: #bbf7d0;
  }

  .elib-content {
    flex: 1;
    min-width: 0;
  }

  .elib-toolbar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 10px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 10px;
    padding: 10px 16px;
    margin-bottom: 1.25rem;
  }

  .elib-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1rem;
  }

  .res-card {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: transform 0.2s, box-shadow 0.2s;
  }

  .res-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px -4px rgba(0, 0, 0, 0.08);
  }

  .res-card-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.65rem;
  }

  .res-grade-pill {
    font-size: 0.72rem;
    font-weight: 800;
    background: #e0f2fe;
    color: #0369a1;
    padding: 3px 8px;
    border-radius: 6px;
    border: 1px solid #bae6fd;
  }

  .res-price-pill {
    font-size: 0.75rem;
    font-weight: 900;
    padding: 3px 9px;
    border-radius: 6px;
  }

  .res-price-pill.free {
    background: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
  }

  .res-price-pill.paid {
    background: #fef3c7;
    color: #b45309;
    border: 1px solid #fde68a;
  }

  .res-title {
    font-size: 0.92rem;
    font-weight: 700;
    color: #0f172a;
    line-height: 1.35;
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    min-height: 2.5rem;
  }

  .res-title a {
    color: inherit;
    text-decoration: none;
  }

  .res-title a:hover {
    color: #0f766e;
  }

  .res-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 0.76rem;
    color: #64748b;
    margin-bottom: 0.85rem;
    padding-bottom: 0.65rem;
    border-bottom: 1px solid #f1f5f9;
  }

  .res-uploader-tag {
    font-size: 0.74rem;
    color: #475569;
    display: flex;
    align-items: center;
    gap: 5px;
    margin-bottom: 0.85rem;
  }

  .res-actions {
    display: grid;
    gap: 6px;
    margin-top: auto;
  }

  .res-actions.paid-grid {
    grid-template-columns: 1fr 1fr 38px;
  }

  .res-actions.free-grid {
    grid-template-columns: 1fr 38px;
  }

  .res-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 5px;
    height: 36px;
    font-size: 0.78rem;
    font-weight: 700;
    border-radius: 8px;
    text-decoration: none;
    border: none;
    cursor: pointer;
    padding: 0 8px;
    box-sizing: border-box;
  }

  .res-btn-cart {
    background: #0f172a !important;
    color: #ffffff !important;
    border: 1px solid #334155 !important;
  }

  .res-btn-cart:hover {
    background: #1e293b !important;
    color: #2dd4bf !important;
  }

  .res-btn-buy {
    background: #0f766e !important;
    color: #ffffff !important;
  }

  .res-btn-buy:hover {
    background: #0d9488 !important;
    color: #ffffff !important;
  }

  .res-btn-download {
    background: #15803d !important;
    color: #ffffff !important;
  }

  .res-btn-download:hover {
    background: #166534 !important;
    color: #ffffff !important;
  }

  .res-btn-share {
    background: #f8fafc;
    color: #64748b;
    border: 1px solid #e2e8f0;
    padding: 0;
  }

  .res-btn-share:hover {
    background: #f0fdf4;
    color: #15803d;
    border-color: #bbf7d0;
  }

  .res-share-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    display: none;
    align-items: center;
    justify-content: center;
    z-index: 99999;
    padding: 1rem;
  }

  .res-share-modal-overlay.active {
    display: flex;
  }

  .res-share-modal {
    background: #ffffff;
    border-radius: 16px;
    width: 100%;
    max-width: 420px;
    padding: 1.5rem;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.2);
  }

  .btn-modal-action {
    color: #ffffff !important;
    font-weight: 800 !important;
    font-size: 0.88rem !important;
    height: 40px !important;
    border-radius: 8px !important;
    display: inline-flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 8px !important;
    text-decoration: none !important;
    border: none !important;
    cursor: pointer !important;
    width: 100% !important;
  }
</style>

<div class="mwalimu-elibrary-container">

  <!-- Sticky Top Breadcrumbs & Cart Indicator -->
  <div
    style="position: sticky; top: 75px; z-index: 99; background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(8px); padding: 8px 12px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 4px 12px rgba(0,0,0,0.04); display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; flex-wrap: wrap; gap: 8px;">
    <div style="font-size: 0.8rem; color: #64748b; display: flex; align-items: center; gap: 6px;">
      <a href="/" style="color: #64748b; text-decoration: none;"><i class="fa fa-home"></i> Home</a>
      <span>/</span>
      <span style="color: #0f766e; font-weight: 700;">E-Library & Teaching Resources</span>
    </div>

    <!-- Active Cart Sticky Trigger -->
    <div>
      <a href="/elibrary/cart"
        style="display: inline-flex; align-items: center; gap: 8px; background: #0f172a; color: #ffffff !important; padding: 7px 16px; border-radius: 20px; font-size: 0.82rem; font-weight: 800; text-decoration: none; border: 1px solid #334155; box-shadow: 0 2px 6px rgba(0,0,0,0.15);">
        <i class="fa fa-cart-shopping" style="color: #2dd4bf;"></i>
        <span style="color: #ffffff !important;">Cart</span>
        <span id="cartCountBadge"
          style="background: #0f766e; color: #ffffff !important; padding: 2px 8px; border-radius: 10px; font-size: 0.72rem; font-weight: 900;"><?= $cartCount ?></span>
      </a>
    </div>
  </div>

  <!-- Hero Banner with Live Search -->
  <div class="elib-hero">
    <div style="max-width: 800px;">
      <h1>E-Library & Teaching Materials Hub</h1>
      <p>Access verified Competency-Based Curriculum (CBC) notes, revision past papers with marking schemes, lesson
        plans, and schemes of work for Kenyan schools and educators.</p>
    </div>

    <!-- Search Form -->
    <form action="/elibrary" method="GET" class="elib-search-bar">
      <input type="text" name="q" value="<?= h($searchQ) ?>" placeholder="Search notes, revision exams, schemes..."
        class="elib-search-input">

      <select name="grade" class="elib-select">
        <option value="">All Grades</option>
        <?php foreach ($grades as $g): ?>
          <option value="<?= h($g) ?>" <?= ($filterGrade === $g) ? 'selected' : '' ?>><?= h($g) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="category" class="elib-select">
        <option value="">All Categories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= h($cat) ?>" <?= ($filterCategory === $cat) ? 'selected' : '' ?>><?= h($cat) ?></option>
        <?php endforeach; ?>
      </select>

      <button type="submit" class="elib-btn-search">
        <i class="fa fa-search"></i> Find Resources
      </button>
    </form>
  </div>

  <!-- Unlocks Banner -->
  <?php if ($authUser && in_array($userType, ['teacher', 'school'])): ?>
    <div
      style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 12px; padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 1.5rem;">
      <div style="display: flex; align-items: center; gap: 10px;">
        <span
          style="background: #15803d; color: white; font-weight: 800; font-size: 0.82rem; padding: 4px 10px; border-radius: 20px;">
          <i class="fa fa-key"></i> <?= (int) $availableUnlocks ?> Download Unlocks Available
        </span>
        <span style="font-size: 0.85rem; color: #166534;">Use your unlocks to download any premium paper instantly without
          paying via M-Pesa.</span>
      </div>
      <div>
        <a href="<?= ($userType === 'teacher') ? '/teacher/elibrary' : '/school/elibrary' ?>" class="res-btn"
          style="background: #15803d; color: white !important; padding: 6px 14px; font-size: 0.82rem;">
          <i class="fa fa-cloud-arrow-up"></i> Upload Material (+3 Unlocks)
        </a>
      </div>
    </div>
  <?php endif; ?>

  <!-- Main Split Layout -->
  <div class="elib-layout">

    <!-- Left Filter Sidebar -->
    <aside class="elib-sidebar">
      <div class="elib-filter-card">
        <div class="elib-filter-title">Curriculum Level</div>
        <div class="elib-filter-list">
          <a href="<?= build_elib_url(['tier' => '']) ?>"
            class="elib-filter-item <?= empty($filterTier) ? 'active' : '' ?>">All Levels</a>
          <?php foreach ($tiers as $t): ?>
            <a href="<?= build_elib_url(['tier' => $t]) ?>"
              class="elib-filter-item <?= ($filterTier === $t) ? 'active' : '' ?>"><?= h($t) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="elib-filter-card">
        <div class="elib-filter-title">Resource Type</div>
        <div class="elib-filter-list">
          <a href="<?= build_elib_url(['category' => '']) ?>"
            class="elib-filter-item <?= empty($filterCategory) ? 'active' : '' ?>">All Categories</a>
          <?php foreach ($categories as $cat): ?>
            <a href="<?= build_elib_url(['category' => $cat]) ?>"
              class="elib-filter-item <?= ($filterCategory === $cat) ? 'active' : '' ?>"><?= h($cat) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="elib-filter-card">
        <div class="elib-filter-title">Grade Level</div>
        <div class="elib-filter-list">
          <a href="<?= build_elib_url(['grade' => '']) ?>"
            class="elib-filter-item <?= empty($filterGrade) ? 'active' : '' ?>">All Grades</a>
          <?php foreach ($grades as $g): ?>
            <a href="<?= build_elib_url(['grade' => $g]) ?>"
              class="elib-filter-item <?= ($filterGrade === $g) ? 'active' : '' ?>"><?= h($g) ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="elib-filter-card">
        <div class="elib-filter-title">Access Type</div>
        <div class="elib-filter-list">
          <a href="<?= build_elib_url(['price' => '']) ?>"
            class="elib-filter-item <?= empty($filterPrice) ? 'active' : '' ?>">All Resources</a>
          <a href="<?= build_elib_url(['price' => 'free']) ?>"
            class="elib-filter-item <?= ($filterPrice === 'free') ? 'active' : '' ?>">🎉 Free Downloads</a>
          <a href="<?= build_elib_url(['price' => 'paid']) ?>"
            class="elib-filter-item <?= ($filterPrice === 'paid') ? 'active' : '' ?>">💎 Premium / Verified</a>
        </div>
      </div>
    </aside>

    <!-- Right Resource Catalog -->
    <main class="elib-content">
      <div class="elib-toolbar">
        <div style="font-size: 0.88rem; color: #475569;">
          Showing <strong><?= count($resources) ?></strong> of <strong><?= $totalResources ?></strong> learning
          materials
        </div>

        <div style="display: flex; align-items: center; gap: 8px;">
          <label style="font-size: 0.82rem; color: #64748b; margin: 0;">Sort:</label>
          <select
            onchange="window.location.href = '<?= build_elib_url(['sort' => 'PLACEHOLDER']) ?>'.replace('PLACEHOLDER', this.value)"
            class="elib-select" style="height: 34px !important; padding: 4px 10px !important; font-size: 0.8rem;">
            <option value="latest" <?= ($sort === 'latest') ? 'selected' : '' ?>>Newest First</option>
            <option value="popular" <?= ($sort === 'popular') ? 'selected' : '' ?>>Most Downloaded</option>
            <option value="price_low" <?= ($sort === 'price_low') ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="price_high" <?= ($sort === 'price_high') ? 'selected' : '' ?>>Price: High to Low</option>
          </select>
        </div>
      </div>

      <?php if (empty($resources)): ?>
        <div
          style="text-align: center; background: white; border: 1px dashed #cbd5e1; border-radius: 12px; padding: 3rem 1.5rem;">
          <i class="fa fa-folder-open" style="font-size: 2.5rem; color: #cbd5e1; margin-bottom: 0.5rem;"></i>
          <h3 style="color: #334155; margin-bottom: 0.5rem;">No resources found</h3>
          <p style="color: #64748b; font-size: 0.9rem; margin-bottom: 1.25rem;">Try adjusting your filters or search
            keywords.</p>
          <a href="/elibrary" class="res-btn"
            style="background: #0f766e; color: white !important; display: inline-flex; padding: 0 16px;">Clear Filters</a>
        </div>
      <?php else: ?>
        <div class="elib-grid">
          <?php foreach ($resources as $res):
            $isFree = (float) $res->price <= 0;
            $hasUnlocked = user_has_unlocked_resource($res->id, $userId, $userType);

            // Clean author display
            $authorLabel = 'Verified Educator';
            $isSchool = ($res->uploader_type === 'school');
            if ($isSchool && !empty($res->uploader_name) && !str_contains($res->uploader_name, 'Admin')) {
              $authorLabel = $res->uploader_name;
            }
            ?>
            <div class="res-card">
              <div>
                <div class="res-card-top">
                  <span class="res-grade-pill"><?= h($res->grade_level ?: 'CBC') ?></span>
                  <?php if ($isFree): ?>
                    <span class="res-price-pill free">FREE</span>
                  <?php else: ?>
                    <span class="res-price-pill paid">KES <?= number_format($res->price, 0) ?></span>
                  <?php endif; ?>
                </div>

                <h3 class="res-title" title="<?= h($res->title) ?>">
                  <a href="/elibrary/view?id=<?= $res->id ?>"><?= h($res->title) ?></a>
                </h3>

                <div class="res-meta">
                  <span><i class="fa fa-book" style="color: #0f766e;"></i> <?= h($res->subject ?: 'General') ?></span>
                  <span><?= h($res->category) ?></span>
                </div>

                <div class="res-uploader-tag">
                  <?php if ($isSchool): ?>
                    <i class="fa fa-school" style="color: #0369a1;"></i>
                    <span><strong><?= h($authorLabel) ?></strong></span>
                  <?php else: ?>
                    <i class="fa fa-graduation-cap" style="color: #0f766e;"></i>
                    <span>Verified Educator</span>
                  <?php endif; ?>
                </div>
              </div>

              <!-- Action buttons -->
              <?php if ($isFree || $hasUnlocked): ?>
                <div class="res-actions free-grid">
                  <a href="/elibrary/view?id=<?= $res->id ?>#access" class="res-btn res-btn-download">
                    <i class="fa fa-download"></i> Get Free File
                  </a>
                  <button type="button" class="res-btn res-btn-share"
                    onclick="openElibShareModal(<?= $res->id ?>, '<?= addslashes(h($res->title)) ?>', '<?= addslashes(h($res->grade_level)) ?>')"
                    title="Share">
                    <i class="fa fa-share-nodes"></i>
                  </button>
                </div>
              <?php else: ?>
                <div class="res-actions paid-grid">
                  <button type="button" class="res-btn res-btn-cart" onclick="addToCart(<?= $res->id ?>)" title="Add to Cart">
                    <i class="fa fa-cart-plus"></i> Cart
                  </button>
                  <a href="/elibrary/view?id=<?= $res->id ?>#access" class="res-btn res-btn-buy">
                    <i class="fa fa-bolt"></i> Buy (<?= number_format($res->price, 0) ?>)
                  </a>
                  <button type="button" class="res-btn res-btn-share"
                    onclick="openElibShareModal(<?= $res->id ?>, '<?= addslashes(h($res->title)) ?>', '<?= addslashes(h($res->grade_level)) ?>')"
                    title="Share">
                    <i class="fa fa-share-nodes"></i>
                  </button>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div style="display: flex; justify-content: center; gap: 6px; margin-top: 2rem; flex-wrap: wrap;">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
              <a href="<?= build_elib_url(['page' => $i]) ?>" class="res-btn"
                style="<?= ($page === $i) ? 'background: #0f766e; color: white !important;' : 'background: #ffffff; color: #334155; border: 1px solid #e2e8f0;' ?> width: 38px; padding: 0;">
                <?= $i ?>
              </a>
            <?php endfor; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </main>
  </div>
</div>

<!-- Share Modal with High-Contrast Buttons -->
<div id="elibShareModalOverlay" class="res-share-modal-overlay" onclick="closeElibShareModal()">
  <div class="res-share-modal" onclick="event.stopPropagation()">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
      <h3 style="font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0;">Share Resource</h3>
      <button type="button" onclick="closeElibShareModal()"
        style="background: none; border: none; font-size: 1.25rem; cursor: pointer; color: #64748b;">&times;</button>
    </div>
    <p id="elibShareModalTitle" style="font-size: 0.85rem; color: #475569; margin-bottom: 1.25rem; font-weight: 600;">
    </p>

    <div style="display: flex; flex-direction: column; gap: 10px;">
      <a id="elibShareWhatsAppBtn" href="#" target="_blank" class="btn-modal-action"
        style="background: #25d366 !important;">
        <i class="fa-brands fa-whatsapp"></i> Share on WhatsApp
      </a>
      <button type="button" onclick="copyElibLink()" id="elibCopyBtn" class="btn-modal-action"
        style="background: #0f766e !important;">
        <i class="fa fa-copy"></i> <span id="elibCopyBtnText">Copy Direct Link</span>
      </button>
    </div>
  </div>
</div>

<script>
  let activeShareUrl = '';

  function addToCart(id) {
    fetch('/api/elibrary?action=add_to_cart&id=' + id, { method: 'POST' })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('cartCountBadge').textContent = data.cart_count;
          alert('Resource added to cart! You can continue browsing or click Cart at the top to checkout.');
        }
      });
  }

  function openElibShareModal(id, title, grade) {
    activeShareUrl = window.location.origin + '/elibrary/view?id=' + id;
    document.getElementById('elibShareModalTitle').textContent = title + ' (' + grade + ')';

    const waText = encodeURIComponent('Check out this teaching resource on MwalimuLink: ' + title + ' (' + grade + ')\n' + activeShareUrl);
    document.getElementById('elibShareWhatsAppBtn').href = 'https://api.whatsapp.com/send?text=' + waText;

    if (navigator.share && /mobile/i.test(navigator.userAgent)) {
      navigator.share({ title: title, text: title, url: activeShareUrl }).catch(() => { });
      return;
    }

    document.getElementById('elibShareModalOverlay').classList.add('active');
  }

  function closeElibShareModal() {
    document.getElementById('elibShareModalOverlay').classList.remove('active');
    document.getElementById('elibCopyBtnText').textContent = 'Copy Direct Link';
  }

  function copyElibLink() {
    if (activeShareUrl) {
      navigator.clipboard.writeText(activeShareUrl).then(() => {
        document.getElementById('elibCopyBtnText').textContent = 'Link Copied to Clipboard!';
        setTimeout(closeElibShareModal, 1200);
      });
    }
  }
</script>

<?php
function build_elib_url($overrides = [])
{
  $params = $_GET;
  foreach ($overrides as $k => $v) {
    if ($v === '' || $v === null)
      unset($params[$k]);
    else
      $params[$k] = $v;
  }
  if (!isset($overrides['page']))
    unset($params['page']);
  $qs = http_build_query($params);
  return '/elibrary' . ($qs ? '?' . $qs : '');
}
?>