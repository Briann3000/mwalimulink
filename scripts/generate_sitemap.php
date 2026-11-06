<?php
// scripts/generate_sitemap.php - Fast static sitemap generator for MwalimuLink (Modular Index wrapper)
require_once __DIR__ . '/generate_sitemap_index.php';

class SitemapGenerator
{
    public static function generate(string $targetFile = null, string $baseUrl = null): array
    {
        $rootDir = $targetFile ? dirname($targetFile) : dirname(__DIR__);
        return SitemapIndexGenerator::generateAll($rootDir, $baseUrl);
    }
}

if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    SitemapGenerator::generate();
}
