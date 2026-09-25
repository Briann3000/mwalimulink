<?php
// services/JobIngestionService.php - Secure Job Aggregation, Sanitization & Ingestion Engine
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

if (!class_exists('UnderscoreFormatter')) {
    class UnderscoreFormatter
    {
        public function formatBeanTable($beanType)
        {
            return $beanType;
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
}

class JobIngestionService
{
    /**
     * Ensure RedBean database connection is active.
     */
    public static function ensureDb(): void
    {
        if (!class_exists('R')) {
            require_once __DIR__ . '/../rb.php';
        }

        try {
            if (!R::testConnection()) {
                R::ext('formatter', function () {
                    return new UnderscoreFormatter();
                });

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
                R::freeze(false);
            }
        } catch (Exception $e) {
            error_log("Database connection error in JobIngestionService: " . $e->getMessage());
        }
    }

    /**
     * Ingest and validate an external job posting into the database.
     *
     * @param array $raw
     * @return array [
     *   'success' => bool,
     *   'action' => 'inserted'|'skipped_duplicate'|'skipped_expired'|'rejected_invalid',
     *   'job_id' => int|null,
     *   'reason' => string
     * ]
     */
    public static function ingest(array $raw): array
    {
        self::ensureDb();
        // 1. Mandatory Fields Check
        $title = self::sanitizeText($raw['title'] ?? '', 200);
        $sourceName = self::sanitizeText($raw['source_name'] ?? 'External Aggregator', 100);
        $sourceUrl = self::sanitizeUrl($raw['source_url'] ?? '');

        if (empty($title)) {
            return [
                'success' => false,
                'action' => 'rejected_invalid',
                'job_id' => null,
                'reason' => 'Job title is required.'
            ];
        }

        if (empty($sourceUrl)) {
            return [
                'success' => false,
                'action' => 'rejected_invalid',
                'job_id' => null,
                'reason' => 'Valid external source URL is required.'
            ];
        }

        $companyName = self::sanitizeText($raw['company_name'] ?? $sourceName, 150);
        $description = self::sanitizeDescription($raw['description'] ?? '');
        $requirements = self::sanitizeDescription($raw['requirements'] ?? '');

        // 2. Normalize and Deduplicate (SHA-256 Hash)
        $normalizedTitle = strtolower(preg_replace('/\s+/', ' ', trim($title)));
        $normalizedCompany = strtolower(preg_replace('/\s+/', ' ', trim($companyName)));
        $normalizedUrl = strtolower(trim($sourceUrl));

        $sourceHash = hash('sha256', "{$normalizedTitle}|{$normalizedCompany}|{$normalizedUrl}");

        $existing = R::findOne('job', 'source_hash = ?', [$sourceHash]);
        if ($existing && $existing->id) {
            return [
                'success' => true,
                'action' => 'skipped_duplicate',
                'job_id' => (int) $existing->id,
                'reason' => "Listing already exists (Job ID: {$existing->id})."
            ];
        }

        // 3. Deadline Parsing and Validation
        $deadline = self::parseDate($raw['deadline'] ?? null);
        if (!empty($deadline)) {
            $deadlineTime = strtotime($deadline);
            if ($deadlineTime !== false && $deadlineTime < strtotime('today')) {
                return [
                    'success' => false,
                    'action' => 'skipped_expired',
                    'job_id' => null,
                    'reason' => "Job deadline ({$deadline}) has already expired."
                ];
            }
        }

        // 4. Application URL / Email Sanitization
        $appType = 'external_link';
        $appUrl = '';

        if (!empty($raw['application_url'])) {
            $cleanedAppUrl = self::sanitizeUrl($raw['application_url']);
            if (!empty($cleanedAppUrl)) {
                $appType = 'external_link';
                $appUrl = $cleanedAppUrl;
            }
        } elseif (!empty($raw['application_email'])) {
            $email = filter_var(trim($raw['application_email']), FILTER_VALIDATE_EMAIL);
            if ($email) {
                $appType = 'email';
                $appUrl = 'mailto:' . $email;
            }
        }

        if (empty($appUrl)) {
            $appUrl = $sourceUrl;
            $appType = 'external_link';
        }

        // 5. Intelligent Subject, Curriculum & Opportunity Type Categorization
        $curriculum = self::detectCurriculum($title . ' ' . $description, $raw['curriculum'] ?? null);
        $subjectCategory = self::detectSubjectCategory($title . ' ' . $description, $raw['subject_category'] ?? null);
        $locationCounty = self::detectLocation($raw['location'] ?? ($raw['county'] ?? ''), $title . ' ' . $description);
        $opportunityType = self::detectOpportunityType($title . ' ' . $description);

        // 6. Salary normalization
        $salary = null;
        if (isset($raw['salary']) && is_numeric($raw['salary'])) {
            $val = (float) $raw['salary'];
            if ($val > 0 && $val < 5000000) { // realistic cap
                $salary = $val;
            }
        }

        // 7. Store using RedBeanPHP
        $job = R::dispense('job');
        $job->title = $title;
        $job->company_name = $companyName;
        $job->description = $description;
        $job->requirements = $requirements;
        $job->salary = $salary;
        $job->deadline = $deadline;
        $job->posted_date = self::parseDate($raw['posted_date'] ?? null) ?: date('Y-m-d H:i:s');
        $job->school_id = 0; // External aggregated job (not tied to local registered school)

        // Metadata fields
        $job->source_type = 'external';
        $job->source_name = $sourceName;
        $job->source_url = $sourceUrl;
        $job->source_hash = $sourceHash;
        $job->curriculum = $curriculum;
        $job->subject_category = $subjectCategory;
        $job->opportunity_type = $opportunityType;
        $job->subject_category = $subjectCategory;
        $job->location_text = $locationCounty;
        $job->application_type = $appType;
        $job->application_url = $appUrl;

        // Default aggregation moderation status: published (or pending_review based on config)
        $defaultStatus = env('JOB_AGGREGATION_DEFAULT_STATUS', 'published');
        $job->aggregation_status = in_array($defaultStatus, ['published', 'pending_review']) ? $defaultStatus : 'published';
        $job->scraped_at = date('Y-m-d H:i:s');

        $jobId = R::store($job);

        // If published immediately, dispatch job alerts to qualified matching teachers
        if ($job->aggregation_status === 'published') {
            require_once __DIR__ . '/JobAlertService.php';
            JobAlertService::notifyMatchingTeachers($job);
        }

        return [
            'success' => true,
            'action' => 'inserted',
            'job_id' => (int) $jobId,
            'reason' => "Job inserted successfully with ID {$jobId}."
        ];
    }

    /**
     * Mark expired external jobs automatically.
     *
     * @param int $cutoffDays
     * @return int Number of updated records
     */
    public static function expireStaleJobs(int $cutoffDays = 45): int
    {
        self::ensureDb();
        $today = date('Y-m-d');
        $dateCutoff = date('Y-m-d H:i:s', strtotime("-{$cutoffDays} days"));

        $stale = R::find('job', "source_type = 'external' AND aggregation_status = 'published' AND ((deadline IS NOT NULL AND deadline < ?) OR (deadline IS NULL AND posted_date < ?))", [
            $today,
            $dateCutoff
        ]);

        $count = 0;
        foreach ($stale as $job) {
            $job->aggregation_status = 'expired';
            R::store($job);
            $count++;
        }

        return $count;
    }

    // -------------------------------------------------------------------
    // Security & Sanitization Helpers
    // -------------------------------------------------------------------

    /**
     * Sanitize plain single-line text (Titles, Names, Sources).
     */
    public static function sanitizeText(?string $input, int $maxLength = 255): string
    {
        if ($input === null)
            return '';
        // Remove null bytes and non-printable control chars
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $input);
        // Strip all HTML/scripts
        $cleaned = strip_tags($cleaned);
        // Normalize whitespace
        $cleaned = preg_replace('/\s+/', ' ', $cleaned);
        $cleaned = trim($cleaned);
        return mb_substr($cleaned, 0, $maxLength, 'UTF-8');
    }

    /**
     * Sanitize multi-line descriptions and requirements.
     */
    public static function sanitizeDescription(?string $input, int $maxLength = 25000): string
    {
        if ($input === null)
            return '';
        // Remove null bytes
        $cleaned = str_replace(chr(0), '', $input);
        // Strip tags completely to prevent stored XSS from external scrapers
        $cleaned = strip_tags($cleaned);
        // Normalize newlines
        $cleaned = str_replace(["\r\n", "\r"], "\n", $cleaned);
        $cleaned = preg_replace("/\n{3,}/", "\n\n", $cleaned);
        $cleaned = trim($cleaned);
        return mb_substr($cleaned, 0, $maxLength, 'UTF-8');
    }

    /**
     * Validate and sanitize URL (Prevent javascript:, vbscript:, data:, SSRF).
     */
    public static function sanitizeUrl(?string $url): string
    {
        if (empty($url))
            return '';
        $url = trim($url);

        // Disallow dangerous schemes
        if (preg_match('/^(javascript|vbscript|data|file):/i', $url)) {
            return '';
        }

        // Must be a valid URL with http or https protocol
        if (!preg_match('/^https?:\/\//i', $url)) {
            return '';
        }

        $filtered = filter_var($url, FILTER_VALIDATE_URL);
        return $filtered ? $filtered : '';
    }

    /**
     * Parse date string safely into Y-m-d or Y-m-d H:i:s.
     */
    public static function parseDate(?string $dateStr): ?string
    {
        if (empty($dateStr))
            return null;
        try {
            $dt = new DateTime($dateStr);
            return $dt->format('Y-m-d');
        } catch (Exception $e) {
            return null;
        }
    }

    // -------------------------------------------------------------------
    // Taxonomy and Classification Helpers
    // -------------------------------------------------------------------

    public static function detectCurriculum(string $text, ?string $preset = null): string
    {
        if (!empty($preset) && in_array($preset, ['CBC', 'IGCSE', 'IB', '8-4-4', 'American', 'British', 'Other'])) {
            return $preset;
        }

        $text = ' ' . strtolower($text) . ' ';

        if (preg_match('/\b(igcse|cambridge|edexcel|gce|a-level|o-level|british curriculum)\b/i', $text)) {
            return 'IGCSE';
        }
        if (preg_match('/\b(international baccalaureate|ibdp|myp|pyp|\bib\b)\b/i', $text)) {
            return 'IB';
        }
        if (preg_match('/\b(cbc|competency based curriculum|junior secondary|jss|senior secondary)\b/i', $text)) {
            return 'CBC';
        }
        if (preg_match('/\b(8-4-4|kcse|kcpe)\b/i', $text)) {
            return '8-4-4';
        }
        if (preg_match('/\b(american curriculum|ap courses|sat)\b/i', $text)) {
            return 'American';
        }

        return 'CBC'; // Default for Kenya education context
    }

    public static function detectOpportunityType(string $text): string
    {
        $text = ' ' . strtolower($text) . ' ';
        if (preg_match('/\b(intern|internship|attachment|teaching practice|\btp\b|trainee|graduate teacher|resident teacher|co-teacher|assistant teacher|student teacher|fellow)\b/i', $text)) {
            return 'internship';
        }
        return 'regular';
    }

    public static function detectSubjectCategory(string $text, ?string $preset = null): string
    {
        if (!empty($preset)) {
            return self::sanitizeText($preset, 100);
        }

        $text = ' ' . strtolower($text) . ' ';

        $categories = [
            'Mathematics' => ['\bmath', '\bmathematics', '\bcalculus', '\balgebra', '\bstatistics'],
            'Sciences' => ['\bphysics', '\bchemistry', '\bbiology', '\bgeneral science', '\bintegrated science', '\blaboratory'],
            'Languages' => ['\benglish', '\bliterature', '\bkiswahili', '\bfrench', '\bgerman', '\bspanish', '\barabic', '\bmandarin', '\blinguistics'],
            'Humanities & Social Studies' => ['\bhistory', '\bgeography', '\bcre\b', '\bire\b', '\breligious studies', '\bsocial studies'],
            'Technical & Creative Arts' => ['\bict\b', '\bcomputer science', '\bmusic', '\bart and craft', '\bhome science', '\bagriculture', '\bphysical education', '\bpe teacher', '\bdrama'],
            'Early Childhood & Primary' => ['\becde\b', '\bkindergarten', '\bnursery', '\bpp1\b', '\bpp2\b', '\bprimary teacher', '\bmontessori'],
            'Special Needs Education' => ['\bspecial needs', '\bsne\b', '\bsign language', '\bbraille', '\blearning support', '\binclusive education'],
            'Leadership & Administration' => ['\bprincipal', '\bhead teacher', '\bheadteacher', '\bdeputy', '\bdean of studies', '\badministrator', '\bregistrar']
        ];

        foreach ($categories as $category => $keywords) {
            foreach ($keywords as $kw) {
                if (preg_match('/' . $kw . '/i', $text)) {
                    return $category;
                }
            }
        }

        return 'General Teaching';
    }

    public static function detectLocation(?string $rawLocation, string $contextText): string
    {
        if (!empty($rawLocation)) {
            $clean = self::sanitizeText($rawLocation, 100);
            if (!empty($clean)) {
                return $clean;
            }
        }

        $counties = function_exists('kenyan_counties') ? kenyan_counties() : [
            'Nairobi',
            'Mombasa',
            'Kisumu',
            'Nakuru',
            'Eldoret',
            'Kiambu',
            'Machakos',
            'Kajiado',
            'Nyeri',
            'Kilifi',
            'Uasin Gishu',
            'Meru',
            'Kakamega',
            'Garissa'
        ];

        foreach ($counties as $county) {
            if (stripos($contextText, $county) !== false) {
                return $county;
            }
        }

        return 'Kenya';
    }
}
