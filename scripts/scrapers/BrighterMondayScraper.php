<?php
// scripts/scrapers/BrighterMondayScraper.php - Scrapes Kenya Teaching Opportunities from BrighterMonday
require_once __DIR__ . '/BaseScraper.php';

class BrighterMondayScraper extends BaseScraper
{
    protected string $sourceName = 'BrighterMonday Kenya';
    protected array $searchUrls = [
        'https://www.brightermonday.co.ke/jobs?q=teacher',
        'https://www.brightermonday.co.ke/jobs?q=teaching'
    ];

    public function fetchJobs(): array
    {
        $jobs = [];
        $seenHrefs = [];

        foreach ($this->searchUrls as $url) {
            $html = $this->httpGet($url);
            if (empty($html)) {
                continue;
            }

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $cards = $xpath->query('//div[contains(@class, "bg-white") and contains(@class, "rounded-lg") and contains(@class, "border")]');

            foreach ($cards as $card) {
                $linkNode = $xpath->query('.//a[contains(@href, "/listings/")]', $card)->item(0);
                if (!$linkNode) {
                    continue;
                }

                $title = trim($linkNode->textContent);
                $href = trim($linkNode->getAttribute('href'));

                if (empty($title) || empty($href) || strlen($title) < 4) {
                    continue;
                }

                $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://www.brightermonday.co.ke' . ltrim($href, '/');
                if (isset($seenHrefs[$sourceUrl])) {
                    continue;
                }
                $seenHrefs[$sourceUrl] = true;

                // Extract Company Name
                $companyNode = $xpath->query('.//p[contains(@class, "text-sm")]//a | .//a[contains(@href, "/companies/")] | .//p[contains(@class, "text-gray-500")]', $card)->item(0);
                $company = $companyNode ? trim($companyNode->textContent) : 'Education Institution';

                // Clean company name if generic category
                if (in_array(strtolower($company), ['research, teaching & training', 'education & training', 'other'])) {
                    $company = 'Education Institution (BrighterMonday)';
                }

                // Extract Description / Summary
                $descNode = $xpath->query('.//p[contains(@class, "text-textGray") or contains(@class, "text-sm")]', $card)->item(0);
                $desc = $descNode ? trim($descNode->textContent) : "Teaching vacancy with {$company} posted on BrighterMonday Kenya. Click 'Apply on BrighterMonday' to view candidate requirements and apply.";

                // Extract Location tag if present
                $locationNode = $xpath->query('.//span[contains(@class, "rounded-full") or contains(@class, "text-xs")]', $card)->item(0);
                $location = $locationNode ? trim($locationNode->textContent) : 'Kenya';

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
}
