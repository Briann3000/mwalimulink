<?php
// views/sitemap.php - Dynamic XML Sitemap for Search Engine Discovery
if (!headers_sent()) {
    header('Content-Type: application/xml; charset=utf-8');
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'] ?? 'mwalimu.info';
$baseUrl = $scheme . $host;

// Fetch active teaching vacancies
$jobWhere = "((source_type = 'external' AND aggregation_status = 'published') OR source_type = 'direct' OR source_type IS NULL OR source_type = '')";
$recentJobs = R::find('job', "{$jobWhere} ORDER BY id DESC LIMIT 1000");

// Static routes with their priorities and frequencies
$staticUrls = [
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

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
<?php foreach ($staticUrls as $s): ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . $s['loc']) ?></loc>
    <changefreq><?= $s['changefreq'] ?></changefreq>
    <priority><?= $s['priority'] ?></priority>
  </url>
<?php endforeach; ?>

<?php foreach ($recentJobs as $job): ?>
  <?php
  $modDate = !empty($job->posted_date) ? date('Y-m-d', strtotime($job->posted_date)) : date('Y-m-d');
  ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/jobs/view?id=' . $job->id) ?></loc>
    <lastmod><?= $modDate ?></lastmod>
    <changefreq>daily</changefreq>
    <priority>0.8</priority>
  </url>
<?php endforeach; ?>
</urlset>
