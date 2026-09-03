<?php
// router.php - PHP built-in server router
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
$file = __DIR__ . $uri;

if ($uri !== '/' && file_exists($file) && !is_dir($file)) {
    return false;
}

require __DIR__ . '/index.php';

