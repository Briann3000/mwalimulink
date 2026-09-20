<?php
// views/api_init_payment.php - Resilient API Endpoint to initiate M-Pesa STK push or prepare checkout session

header('Content-Type: application/json');

try {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Authentication required. Please log in first.']);
        exit();
    }

    $authUser = auth_user();
    $userId = intval($authUser['user_id'] ?? 0);
    $userRole = trim($authUser['role'] ?? 'teacher');
    $userEmail = strtolower(trim($authUser['email'] ?? ''));

    // Fetch user object to get latest phone & name
    $userObj = null;
    try {
        if ($userRole === 'teacher') {
            $userObj = R::load('teacher', $userId);
        } elseif ($userRole === 'school') {
            $userObj = R::load('school', $userId);
        }
    } catch (\Throwable $e) {
        // Safe fallback
    }

    $userName = $authUser['name'] ?: ($userObj->name ?? 'Educator');
    $phoneInput = trim($_POST['phone'] ?? ($_GET['phone'] ?? ($userObj->phone_number ?? ($userObj->mobile ?? ''))));
    $normalizedPhone = normalize_kenyan_phone($phoneInput) ?: $phoneInput;

    $method = trim($_POST['method'] ?? ($_GET['method'] ?? 'mpesa')); // 'mpesa' (direct STK) or 'checkout' / 'card'
    $purpose = trim($_POST['purpose'] ?? ($_GET['purpose'] ?? 'directory_access'));
    $targetRedirect = trim($_POST['target_redirect'] ?? ($_GET['target_redirect'] ?? '/schools/private'));

    if ($purpose === 'school_subscription') {
        $amount = (float) get_setting('school_pro_fee', 1000);
    } else {
        $purpose = 'directory_access';
        $amount = (float) get_setting('directory_fee', 100);
    }

    $currency = (string) get_setting('payment_currency', 'KES');
    $isTestMode = (strtolower((string) env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');
    $publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');
    $secretKey = env('INTASEND_SECRET_KEY');

    if (empty($publicKey)) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => 'Payment gateway public key is not configured. Please contact support.']);
        exit();
    }

    // Generate tamper-proof api_ref
    $randomHex = bin2hex(random_bytes(4));
    $prefix = ($purpose === 'school_subscription') ? 'MwalimuLink_School_Subscription_' : 'MWALIMU_DIR_';
    $apiRef = $prefix . $userRole . "_" . $userId . "_" . time() . "_" . $randomHex;

    // Split user name into first and last
    $nameParts = explode(' ', trim($userName), 2);
    $firstName = preg_replace('/[^a-zA-Z0-9]/', '', $nameParts[0] ?? 'Educator');
    $lastName = preg_replace('/[^a-zA-Z0-9]/', '', $nameParts[1] ?? ($purpose === 'school_subscription' ? 'Subscription' : 'Access'));
    if (empty($firstName))
        $firstName = 'Educator';
    if (empty($lastName))
        $lastName = 'Pass';

    // Record pending payment in database
    $payment = null;
    try {
        if (class_exists('R') && R::testConnection()) {
            $payment = R::dispense('payment');
            $payment->user_id = $userId;
            $payment->user_type = $userRole;
            $payment->email = $userEmail;
            $payment->phone = $normalizedPhone;
            $payment->amount = $amount;
            $payment->currency = $currency;
            $payment->purpose = $purpose;
            $payment->api_ref = $apiRef;
            $payment->target_redirect = $targetRedirect;
            $payment->state = 'PENDING';
            $payment->created_at = date('Y-m-d H:i:s');
            $payment->updated_at = date('Y-m-d H:i:s');
            R::store($payment);
        }
    } catch (\Throwable $e) {
        error_log("Failed to initialize payment bean in API: " . $e->getMessage());
    }

    // If direct M-Pesa STK push requested (default):
    if ($method === 'mpesa') {
        if (empty($normalizedPhone)) {
            http_response_code(200);
            echo json_encode(['success' => false, 'error' => 'Valid Kenyan phone number (e.g. 07XXXXXXXX) is required for M-Pesa prompt.']);
            exit();
        }

        if (empty($secretKey)) {
            http_response_code(200);
            echo json_encode(['success' => false, 'error' => 'Payment gateway secret key is not configured on the server. Please contact support.']);
            exit();
        }

        $stkUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/payment/mpesa-stk-push/" : "https://payment.intasend.com/api/v1/payment/mpesa-stk-push/";

        $payload = [
            'public_key' => $publicKey,
            'amount' => $amount,
            'currency' => $currency,
            'phone_number' => $normalizedPhone,
            'email' => $userEmail,
            'api_ref' => $apiRef
        ];

        $ch = curl_init($stkUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json',
            'Authorization: Bearer ' . $secretKey
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $stkData = json_decode($response, true);

        if ($httpCode >= 200 && $httpCode < 300 && isset($stkData['invoice']['invoice_id'])) {
            $invoiceId = $stkData['invoice']['invoice_id'];

            try {
                if ($payment && class_exists('R')) {
                    $payment->invoice_code = $invoiceId;
                    $payment->tracking_code = $invoiceId;
                    $payment->updated_at = date('Y-m-d H:i:s');
                    R::store($payment);
                }
            } catch (\Throwable $e) {
                // Non-fatal
            }

            echo json_encode([
                'success' => true,
                'method' => 'mpesa',
                'invoice_id' => $invoiceId,
                'api_ref' => $apiRef,
                'amount' => $amount,
                'currency' => $currency,
                'phone_number' => $normalizedPhone,
                'message' => 'STK push prompt sent to ' . $normalizedPhone . '. Please enter your M-Pesa PIN.'
            ]);
            exit();
        } else {
            $errMessage = $stkData['detail'] ?? ($stkData['message'] ?? ($stkData['errors'][0]['detail'] ?? ($stkData['errors'][0]['message'] ?? 'Failed to send M-Pesa prompt.')));
            if ($curlErr) {
                $errMessage .= " ($curlErr)";
            }

            http_response_code(200);
            echo json_encode([
                'success' => false,
                'error' => $errMessage,
                'raw' => $stkData
            ]);
            exit();
        }
    }

    // Card / Alternative Checkout mode
    $appUrl = rtrim(env('APP_URL', 'http://localhost:8000'), '/');
    $callbackUrl = $appUrl . ($purpose === 'school_subscription' ? '/school/callback' : '/payment/callback');

    $checkoutPayload = [
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
    $ch2 = curl_init($checkoutUrl);
    curl_setopt($ch2, CURLOPT_POST, true);
    curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($checkoutPayload));
    curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch2, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);

    $checkoutRes = curl_exec($ch2);
    $checkoutCode = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $checkoutData = json_decode($checkoutRes, true);
    $hostedUrl = ($checkoutCode >= 200 && $checkoutCode < 300 && isset($checkoutData['url'])) ? $checkoutData['url'] : null;

    echo json_encode([
        'success' => true,
        'method' => 'checkout',
        'url' => $hostedUrl,
        'public_key' => $publicKey,
        'amount' => $amount,
        'currency' => $currency,
        'api_ref' => $apiRef,
        'email' => $userEmail,
        'phone_number' => $normalizedPhone,
        'first_name' => $firstName,
        'last_name' => $lastName,
        'country' => 'KE',
        'live' => !$isTestMode,
        'target_redirect' => $targetRedirect
    ]);
    exit();

} catch (\Throwable $t) {
    http_response_code(200);
    echo json_encode([
        'success' => false,
        'error' => 'Server initialization error: ' . $t->getMessage()
    ]);
    exit();
}
