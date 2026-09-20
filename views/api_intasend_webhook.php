<?php
// views/api_intasend_webhook.php - Robust Asynchronous IntaSend Webhook Handler

header('Content-Type: application/json');

$rawPayload = file_get_contents('php://input');
$data = json_decode($rawPayload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload.']);
    exit();
}

// 1. Handle IntaSend Challenge / Handshake Verification
if (isset($data['challenge'])) {
    http_response_code(200);
    echo json_encode(['challenge' => $data['challenge']]);
    exit();
}

$invoiceId = $data['invoice_id'] ?? ($data['tracking_id'] ?? ($data['checkout_id'] ?? ''));
$state = strtoupper($data['state'] ?? ($data['status'] ?? ''));
$apiRef = $data['api_ref'] ?? '';
$value = floatval($data['value'] ?? ($data['net_amount'] ?? ($data['amount'] ?? 0)));
$currency = $data['currency'] ?? 'KES';

if (empty($invoiceId) && empty($apiRef)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing invoice reference.']);
    exit();
}

$isTestMode = (strtolower((string) env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');
$secretKey = env('INTASEND_SECRET_KEY');
$publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');

// 2. Authoritative Verification via IntaSend Server API
$isVerified = false;
if (!empty($secretKey) && !empty($invoiceId)) {
    $statusUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/payment/status/" : "https://payment.intasend.com/api/v1/payment/status/";

    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'public_key' => $publicKey,
        'invoice_id' => $invoiceId
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $apiResp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($apiResp, true);
    $authoritativeState = strtoupper($result['invoice']['state'] ?? '');

    if ($httpCode == 200 && in_array($authoritativeState, ['COMPLETE', 'SUCCESS', 'PAID'])) {
        $isVerified = true;
        $state = 'COMPLETE';
    }
} elseif (in_array($state, ['COMPLETE', 'SUCCESS', 'PAID'])) {
    $isVerified = true;
}

// 3. Find and Process Payment Bean
$payment = null;
if (!empty($apiRef)) {
    $payment = R::findOne('payment', 'api_ref = ?', [$apiRef]);
}
if (!$payment && !empty($invoiceId)) {
    $payment = R::findOne('payment', 'tracking_code = ?', [$invoiceId]);
}

if ($payment && $payment->state === 'COMPLETE') {
    // Idempotent: already processed
    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Already processed.']);
    exit();
}

if ($isVerified && in_array($state, ['COMPLETE', 'SUCCESS', 'PAID'])) {
    if ($payment) {
        $payment->state = 'COMPLETE';
        $payment->tracking_id = (string) $invoiceId;
        $payment->invoice_id = (string) $invoiceId;
        $payment->tracking_code = (string) $invoiceId;
        $payment->raw_response = $rawPayload;
        $payment->updated_at = date('Y-m-d H:i:s');
        R::store($payment);

        if ($payment->purpose === 'directory_access') {
            grant_directory_access($payment->user_id, $payment->user_type, $payment->email, $payment->amount, $apiRef);
        } elseif ($payment->purpose === 'school_subscription') {
            $school = R::load('school', $payment->user_id);
            if ($school->id) {
                $school->plan = 'pro';
                $school->status = 'active';
                $expiry = new DateTime();
                $expiry->add(new DateInterval('P12M'));
                $school->subscription_expiry = $expiry->format('Y-m-d H:i:s');
                R::store($school);
            }
        }
    } else {
        // Fallback for directory access or school subscription from api_ref
        if (str_starts_with($apiRef, 'MWALIMU_DIR_')) {
            $parts = explode('_', $apiRef);
            $uType = $parts[2] ?? 'teacher';
            $uId = intval($parts[3] ?? 0);
            if ($uId > 0) {
                $uEmail = $data['account'] ?? ($data['customer']['email'] ?? '');
                if (empty($uEmail)) {
                    if ($uType === 'teacher') {
                        $t = R::load('teacher', $uId);
                        $uEmail = $t->email ?? '';
                    } elseif ($uType === 'school') {
                        $s = R::load('school', $uId);
                        $uEmail = $s->email ?? '';
                    }
                }
                grant_directory_access($uId, $uType, $uEmail, $value ?: 100, $apiRef);
            }
        } elseif (str_starts_with($apiRef, 'MwalimuLink_School_Subscription_')) {
            $parts = explode('_', $apiRef);
            foreach ($parts as $part) {
                if (is_numeric($part)) {
                    $schoolId = (int) $part;
                    $school = R::load('school', $schoolId);
                    if ($school->id) {
                        $school->plan = 'pro';
                        $school->status = 'active';
                        $expiry = new DateTime();
                        $expiry->add(new DateInterval('P12M'));
                        $school->subscription_expiry = $expiry->format('Y-m-d H:i:s');
                        R::store($school);
                    }
                    break;
                }
            }
        }
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Payment verified and access granted.']);
    exit();
}

if ($payment && in_array($state, ['FAILED', 'CANCELLED'])) {
    $payment->state = 'FAILED';
    $payment->raw_response = $rawPayload;
    $payment->updated_at = date('Y-m-d H:i:s');
    R::store($payment);
}

http_response_code(200);
echo json_encode(['status' => 'recorded', 'state' => $state]);
exit();
