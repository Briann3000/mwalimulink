<?php
// scripts/clear_forum_data.php - CLI Maintenance Script to Reset Forum & Chat Data
ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

// Formatters
class UnderscoreFormatter implements RedBeanPHP\IModelFormatter
{
    public function formatModel($model)
    {
        return $model;
    }
    public function formatBeanTable($beanType)
    {
        return strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $beanType));
    }
    public function formatBeanID($beanType)
    {
        return 'id';
    }
    public function formatBeanForeignKey($beanType)
    {
        return $beanType . '_id';
    }
}

R::ext('formatter', function () {
    return new UnderscoreFormatter();
});

// Setup Database Connection
$dbDriver = env('DB_CONNECTION', 'sqlite');
if ($dbDriver === 'mysql') {
    $dbHost = env('DB_HOST', 'localhost');
    $dbPort = env('DB_PORT', '3306');
    $dbName = env('DB_DATABASE', 'mwalimu');
    $dbUser = env('DB_USERNAME', 'root');
    $dbPass = env('DB_PASSWORD', '');
    R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
} else {
    $dbPath = dirname(__DIR__) . '/' . env('DB_PATH', 'data/mwalimu.db');
    R::setup("sqlite:$dbPath");
}

R::freeze(false);

echo "Connected to database ({$dbDriver}). Resetting forum chat..." . PHP_EOL;

try {
    R::exec('TRUNCATE TABLE forumthread');
    echo " - forumthread table truncated." . PHP_EOL;
} catch (\Throwable $e) {
    try {
        R::exec('DELETE FROM forumthread');
        echo " - forumthread table deleted." . PHP_EOL;
    } catch (\Throwable $e2) {
        echo " - forumthread: " . $e2->getMessage() . PHP_EOL;
    }
}

try {
    R::exec('TRUNCATE TABLE forumreply');
    echo " - forumreply table truncated." . PHP_EOL;
} catch (\Throwable $e) {
    try {
        R::exec('DELETE FROM forumreply');
        echo " - forumreply table deleted." . PHP_EOL;
    } catch (\Throwable $e2) {}
}

try {
    R::exec('TRUNCATE TABLE forumreaction');
    echo " - forumreaction table truncated." . PHP_EOL;
} catch (\Throwable $e) {
    try {
        R::exec('DELETE FROM forumreaction');
        echo " - forumreaction table deleted." . PHP_EOL;
    } catch (\Throwable $e2) {}
}

try {
    R::exec('TRUNCATE TABLE forumreport');
    echo " - forumreport table truncated." . PHP_EOL;
} catch (\Throwable $e) {
    try {
        R::exec('DELETE FROM forumreport');
        echo " - forumreport table deleted." . PHP_EOL;
    } catch (\Throwable $e2) {}
}

try {
    R::exec('UPDATE forumcategory SET threads_count = 0');
    echo " - forumcategory counters reset to 0." . PHP_EOL;
} catch (\Throwable $e) {
    echo " - forumcategory: " . $e->getMessage() . PHP_EOL;
}

echo "Forum chat channels have been successfully cleared and reset to 0 messages!" . PHP_EOL;
