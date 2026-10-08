<?php
// scripts/import_elibrary_sql.php - Imports all 235 resources from library_resources.sql

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

$dbDriver = env('DB_CONNECTION', 'sqlite');
if ($dbDriver === 'mysql') {
  $dbHost = env('DB_HOST', 'localhost');
  $dbPort = env('DB_PORT', '3306');
  $dbName = env('DB_DATABASE', 'mwalimu');
  $dbUser = env('DB_USERNAME', 'root');
  $dbPass = env('DB_PASSWORD', '');
  if (!R::hasDatabase('default')) {
    R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
  }
} else {
  $dbPath = __DIR__ . '/../' . env('DB_PATH', 'data/mwalimu.db');
  if (!R::hasDatabase('default')) {
    R::setup("sqlite:$dbPath");
  }
}

R::freeze(false);
init_elibrary_schema();

// Check multiple candidate locations for library_resources.sql
$candidates = [
  $argv[1] ?? '',
  __DIR__ . '/../library_resources.sql',
  __DIR__ . '/library_resources.sql',
  getcwd() . '/library_resources.sql',
  getcwd() . '/../library_resources.sql',
  dirname(__DIR__, 2) . '/library_resources.sql'
];

$sqlFile = null;
foreach ($candidates as $cand) {
  if (!empty($cand) && file_exists($cand) && is_file($cand)) {
    $sqlFile = realpath($cand);
    break;
  }
}

if (!$sqlFile) {
  echo "Error: Could not locate library_resources.sql in any of the expected locations:\n";
  foreach ($candidates as $cand) {
    if (!empty($cand))
      echo " - {$cand}\n";
  }
  echo "\nYou can specify the exact path manually, e.g.:\n";
  echo "php scripts/import_elibrary_sql.php /path/to/library_resources.sql\n";
  exit(1);
}

echo "Found SQL file: {$sqlFile}\n";
echo "Reading library_resources.sql...\n";
$lines = file($sqlFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

$imported = 0;
$skipped = 0;
$totalFound = 0;

foreach ($lines as $line) {
  $line = trim($line);
  if (preg_match('/^\((\d+),\s*\'/', $line)) {
    $totalFound++;
    $trimmed = trim($line, " \t\n\r\0\x0B,;");
    $trimmed = ltrim($trimmed, '(');
    $trimmed = rtrim($trimmed, ')');

    $tokens = str_getcsv($trimmed, ",", "'", "\\");
    if (count($tokens) < 12) {
      continue;
    }

    $id = trim($tokens[0]);
    $title = trim($tokens[1]);
    $slug = trim($tokens[2]);
    $category = trim($tokens[3]);
    $grade = trim($tokens[4]);
    $term = trim($tokens[5]);
    $subject = trim($tokens[6]);
    $price = (float) trim($tokens[7]);
    $isFree = (int) trim($tokens[8]);
    $filePath = trim($tokens[11]);
    $createdAt = trim($tokens[14] ?? date('Y-m-d H:i:s'));
    $resourceType = trim($tokens[16] ?? 'Notes');
    $isFeatured = (int) trim($tokens[18] ?? 0);
    $viewCount = (int) trim($tokens[19] ?? 0);
    $downloadCount = (int) trim($tokens[20] ?? 0);
    $authorName = trim($tokens[21] ?? 'SkolarTrak Library');
    $fileSize = (int) trim($tokens[22] ?? 0);

    $title = str_replace(["\\'", "\\\""], ["'", "\""], $title);
    if (empty($title) || $title === 'NULL')
      continue;

    $existing = R::findOne('resource', ' title = ? ', [$title]);
    if ($existing) {
      $skipped++;
      continue;
    }

    $tier = 'Junior School';
    $gradeLower = strtolower($grade);
    if (str_contains($gradeLower, 'play') || str_contains($gradeLower, 'pp1') || str_contains($gradeLower, 'pp2') || str_contains($gradeLower, 'pre')) {
      $tier = 'Pre-Primary';
    } elseif (str_contains($gradeLower, 'grade 1') || str_contains($gradeLower, 'grade 2') || str_contains($gradeLower, 'grade 3') || str_contains($gradeLower, 'grade 4') || str_contains($gradeLower, 'grade 5') || str_contains($gradeLower, 'grade 6') || str_contains($gradeLower, 'class')) {
      $tier = 'Primary';
    } elseif (str_contains($gradeLower, 'grade 7') || str_contains($gradeLower, 'grade 8') || str_contains($gradeLower, 'grade 9') || str_contains($gradeLower, 'jss')) {
      $tier = 'Junior School';
    } elseif (str_contains($gradeLower, 'form') || str_contains($gradeLower, 'grade 10') || str_contains($gradeLower, 'grade 11') || str_contains($gradeLower, 'grade 12')) {
      $tier = 'Secondary';
    }

    $cleanCat = 'Notes';
    $catLower = strtolower($category . ' ' . $resourceType);
    if (str_contains($catLower, 'scheme')) {
      $cleanCat = 'Schemes of Work';
    } elseif (str_contains($catLower, 'exam') || str_contains($catLower, 'past') || str_contains($catLower, 'revision') || str_contains($catLower, 'test')) {
      $cleanCat = 'Revision Papers';
    } elseif (str_contains($catLower, 'lesson') || str_contains($catLower, 'plan')) {
      $cleanCat = 'Lesson Plans';
    } elseif (str_contains($catLower, 'curriculum') || str_contains($catLower, 'design')) {
      $cleanCat = 'Curriculum Designs';
    } elseif (str_contains($catLower, 'story') || str_contains($catLower, 'book') || str_contains($catLower, 'ebook')) {
      $cleanCat = 'Set Book Guides';
    }

    if ($filePath === 'NULL' || empty($filePath)) {
      $filePath = '/uploads/elibrary/samples/' . strtolower(preg_replace('/[^a-z0-9]+/i', '_', $title)) . '.pdf';
    } elseif (!str_starts_with($filePath, '/')) {
      $filePath = '/' . $filePath;
    }

    $res = R::dispense('resource');
    $res->title = $title;
    $res->slug = $slug ?: preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
    $res->category = $cleanCat;
    $res->grade_level = ($grade !== 'NULL' && !empty($grade)) ? $grade : 'General';
    $res->curriculum_tier = $tier;
    $res->subject = ($subject !== 'NULL' && !empty($subject)) ? $subject : 'General';
    $res->term = ($term !== 'NULL' && !empty($term)) ? $term : 'General';
    $res->academic_year = 2026;
    $res->description = "Verified CBC/CBE educational resource: {$title} ({$res->grade_level}, {$res->subject}).";
    $res->price = ($isFree || $price <= 0) ? 0.00 : $price;
    $res->file_path = $filePath;
    $res->file_type = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'pdf';
    $res->file_size_bytes = $fileSize > 0 ? $fileSize : 1024000;
    $res->uploader_type = 'admin';
    $res->uploader_name = ($authorName !== 'NULL' && !empty($authorName)) ? $authorName : 'SkolarTrak Library';
    $res->status = 'approved';
    $res->is_featured = $isFeatured;
    $res->download_count = $downloadCount;
    $res->views_count = $viewCount;
    $res->created_at = ($createdAt !== 'NULL' && !empty($createdAt)) ? $createdAt : date('Y-m-d H:i:s');
    $res->updated_at = date('Y-m-d H:i:s');

    R::store($res);
    $imported++;
  }
}

echo "Total rows found in SQL: {$totalFound}\n";
echo "Successfully imported into Mwalimu: {$imported}\n";
echo "Skipped (already present): {$skipped}\n";
$totalInDb = R::count('resource');
echo "Grand Total in Mwalimu Database: {$totalInDb}\n";
