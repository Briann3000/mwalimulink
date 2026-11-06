<?php
// views/sitemap.php - High-performance cached sitemap delivery
if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
}

$rootDir = realpath(__DIR__ . '/..');

// Map incoming route or URI to corresponding sitemap file
$uri = trim($_SERVER['REQUEST_URI'] ?? '', '/');
$uri = strtok($uri, '?'); // strip query strings

$sitemapMap = [
    'sitemap.xml' => 'sitemap.xml',
    'sitemap_index.xml' => 'sitemap_index.xml',
    'sitemaps.xml' => 'sitemap_index.xml', // Typo tolerance alias
    'sitemap-pages.xml' => 'sitemap-pages.xml',
    'sitemap-jobs.xml' => 'sitemap-jobs.xml',
    'sitemap-schools.xml' => 'sitemap-schools.xml',
    'sitemap-resources.xml' => 'sitemap-resources.xml',
];

$targetFile = $sitemapMap[$uri] ?? 'sitemap_index.xml';
$filePath = $rootDir . '/' . $targetFile;

// If target file exists and is fresh (< 24h), deliver it instantly
$cacheDuration = 86400; // 24 hours
if (file_exists($filePath) && filesize($filePath) > 50 && (time() - filemtime($filePath) < $cacheDuration)) {
    readfile($filePath);
    exit();
}

// Otherwise regenerate all sitemaps using the fast generator
require_once __DIR__ . '/../scripts/generate_sitemap_index.php';
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'mwalimu.info';
$baseUrl = rtrim($scheme . $host, '/');

SitemapIndexGenerator::generateAll($rootDir, $baseUrl);

if (file_exists($filePath) && filesize($filePath) > 50) {
    readfile($filePath);
    exit();
}

// Fallback if target file still missing
if (file_exists($rootDir . '/sitemap_index.xml')) {
    readfile($rootDir . '/sitemap_index.xml');
    exit();
}

