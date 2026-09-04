<?php
// views/school_subscribe.php - School Subscription Portal
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];

// Fetch the school details
$school = R::load('school', $school_id);
if (!$school->id) {
    echo "<div class='container' style='padding: 2rem;'><div style='background: #fee2e2; color: #991b1b; padding: 1.5rem; border-radius: 8px;'>School account not found.</div></div>";
    exit();
}

$error = '';
// If the form is submitted, process the payment request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please refresh and try again.";
    } else {
        $public_key = env('INTASEND_PUBLIC_KEY', 'ISPubKey_live_40f25458-716c-47c5-b049-786fd1f3a1ce');
        $amount = 12000;
        $currency = "KES";
        $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        $redirect_url = $appUrl . "/school/callback";
        $api_ref = "MwalimuLink_School_Subscription_" . $school->id . "_" . time();

        $school_name = preg_replace('/[^a-zA-Z0-9\\s\\-\\_]/', '', $school->name ?: 'School');

        $data = [
            "public_key" => $public_key,
            "amount" => $amount,
            "currency" => $currency,
            "api_ref" => $api_ref,
            "redirect_url" => $redirect_url,
            "first_name" => $school_name,
            "last_name" => "Subscription",
            "email" => $school->email,
            "phone" => $school->phone_number,
            "country" => "KE"
        ];

        $url = "https://payment.intasend.com/api/v1/checkout/";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "Accept: application/json"
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $responseData = json_decode($response, true);
        if ($http_code == 200 || $http_code == 201) {
            if (isset($responseData['url'])) {
                header("Location: " . $responseData['url']);
                exit();
            } else {
                $error = "Payment initiation received without redirect URL.";
            }
        } else {
            $error = "Payment Gateway Error: " . ($responseData['detail'] ?? ($responseData['message'] ?? "Status {$http_code}"));
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane">
        <div style="max-width: 700px; margin: 0 auto;">
            <div style="margin-bottom: 1.5rem;">
                <h2 style="margin: 0 0 4px; color: #0f172a;">School Subscription & Recruitment Access</h2>
                <p style="margin: 0; font-size: 0.88rem; color: #64748b;">Activate premium search, unlimited vacancy posting, and direct contact with verified educators.</p>
            </div>

            <?php if ($error): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error) ?>
                </div>
            <?php endif; ?>

            <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 2rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.5rem; border-bottom: 1px solid #f1f5f9; padding-bottom: 1rem; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h3 style="margin: 0 0 4px; color: #0f766e; font-size: 1.25rem;">Annual Institutional Plan</h3>
                        <span style="font-size: 0.85rem; color: #64748b;">Full 12-Month Access for <?= h($school->name) ?></span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 1.6rem; font-weight: 800; color: #0f172a;">KES 12,000</span>
                        <span style="display: block; font-size: 0.75rem; color: #64748b;">billed annually</span>
                    </div>
                </div>

                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <h4 style="margin: 0 0 10px; color: #166534; font-size: 0.95rem;">Plan Inclusions:</h4>
                    <ul style="margin: 0; padding-left: 20px; color: #15803d; font-size: 0.88rem; line-height: 1.7;">
                        <li><strong>Unlimited Job Postings:</strong> Publish vacancies across all subjects and grades.</li>
                        <li><strong>Direct WhatsApp & Email Contact:</strong> Instant access to qualified teacher details.</li>
                        <li><strong>Verified Candidate Filtering:</strong> Filter teachers with authenticated Good Conduct certificates.</li>
                        <li><strong>Priority Placement:</strong> Feature your school in the verified school directory.</li>
                    </ul>
                </div>

                <form method="POST" action="/school/subscribe" style="margin: 0;">
                    <?= csrf_field() ?>
                    <button type="submit" style="width: 100%; background: #0f766e; color: white !important; font-size: 1rem; font-weight: 700; padding: 12px; border-radius: 6px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px;">
                        <i class="fa fa-credit-card"></i> Proceed to Secure IntaSend Checkout
                    </button>
                </form>
            </div>
        </div>
    </main>
</div>
