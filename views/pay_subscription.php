<?php
// views/pay_subscription.php
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];

// Fetch the school details
$school = R::load('school', $school_id);
if (!$school->id) {
    echo "<div class='container'><h2>Error</h2><p>School not found.</p></div>";
    exit();
}

$amount = (float)env('INTASEND_SUBSCRIPTION_AMOUNT', 1000);
$currency = env('INTASEND_CURRENCY', 'KES');
$error_message = null;

// If the form is submitted, process the payment request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    validate_csrf();
    
    $publicKey = env('INTASEND_PUBLIC_KEY');
    $appUrl = rtrim(env('APP_URL', 'https://mwalimu.info'), '/');
    $redirect_url = $appUrl . "/school/callback";
    $api_ref = "MwalimuLink_School_Subscription_" . $school->id . "_" . time();

    // Sanitize school name
    $school_name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $school->name);

    $data = [
        "public_key" => $publicKey,
        "amount" => $amount,
        "currency" => $currency,
        "api_ref" => $api_ref,
        "redirect_url" => $redirect_url,
        "first_name" => $school_name,
        "last_name" => "",
        "email" => $school->email,
        "phone" => $school->phone_number,
        "country" => "KE"
    ];

    $ch = curl_init("https://payment.intasend.com/api/v1/checkout/");
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json'
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $responseData = json_decode($response, true);

    if ($http_code == 201 && isset($responseData['url'])) {
        header("Location: " . $responseData['url']);
        exit();
    } else {
        $error_message = "Payment service error (" . $http_code . "): " . ($responseData['detail'] ?? 'Please verify IntaSend credentials in .env');
    }
}
?>

<article class="card" style="max-width: 80%; margin: 2rem auto;">
    <header>
        <hgroup>
            <h1>School Annual Subscription</h1>
            <p>Kindly settle the annual subscription fee to activate candidate search and job posting features.</p>
        </hgroup>
    </header>

    <?php if ($error_message): ?>
        <div class="alert alert-error">
            <p><?= h($error_message) ?></p>
        </div>
    <?php endif; ?>

    <figure>
        <img src="payment.jpg" alt="Payment Illustration">
        <figcaption>Secure and fast M-Pesa & Card payment via IntaSend.</figcaption>
    </figure>

    <div style="text-align: center; padding: 1.5rem;">
        <h3>Subscription Fee: <?= h($currency) ?> <?= number_format($amount) ?> / Year</h3>
        <p>School: <strong><?= h($school->name) ?></strong> (<?= h($school->email) ?>)</p>
        
        <form method="post">
            <?= csrf_field() ?>
            <button type="submit" class="contrast" style="font-size: 1.2rem; padding: 0.8rem 2rem;">Pay Now via IntaSend</button>
        </form>
    </div>
    
    <footer>
        <small>&copy; <?= date('Y') ?> Mwalimu Link. All Rights Reserved.</small>
    </footer>
</article>

