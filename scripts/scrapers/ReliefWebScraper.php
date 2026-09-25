<?php
// scripts/scrapers/ReliefWebScraper.php - Official ReliefWeb Humanitarian & NGO Education Jobs REST API Crawler
require_once __DIR__ . '/BaseScraper.php';

class ReliefWebScraper extends BaseScraper
{
    protected string $sourceName = 'ReliefWeb';
    protected string $baseUrl = 'https://api.reliefweb.int/v2/jobs';
    protected int $requestDelaySeconds = 1;

    /**
     * Fetch education and teaching jobs in Kenya from ReliefWeb REST API.
     */
    public function fetchJobs(): array
    {
        $jobs = [];

        // Build ReliefWeb API v2 Query Payload
        $apiUrl = $this->baseUrl . '?appname=mwalimulink&profile=full&limit=30'
            . '&filter[operator]=AND'
            . '&filter[conditions][0][field]=country.name'
            . '&filter[conditions][0][value]=Kenya'
            . '&filter[conditions][1][field]=theme.name'
            . '&filter[conditions][1][value]=Education';

        $response = $this->httpGet($apiUrl, ['Accept: application/json']);
        if (empty($response)) {
            return $jobs;
        }

        $data = json_decode($response, true);
        if (empty($data['data']) || !is_array($data['data'])) {
            return $jobs;
        }

        foreach ($data['data'] as $item) {
            $fields = $item['fields'] ?? [];
            if (empty($fields['title'])) {
                continue;
            }

            $title = trim($fields['title']);
            
            // Organizations / Company Name
            $orgName = 'Humanitarian Education Partner';
            if (!empty($fields['source']) && is_array($fields['source'])) {
                $firstSource = $fields['source'][0] ?? [];
                $orgName = $firstSource['name'] ?? ($firstSource['shortname'] ?? 'NGO Education Partner');
            }

            // Canonical Source URL
            $sourceUrl = $fields['url'] ?? ($item['href'] ?? '');

            // Location Text
            $location = 'Kenya';
            if (!empty($fields['city'])) {
                $location = trim($fields['city']) . ', Kenya';
            } elseif (!empty($fields['country'][0]['name'])) {
                $location = $fields['country'][0]['name'];
            }

            // Description and Requirements from ReliefWeb Markdown / Plain Text
            $body = $fields['body-html'] ?? ($fields['body'] ?? '');
            // Strip complex HTML but preserve linebreaks
            $cleanBody = strip_tags(str_replace(['<p>', '<br>', '<li>'], ["\n\n", "\n", "\n• "], $body));
            $cleanBody = trim(preg_replace("/\n{3,}/", "\n\n", $cleanBody));

            // Posted Date & Application Closing Date
            $postedDate = null;
            if (!empty($fields['date']['created'])) {
                $postedDate = date('Y-m-d H:i:s', strtotime($fields['date']['created']));
            }

            $deadline = null;
            if (!empty($fields['date']['closing'])) {
                $deadline = date('Y-m-d', strtotime($fields['date']['closing']));
            }

            // Application URL / Method
            $applyUrl = !empty($fields['how_to_apply']) ? $sourceUrl : $sourceUrl;

            $jobs[] = [
                'title' => $title,
                'company_name' => $orgName,
                'source_name' => 'ReliefWeb',
                'source_url' => $sourceUrl,
                'description' => !empty($cleanBody) ? mb_substr($cleanBody, 0, 2500) : "Education & Training program role with {$orgName}.",
                'requirements' => "Please review qualification and eligibility guidelines on ReliefWeb.",
                'salary' => null,
                'deadline' => $deadline,
                'posted_date' => $postedDate ?: date('Y-m-d H:i:s'),
                'location' => $location,
                'curriculum' => 'CBC',
                'subject_category' => 'Community & Development Education',
                'opportunity_type' => 'regular',
                'application_url' => $applyUrl,
                'application_email' => null
            ];
        }

        return $jobs;
    }
}
