<?php
define('MWALIMU_TEST_MODE', true);
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';
require_once __DIR__ . '/../services/JobIngestionService.php';

echo "=== MWALIMULINK JOB INGESTION TEST SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assertTest($name, $condition, $failMessage = '')
{
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo " [FAIL] $name - $failMessage\n";
        $failCount++;
    }
}

// -------------------------------------------------------------
// Test 1: XSS and Dangerous Protocol Sanitization
// -------------------------------------------------------------
$xssPayload = [
    'title' => 'Biology Teacher <script>alert("xss")</script>',
    'company_name' => 'Nairobi Academy <img src=x onerror=alert(1)>',
    'source_name' => 'Aggregator Test',
    'source_url' => 'javascript:alert(document.cookie)', // Dangerous scheme
    'description' => 'Teaching CBC biology. <script>evil()</script><b>Great environment</b>',
    'requirements' => 'Degree in Education. <a href="http://evil.com" onclick="steal()">Click</a>',
    'application_url' => 'vbscript:msgbox("pwnd")', // Dangerous scheme
    'curriculum' => 'CBC',
    'location' => 'Nairobi'
];

$res1 = JobIngestionService::ingest($xssPayload);
assertTest('Rejects dangerous javascript: source URL', $res1['action'] === 'rejected_invalid');

// Safe source URL with XSS in text fields
$xssPayload['source_url'] = 'https://example.com/jobs/biology-teacher';
$res1b = JobIngestionService::ingest($xssPayload);
assertTest('Accepts sanitized entry with valid URL', $res1b['action'] === 'inserted' && $res1b['job_id'] > 0);

if ($res1b['job_id']) {
    $insertedJob = R::load('job', $res1b['job_id']);
    assertTest('Sanitizes XSS from Title', !str_contains($insertedJob->title, '<script>') && $insertedJob->title === 'Biology Teacher alert("xss")');
    assertTest('Sanitizes XSS from Company Name', !str_contains($insertedJob->company_name, '<img'));
    assertTest('Sanitizes XSS from Description', !str_contains($insertedJob->description, '<script>'));
    assertTest('Falls back to source_url when application_url has dangerous vbscript: scheme', $insertedJob->application_url === 'https://example.com/jobs/biology-teacher');

    // Clean up test record
    R::trash($insertedJob);
}

// -------------------------------------------------------------
// Test 2: Deduplication by SHA-256 Content Hash
// -------------------------------------------------------------
$jobA = [
    'title' => 'Head of Mathematics (IGCSE)',
    'company_name' => 'Premier International School',
    'source_name' => 'ReliefWeb Education',
    'source_url' => 'https://example.com/jobs/math-lead',
    'description' => 'Leading Cambridge IGCSE Math Department in Nairobi.',
    'deadline' => date('Y-m-d', strtotime('+30 days')),
    'location' => 'Nairobi'
];

// Clean prior test runs if any
$existingTestJob = R::findOne('job', 'source_url = ?', ['https://example.com/jobs/math-lead']);
if ($existingTestJob) {
    R::trash($existingTestJob);
}

$resA1 = JobIngestionService::ingest($jobA);
assertTest('First insert succeeds', $resA1['action'] === 'inserted');

$resA2 = JobIngestionService::ingest($jobA);
assertTest('Duplicate insert is skipped safely', $resA2['action'] === 'skipped_duplicate' && $resA2['job_id'] === $resA1['job_id']);

// Cleanup
if (!empty($resA1['job_id'])) {
    $jobObj = R::load('job', $resA1['job_id']);
    R::trash($jobObj);
}

// -------------------------------------------------------------
// Test 3: Past Deadline Rejection
// -------------------------------------------------------------
$expiredJob = [
    'title' => 'English Teacher',
    'company_name' => 'County High School',
    'source_name' => 'Test Source',
    'source_url' => 'https://example.com/jobs/expired-english',
    'description' => 'English language vacancy.',
    'deadline' => date('Y-m-d', strtotime('-5 days')), // 5 days ago
    'location' => 'Nakuru'
];

$resExp = JobIngestionService::ingest($expiredJob);
assertTest('Expired deadline is skipped on ingestion', $resExp['action'] === 'skipped_expired');

// -------------------------------------------------------------
// Test 4: Intelligent Curriculum and Subject Categorization
// -------------------------------------------------------------
$tag1 = JobIngestionService::detectCurriculum('Looking for a Cambridge A-Level Chemistry teacher');
assertTest('Detects IGCSE/Cambridge curriculum', $tag1 === 'IGCSE');

$tag2 = JobIngestionService::detectCurriculum('Junior Secondary School Grade 7 CBC Facilitator');
assertTest('Detects CBC curriculum', $tag2 === 'CBC');

$tag3 = JobIngestionService::detectCurriculum('IB Diploma Physics and Mathematics Higher Level');
assertTest('Detects IB curriculum', $tag3 === 'IB');

$subj1 = JobIngestionService::detectSubjectCategory('Teacher of Kiswahili and CRE');
assertTest('Detects Languages subject category', $subj1 === 'Languages');

$subj2 = JobIngestionService::detectSubjectCategory('Computer Science & ICT Instructor');
assertTest('Detects Technical & Creative Arts subject category', $subj2 === 'Technical & Creative Arts');

// -------------------------------------------------------------
// Summary
// -------------------------------------------------------------
echo "\n============================================\n";
echo "Test Suite Finished: {$passCount} Passed, {$failCount} Failed\n";
echo "============================================\n";

exit($failCount === 0 ? 0 : 1);
