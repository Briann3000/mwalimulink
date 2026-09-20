<?php
// views/pay_directory_access.php - Directory Access Fee Checkout (KES 100 for 1-Year Access)

if (!is_logged_in()) {
    $targetRedirect = $_GET['redirect'] ?? '/schools/private';
    header('Location: /login?redirect=' . urlencode($targetRedirect) . '&notice=auth_required');
    exit();
}

$authUser = auth_user();
$userId = intval($authUser['user_id'] ?? 0);
$userRole = trim($authUser['role'] ?? 'teacher');
$userEmail = strtolower(trim($authUser['email'] ?? ''));
$targetRedirect = $_GET['redirect'] ?? ($_POST['target_redirect'] ?? '/schools/private');

// Fetch user object to pre-fill phone and name
$userObj = null;
if ($userRole === 'teacher') {
    $userObj = R::load('teacher', $userId);
} elseif ($userRole === 'school') {
    $userObj = R::load('school', $userId);
}

$userName = $authUser['name'] ?: ($userObj->name ?? 'Educator');
$userPhone = $userObj->phone_number ?? ($userObj->mobile ?? '');

// Check if access is already active
$isAlreadyActive = has_directory_access();
$activeExpiryDate = null;
if ($isAlreadyActive) {
    $access = R::findOne('directoryaccess', '((user_id = ? AND user_type = ?) OR email = ?) AND status = "active"', [$userId, $userRole, $userEmail]);
    if ($access && !empty($access->expires_at)) {
        $activeExpiryDate = $access->expires_at;
    }
}

$isTestMode = (strtolower((string) env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');
$amount = (float) get_setting('directory_fee', 100);
$durationMonths = (int) get_setting('directory_access_months', 12);
$currency = (string) get_setting('payment_currency', 'KES');
$publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');
$errorMessage = null;

// Server-Side Fallback Handler (if JS disabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validate_csrf();

    $phoneInput = trim($_POST['phone'] ?? $userPhone);
    $normalizedPhone = normalize_kenyan_phone($phoneInput) ?: $phoneInput;

    if (empty($publicKey)) {
        $errorMessage = "Payment gateway is currently unavailable. Please contact support.";
    } else {
        $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
        $callbackUrl = $appUrl . "/payment/callback";
        $randomHex = bin2hex(random_bytes(4));
        $apiRef = "MWALIMU_DIR_" . $userRole . "_" . $userId . "_" . time() . "_" . $randomHex;

        $nameParts = explode(' ', trim($userName), 2);
        $firstName = preg_replace('/[^a-zA-Z0-9]/', '', $nameParts[0] ?? 'Educator');
        $lastName = preg_replace('/[^a-zA-Z0-9]/', '', $nameParts[1] ?? 'Access');
        if (empty($firstName))
            $firstName = 'Educator';
        if (empty($lastName))
            $lastName = 'Pass';

        try {
            $payment = R::dispense('payment');
            $payment->user_id = $userId;
            $payment->user_type = $userRole;
            $payment->email = $userEmail;
            $payment->phone = $normalizedPhone;
            $payment->amount = $amount;
            $payment->currency = $currency;
            $payment->purpose = 'directory_access';
            $payment->api_ref = $apiRef;
            $payment->target_redirect = $targetRedirect;
            $payment->state = 'PENDING';
            $payment->created_at = date('Y-m-d H:i:s');
            $payment->updated_at = date('Y-m-d H:i:s');
            R::store($payment);
        } catch (\Throwable $e) {
            error_log("Failed to create pending payment record: " . $e->getMessage());
        }

        $payload = [
            "public_key" => $publicKey,
            "amount" => $amount,
            "currency" => $currency,
            "api_ref" => $apiRef,
            "redirect_url" => $callbackUrl,
            "first_name" => $firstName,
            "last_name" => $lastName,
            "email" => $userEmail,
            "phone_number" => $normalizedPhone,
            "country" => "KE"
        ];

        $checkoutUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/checkout/" : "https://payment.intasend.com/api/v1/checkout/";

        $ch = curl_init($checkoutUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $responseData = json_decode($response, true);

        if (($httpCode == 200 || $httpCode == 201) && isset($responseData['url'])) {
            header("Location: " . $responseData['url']);
            exit();
        } else {
            $errDetail = $responseData['detail'] ?? ($responseData['message'] ?? ($responseData['errors'][0]['message'] ?? $curlErr));
            $errorMessage = "Payment initialization failed: " . ($errDetail ?: 'Please check your connection or contact support.');
        }
    }
}
?>

<!-- Include Payment Gateway Inline JS SDK -->
<script src="https://unpkg.com/intasend-inlinejs-sdk@latest/build/intasend-inline.js"></script>

<div class="workspace-wrapper">
    <?php include __DIR__ . '/partials/sidebar.php'; ?>

    <main class="content-pane"
        style="max-width: 1100px; width: 100%; margin: 0 auto; padding: 2rem 1.5rem; box-sizing: border-box;">

        <!-- Header Breadcrumb -->
        <div
            style="margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px;">
            <div>
                <h2 style="margin: 0 0 4px; color: #0f172a; font-size: 1.45rem; font-weight: 800;">
                    🔒 Premium School Directories Access
                </h2>
                <p style="margin: 0; font-size: 0.88rem; color: #64748b;">
                    Unlock <?= $durationMonths ?> Months Unlimited Access to Private Academies & International Schools
                    directories.
                </p>
            </div>
            <div>
                <a href="/schools/public"
                    style="background: white; color: #475569; font-size: 0.82rem; font-weight: 600; padding: 8px 16px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px; border: 1px solid #cbd5e1;">
                    <i class="fa fa-landmark"></i> Browse Free Public Schools
                </a>
            </div>
        </div>

        <?php if ($errorMessage): ?>
            <div
                style="background: #fee2e2; border: 1px solid #fca5a5; color: #991b1b; padding: 14px 18px; border-radius: 8px; margin-bottom: 1.5rem; font-size: 0.88rem; display: flex; align-items: center; gap: 10px;">
                <i class="fa fa-exclamation-circle" style="font-size: 1.1rem;"></i>
                <div><?= h($errorMessage) ?></div>
            </div>
        <?php endif; ?>

        <?php if ($isAlreadyActive): ?>
            <div
                style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 10px; padding: 1.5rem; margin-bottom: 2rem; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 1rem;">
                <div style="display: flex; align-items: center; gap: 14px;">
                    <span
                        style="background: #22c55e; color: white; border-radius: 50%; width: 40px; height: 40px; display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800;">✓</span>
                    <div>
                        <h3 style="margin: 0 0 2px; color: #166534; font-size: 1.15rem; font-weight: 700;">Directory Access
                            is Active</h3>
                        <span style="font-size: 0.85rem; color: #15803d;">
                            <?php if ($activeExpiryDate): ?>
                                Valid until <strong><?= date('M d, Y', strtotime($activeExpiryDate)) ?></strong> &bull; Full
                                access enabled.
                            <?php else: ?>
                                Institutional Pro Access Active &bull; Full access enabled.
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div style="display: flex; gap: 8px;">
                    <a href="<?= h($targetRedirect) ?>"
                        style="background: #166534; color: white !important; font-size: 0.85rem; font-weight: 700; padding: 10px 20px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 6px;">
                        Continue to School Directory &rarr;
                    </a>
                </div>
            </div>
        <?php endif; ?>

        <!-- 2-Column Responsive Layout -->
        <div
            style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">

            <!-- Left Card: What's Included -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 1rem;">
                    <h3 style="margin: 0; font-size: 1.2rem; color: #0f172a; font-weight: 700;">1-Year Directory Pass
                    </h3>
                    <span
                        style="background: #ccfbf1; color: #0f766e; font-size: 0.72rem; font-weight: 800; padding: 3px 8px; border-radius: 12px;"><?= $durationMonths ?>
                        MONTHS</span>
                </div>
                <p style="margin: 0 0 1.5rem; font-size: 0.86rem; color: #64748b; line-height: 1.5;">
                    Gain unrestricted <?= $durationMonths ?>-month access to complete institutional registries across
                    Kenya and abroad.
                </p>

                <div style="display: flex; flex-direction: column; gap: 1rem;">
                    <div
                        style="display: flex; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <div
                            style="width: 36px; height: 36px; border-radius: 8px; background: #e6fffa; color: #0f766e; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa fa-building"></i>
                        </div>
                        <div>
                            <strong
                                style="font-size: 0.9rem; color: #1e293b; display: block; margin-bottom: 2px;">Private
                                Schools & Academies Directory</strong>
                            <span style="font-size: 0.82rem; color: #64748b; line-height: 1.4; display: block;">Search
                                thousands of private primary schools, academies, JSS, and high schools with direct
                                contact information.</span>
                        </div>
                    </div>

                    <div
                        style="display: flex; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <div
                            style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; color: #2563eb; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa fa-globe"></i>
                        </div>
                        <div>
                            <strong
                                style="font-size: 0.9rem; color: #1e293b; display: block; margin-bottom: 2px;">International
                                Schools Directory</strong>
                            <span style="font-size: 0.82rem; color: #64748b; line-height: 1.4; display: block;">Access
                                accredited international, British, and IB curriculum institutions in Kenya, East Africa,
                                and globally.</span>
                        </div>
                    </div>

                    <div
                        style="display: flex; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 8px; border: 1px solid #f1f5f9;">
                        <div
                            style="width: 36px; height: 36px; border-radius: 8px; background: #f0fdf4; color: #16a34a; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <i class="fa fa-phone-volume"></i>
                        </div>
                        <div>
                            <strong
                                style="font-size: 0.9rem; color: #1e293b; display: block; margin-bottom: 2px;">Verified
                                Contact & Placement Inquiries</strong>
                            <span style="font-size: 0.82rem; color: #64748b; line-height: 1.4; display: block;">Send
                                direct teaching inquiries and generate official Teaching Practice (TP) letters
                                instantly.</span>
                        </div>
                    </div>
                </div>

                <div
                    style="margin-top: 1.5rem; padding-top: 1.25rem; border-top: 1px solid #f1f5f9; font-size: 0.82rem; color: #64748b;">
                    <i class="fa fa-info-circle" style="color: #0f766e;"></i>
                    Note: <strong>Public Schools</strong> remain completely free and accessible for all users at any
                    time.
                </div>
            </div>

            <!-- Right Card: Payment Summary & Checkout Action -->
            <div
                style="background: white; border: 1px solid #e2e8f0; border-radius: 10px; padding: 1.75rem; box-shadow: 0 1px 3px rgba(0,0,0,0.03);">
                <h3 style="margin: 0 0 1rem; font-size: 1.2rem; color: #0f172a; font-weight: 700;">Checkout & Access
                    Pass</h3>

                <div
                    style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 1.5rem;">
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.86rem;">
                        <span style="color: #64748b;">Account:</span>
                        <strong style="color: #0f172a;"><?= h($userName) ?> (<?= ucfirst($userRole) ?>)</strong>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.86rem;">
                        <span style="color: #64748b;">Email:</span>
                        <strong style="color: #0f172a;"><?= h($userEmail) ?></strong>
                    </div>
                    <div
                        style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; font-size: 0.86rem;">
                        <span style="color: #64748b;">Duration:</span>
                        <strong style="color: #0f172a;"><?= $durationMonths ?> Months Access</strong>
                    </div>
                    <div
                        style="border-top: 1px dashed #cbd5e1; padding-top: 10px; margin-top: 10px; display: flex; justify-content: space-between; align-items: baseline;">
                        <span style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">Total Payable:</span>
                        <div style="text-align: right;">
                            <span style="font-size: 1.85rem; font-weight: 800; color: #0f766e;"><?= h($currency) ?>
                                <?= number_format($amount) ?></span>
                            <span style="display: block; font-size: 0.72rem; color: #64748b;">one-time annual
                                payment</span>
                        </div>
                    </div>
                </div>

                <!-- In-Card Status / Error Notification Banner -->
                <div id="paymentStatusBanner"
                    style="display: none; padding: 12px 14px; border-radius: 8px; margin-bottom: 1.25rem; font-size: 0.88rem; align-items: center; gap: 10px; line-height: 1.4;">
                    <i id="paymentStatusIcon" class="fa fa-info-circle" style="font-size: 1.2rem; flex-shrink: 0;"></i>
                    <div id="paymentStatusText" style="flex: 1;"></div>
                </div>

                <form id="checkoutForm" method="POST" action="/schools/pay" style="margin: 0;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="target_redirect" id="targetRedirectInput"
                        value="<?= h($targetRedirect) ?>">

                    <div style="margin-bottom: 1.25rem;">
                        <label for="phone"
                            style="display: block; font-size: 0.82rem; font-weight: 700; color: #475569; margin-bottom: 6px;">
                            M-Pesa Phone Number
                        </label>
                        <div style="position: relative;">
                            <input type="tel" name="phone" id="phoneInput" value="<?= h($userPhone) ?>"
                                placeholder="e.g. 0712345678 or 254712345678" required
                                style="width: 100%; box-sizing: border-box; height: 44px; margin: 0; padding-left: 12px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.95rem; font-weight: 600; transition: border-color 0.2s;">
                        </div>
                        <span style="font-size: 0.75rem; color: #64748b; margin-top: 4px; display: block;">
                            An instant M-Pesa PIN prompt (STK push) will pop up directly on this phone.
                        </span>
                    </div>

                    <!-- Direct M-Pesa STK Push Button -->
                    <button type="button" id="payMpesaBtn" onclick="initiateDirectMpesa()"
                        style="width: 100%; background: #16a34a !important; color: #ffffff !important; font-size: 1.05rem; font-weight: 700; padding: 14px; border-radius: 8px; border: none !important; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 10px; box-shadow: 0 2px 4px rgba(22,163,74,0.25); transition: opacity 0.2s;">
                        <i class="fa fa-mobile-alt"></i> Pay <?= h($currency) ?> <?= number_format($amount) ?> via
                        M-Pesa
                    </button>

                    <noscript>
                        <button type="submit"
                            style="width: 100%; background: #0f766e; color: white; padding: 14px; border-radius: 8px; font-weight: 700; margin-top: 10px;">
                            Submit Payment
                        </button>
                    </noscript>
                </form>

                <!-- In-Progress STK Push Status Card -->
                <div id="stkProgressCard"
                    style="display: none; margin-top: 1.25rem; background: #f8fafc; border: 1px solid #cbd5e1; border-radius: 8px; padding: 1.25rem; text-align: center;">
                    <div style="font-size: 2.2rem; color: #16a34a; margin-bottom: 8px;">
                        <i class="fa fa-mobile-alt fa-bounce"></i>
                    </div>
                    <h4 style="margin: 0 0 6px; color: #0f172a; font-size: 1.05rem; font-weight: 700;">
                        Prompt Sent to Your Phone!
                    </h4>
                    <p style="margin: 0 0 12px; font-size: 0.86rem; color: #475569;">
                        Please check your phone screen for the Safaricom M-Pesa prompt and enter your <strong>M-Pesa
                            PIN</strong> to authorize <strong>KES <?= number_format($amount) ?></strong>.
                    </p>
                    <div
                        style="display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 0.85rem; color: #0284c7; font-weight: 600; margin-bottom: 12px;">
                        <i class="fa fa-spinner fa-spin"></i> <span id="stkStatusText">Awaiting PIN entry...</span>
                        (<span id="stkCountdown">60</span>s)
                    </div>
                    <button type="button" onclick="cancelStkPolling()"
                        style="background: transparent; border: 1px solid #cbd5e1; color: #64748b; font-size: 0.8rem; font-weight: 600; padding: 6px 14px; border-radius: 6px; cursor: pointer;">
                        Cancel / Change Number
                    </button>
                </div>

            </div>

        </div>

    </main>
</div>

<script>
    let intasendInstance = null;
    let currentApiRef = null;
    let pollInterval = null;
    let countdownInterval = null;
    let countdownSeconds = 60;

    function setStatusBanner(type, message, iconClass) {
        const banner = document.getElementById('paymentStatusBanner');
        const icon = document.getElementById('paymentStatusIcon');
        const text = document.getElementById('paymentStatusText');

        banner.style.display = 'flex';
        text.innerHTML = message;
        icon.className = iconClass || 'fa fa-info-circle';

        if (type === 'error') {
            banner.style.background = '#fee2e2';
            banner.style.border = '1px solid #fca5a5';
            banner.style.color = '#991b1b';
        } else if (type === 'success') {
            banner.style.background = '#f0fdf4';
            banner.style.border = '1px solid #bbf7d0';
            banner.style.color = '#166534';
        } else if (type === 'info') {
            banner.style.background = '#eff6ff';
            banner.style.border = '1px solid #bfdbfe';
            banner.style.color = '#1e40af';
        }

        try {
            banner.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } catch (e) { }
    }

    function resetPayButtons() {
        const mpesaBtn = document.getElementById('payMpesaBtn');
        const form = document.getElementById('checkoutForm');
        const progressCard = document.getElementById('stkProgressCard');

        if (pollInterval) clearInterval(pollInterval);
        if (countdownInterval) clearInterval(countdownInterval);

        if (form) form.style.display = 'block';
        if (progressCard) progressCard.style.display = 'none';

        if (mpesaBtn) {
            mpesaBtn.disabled = false;
            mpesaBtn.style.opacity = '1';
            mpesaBtn.style.cursor = 'pointer';
            mpesaBtn.innerHTML = '<i class="fa fa-mobile-alt"></i> Pay <?= h($currency) ?> <?= number_format($amount) ?> via M-Pesa';
        }
    }

    function cancelStkPolling() {
        resetPayButtons();
        setStatusBanner('info', 'Payment prompt cancelled. You can enter a new phone number or retry.', 'fa fa-info-circle');
    }

    // Direct Instant M-Pesa STK Push
    function initiateDirectMpesa() {
        const phoneInput = document.getElementById('phoneInput');
        const phoneVal = phoneInput.value.trim();
        const targetRedirect = document.getElementById('targetRedirectInput').value;
        const mpesaBtn = document.getElementById('payMpesaBtn');
        const form = document.getElementById('checkoutForm');
        const progressCard = document.getElementById('stkProgressCard');
        const statusText = document.getElementById('stkStatusText');
        const countdownEl = document.getElementById('stkCountdown');

        if (!phoneVal) {
            phoneInput.style.borderColor = '#ef4444';
            phoneInput.focus();
            setStatusBanner('error', 'Please enter your M-Pesa phone number (e.g. 0712345678).', 'fa fa-exclamation-circle');
            return;
        }
        phoneInput.style.borderColor = '#cbd5e1';

        mpesaBtn.disabled = true;
        mpesaBtn.style.opacity = '0.7';
        mpesaBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Sending prompt to ' + phoneVal + '...';

        const formData = new FormData();
        formData.append('phone', phoneVal);
        formData.append('method', 'mpesa');
        formData.append('purpose', 'directory_access');
        formData.append('target_redirect', targetRedirect);

        fetch('/api/init-payment', {
            method: 'POST',
            body: formData
        })
            .then(async res => {
                const rawText = await res.text();
                let data = null;
                try {
                    data = JSON.parse(rawText);
                } catch (jsonErr) {
                    console.error("Non-JSON Server Response:", rawText);
                    throw new Error("Server error. Please verify payment configuration.");
                }
                return data;
            })
            .then(data => {
                if (!data || !data.success) {
                    resetPayButtons();
                    setStatusBanner('error', (data && data.error) ? data.error : 'Failed to dispatch M-Pesa prompt. Please check your phone number and try again.', 'fa fa-exclamation-circle');
                    return;
                }

                // STK Push dispatched successfully
                const invoiceId = data.invoice_id;
                const apiRef = data.api_ref;

                form.style.display = 'none';
                progressCard.style.display = 'block';
                setStatusBanner('info', '<strong>Prompt Sent!</strong> An M-Pesa prompt has been dispatched to <strong>' + data.phone_number + '</strong>.', 'fa fa-mobile-alt');

                // Start countdown
                countdownSeconds = 60;
                countdownEl.innerText = countdownSeconds;
                if (countdownInterval) clearInterval(countdownInterval);
                countdownInterval = setInterval(() => {
                    countdownSeconds--;
                    countdownEl.innerText = countdownSeconds;
                    if (countdownSeconds <= 0) {
                        clearInterval(countdownInterval);
                        clearInterval(pollInterval);
                        resetPayButtons();
                        setStatusBanner('error', 'Payment prompt timed out. Please ensure your phone is unlocked and retry.', 'fa fa-clock');
                    }
                }, 1000);

                // Start polling status
                if (pollInterval) clearInterval(pollInterval);
                pollInterval = setInterval(() => {
                    fetch('/api/check-payment-status?invoice_id=' + encodeURIComponent(invoiceId) + '&api_ref=' + encodeURIComponent(apiRef))
                        .then(async res => {
                            const text = await res.text();
                            try { return JSON.parse(text); } catch (e) { return null; }
                        })
                        .then(pollData => {
                            if (!pollData) return;
                            if (pollData.state === 'COMPLETE') {
                                clearInterval(pollInterval);
                                clearInterval(countdownInterval);
                                statusText.innerText = 'Payment Verified!';
                                setStatusBanner('success', '<strong>Payment Verified!</strong> Unlocking your Directory Pass...', 'fa fa-check-circle');
                                setTimeout(() => {
                                    window.location.href = pollData.redirect_url || targetRedirect || '/schools/private';
                                }, 1200);
                            } else if (pollData.state === 'FAILED' || pollData.state === 'CANCELLED') {
                                clearInterval(pollInterval);
                                clearInterval(countdownInterval);
                                resetPayButtons();
                                setStatusBanner('error', pollData.message || 'Payment prompt was cancelled or declined on your phone.', 'fa fa-times-circle');
                            }
                        })
                        .catch(err => {
                            console.warn('Poll status error:', err);
                        });
                }, 2500);

            })
            .catch(err => {
                resetPayButtons();
                console.error('Init error:', err);
                setStatusBanner('error', err.message || 'Network error while connecting to payment gateway.', 'fa fa-exclamation-circle');
            });
    }

    // Card / Alternative Checkout Modal
    function launchInlineCheckout() {
        const phoneInput = document.getElementById('phoneInput');
        const phoneVal = phoneInput.value.trim();
        const targetRedirect = document.getElementById('targetRedirectInput').value;
        const cardBtn = document.getElementById('payCardBtn');
        const mpesaBtn = document.getElementById('payMpesaBtn');

        if (typeof window.IntaSend === 'undefined') {
            document.getElementById('checkoutForm').submit();
            return;
        }

        cardBtn.disabled = true;
        cardBtn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Launching Card Checkout...';
        if (mpesaBtn) mpesaBtn.disabled = true;

        const formData = new FormData();
        formData.append('phone', phoneVal);
        formData.append('method', 'checkout');
        formData.append('purpose', 'directory_access');
        formData.append('target_redirect', targetRedirect);

        fetch('/api/init-payment', {
            method: 'POST',
            body: formData
        })
            .then(async res => {
                const rawText = await res.text();
                let data = null;
                try {
                    data = JSON.parse(rawText);
                } catch (jsonErr) {
                    console.error("Non-JSON Server Response:", rawText);
                    throw new Error("Server error. Please verify payment configuration.");
                }
                return data;
            })
            .then(data => {
                cardBtn.disabled = false;
                cardBtn.innerHTML = '<i class="fa fa-credit-card"></i> Pay with Card (Visa / Mastercard)';
                if (mpesaBtn) mpesaBtn.disabled = false;

                if (!data || !data.success) {
                    setStatusBanner('error', (data && data.error) ? data.error : 'Failed to initialize card checkout.', 'fa fa-exclamation-circle');
                    return;
                }

                if (data.url && (typeof window.IntaSend === 'undefined' || /iPhone|iPad|iPod|Android/i.test(navigator.userAgent))) {
                    window.location.href = data.url;
                    return;
                }

                try {
                    intasendInstance = new window.IntaSend({
                        public_key: data.public_key,
                        live: data.live
                    });

                    intasendInstance
                        .on("COMPLETE", (results) => {
                            setStatusBanner('success', '<strong>Payment Verified!</strong> Unlocking your Directory Pass...', 'fa fa-check-circle');
                            const trackingId = results.tracking_id || results.checkout_id || results.invoice_id || '';
                            window.location.href = `/payment/callback?api_ref=${encodeURIComponent(data.api_ref)}&tracking_id=${encodeURIComponent(trackingId)}&status=COMPLETE`;
                        })
                        .on("FAILED", (results) => {
                            setStatusBanner('error', '<strong>Payment Incomplete or Declined:</strong> Please check your card details and retry.', 'fa fa-exclamation-triangle');
                        })
                        .on("CLOSED", () => {
                            setStatusBanner('info', 'Payment window closed. Click retry whenever you are ready.', 'fa fa-info-circle');
                        });

                    intasendInstance.run({
                        amount: Number(data.amount),
                        currency: data.currency || 'KES',
                        api_ref: data.api_ref,
                        email: data.email,
                        phone_number: data.phone_number,
                        first_name: data.first_name,
                        last_name: data.last_name,
                        country: data.country || 'KE'
                    });
                } catch (runErr) {
                    console.warn("Inline SDK run error, redirecting to hosted checkout:", runErr);
                    if (data.url) {
                        window.location.href = data.url;
                    }
                }
            })
            .catch(err => {
                const mpesaBtn = document.getElementById('payMpesaBtn');
                if (mpesaBtn) mpesaBtn.disabled = false;
                console.error("Card init error:", err);
            });
    }

    const phoneInp = document.getElementById('phoneInput');
    if (phoneInp) {
        phoneInp.addEventListener('input', function () {
            this.style.borderColor = '#cbd5e1';
        });
    }
</script>