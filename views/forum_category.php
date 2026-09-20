<?php
// views/forum_category.php - Category Discussion Feed
$catSlug = trim($_GET['slug'] ?? ($_GET['category'] ?? ''));
if (!empty($catSlug)) {
    $_GET['category'] = $catSlug;
}
require __DIR__ . '/forum.php';
