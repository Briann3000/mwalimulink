<?php
// views/subscription_callback.php - IntaSend Payment Callback & Verification

// Check for necessary GET parameters
if (!isset($_GET['api_ref'])) {
    header('Location: /login/school');
    exit();
}

$api_ref = $_GET['api_ref'];
$raw_status = $_GET['status'] ?? ($_GET['state'] ?? '');
$tracking_id = $_GET['tracking_id'] ?? ($_GET['checkout_id'] ?? '');

// Extract school ID from api_ref (e.g. "MwalimuLink_School_Subscription_123_1690000000")
$parts = explode("_", $api_ref);
$school_id = null;
foreach ($parts as $part) {
    if (is_numeric($part)) {
        $school_id = (int)$part;
        break; // First number is school ID
    }
}

if (!$school_id) {
    echo "<div class='container' style='max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border: 1px solid #fee2e2; border-radius: 8px; color: #991b1b;'>
        <h3 style='margin-top:0;'>Error</h3>
        <p>Invalid payment reference parameter.</p>
        <a href='/school/dashboard' style='color: #0f766e;'>Return to Dashboard</a>
    </div>";
    exit();
}

$school = R::load('school', $school_id);
if (!$school->id) {
    echo "<div class='container' style='max-width: 600px; margin: 3rem auto; padding: 2rem; background: white; border: 1px solid #fee2e2; border-radius: 8px; color: #991b1b;'>
        <h3 style='margin-top:0;'>Error</h3>
        <p>School account not found.</p>
        <a href='/login/school' style='color: #0f766e;'>Log In</a>
    </div>";
    exit();
}

// Payment verification
$isVerified = false;
$isTestMode = (strtolower((string)env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');
$secretKey = env('INTASEND_SECRET_KEY');
$publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');

// If secret key is provided, perform direct API status verification
if (!empty($secretKey) && !empty($tracking_id)) {
    $statusUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/payment/status/" : "https://payment.intasend.com/api/v1/payment/status/";
    
    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'public_key' => $publicKey,
        'invoice_id' => $tracking_id
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = json_decode($response, true);
    if ($http_code == 200 && isset($result['invoice']['state']) && in_array(strtoupper($result['invoice']['state']), ['COMPLETE', 'SUCCESS'])) {
        $isVerified = true;
    }
}

// Fallback: check status parameter from return URL
if (!$isVerified) {
    $cleanStatus = strtoupper((string)$raw_status);
    if ($cleanStatus === 'SUCCESS' || $cleanStatus === 'COMPLETE' || $cleanStatus === 'PAID') {
        $isVerified = true;
    }
}

if ($isVerified) {
    // Payment verified: activate the Pro plan for 12 months
    $school->plan = 'pro';
    $school->status = 'active';

    $expiry_date = new DateTime();
    $expiry_date->add(new DateInterval('P12M'));
    $school->subscription_expiry = $expiry_date->format('Y-m-d H:i:s');

    R::store($school);
    auth_login($school, 'school');
}
?>

<div style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: #f8fafc;">
    <div style="max-width: 560px; width: 100%; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); text-align: center;">
        <?php if ($isVerified): ?>
            <div style="width: 64px; height: 64px; background: #ecfdf5; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                <i class="fa fa-check" style="font-size: 2rem; color: #059669;"></i>
            </div>
            
            <h2 style="margin: 0 0 8px; color: #0f172a; font-size: 1.5rem;">Payment Successful!</h2>
            <p style="margin: 0 0 1.5rem; color: #475569; font-size: 0.95rem;">
                Congratulations! <strong><?= h($school->name) ?></strong> has been upgraded to the <strong>Pro Plan</strong>.
            </p>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 2rem; text-align: left; font-size: 0.88rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: #64748b;">Plan:</span>
                    <strong style="color: #0f766e;">Institutional Pro (1 Year)</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 6px;">
                    <span style="color: #64748b;">Valid Until:</span>
                    <strong style="color: #1e293b;"><?= date('F d, Y', strtotime($school->subscription_expiry)) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Features:</span>
                    <strong style="color: #1e293b;">Unlimited Vacancies + Full Contact Access</strong>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="/school/dashboard" style="background: #0f766e; color: white !important; font-size: 0.9rem; font-weight: 700; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-tachometer-alt"></i> Go to Dashboard
                </a>
                <a href="/school/search-candidates" style="background: #f1f5f9; color: #334155 !important; font-size: 0.9rem; font-weight: 600; padding: 10px 20px; border-radius: 6px; text-decoration: none; border: 1px solid #cbd5e1; display: inline-flex; align-items: center; gap: 6px;">
                    <i class="fa fa-search"></i> Find Candidates
                </a>
            </div>

        <?php else: ?>
            <div style="width: 64px; height: 64px; background: #fef2f2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                <i class="fa fa-exclamation-triangle" style="font-size: 2rem; color: #dc2626;"></i>
            </div>
            
            <h2 style="margin: 0 0 8px; color: #0f172a; font-size: 1.5rem;">Payment Verification Pending</h2>
            <p style="margin: 0 0 1.5rem; color: #475569; font-size: 0.95rem;">
                We could not automatically confirm this transaction. If you were debited, please keep your reference code handy.
            </p>

            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; font-size: 0.85rem; color: #64748b; word-break: break-all;">
                Reference: <code><?= h($api_ref) ?></code>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="/school/pay" style="background: #0f766e; color: white !important; font-size: 0.9rem; font-weight: 700; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                    Try Payment Again
                </a>
                <a href="/school/dashboard" style="background: #f1f5f9; color: #334155 !important; font-size: 0.9rem; font-weight: 600; padding: 10px 20px; border-radius: 6px; text-decoration: none; border: 1px solid #cbd5e1;">
                    Continue on Free Plan
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
