<?php
// scripts/scrapers/MyJobMagScraper.php - Scrapes Real Teaching Vacancies in Kenya from MyJobMag
require_once __DIR__ . '/BaseScraper.php';

class MyJobMagScraper extends BaseScraper
{
    protected string $sourceName = 'MyJobMag Kenya';
    protected array $searchUrls = [
        'https://www.myjobmag.co.ke/search/jobs?q=teacher',
        'https://www.myjobmag.co.ke/search/jobs?q=teaching'
    ];

    public function fetchJobs(): array
    {
        $jobs = [];
        $seenHrefs = [];

        foreach ($this->searchUrls as $targetUrl) {
            $html = $this->httpGet($targetUrl);
            if (empty($html)) {
                continue;
            }

            libxml_use_internal_errors(true);
            $dom = new DOMDocument();
            $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);
            $nodes = $xpath->query('//li[contains(@class, "job-list-li") or contains(@class, "job-info")]');

            foreach ($nodes as $node) {
                $titleNode = $xpath->query('.//h2/a', $node)->item(0);
                if (!$titleNode) {
                    continue;
                }

                $rawTitle = trim($titleNode->textContent);
                $href = trim($titleNode->getAttribute('href'));

                if (empty($rawTitle) || empty($href)) {
                    continue;
                }

                // Prevent duplicates across mobile/desktop HTML duplicate nodes
                if (isset($seenHrefs[$href])) {
                    continue;
                }
                $seenHrefs[$href] = true;

                $sourceUrl = str_starts_with($href, 'http') ? $href : 'https://www.myjobmag.co.ke' . ltrim($href, '/');

                // Parse Title & Company Name (often formatted as "Title at Company")
                $title = $rawTitle;
                $company = 'Education Institution';
                if (str_contains($rawTitle, ' at ')) {
                    $parts = explode(' at ', $rawTitle, 2);
                    $title = trim($parts[0]);
                    $company = trim($parts[1]);
                }

                // Extract Description summary
                $descNode = $xpath->query('.//li[contains(@class, "job-desc")] | .//div[contains(@class, "job-desc")] | .//p', $node)->item(0);
                $desc = $descNode ? trim($descNode->textContent) : "Teaching vacancy with {$company}. Click the application link to view full curriculum requirements and apply directly.";

                // Extract Date
                $dateNode = $xpath->query('.//li[contains(@id, "job-date")] | .//span[contains(@class, "date")]', $node)->item(0);
                $postedDate = null;
                if ($dateNode && !empty($dateNode->textContent)) {
                    $rawDate = trim($dateNode->textContent);
                    $parsed = strtotime($rawDate);
                    if ($parsed !== false) {
                        $postedDate = date('Y-m-d H:i:s', $parsed);
                    }
                }

                $jobs[] = [
                    'title' => $title,
                    'company_name' => $company,
                    'source_name' => $this->sourceName,
                    'source_url' => $sourceUrl,
                    'description' => $desc,
                    'requirements' => null,
                    'salary' => null,
                    'deadline' => null,
                    'posted_date' => $postedDate ?: date('Y-m-d H:i:s'),
                    'location' => 'Kenya',
                    'curriculum' => null, // Inferred automatically by JobIngestionService
                    'subject_category' => null,
                    'application_url' => $sourceUrl,
                    'application_email' => null
                ];
            }
        }

        return $jobs;
    }
}
