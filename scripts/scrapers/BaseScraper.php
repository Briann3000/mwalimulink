<?php
// scripts/scrapers/BaseScraper.php - Base Scraper Class for Job Aggregation
abstract class BaseScraper
{
    protected string $sourceName = 'External';
    protected string $baseUrl = '';
    protected int $requestDelaySeconds = 1; // Polite rate limiting

    /**
     * Fetch and return normalized job data array.
     * Each item in the returned array MUST be formatted as:
     * [
     *   'title' => string,
     *   'company_name' => string,
     *   'source_name' => string,
     *   'source_url' => string,
     *   'description' => string,
     *   'requirements' => string,
     *   'salary' => float|null,
     *   'deadline' => string|null (Y-m-d),
     *   'posted_date' => string|null (Y-m-d),
     *   'location' => string,
     *   'curriculum' => string|null,
     *   'subject_category' => string|null,
     *   'application_url' => string|null,
     *   'application_email' => string|null
     * ]
     *
     * @return array
     */
    abstract public function fetchJobs(): array;

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    /**
     * Perform an HTTP GET request with standard browser headers and rate limit pause.
     */
    protected function httpGet(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);

        $defaultHeaders = [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,application/json,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.9',
            'Cache-Control: no-cache',
            'Pragma: no-cache',
            'User-Agent: MwalimuLinkJobAggregator/1.0 (+https://mwalimulink.com; education-crawler)'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => ''
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->requestDelaySeconds > 0) {
            sleep($this->requestDelaySeconds);
        }

        if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
            return $response;
        }

        error_log("[{$this->sourceName} Scraper] HTTP GET error ({$httpCode}) for URL {$url}: {$error}");
        return null;
    }

    /**
     * Perform an HTTP POST request sending JSON payload.
     */
    protected function httpPostJson(string $url, array $payload, array $headers = []): ?string
    {
        $ch = curl_init($url);

        $jsonPayload = json_encode($payload);
        $defaultHeaders = [
            'Content-Type: application/json',
            'Accept: application/json',
            'User-Agent: MwalimuLinkJobAggregator/1.0 (+https://mwalimulink.com)'
        ];

        $allHeaders = array_merge($defaultHeaders, $headers);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $jsonPayload,
            CURLOPT_TIMEOUT => 25,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($this->requestDelaySeconds > 0) {
            sleep($this->requestDelaySeconds);
        }

        if ($httpCode >= 200 && $httpCode < 300 && $response !== false) {
            return $response;
        }

        error_log("[{$this->sourceName} Scraper] HTTP POST JSON error ({$httpCode}) for URL {$url}: {$error}");
        return null;
    }
}
