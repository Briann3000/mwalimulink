<?php
// scripts/generate_sitemap_index.php - Enterprise Modular Sitemap Index & Split Sub-Sitemaps Generator
if (php_sapi_name() === 'cli' && !defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

// Ensure RedBean is connected
if (!class_exists('R') || !R::testConnection()) {
    $dbDriver = env('DB_CONNECTION', 'sqlite');
    if ($dbDriver === 'mysql') {
        $dbHost = env('DB_HOST', 'localhost');
        $dbPort = env('DB_PORT', '3306');
        $dbName = env('DB_DATABASE', 'mwalimu');
        $dbUser = env('DB_USERNAME', 'root');
        $dbPass = env('DB_PASSWORD', '');
        R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
    } else {
        $dbPath = __DIR__ . '/../' . env('DB_PATH', 'data/mwalimu.db');
        R::setup("sqlite:$dbPath");
    }
}

class SitemapIndexGenerator
{
    public static function generateAll(string $rootDir = null, string $baseUrl = null): array
    {
        $startTime = microtime(true);
        $rootDir = $rootDir ? rtrim($rootDir, '/\\') : dirname(__DIR__);
        $baseUrl = rtrim($baseUrl ?: (env('APP_URL') ?: 'https://mwalimu.info'), '/');
        if (str_starts_with($baseUrl, 'http://localhost')) {
            $baseUrl = 'https://mwalimu.info';
        }

        $pdo = R::getDatabaseAdapter()->getDatabase()->getPDO();
        $results = [];

        // 1. Generate sitemap-pages.xml
        $pages = [
            ['loc' => '/', 'priority' => '1.0', 'changefreq' => 'daily'],
            ['loc' => '/jobs', 'priority' => '0.9', 'changefreq' => 'hourly'],
            ['loc' => '/schools/public', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => '/schools/private', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => '/schools/international', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => '/elibrary', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => '/publications', 'priority' => '0.8', 'changefreq' => 'weekly'],
            ['loc' => '/forum', 'priority' => '0.8', 'changefreq' => 'daily'],
            ['loc' => '/tp-hub', 'priority' => '0.7', 'changefreq' => 'weekly'],
            ['loc' => '/faqs', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => '/about', 'priority' => '0.5', 'changefreq' => 'monthly'],
            ['loc' => '/contacts', 'priority' => '0.5', 'changefreq' => 'monthly'],
        ];
        $results['pages'] = self::writeUrlset($rootDir . '/sitemap-pages.xml', $pages, $baseUrl);

        // 2. Generate sitemap-jobs.xml
        $jobs = [];
        try {
            $jobSql = "SELECT id, posted_date FROM job 
                       WHERE ((source_type = 'external' AND aggregation_status = 'published') 
                              OR source_type = 'direct' OR source_type IS NULL OR source_type = '')
                       ORDER BY id DESC LIMIT 2000";
            $stmt = $pdo->query($jobSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $jobs[] = [
                    'loc' => '/jobs/view?id=' . $row['id'],
                    'lastmod' => !empty($row['posted_date']) ? date('Y-m-d', strtotime($row['posted_date'])) : date('Y-m-d'),
                    'changefreq' => 'daily',
                    'priority' => '0.9'
                ];
            }
        } catch (\Throwable $e) {
            error_log("Jobs query error in sitemap: " . $e->getMessage());
        }
        $results['jobs'] = self::writeUrlset($rootDir . '/sitemap-jobs.xml', $jobs, $baseUrl);

        // 3. Generate sitemap-schools.xml
        $schools = [];
        try {
            $pubSql = "SELECT id FROM public_school ORDER BY id ASC LIMIT 1000";
            $stmt = $pdo->query($pubSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $schools[] = [
                    'loc' => '/schools/public/detail?id=' . $row['id'],
                    'changefreq' => 'monthly',
                    'priority' => '0.7'
                ];
            }
        } catch (\Throwable $e) {}

        try {
            $privSql = "SELECT id FROM private_school ORDER BY id ASC LIMIT 1000";
            $stmt = $pdo->query($privSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $schools[] = [
                    'loc' => '/schools/private/detail?id=' . $row['id'],
                    'changefreq' => 'monthly',
                    'priority' => '0.7'
                ];
            }
        } catch (\Throwable $e) {}
        $results['schools'] = self::writeUrlset($rootDir . '/sitemap-schools.xml', $schools, $baseUrl);

        // 4. Generate sitemap-resources.xml (E-Library, Publications, Forum)
        $resources = [];
        try {
            $resSql = "SELECT id, created_at FROM resource WHERE status = 'approved' ORDER BY id DESC LIMIT 500";
            $stmt = $pdo->query($resSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $resources[] = [
                    'loc' => '/elibrary/view?id=' . $row['id'],
                    'lastmod' => !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : date('Y-m-d'),
                    'changefreq' => 'weekly',
                    'priority' => '0.8'
                ];
            }
        } catch (\Throwable $e) {}

        try {
            $pubSql = "SELECT id, created_at FROM publication ORDER BY id DESC LIMIT 200";
            $stmt = $pdo->query($pubSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $resources[] = [
                    'loc' => '/publications/view?id=' . $row['id'],
                    'lastmod' => !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : date('Y-m-d'),
                    'changefreq' => 'monthly',
                    'priority' => '0.8'
                ];
            }
        } catch (\Throwable $e) {}

        try {
            $thSql = "SELECT id, created_at FROM forumthread ORDER BY id DESC LIMIT 200";
            $stmt = $pdo->query($thSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $resources[] = [
                    'loc' => '/forum/thread?id=' . $row['id'],
                    'lastmod' => !empty($row['created_at']) ? date('Y-m-d', strtotime($row['created_at'])) : date('Y-m-d'),
                    'changefreq' => 'weekly',
                    'priority' => '0.7'
                ];
            }
        } catch (\Throwable $e) {}
        $results['resources'] = self::writeUrlset($rootDir . '/sitemap-resources.xml', $resources, $baseUrl);

        // 5. Generate Master sitemap_index.xml
        $subSitemaps = [
            'sitemap-pages.xml',
            'sitemap-jobs.xml',
            'sitemap-schools.xml',
            'sitemap-resources.xml'
        ];
        $indexFile = $rootDir . '/sitemap_index.xml';
        $results['index'] = self::writeIndex($indexFile, $subSitemaps, $baseUrl);

        // Also duplicate to sitemap.xml for legacy compatibility
        @copy($indexFile, $rootDir . '/sitemap.xml');

        $elapsed = round(microtime(true) - $startTime, 4);

        return [
            'success' => true,
            'results' => $results,
            'elapsed_sec' => $elapsed
        ];
    }

    private static function writeUrlset(string $targetFile, array $items, string $baseUrl): array
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('urlset');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        foreach ($items as $it) {
            $xml->startElement('url');
            $loc = str_starts_with($it['loc'], 'http') ? $it['loc'] : ($baseUrl . '/' . ltrim($it['loc'], '/'));
            $xml->writeElement('loc', $loc);
            if (!empty($it['lastmod'])) {
                $xml->writeElement('lastmod', $it['lastmod']);
            }
            if (!empty($it['changefreq'])) {
                $xml->writeElement('changefreq', $it['changefreq']);
            }
            if (!empty($it['priority'])) {
                $xml->writeElement('priority', (string)$it['priority']);
            }
            $xml->endElement();
        }

        $xml->endElement();
        $content = $xml->outputMemory();

        file_put_contents($targetFile, $content);
        return [
            'file' => basename($targetFile),
            'count' => count($items),
            'size' => strlen($content)
        ];
    }

    private static function writeIndex(string $targetFile, array $sitemaps, string $baseUrl): array
    {
        $xml = new XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString('  ');
        $xml->startDocument('1.0', 'UTF-8');
        $xml->startElement('sitemapindex');
        $xml->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');

        $now = date('Y-m-d');
        foreach ($sitemaps as $sm) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $baseUrl . '/' . $sm);
            $xml->writeElement('lastmod', $now);
            $xml->endElement();
        }

        $xml->endElement();
        $content = $xml->outputMemory();

        file_put_contents($targetFile, $content);
        return [
            'file' => basename($targetFile),
            'count' => count($sitemaps),
            'size' => strlen($content)
        ];
    }
}

// CLI Execution Support
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'] ?? '')) {
    echo "[" . date('Y-m-d H:i:s') . "] Generating sitemap_index.xml and sub-sitemaps...\n";
    $res = SitemapIndexGenerator::generateAll();
    echo "Completed in " . $res['elapsed_sec'] . "s:\n";
    foreach ($res['results'] as $key => $info) {
        echo "  - {$info['file']}: {$info['count']} items (" . number_format($info['size'] / 1024, 2) . " KB)\n";
    }
}
