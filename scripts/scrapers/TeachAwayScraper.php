<?php
// scripts/scrapers/TeachAwayScraper.php - Aggregates International & Overseas Teaching Vacancies
require_once __DIR__ . '/BaseScraper.php';

class TeachAwayScraper extends BaseScraper
{
    protected string $sourceName = 'TeachAway International';
    protected array $targetUrls = [
        'https://www.teachaway.com/teaching-jobs-abroad/kenya',
        'https://www.teachaway.com/teaching-jobs-abroad/all-countries/all-positions/any-subject/any-level?featured=true'
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
            // Query job links on TeachAway
            $links = $xpath->query('//a[contains(@href, "/teaching-jobs-abroad/") and not(contains(@href, "/any-subject/")) and not(contains(@href, "/all-countries/"))]');

            foreach ($links as $link) {
                $href = $link->getAttribute('href');
                $rawText = trim($link->textContent);

                if (empty($rawText) || empty($href) || strlen($rawText) < 8) {
                    continue;
                }

                // Exclude category navigation links
                if (preg_match('/(any-level|all-positions|featured=true|teach in|explore)/i', $href)) {
                    continue;
                }

                $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://www.teachaway.com' . ltrim($href, '/');
                if (isset($seenHrefs[$sourceUrl])) {
                    continue;
                }
                $seenHrefs[$sourceUrl] = true;

                // Clean title and institution
                $title = preg_replace('/\s+/', ' ', $rawText);
                $company = 'International Partner School';

                // If title contains school suffix
                if (preg_match('/^(.*?)(BASIS|Charter School|International School|Academy|School)(.*)$/i', $title, $matches)) {
                    $company = trim($matches[2] . $matches[3]);
                }

                // Clean title if too long
                if (strlen($title) > 90) {
                    $parts = explode(' - ', $title);
                    $title = trim($parts[0]);
                }

                $desc = "International teaching vacancy with {$company} on TeachAway. Curriculum requirements include international accreditation (IB / Cambridge / American). Click 'Apply on TeachAway' to review qualifications and submit your application.";

                $jobs[] = [
                    'title' => $title,
                    'company_name' => $company,
                    'source_name' => $this->sourceName,
                    'source_url' => $sourceUrl,
                    'description' => $desc,
                    'requirements' => null,
                    'salary' => null,
                    'deadline' => null,
                    'posted_date' => date('Y-m-d H:i:s'),
                    'location' => 'International / Kenya',
                    'curriculum' => 'IB',
                    'subject_category' => null,
                    'application_url' => $sourceUrl,
                    'application_email' => null
                ];
            }
        }

        return $jobs;
    }
}
