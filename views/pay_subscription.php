<?php
// views/pay_subscription.php - School Pro Upgrade & Checkout
require_auth('school');

$authUser = auth_user();
$school_id = $authUser['user_id'];

// Fetch the school details
$school = R::load('school', $school_id);
if (!$school->id) {
    echo "<div class='container' style='padding: 2rem;'><div style='background: #fee2e2; color: #991b1b; padding: 1.5rem; border-radius: 8px;'>School account not found.</div></div>";
    exit();
}

$now = new DateTime();
$isPro = false;
if (!empty($school->subscription_expiry)) {
    try {
        $expiryDate = new DateTime($school->subscription_expiry);
        if ($school->status === 'active' && $expiryDate >= $now) {
            $isPro = true;
        }
    } catch (Exception $e) {
        $isPro = false;
    }
}

$isTestMode = (strtolower((string)env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');
$amount = (float)env('INTASEND_SUBSCRIPTION_AMOUNT', 10);
$currency = env('INTASEND_CURRENCY', 'KES');
$error_message = null;

// If the form is submitted, process the payment request
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_message = "Security token mismatch. Please refresh and try again.";
    } else {
        $publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');
        $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        $redirect_url = $appUrl . "/school/callback";
        $api_ref = "MwalimuLink_School_Subscription_" . $school->id . "_" . time();

        // Sanitize school name
        $school_name = preg_replace('/[^a-zA-Z0-9\s\-_]/', '', $school->name ?: 'School');

        $data = [
            "public_key" => $publicKey,
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

        $checkoutUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/checkout/" : "https://payment.intasend.com/api/v1/checkout/";

        $ch = curl_init($checkoutUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_err = curl_error($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if (($http_code == 200 || $http_code == 201) && isset($responseData['url'])) {
            header("Location: " . $responseData['url']);
            exit();
        } else {
            $errDetail = $responseData['detail'] ?? ($responseData['message'] ?? ($responseData['errors'][0]['message'] ?? $curl_err));
            $error_message = "Payment service error (" . $http_code . "): " . ($errDetail ?: 'Please verify IntaSend credentials or try again.');
        }
    }
}
?>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane" style="padding-top: 1.5rem !important;">
        <div style="max-width: 1080px; margin: 0 auto; width: 100%;">
            
            <!-- Page Header -->
            <div style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
                <div>
                    <h2 style="margin: 0 0 4px; color: #0f172a; font-size: 1.45rem; font-weight: 800;">Institutional Plan & Recruitment Access</h2>
                    <p style="margin: 0; font-size: 0.88rem; color: #64748b;">Manage your school's recruiting capabilities, job posting quotas, and candidate outreach.</p>
                </div>
                <div>
                    <a href="/school/dashboard" style="background: white; color: #475569; font-size: 0.82rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #cbd5e1; box-shadow: 0 1px 2px rgba(0,0,0,0.02);">
                        <i class="fa fa-arrow-left"></i> Return to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($isTestMode): ?>
                <div style="background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 12px 18px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 12px;">
                    <i class="fa fa-flask" style="font-size: 1.25rem; color: #2563eb;"></i>
                    <div>
                        <strong>IntaSend Sandbox Test Mode Active:</strong> Payment transactions are simulated on the IntaSend sandbox gateway. The test fee is set to <strong><?= h($currency) ?> <?= number_format($amount, 2) ?></strong>.
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 12px 16px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem;">
                    <i class="fa fa-exclamation-circle"></i> <?= h($error_message) ?>
                </div>
            <?php endif; ?>

            <?php if ($isPro): ?>
                <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 1.5rem; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                    <div style="display: flex; align-items: center; gap: 14px;">
                        <span style="background: #22c55e; color: white; border-radius: 50%; width: 36px; height: 36px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 800;">✓</span>
                        <div>
                            <h3 style="margin: 0 0 2px; color: #166534; font-size: 1.15rem;">Institutional Pro Plan is Active</h3>
                            <span style="font-size: 0.85rem; color: #15803d;">
                                Valid until <strong><?= date('M d, Y', strtotime($school->subscription_expiry)) ?></strong> &bull; Unlimited vacancy postings & verified candidate outreach enabled.
                            </span>
                        </div>
                    </div>
                    <div>
                        <a href="/school/search-candidates" style="background: #166534; color: white !important; font-size: 0.82rem; font-weight: 700; padding: 9px 18px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                            <i class="fa fa-search"></i> Find Candidates
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <!-- 2-Column Responsive Workspace Layout -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 1.5rem; align-items: start; margin-bottom: 2rem;">
                
                <!-- Left Column: Plan Inclusions & Features -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1rem;">
                        <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a; font-weight: 700;">Institutional Plan Capabilities</h3>
                        <span style="background: #ccfbf1; color: #0f766e; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 12px;">ANNUAL PRO</span>
                    </div>
                    <p style="margin: 0 0 1.5rem; font-size: 0.86rem; color: #64748b; line-height: 1.5;">
                        Empower your administrative recruitment team with direct access to Kenya's qualified teacher database.
                    </p>

                    <div style="display: flex; flex-direction: column; gap: 1rem;">
                        <div style="display: flex; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #f1f5f9;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #e6fffa; color: #0f766e; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fa fa-briefcase"></i>
                            </div>
                            <div>
                                <strong style="font-size: 0.9rem; color: #1e293b; display: block; margin-bottom: 2px;">Unlimited Job Vacancies</strong>
                                <span style="font-size: 0.82rem; color: #64748b; line-height: 1.4; display: block;">Publish as many active openings across all school sections (Pre-Primary, Primary, JSS, and High School) with custom requirements.</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #f1f5f9;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fa fa-envelope"></i>
                            </div>
                            <div>
                                <strong style="font-size: 0.9rem; color: #1e293b; display: block; margin-bottom: 2px;">Direct Candidate Outreach</strong>
                                <span style="font-size: 0.82rem; color: #64748b; line-height: 1.4; display: block;">Contact teachers directly via verified institutional email invitations and view full curriculum vitae & TSC credentials.</span>
                            </div>
                        </div>

                        <div style="display: flex; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #f1f5f9;">
                            <div style="width: 36px; height: 36px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                                <i class="fa fa-shield-check"></i>
                            </div>
                            <div>
                                <strong style="font-size: 0.9rem; color: #1e293b; display: block; margin-bottom: 2px;">Verified School Directory Badge</strong>
                                <span style="font-size: 0.82rem; color: #64748b; line-height: 1.4; display: block;">Prominent verified school badge in the public directory, attracting high-caliber educators across all 47 counties.</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Order Summary & Checkout Action -->
                <div style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                    <h3 style="margin: 0 0 1rem; font-size: 1.2rem; color: #0f172a; font-weight: 700;">Subscription Summary</h3>
                    
                    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.86rem;">
                            <span style="color: #64748b;">Institution:</span>
                            <strong style="color: #0f172a;"><?= h($school->name) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.86rem;">
                            <span style="color: #64748b;">Billing Contact:</span>
                            <strong style="color: #0f172a;"><?= h($school->email) ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.86rem;">
                            <span style="color: #64748b;">Duration:</span>
                            <strong style="color: #0f172a;">12 Months Access</strong>
                        </div>
                        <div style="border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; align-items: baseline;">
                            <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">Total Payable:</span>
                            <div style="text-align: right;">
                                <span style="font-size: 1.75rem; font-weight: 800; color: #0f766e;"><?= h($currency) ?> <?= number_format($amount) ?></span>
                                <span style="display: block; font-size: 0.72rem; color: #64748b;">inclusive of all fees</span>
                            </div>
                        </div>
                    </div>

                    <div style="margin-bottom: 1.5rem; font-size: 0.82rem; color: #64748b; line-height: 1.5;">
                        <strong style="color: #334155; display: block; margin-bottom: 4px;">Supported Payment Methods:</strong>
                        <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                            <span style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #15803d;"><i class="fa fa-mobile-alt"></i> M-Pesa Express</span>
                            <span style="background: #f1f5f9; padding: 4px 8px; border-radius: 4px; font-weight: 600; color: #1d4ed8;"><i class="fa fa-credit-card"></i> Visa / Mastercard</span>
                        </div>
                    </div>

                    <form method="POST" action="/school/pay" style="margin: 0;">
                        <?= csrf_field() ?>
                        <button type="submit" style="width: 100%; background: #0f766e; color: white !important; font-size: 1.05rem; font-weight: 700; padding: 14px; border-radius: 8px; border: none; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 2px 4px rgba(15,118,110,0.25); transition: background 0.2s;">
                            <i class="fa fa-lock"></i> Pay <?= h($currency) ?> <?= number_format($amount) ?> via IntaSend
                        </button>
                    </form>
                </div>
            </div>

        </div>
    </main>
</div>
