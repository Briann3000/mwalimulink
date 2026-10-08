<?php
// views/api_elibrary.php - API endpoint for Cart actions & Search suggestions
if (session_status() === PHP_SESSION_NONE) {
  init_session();
}
header('Content-Type: application/json');

$action = $_GET['action'] ?? ($_POST['action'] ?? 'search');

// Initialize session cart array if needed
if (!isset($_SESSION['elibrary_cart']) || !is_array($_SESSION['elibrary_cart'])) {
  $_SESSION['elibrary_cart'] = [];
}

// 1. ADD TO CART
if ($action === 'add_to_cart') {
  $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
  if ($id > 0) {
    $res = R::load('resource', $id);
    if ($res && $res->id) {
      if (!in_array($id, $_SESSION['elibrary_cart'])) {
        $_SESSION['elibrary_cart'][] = $id;
      }
      echo json_encode([
        'success' => true,
        'message' => 'Added to cart',
        'cart_count' => count($_SESSION['elibrary_cart'])
      ]);
      exit();
    }
  }
  echo json_encode(['success' => false, 'error' => 'Resource not found']);
  exit();
}

// 2. REMOVE FROM CART
if ($action === 'remove_from_cart') {
  $id = (int) ($_POST['id'] ?? ($_GET['id'] ?? 0));
  if ($id > 0) {
    $_SESSION['elibrary_cart'] = array_values(array_diff($_SESSION['elibrary_cart'], [$id]));
    echo json_encode([
      'success' => true,
      'cart_count' => count($_SESSION['elibrary_cart'])
    ]);
    exit();
  }
  echo json_encode(['success' => false]);
  exit();
}

// 3. GET CART
if ($action === 'get_cart') {
  $items = [];
  $total = 0.00;
  if (!empty($_SESSION['elibrary_cart'])) {
    $ids = array_map('intval', $_SESSION['elibrary_cart']);
    $slots = R::genSlots($ids);
    $resources = R::find('resource', " id IN ({$slots}) ", $ids);
    foreach ($resources as $r) {
      $p = (float) $r->price;
      $total += $p;
      $items[] = [
        'id' => $r->id,
        'title' => $r->title,
        'grade' => $r->grade_level,
        'subject' => $r->subject,
        'price' => $p
      ];
    }
  }
  echo json_encode([
    'cart_count' => count($_SESSION['elibrary_cart']),
    'total_amount' => $total,
    'items' => $items
  ]);
  exit();
}

// 4. CLEAR CART
if ($action === 'clear_cart') {
  $_SESSION['elibrary_cart'] = [];
  echo json_encode(['success' => true, 'cart_count' => 0]);
  exit();
}

// 5. SEARCH AUTOCOMPLETE
if ($action === 'search') {
  $q = trim($_GET['q'] ?? '');
  if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit();
  }

  $searchTerm = "%{$q}%";
  $results = R::findAll('resource', ' status = ? AND (title LIKE ? OR subject LIKE ? OR description LIKE ?) ORDER BY download_count DESC LIMIT 8 ', [
    'approved',
    $searchTerm,
    $searchTerm,
    $searchTerm
  ]);

  $output = [];
  foreach ($results as $r) {
    $output[] = [
      'id' => $r->id,
      'title' => $r->title,
      'grade_level' => $r->grade_level,
      'subject' => $r->subject,
      'category' => $r->category,
      'price' => (float) $r->price,
      'url' => '/elibrary/view?id=' . $r->id
    ];
  }

  echo json_encode(['results' => $output]);
  exit();
}

echo json_encode(['status' => 'ok', 'cart_count' => count($_SESSION['elibrary_cart'] ?? [])]);
exit();
