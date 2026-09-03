<?php
// views/subscription_callback.php

// Check for necessary GET parameters
if (!isset($_GET['api_ref'])) {
    header('Location: index.php?action=school_login');
    exit();
}

$api_ref = $_GET['api_ref'];
$raw_status = $_GET['status'] ?? '';
$tracking_id = $_GET['tracking_id'] ?? ($_GET['checkout_id'] ?? '');

// Extract school ID from the api_ref (e.g. "MwalimuLink_School_Subscription_123_1690000000")
$parts = explode("_", $api_ref);
$school_id = null;
foreach ($parts as $part) {
    if (is_numeric($part)) {
        $school_id = (int)$part;
        break; // First number is the school ID
    }
}

if (!$school_id) {
    echo "<div class='container'><article class='message -error'><h2>Error</h2><p>Invalid School Reference in payment callback.</p></article></div>";
    exit();
}

$school = R::load('school', $school_id);
if (!$school->id) {
    echo "<div class='container'><article class='message -error'><h2>Error</h2><p>School not found in database.</p></article></div>";
    exit();
}

// Server-side payment verification
$isVerified = false;
$secretKey = env('INTASEND_SECRET_KEY');

// If secret key is provided, perform direct API status verification
if (!empty($secretKey) && !empty($tracking_id)) {
    $ch = curl_init("https://payment.intasend.com/api/v1/payment/status/");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'public_key' => env('INTASEND_PUBLIC_KEY'),
        'invoice_id' => $tracking_id
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($http_code == 200 && isset($result['invoice']['state']) && ($result['invoice']['state'] === 'COMPLETE' || $result['invoice']['state'] === 'SUCCESS')) {
        $isVerified = true;
    }
} else {
    // Fallback if secret key not yet configured: check status parameter from return URL
    if (strtoupper($raw_status) === 'SUCCESS' || strtoupper($raw_status) === 'COMPLETE') {
        $isVerified = true;
    }
}

if ($isVerified) {
    // Payment verified: activate the account for 12 months
    $school->status = 'active';

    $expiry_date = new DateTime();
    $expiry_date->add(new DateInterval('P12M'));
    $school->subscription_expiry = $expiry_date->format('Y-m-d H:i:s');

    R::store($school);
    auth_login($school, 'school');

    echo "<div class='container' style='max-width: 700px; margin: 2rem auto;'>
        <article class='card'>
            <header>
                <h2 style='color: #28a745;'>🎉 Payment Successful!</h2>
            </header>
            <p>Your school subscription is now <strong>Active</strong> and valid until <strong>" . htmlspecialchars($expiry_date->format('d M Y')) . "</strong>.</p>
            <p>You can now search available teachers, view contact profiles, and post job vacancies.</p>
            <footer>
                <a href='index.php?action=school_dashboard' role='button' class='primary'>Go to School Dashboard</a>
            </footer>
        </article>
    </div>";
} else {
    echo "<div class='container' style='max-width: 700px; margin: 2rem auto;'>
        <article class='card'>
            <header>
                <h2 style='color: #dc3545;'>⚠️ Payment Verification Failed</h2>
            </header>
            <p>Your payment could not be automatically confirmed. If funds were deducted, please contact support with reference: <code>" . h($api_ref) . "</code>.</p>
            <footer>
                <a href='index.php?action=pay_subscription' role='button' class='secondary'>Try Again</a>
            </footer>
        </article>
    </div>";
}
?>
