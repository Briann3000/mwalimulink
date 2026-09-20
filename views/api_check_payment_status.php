<?php
// views/api_check_payment_status.php - Crash-proof real-time polling API endpoint for M-Pesa STK Push payments

header('Content-Type: application/json');

try {
    if (!is_logged_in()) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => 'Authentication required.']);
        exit();
    }

    $authUser = auth_user();
    $userId = intval($authUser['user_id'] ?? 0);
    $userRole = trim($authUser['role'] ?? 'teacher');
    $userEmail = strtolower(trim($authUser['email'] ?? ''));

    $invoiceId = trim($_GET['invoice_id'] ?? ($_POST['invoice_id'] ?? ''));
    $apiRef = trim($_GET['api_ref'] ?? ($_POST['api_ref'] ?? ''));

    if (empty($invoiceId) && empty($apiRef)) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => 'Missing invoice_id or api_ref.']);
        exit();
    }

    // Locate payment record in database safely
    $payment = null;
    try {
        if (class_exists('R')) {
            if (!empty($apiRef)) {
                $payment = R::findOne('payment', 'api_ref = ?', [$apiRef]);
            }
            if (!$payment && !empty($invoiceId)) {
                $payment = R::findOne('payment', 'api_ref LIKE ?', ["%{$invoiceId}%"]);
            }
            if (!$payment && !empty($invoiceId)) {
                // Fallback direct query
                $payment = R::findOne('payment', 'invoice_code = ? OR tracking_code = ?', [$invoiceId, $invoiceId]);
            }
        }
    } catch (\Throwable $e) {
        // Continue querying gateway directly even if local DB query has column variations
    }

    $secretKey = env('INTASEND_SECRET_KEY');
    $publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');
    $isTestMode = (strtolower((string) env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');

    // If already complete in our database
    if ($payment && strtoupper((string) $payment->state) === 'COMPLETE') {
        $targetRedirect = $payment->target_redirect ?: '/schools/private';
        echo json_encode([
            'success' => true,
            'state' => 'COMPLETE',
            'message' => 'Payment verified successfully.',
            'redirect_url' => $targetRedirect
        ]);
        exit();
    }

    if (empty($secretKey)) {
        http_response_code(200);
        echo json_encode(['success' => false, 'error' => 'Gateway secret key not configured.']);
        exit();
    }

    // Query Gateway Status API
    $statusUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/payment/status/" : "https://payment.intasend.com/api/v1/payment/status/";
    $queryPayload = [
        'public_key' => $publicKey
    ];
    if (!empty($invoiceId)) {
        $queryPayload['invoice_id'] = $invoiceId;
    } elseif ($payment && !empty($payment->invoice_code)) {
        $queryPayload['invoice_id'] = $payment->invoice_code;
    }

    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($queryPayload));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $secretKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $resData = json_decode($response, true);
    $invoice = $resData['invoice'] ?? [];
    $state = strtoupper($invoice['state'] ?? 'PROCESSING');
    $mpesaRef = $invoice['mpesa_reference'] ?? ($invoice['provider_ref'] ?? null);
    $failedReason = $invoice['failed_reason'] ?? null;

    if ($state === 'COMPLETE') {
        try {
            if ($payment && class_exists('R')) {
                $payment->state = 'COMPLETE';
                $payment->mpesa_reference = $mpesaRef;
                $payment->updated_at = date('Y-m-d H:i:s');
                R::store($payment);

                $targetRedirect = $payment->target_redirect ?: '/schools/private';
                $pUserRole = $payment->user_type ?: $userRole;
                $pUserId = $payment->user_id ?: $userId;
                $pEmail = $payment->email ?: $userEmail;
                $pAmount = floatval($payment->amount ?: 100);

                if ($payment->purpose === 'school_subscription') {
                    $school = R::load('school', $pUserId);
                    if ($school && $school->id) {
                        $schoolMonths = intval(get_setting('school_pro_months', 12));
                        if ($schoolMonths <= 0)
                            $schoolMonths = 12;
                        $expiryStr = date('Y-m-d H:i:s', strtotime("+{$schoolMonths} months"));

                        $school->status = 'active';
                        $school->subscription_status = 'active';
                        $school->subscription_plan = 'pro';
                        $school->subscription_expiry = $expiryStr;
                        $school->subscription_expires_at = $expiryStr;
                        R::store($school);
                        log_admin_audit('subscription', 'PRO_ACTIVATED', 'school', $school->id, $school->name, "Activated {$schoolMonths}-Month School Pro via M-Pesa (KES {$pAmount})");
                    }
                } else {
                    grant_directory_access($pUserId, $pUserRole, $pEmail, $pAmount, $mpesaRef ?: $invoiceId);
                }
            } else {
                grant_directory_access($userId, $userRole, $userEmail, 100, $mpesaRef ?: $invoiceId);
                $targetRedirect = '/schools/private';
            }
        } catch (\Throwable $e) {
            error_log("Payment status save error: " . $e->getMessage());
            grant_directory_access($userId, $userRole, $userEmail, 100, $mpesaRef ?: $invoiceId);
            $targetRedirect = '/schools/private';
        }

        echo json_encode([
            'success' => true,
            'state' => 'COMPLETE',
            'message' => 'Payment completed and verified!',
            'redirect_url' => $targetRedirect
        ]);
        exit();

    } elseif ($state === 'FAILED' || $state === 'CANCELLED') {
        try {
            if ($payment && class_exists('R')) {
                $payment->state = 'FAILED';
                $payment->updated_at = date('Y-m-d H:i:s');
                R::store($payment);
            }
        } catch (\Throwable $e) {
            // Safe ignore
        }

        echo json_encode([
            'success' => false,
            'state' => 'FAILED',
            'message' => $failedReason ? "Payment declined: $failedReason" : "Payment prompt was cancelled on your phone."
        ]);
        exit();

    } else {
        // PROCESSING or PENDING
        echo json_encode([
            'success' => true,
            'state' => $state,
            'message' => 'Waiting for M-Pesa PIN authorization on your phone...'
        ]);
        exit();
    }

} catch (\Throwable $t) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'state' => 'PROCESSING',
        'message' => 'Checking payment status...'
    ]);
    exit();
}
