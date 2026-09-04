<?php
// router.php - PHP built-in server router
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$file = __DIR__ . $uri;

// Only serve static assets (css, js, images, fonts, pdfs) directly
if ($uri !== '/' && $uri !== '/index.php' && file_exists($file) && !is_dir($file) && !str_ends_with($file, '.php')) {
    return false;
}

require __DIR__ . '/index.php';

