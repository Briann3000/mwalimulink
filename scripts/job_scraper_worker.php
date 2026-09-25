<?php
// scripts/job_scraper_worker.php - Master Job Aggregation Worker & CLI Dispatcher
if (php_sapi_name() === 'cli' && !defined('STDIN')) {
    define('STDIN', fopen('php://stdin', 'r'));
}

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';
require_once __DIR__ . '/../services/JobIngestionService.php';
require_once __DIR__ . '/scrapers/ReliefWebScraper.php';
require_once __DIR__ . '/scrapers/MyJobMagScraper.php';
require_once __DIR__ . '/scrapers/BrighterMondayScraper.php';
require_once __DIR__ . '/scrapers/TeachAwayScraper.php';

class JobScraperWorker
{
    /**
     * Run all registered scrapers.
     *
     * @param array $options ['source' => string|null, 'dry_run' => bool]
     * @return array Summary of execution
     */
    public static function run(array $options = []): array
    {
        $targetSource = strtolower($options['source'] ?? '');
        $dryRun = !empty($options['dry_run']);

        $scrapers = [
            'reliefweb' => new ReliefWebScraper(),
            'myjobmag' => new MyJobMagScraper(),
            'brightermonday' => new BrighterMondayScraper(),
            'teachaway' => new TeachAwayScraper()
        ];

        if (!empty($targetSource) && isset($scrapers[$targetSource])) {
            $activeScrapers = [$targetSource => $scrapers[$targetSource]];
        } else {
            $activeScrapers = $scrapers;
        }

        $summary = [
            'started_at' => date('Y-m-d H:i:s'),
            'scrapers_run' => count($activeScrapers),
            'total_fetched' => 0,
            'total_inserted' => 0,
            'total_skipped_duplicates' => 0,
            'total_skipped_expired' => 0,
            'total_errors' => 0,
            'details' => []
        ];

        foreach ($activeScrapers as $key => $scraper) {
            $sourceName = $scraper->getSourceName();
            $logPrefix = "[" . date('H:i:s') . "][{$sourceName}]";

            $sourceStats = [
                'source' => $sourceName,
                'fetched' => 0,
                'inserted' => 0,
                'duplicates' => 0,
                'expired' => 0,
                'invalid' => 0,
                'error' => null
            ];

            try {
                if (php_sapi_name() === 'cli') {
                    echo "{$logPrefix} Fetching opportunities...\n";
                }

                $jobs = $scraper->fetchJobs();
                $sourceStats['fetched'] = count($jobs);
                $summary['total_fetched'] += count($jobs);

                if (php_sapi_name() === 'cli') {
                    echo "{$logPrefix} Fetched {$sourceStats['fetched']} raw postings. Ingesting...\n";
                }

                foreach ($jobs as $jobData) {
                    if ($dryRun) {
                        $sourceStats['inserted']++;
                        continue;
                    }

                    $res = JobIngestionService::ingest($jobData);
                    switch ($res['action']) {
                        case 'inserted':
                            $sourceStats['inserted']++;
                            $summary['total_inserted']++;
                            if (php_sapi_name() === 'cli') {
                                echo "  + [NEW] {$jobData['title']} (ID: {$res['job_id']})\n";
                            }
                            break;
                        case 'skipped_duplicate':
                            $sourceStats['duplicates']++;
                            $summary['total_skipped_duplicates']++;
                            break;
                        case 'skipped_expired':
                            $sourceStats['expired']++;
                            $summary['total_skipped_expired']++;
                            break;
                        default:
                            $sourceStats['invalid']++;
                            $summary['total_errors']++;
                            break;
                    }
                }
            } catch (Exception $e) {
                $sourceStats['error'] = $e->getMessage();
                $summary['total_errors']++;
                if (php_sapi_name() === 'cli') {
                    echo "{$logPrefix} ERROR: " . $e->getMessage() . "\n";
                }
            }

            $summary['details'][$key] = $sourceStats;
        }

        // Clean up stale listings
        if (!$dryRun) {
            $expiredCount = JobIngestionService::expireStaleJobs(45);
            $summary['expired_cleaned'] = $expiredCount;
        }

        $summary['finished_at'] = date('Y-m-d H:i:s');
        return $summary;
    }
}

// If invoked directly from CLI
if (php_sapi_name() === 'cli' && isset($argv[0]) && realpath($argv[0]) === __FILE__) {
    echo "=======================================================\n";
    echo " MwalimuLink Job Aggregator - Ingestion Worker CLI\n";
    echo "=======================================================\n";

    $opts = getopt('', ['source:', 'dry-run']);
    $sourceOpt = $opts['source'] ?? null;
    $isDryRun = isset($opts['dry-run']);

    $results = JobScraperWorker::run([
        'source' => $sourceOpt,
        'dry_run' => $isDryRun
    ]);

    echo "\n=== Ingestion Summary ===\n";
    echo "Total Fetched:    " . $results['total_fetched'] . "\n";
    echo "New Inserted:     " . $results['total_inserted'] . "\n";
    echo "Duplicates:       " . $results['total_skipped_duplicates'] . "\n";
    echo "Expired Skipped:  " . $results['total_skipped_expired'] . "\n";
    echo "Stale Cleaned:    " . ($results['expired_cleaned'] ?? 0) . "\n";
    echo "Completed at:     " . $results['finished_at'] . "\n";
    echo "=======================================================\n";
}
