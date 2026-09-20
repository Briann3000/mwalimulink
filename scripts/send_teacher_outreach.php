<?php
// scripts/send_teacher_outreach.php - Bulk Teacher Outreach Campaign Engine
// Usage:
//   php scripts/send_teacher_outreach.php --test-to=your_email@gmail.com
//   php scripts/send_teacher_outreach.php --dry-run --file=data/teachers.csv
//   php scripts/send_teacher_outreach.php --batch=500 --file=data/teachers.csv

require_once __DIR__ . '/../config.php';

// Command line argument parser
$options = getopt('', ['file::', 'batch::', 'test-to::', 'provider::', 'dry-run', 'delay::', 'help']);

if (isset($options['help'])) {
    echo "\n=== MwalimuLink Bulk Teacher Outreach CLI ===\n";
    echo "Options:\n";
    echo "  --file=<path>          Path to CSV file (Default: data/teachers.csv or teachers.csv)\n";
    echo "  --batch=<num>          Maximum number of emails to send in this batch (Default: 500)\n";
    echo "  --provider=<name>      Provider to use: 'brevo' (300/day), 'resend' (100/day), or 'hybrid' (Default: brevo)\n";
    echo "  --test-to=<email>      Send 1 test email to verify formatting before bulk run\n";
    echo "  --dry-run              Inspect and validate CSV without sending emails\n";
    echo "  --delay=<seconds>      Delay between sends in seconds (Default: 1.2)\n";
    echo "  --help                 Display this help message\n\n";
    exit(0);
}

$csvPath = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--file=')) {
        $csvPath = trim(substr($arg, 7), " \"'");
    }
}
if (!$csvPath) {
    $csvPath = $options['file'] ?? (__DIR__ . '/../data/teachers.csv');
}
if (!file_exists($csvPath) && file_exists(__DIR__ . '/../teachers.csv')) {
    $csvPath = __DIR__ . '/../teachers.csv';
}

$batchLimit = intval($options['batch'] ?? 500);
$testRecipient = $options['test-to'] ?? null;
$selectedProvider = strtolower($options['provider'] ?? 'brevo'); // 'brevo', 'resend', or 'hybrid'
$isDryRun = isset($options['dry-run']);
$sendDelay = floatval($options['delay'] ?? 1.2); // Pacing delay in seconds

$trackerFile = __DIR__ . '/../data/campaign_tracker.json';
if (!is_dir(dirname($trackerFile))) {
    mkdir(dirname($trackerFile), 0755, true);
}

$tracker = file_exists($trackerFile) ? json_decode(file_get_contents($trackerFile), true) : [];
if (!is_array($tracker))
    $tracker = [];

$resendApiKey = env('RESEND_API_KEY');
$brevoApiKey = env('BREVO_API_KEY');
$fromAddress = env('MAIL_FROM_ADDRESS', 'info@mwalimu.info');
$brevoFromAddress = env('BREVO_FROM_ADDRESS', 'infomwalimulink@gmail.com');
$fromName = env('MAIL_FROM_NAME', 'MwalimuLink');
$replyToAddress = env('MAIL_REPLY_TO', 'infomwalimulink@gmail.com');

$unsubFile = __DIR__ . '/../data/unsubscribes.json';
$unsubList = file_exists($unsubFile) ? json_decode(file_get_contents($unsubFile), true) : [];
if (!is_array($unsubList))
    $unsubList = [];

echo "\n=======================================================\n";
echo "  MwalimuLink Verified Educator Outreach Engine\n";
echo "=======================================================\n";
echo "Sender:      {$fromName} <{$fromAddress}>\n";
echo "Reply-To:    {$replyToAddress}\n";
echo "Provider:    " . strtoupper($selectedProvider) . "\n";
echo "Brevo Key:   " . (!empty($brevoApiKey) ? substr($brevoApiKey, 0, 14) . '...' : 'NOT SET') . "\n";
echo "Resend Key:  " . (!empty($resendApiKey) ? substr($resendApiKey, 0, 10) . '...' : 'NOT SET') . "\n";
echo "CSV Source:  {$csvPath}\n";
echo "Batch Limit: {$batchLimit} emails\n";
echo "Delay:       {$sendDelay}s per dispatch\n";
echo "=======================================================\n\n";

if ($selectedProvider === 'brevo' && empty($brevoApiKey) && !$isDryRun && empty($testRecipient)) {
    die("Error: BREVO_API_KEY is not configured in .env.\n");
}
if ($selectedProvider === 'resend' && empty($resendApiKey) && !$isDryRun && empty($testRecipient)) {
    die("Error: RESEND_API_KEY is not configured in .env.\n");
}

/**
 * Clean and format teacher's first name for warm personalization
 */
function extract_first_name($fullName)
{
    if (empty($fullName))
        return '';
    $clean = preg_replace('/^(mr\.|mrs\.|ms\.|dr\.|prof\.|tr\.|tr )\s+/i', '', trim($fullName));
    $parts = preg_split('/\s+/', $clean);
    return !empty($parts[0]) ? ucfirst(strtolower($parts[0])) : '';
}

/**
 * Clean and format teacher's full display name
 */
function clean_teacher_name($name)
{
    if (empty($name))
        return '';
    $words = explode(' ', trim($name));
    $cleanWords = array_map(function ($w) {
        return ucfirst(strtolower($w));
    }, $words);
    return implode(' ', $cleanWords);
}

/**
 * Build Outreach Email HTML Template (Optimized for Primary Inbox Placement)
 */
function build_outreach_email_html($teacherName, $teacherEmail, $profileSnippet = '')
{
    $hasName = !empty($teacherName) && strtolower(trim($teacherName)) !== 'educator';
    $greeting = $hasName ? ('Dear Mwalimu ' . htmlspecialchars(extract_first_name($teacherName))) : 'Dear Mwalimu';

    $registerUrl = "https://www.mwalimu.info/register/teacher?email=" . urlencode($teacherEmail);
    if ($hasName) {
        $registerUrl .= "&name=" . urlencode($teacherName);
    }
    $unsubscribeUrl = "https://www.mwalimu.info/unsubscribe?email=" . urlencode($teacherEmail);

    return '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    </head>
    <body style="font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; font-size: 15px; line-height: 1.6; color: #1e293b; background-color: #ffffff; margin: 0; padding: 16px 8px;">
        <div style="max-width: 560px; margin: 0 auto;">
            
            <p style="margin-top: 0; font-size: 15px; font-weight: 600; color: #0f172a;">
                ' . $greeting . ',
            </p>

            <p>
                I hope this email finds you well.
            </p>

            <p>
                I am reaching out from <strong>MwalimuLink</strong> (<a href="https://www.mwalimu.info" style="color: #0f766e; text-decoration: underline;">www.mwalimu.info</a>) — Kenya\'s dedicated educator platform connecting teachers directly with hiring administrators across public, private, and international schools.
            </p>

            <p>
                Whether you are an active teacher looking for your next placement, a BOM educator, or seeking a Teaching Practice (TP) attachment, MwalimuLink helps you get discovered:
            </p>

            <ul style="padding-left: 20px; margin: 12px 0; color: #334155; line-height: 1.6;">
                <li style="margin-bottom: 6px;"><strong>Direct School Access:</strong> Verified headteachers and principals search our directory daily for subject teachers.</li>
                <li style="margin-bottom: 6px;"><strong>Automated CV Builder:</strong> Generate a professional, standard Kenyan teacher CV formatted for school boards.</li>
                <li style="margin-bottom: 6px;"><strong>Nationwide Directory:</strong> Search over 30,000 schools across all 47 counties.</li>
                <li style="margin-bottom: 6px;"><strong>Teaching Practice (TP):</strong> Find approved schools accepting student teachers.</li>
            </ul>

            <p>
                Joining is completely free and takes less than 2 minutes:
            </p>

            <p style="margin: 20px 0;">
                👉 <a href="' . $registerUrl . '" style="color: #0f766e; font-weight: 700; text-decoration: underline; font-size: 15px;">Create Your Free Educator Profile Here</a>
            </p>

            <p>
                If you have any questions or need help setting up your profile, feel free to reply directly to this email and our team will be glad to assist.
            </p>

            <p style="margin-bottom: 0;">
                Warm regards,<br>
                <strong>The MwalimuLink Team</strong><br>
                <span style="color: #64748b; font-size: 13px;">Nairobi, Kenya &bull; <a href="https://www.mwalimu.info" style="color: #64748b;">www.mwalimu.info</a></span>
            </p>

            <div style="margin-top: 36px; padding-top: 14px; border-top: 1px solid #f1f5f9; font-size: 11px; color: #94a3b8;">
                If you prefer not to receive notifications, you can <a href="' . $unsubscribeUrl . '" style="color: #94a3b8; text-decoration: underline;">opt out here</a>.
            </div>
        </div>
    </body>
    </html>';
}

/**
 * Dispatch single email via Resend API
 */
function send_resend_email($apiKey, $from, $to, $subject, $html, $replyTo = 'infomwalimulink@gmail.com')
{
    $payload = [
        'from' => $from,
        'to' => [$to],
        'subject' => $subject,
        'html' => $html,
        'text' => strip_tags($html)
    ];

    if (!empty($replyTo)) {
        $payload['reply_to'] = $replyTo;
    }

    $ch = curl_init('https://api.resend.com/emails');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'Content-Type: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrNo = curl_errno($ch);
    curl_close($ch);

    if ($curlErrNo !== 0) {
        return ['success' => false, 'error' => "cURL Error ({$curlErrNo}): {$curlErr}"];
    }

    $resData = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'id' => $resData['id'] ?? 'ok', 'provider' => 'resend'];
    }
    return ['success' => false, 'error' => $resData['message'] ?? "HTTP {$httpCode}: {$response}"];
}

/**
 * Dispatch single email via Brevo API (v3 transactional emails)
 */
function send_brevo_email($apiKey, $fromName, $fromEmail, $toEmail, $subject, $html, $replyToEmail = 'infomwalimulink@gmail.com')
{
    $payload = [
        'sender' => [
            'name' => $fromName,
            'email' => $fromEmail
        ],
        'to' => [
            ['email' => $toEmail]
        ],
        'replyTo' => [
            'email' => $replyToEmail,
            'name' => $fromName
        ],
        'subject' => $subject,
        'htmlContent' => $html,
        'textContent' => strip_tags($html)
    ];

    $ch = curl_init('https://api.brevo.com/v3/smtp/email');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'api-key: ' . $apiKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    $curlErrNo = curl_errno($ch);
    curl_close($ch);

    if ($curlErrNo !== 0) {
        return ['success' => false, 'error' => "cURL Error ({$curlErrNo}): {$curlErr}"];
    }

    $resData = json_decode($response, true);
    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'id' => $resData['messageId'] ?? 'ok', 'provider' => 'brevo'];
    }
    return ['success' => false, 'error' => $resData['message'] ?? "HTTP {$httpCode}: {$response}"];
}

// -------------------------------------------------------------
// 1. Handle Test Send Mode
// -------------------------------------------------------------
if (!empty($testRecipient)) {
    echo "[TEST MODE] Sending 1 sample outreach email via " . strtoupper($selectedProvider) . " to: {$testRecipient}...\n";
    $sampleName = "";
    $html = build_outreach_email_html($sampleName, $testRecipient, "");
    $subject = "Connect with verified schools hiring on MwalimuLink";

    if ($selectedProvider === 'brevo') {
        $result = send_brevo_email($brevoApiKey, $fromName, $brevoFromAddress, $testRecipient, $subject, $html, $replyToAddress);
    } else {
        $result = send_resend_email($resendApiKey, "{$fromName} <{$fromAddress}>", $testRecipient, $subject, $html, $replyToAddress);
    }

    if ($result['success']) {
        echo "✅ SUCCESS! Test email dispatched via " . ucfirst($result['provider']) . " (ID: {$result['id']}).\nCheck your inbox at {$testRecipient}!\n\n";
    } else {
        echo "❌ FAILED: " . $result['error'] . "\n\n";
    }
    exit(0);
}

// -------------------------------------------------------------
// 2. Parse CSV and Execute Outreach Campaign
// -------------------------------------------------------------
if (!file_exists($csvPath)) {
    die("Error: CSV file not found at '{$csvPath}'. Please place your teachers CSV file there and re-run.\n");
}

$handle = fopen($csvPath, 'r');
if (!$handle) {
    die("Error: Unable to open CSV file at '{$csvPath}'.\n");
}

$firstRow = fgetcsv($handle);
if (!$firstRow) {
    die("Error: CSV file is empty.\n");
}

// Auto-detect if first row is a header or already data
$isHeader = false;
$colMap = ['name' => null, 'email' => null];

foreach ($firstRow as $idx => $val) {
    $norm = strtolower(trim(preg_replace('/[^a-zA-Z0-9]/', '', $val)));
    if (in_array($norm, ['fullnames', 'fullname', 'name', 'teachername'])) {
        $colMap['name'] = $idx;
        $isHeader = true;
    }
    if (in_array($norm, ['email', 'emailaddress', 'mail', 'emails'])) {
        $colMap['email'] = $idx;
        $isHeader = true;
    }
}

$rowsToProcess = [];
if ($isHeader) {
    echo "Detected CSV header row.\n";
} else {
    // First row is actual data
    $rowsToProcess[] = $firstRow;
}

while (($row = fgetcsv($handle)) !== false) {
    $rowsToProcess[] = $row;
}
fclose($handle);

$totalInCsv = 0;
$alreadySentCount = 0;
$invalidEmailCount = 0;
$dispatchedInBatch = 0;
$failedCount = 0;

$pendingQueue = [];
$seenEmails = [];

foreach ($rowsToProcess as $row) {
    $totalInCsv++;

    // Find email in row columns
    $email = '';
    $name = 'Educator';

    if ($colMap['email'] !== null && !empty($row[$colMap['email']])) {
        $email = strtolower(trim($row[$colMap['email']]));
    } else {
        // Auto-detect which column contains the email address
        foreach ($row as $cell) {
            $cleaned = strtolower(trim($cell));
            if (filter_var($cleaned, FILTER_VALIDATE_EMAIL)) {
                $email = $cleaned;
                break;
            }
        }
    }

    if (!empty($colMap['name']) && !empty($row[$colMap['name']])) {
        $name = trim($row[$colMap['name']]);
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $invalidEmailCount++;
        continue;
    }

    // Skip duplicates within the CSV file itself
    if (isset($seenEmails[$email])) {
        continue;
    }
    $seenEmails[$email] = true;

    if (isset($tracker[$email])) {
        $alreadySentCount++;
        continue;
    }

    // Skip unsubscribed contacts
    if (isset($unsubList[$email])) {
        continue;
    }

    $pendingQueue[] = [
        'email' => $email,
        'name' => $name,
        'profile' => ''
    ];
}

echo "Campaign Status Summary:\n";
echo "  Total Records in CSV: " . number_format($totalInCsv) . "\n";
echo "  Already Sent (Logged): " . number_format($alreadySentCount) . "\n";
echo "  Invalid Emails:       " . number_format($invalidEmailCount) . "\n";
echo "  Pending to Send:      " . number_format(count($pendingQueue)) . "\n\n";

if ($isDryRun) {
    echo "ℹ️ DRY-RUN complete. No emails were sent. Remove --dry-run to start dispatching.\n\n";
    exit(0);
}

if (empty($pendingQueue)) {
    echo "🎉 All valid emails in the CSV have already been sent! Nothing to dispatch.\n\n";
    exit(0);
}

$toProcess = array_slice($pendingQueue, 0, $batchLimit);
echo "Starting dispatch of " . count($toProcess) . " emails (Batch Limit: {$batchLimit})...\n\n";

foreach ($toProcess as $index => $item) {
    $num = $index + 1;
    $email = $item['email'];
    $name = $item['name'];
    $hasName = !empty($name) && strtolower(trim($name)) !== 'educator';
    $subject = $hasName
        ? "Mwalimu " . extract_first_name($name) . ", connect with hiring schools on MwalimuLink"
        : "Connect with verified schools hiring on MwalimuLink";
    $html = build_outreach_email_html($name, $email, '');

    if ($selectedProvider === 'brevo') {
        $result = send_brevo_email($brevoApiKey, $fromName, $brevoFromAddress, $email, $subject, $html, $replyToAddress);
    } else {
        $result = send_resend_email($resendApiKey, "{$fromName} <{$fromAddress}>", $email, $subject, $html, $replyToAddress);
    }

    if ($result['success']) {
        $dispatchedInBatch++;
        $tracker[$email] = [
            'name' => $name,
            'sent_at' => date('Y-m-d H:i:s'),
            'provider' => $result['provider'],
            'message_id' => $result['id']
        ];
        echo "[{$num}/" . count($toProcess) . "] ✅ Sent via " . ucfirst($result['provider']) . " to: {$email} ({$name})\n";
    } else {
        $failedCount++;
        echo "[{$num}/" . count($toProcess) . "] ❌ Failed for: {$email} - {$result['error']}\n";
    }

    // Save tracker every 10 sends to be crash-resilient
    if ($num % 10 === 0) {
        file_put_contents($trackerFile, json_encode($tracker, JSON_PRETTY_PRINT));
    }

    // Rate pacing delay
    usleep((int) ($sendDelay * 1000000));
}

// Final tracker save
file_put_contents($trackerFile, json_encode($tracker, JSON_PRETTY_PRINT));

echo "\n=======================================================\n";
echo "Batch Completed Successfully!\n";
echo "  Dispatched this batch: {$dispatchedInBatch}\n";
echo "  Failed this batch:     {$failedCount}\n";
echo "  Total Lifetime Sent:   " . count($tracker) . " / " . number_format($totalInCsv) . "\n";
echo "=======================================================\n\n";
