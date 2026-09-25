<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

$dbDriver = env('DB_CONNECTION', 'sqlite');
if ($dbDriver === 'mysql') {
    $dbHost = env('DB_HOST', 'localhost');
    $dbPort = env('DB_PORT', '3306');
    $dbName = env('DB_DATABASE', 'mwalimu');
    $dbUser = env('DB_USERNAME', 'root');
    $dbPass = env('DB_PASSWORD', '');
    R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
} else {
    $dbPath = __DIR__ . '/../' . env('DB_DATABASE', 'database.sqlite');
    R::setup("sqlite:{$dbPath}");
}

$countyFilter = '';
$internshipParams = [];
$internshipWhere = "((opportunity_type = 'internship') OR (title LIKE '%intern%') OR (title LIKE '%attachment%') OR (title LIKE '%trainee%') OR (title LIKE '%resident teacher%') OR (title LIKE '%co-teacher%') OR (title LIKE '%graduate teacher%') OR (description LIKE '%teaching practice%') OR (description LIKE '%student teacher%')) AND (aggregation_status = 'published' OR aggregation_status IS NULL OR source_type = 'direct' OR source_type IS NULL)";

$activeInternships = R::find('job', "{$internshipWhere} ORDER BY id DESC LIMIT 20", $internshipParams);
echo "Matched activeInternships count: " . count($activeInternships) . PHP_EOL;
foreach ($activeInternships as $ai) {
    echo " -> ID: {$ai->id} | {$ai->title} | Type: {$ai->opportunity_type} | AggStatus: {$ai->aggregation_status}" . PHP_EOL;
}


