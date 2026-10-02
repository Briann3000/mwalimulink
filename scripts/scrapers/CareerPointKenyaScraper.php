<?php
// scripts/scrapers/CareerPointKenyaScraper.php - Scrapes Kenyan School & Teaching Vacancies from Career Point Kenya
require_once __DIR__ . '/BaseScraper.php';

class CareerPointKenyaScraper extends BaseScraper
{
    protected string $sourceName = 'Career Point Kenya';
    protected array $targetUrls = [
        'https://www.careerpointkenya.co.ke/category/teaching-jobs-in-kenya/',
        'https://www.careerpointkenya.co.ke/?s=teacher',
        'https://www.careerpointkenya.co.ke/?s=teaching'
    ];

    public function fetchJobs(): array
    {
        $jobs = [];
        $seenHrefs = [];

        foreach ($this->targetUrls as $url) {
            $html = $this->httpGet($url);
            if (empty($html)) {
                continue;
            }

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $articles = $xpath->query('//article | //div[contains(@class, "post-item")] | //div[contains(@class, "type-post")]');

            foreach ($articles as $article) {
                $titleNode = $xpath->query('.//h2//a | .//h3//a', $article)->item(0);
                if (!$titleNode) {
                    continue;
                }

                $rawTitle = trim($titleNode->textContent);
                $href = trim($titleNode->getAttribute('href'));

                if (empty($rawTitle) || empty($href) || strlen($rawTitle) < 5) {
                    continue;
                }

                if (isset($seenHrefs[$href])) {
                    continue;
                }
                $seenHrefs[$href] = true;

                $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://www.careerpointkenya.co.ke' . ltrim($href, '/');

                // Parse Title & Company
                $parsed = $this->parseTitleAndCompany($rawTitle);
                $title = $parsed['title'];
                $company = $parsed['company'];

                // Description
                $descNode = $xpath->query('.//div[contains(@class, "entry-summary")] | .//div[contains(@class, "post-summary")] | .//p', $article)->item(0);
                $descText = $descNode ? trim($descNode->textContent) : '';
                $cleanDesc = strip_tags($descText);
                $cleanDesc = html_entity_decode($cleanDesc, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $cleanDesc = preg_replace('/\s+/', ' ', $cleanDesc);
                $cleanDesc = trim(preg_replace('/Read (More|>>>).*$/i', '', $cleanDesc));

                if (strlen($cleanDesc) < 30) {
                    $cleanDesc = "Teaching job vacancy for {$title} at {$company} in Kenya. Click 'Apply on Career Point Kenya' to review qualifications and application details.";
                }

                // Date
                $timeNode = $xpath->query('.//time | .//span[contains(@class, "date")] | .//span[contains(@class, "entry-date")]', $article)->item(0);
                $postedDate = null;
                if ($timeNode && !empty($timeNode->textContent)) {
                    $rawDate = trim($timeNode->textContent);
                    $parsedTime = strtotime($rawDate);
                    if ($parsedTime !== false) {
                        $postedDate = date('Y-m-d H:i:s', $parsedTime);
                    }
                }

                $location = $this->detectKenyanLocation($title . ' ' . $cleanDesc);

                $jobs[] = [
                    'title' => $title,
                    'company_name' => $company,
                    'source_name' => $this->sourceName,
                    'source_url' => $sourceUrl,
                    'description' => $cleanDesc,
                    'requirements' => null,
                    'salary' => null,
                    'deadline' => null,
                    'posted_date' => $postedDate ?: date('Y-m-d H:i:s'),
                    'location' => $location,
                    'curriculum' => null, // Inferred automatically
                    'subject_category' => null,
                    'application_url' => $sourceUrl,
                    'application_email' => null
                ];
            }
        }

        return $jobs;
    }

    /**
     * Clean CareerPoint format e.g. "Lead Senior Teacher Job Pharo School" or "ECD Teacher Job Browns Plantations".
     */
    protected function parseTitleAndCompany(string $rawTitle): array
    {
        $title = $rawTitle;
        $company = 'Education Institution';

        // Pattern 1: "... Job [Company]" or "... Jobs [Company]"
        if (preg_match('/^(.*?)\s+Jobs?\s+(?:at|in)?\s*(.*?)$/i', $rawTitle, $matches)) {
            $title = trim($matches[1]);
            $comp = trim($matches[2]);
            if (!empty($comp)) {
                $company = $comp;
            }
        } elseif (preg_match('/^(.*?)\s+(?:at|@)\s+(.*?)$/i', $rawTitle, $matches)) {
            $title = trim($matches[1]);
            $company = trim($matches[2]);
        }

        // Clean company trailing words like "Kenya" or "2026"
        $company = preg_replace('/\s*(Kenya|\d{4})\b/i', '', $company);
        $company = trim($company, " -–,");

        if (empty($company)) {
            $company = 'Education Institution (Kenya)';
        }

        return [
            'title' => !empty($title) ? $title : $rawTitle,
            'company' => $company
        ];
    }

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
