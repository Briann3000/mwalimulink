<?php
// scripts/scrapers/JobWebKenyaScraper.php - Aggregates Kenyan Teaching & Education Vacancies from JobWebKenya
require_once __DIR__ . '/BaseScraper.php';

class JobWebKenyaScraper extends BaseScraper
{
    protected string $sourceName = 'JobWebKenya';
    protected array $feedUrls = [
        'https://jobwebkenya.com/feed/?s=teacher',
        'https://jobwebkenya.com/feed/?s=teaching',
        'https://jobwebkenya.com/feed/?s=tutor',
        'https://jobwebkenya.com/feed/?s=headteacher',
        'https://jobwebkenya.com/feed/?s=curriculum'
    ];

    /**
     * Keywords that qualify a post as an educational/teaching opportunity.
     */
    protected array $educationKeywords = [
        'teacher', 'teaching', 'tutor', 'tutoring', 'lecturer', 'instructor',
        'headteacher', 'head teacher', 'principal', 'dean', 'curriculum',
        'school', 'academy', 'kindergarten', 'ecde', 'ecd', 'cbc',
        'faculty', 'trainer', 'educator', 'education'
    ];

    public function fetchJobs(): array
    {
        $jobs = [];
        $seenHrefs = [];

        foreach ($this->feedUrls as $feedUrl) {
            $rssContent = $this->httpGet($feedUrl);
            if (empty($rssContent)) {
                continue;
            }

            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $loaded = $dom->loadXML($rssContent);
            libxml_clear_errors();

            if (!$loaded) {
                continue;
            }

            $items = $dom->getElementsByTagName('item');
            foreach ($items as $item) {
                $title = trim($item->getElementsByTagName('title')->item(0)?->textContent ?? '');
                $link = trim($item->getElementsByTagName('link')->item(0)?->textContent ?? '');
                $pubDate = trim($item->getElementsByTagName('pubDate')->item(0)?->textContent ?? '');
                $rawDesc = trim($item->getElementsByTagName('description')->item(0)?->textContent ?? '');

                if (empty($title) || empty($link) || isset($seenHrefs[$link])) {
                    continue;
                }

                // Check educational relevance
                if (!$this->isEducationJob($title, $rawDesc)) {
                    continue;
                }

                $seenHrefs[$link] = true;

                // Parse Company Name and clean Title
                $parsed = $this->parseTitleAndCompany($title);
                $cleanTitle = $parsed['title'];
                $company = $parsed['company'];

                // Clean description text
                $cleanDesc = strip_tags($rawDesc);
                $cleanDesc = html_entity_decode($cleanDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $cleanDesc = preg_replace('/\s+/', ' ', $cleanDesc);
                $cleanDesc = trim(preg_replace('/\[\.\.\.\]$/', '', $cleanDesc));

                if (strlen($cleanDesc) < 30) {
                    $cleanDesc = "Educational teaching vacancy for {$cleanTitle} at {$company} in Kenya. Click 'Apply on JobWebKenya' to review full duties and application details.";
                }

                // Parse posted date
                $postedDate = null;
                if (!empty($pubDate)) {
                    $time = strtotime($pubDate);
                    if ($time !== false) {
                        $postedDate = date('Y-m-d H:i:s', $time);
                    }
                }

                // Parse location from title/description
                $location = $this->detectKenyanLocation($title . ' ' . $cleanDesc);

                $jobs[] = [
                    'title' => $cleanTitle,
                    'company_name' => $company,
                    'source_name' => $this->sourceName,
                    'source_url' => $link,
                    'description' => $cleanDesc,
                    'requirements' => null,
                    'salary' => null,
                    'deadline' => null,
                    'posted_date' => $postedDate ?: date('Y-m-d H:i:s'),
                    'location' => $location,
                    'curriculum' => null, // Inferred automatically by JobIngestionService
                    'subject_category' => null,
                    'application_url' => $link,
                    'application_email' => null
                ];
            }
        }

        return $jobs;
    }

    /**
     * Check if posting is relevant to teaching, education, or school administration.
     */
    protected function isEducationJob(string $title, string $description): bool
    {
        $haystack = strtolower($title . ' ' . $description);
        foreach ($this->educationKeywords as $kw) {
            if (str_contains($haystack, $kw)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract title and company from formats like "Maths Teacher at Riara Group" or "Peponi School - Art Teacher".
     */
    protected function parseTitleAndCompany(string $rawTitle): array
    {
        $title = $rawTitle;
        $company = 'Education Institution';

        if (preg_match('/^(.*?)\s+(?:at|@)\s+(.*?)$/i', $rawTitle, $matches)) {
            $title = trim($matches[1]);
            $company = trim($matches[2]);
        } elseif (preg_match('/^(.*?)\s*[-–—:]\s*(.*?)$/u', $rawTitle, $matches)) {
            // "Nova Pioneer - Primary Teacher" or "Primary Teacher - Nova Pioneer"
            $part1 = trim($matches[1]);
            $part2 = trim($matches[2]);
            if (preg_match('/(school|academy|college|university|institute|group|pioneer|centre)/i', $part1)) {
                $company = $part1;
                $title = $part2;
            } else {
                $title = $part1;
                $company = $part2;
            }
        }

        // Clean company trailing metadata
        $company = preg_replace('/\s*\(.*?\)$/', '', $company);
        $company = trim($company);

        return [
            'title' => !empty($title) ? $title : $rawTitle,
            'company' => !empty($company) ? $company : 'Kenyan Education Institution'
        ];
    }

    /**
     * Detect specific Kenyan counties or cities if mentioned.
     */
    protected function detectKenyanLocation(string $text): string
    {
        $cities = [
            'Nairobi', 'Mombasa', 'Kisumu', 'Nakuru', 'Eldoret', 'Thika',
            'Nyeri', 'Machakos', 'Kiambu', 'Kakamega', 'Meru', 'Kilifi',
            'Naivasha', 'Kitale', 'Kericho', 'Embu', 'Garissa', 'Kajiado'
        ];

        foreach ($cities as $city) {
            if (stripos($text, $city) !== false) {
                return $city . ', Kenya';
            }
        }

        return 'Kenya';
    }
}
