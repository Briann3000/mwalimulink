<?php
// views/payment_callback.php - Secure Multi-Purpose IntaSend Payment Callback & Verification

$apiRef = trim($_GET['api_ref'] ?? '');
$trackingId = trim($_GET['tracking_id'] ?? ($_GET['checkout_id'] ?? ($_GET['invoice_id'] ?? '')));
$rawStatus = trim($_GET['status'] ?? ($_GET['state'] ?? ''));

if (empty($apiRef) && empty($trackingId)) {
    header('Location: /schools/public');
    exit();
}

$isTestMode = (strtolower((string) env('INTASEND_TEST_MODE', 'false')) === 'true' || env('INTASEND_TEST_MODE') === '1');
$publicKey = env('INTASEND_PUBLIC_KEY') ?: env('INTASEND_PUBLISHABLE_KEY');
$secretKey = env('INTASEND_SECRET_KEY');

// Find corresponding payment record
$payment = null;
if (!empty($apiRef)) {
    $payment = R::findOne('payment', 'api_ref = ?', [$apiRef]);
}
if (!$payment && !empty($trackingId)) {
    $payment = R::findOne('payment', 'tracking_code = ?', [$trackingId]);
}

$targetRedirect = $payment->target_redirect ?? '/schools/private';
$isVerified = false;
$isProcessing = false;
$verificationError = null;

// Check if already completed previously (e.g. by webhook)
if ($payment && $payment->state === 'COMPLETE') {
    $isVerified = true;
} elseif (!empty($secretKey) && !empty($trackingId)) {
    // Perform authoritative server-to-server verification with IntaSend API
    $statusUrl = $isTestMode ? "https://sandbox.intasend.com/api/v1/payment/status/" : "https://payment.intasend.com/api/v1/payment/status/";

    $ch = curl_init($statusUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'public_key' => $publicKey,
        'invoice_id' => $trackingId
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $secretKey,
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $result = json_decode($response, true);
    $invoiceState = strtoupper($result['invoice']['state'] ?? '');

    if ($httpCode == 200 && in_array($invoiceState, ['COMPLETE', 'SUCCESS', 'PAID'])) {
        $isVerified = true;

        if ($payment) {
            $payment->state = 'COMPLETE';
            $payment->tracking_id = (string) $trackingId;
            $payment->invoice_id = (string) $trackingId;
            $payment->tracking_code = (string) $trackingId;
            $payment->raw_response = $response;
            $payment->updated_at = date('Y-m-d H:i:s');
            R::store($payment);

            // Grant 1-Year access
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
            // Fallback if payment bean wasn't found, parse from api_ref
            if (str_starts_with($apiRef, 'MWALIMU_DIR_')) {
                $parts = explode('_', $apiRef);
                $uType = $parts[2] ?? 'teacher';
                $uId = intval($parts[3] ?? 0);
                if ($uId > 0) {
                    $uEmail = '';
                    if ($uType === 'teacher') {
                        $t = R::load('teacher', $uId);
                        $uEmail = $t->email ?? '';
                    } elseif ($uType === 'school') {
                        $s = R::load('school', $uId);
                        $uEmail = $s->email ?? '';
                    }
                    grant_directory_access($uId, $uType, $uEmail, null, $apiRef);
                }
            }
        }
    } elseif (in_array($invoiceState, ['PROCESSING', 'PENDING'])) {
        $isProcessing = true;
    } else {
        $verificationError = "Payment status could not be verified (State: " . ($invoiceState ?: 'UNKNOWN') . ").";
        if ($payment) {
            $payment->state = 'FAILED';
            $payment->raw_response = $response;
            $payment->updated_at = date('Y-m-d H:i:s');
            R::store($payment);
        }
    }
} elseif (in_array(strtoupper($rawStatus), ['COMPLETE', 'SUCCESS', 'PAID'])) {
    // If test mode or direct status match
    if ($isTestMode && $payment) {
        $isVerified = true;
        $payment->state = 'COMPLETE';
        $payment->tracking_id = $trackingId;
        $payment->updated_at = date('Y-m-d H:i:s');
        R::store($payment);
        grant_directory_access($payment->user_id, $payment->user_type, $payment->email, $payment->amount, $apiRef);
    }
}
?>

<div
    style="min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem; background: #f8fafc;">
    <div
        style="max-width: 560px; width: 100%; background: white; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); text-align: center;">

        <?php if ($isVerified): ?>
            <div
                style="width: 68px; height: 68px; background: #ecfdf5; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                <i class="fa fa-check" style="font-size: 2.2rem; color: #059669;"></i>
            </div>

            <h2 style="margin: 0 0 8px; color: #0f172a; font-size: 1.5rem; font-weight: 800;">Payment Successful!</h2>
            <p style="margin: 0 0 1.5rem; color: #475569; font-size: 0.95rem;">
                Your <strong>1-Year (12 Months) Directory Pass</strong> is now active. You have full unrestricted access to
                Private and International school directories.
            </p>

            <div
                style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1.25rem; margin-bottom: 2rem; text-align: left; font-size: 0.88rem;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #64748b;">Plan:</span>
                    <strong style="color: #0f766e;">1-Year School Directories Pass</strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #64748b;">Amount Paid:</span>
                    <strong style="color: #1e293b;">KES <?= number_format($payment->amount ?? 100, 2) ?></strong>
                </div>
                <div style="display: flex; justify-content: space-between; margin-bottom: 8px;">
                    <span style="color: #64748b;">Valid For:</span>
                    <strong style="color: #1e293b;">12 Months from Today</strong>
                </div>
                <div style="display: flex; justify-content: space-between;">
                    <span style="color: #64748b;">Reference:</span>
                    <code style="font-size: 0.78rem; color: #64748b;"><?= h($apiRef) ?></code>
                </div>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="<?= h($targetRedirect) ?>"
                    style="background: #0f766e; color: white !important; font-size: 0.95rem; font-weight: 700; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 2px 4px rgba(15,118,110,0.2);">
                    Continue to Directory &rarr;
                </a>
                <a href="/schools/international"
                    style="background: #f1f5f9; color: #334155 !important; font-size: 0.95rem; font-weight: 600; padding: 12px 20px; border-radius: 6px; text-decoration: none; border: 1px solid #cbd5e1;">
                    International Schools
                </a>
            </div>

        <?php elseif ($isProcessing): ?>
            <div
                style="width: 68px; height: 68px; background: #eff6ff; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                <i class="fa fa-spinner fa-spin" style="font-size: 2.2rem; color: #2563eb;"></i>
            </div>

            <h2 style="margin: 0 0 8px; color: #0f172a; font-size: 1.5rem; font-weight: 800;">Processing Transaction</h2>
            <p style="margin: 0 0 1.5rem; color: #475569; font-size: 0.95rem;">
                We are confirming your payment with the gateway. If you have completed the M-Pesa prompt, please refresh to
                check status.
            </p>

            <div style="margin-bottom: 2rem;">
                <a href="?api_ref=<?= urlencode($apiRef) ?>&tracking_id=<?= urlencode($trackingId) ?>"
                    style="background: #2563eb; color: white !important; font-size: 0.95rem; font-weight: 700; padding: 12px 24px; border-radius: 6px; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="fa fa-sync-alt"></i> Refresh & Check Status
                </a>
            </div>

        <?php else: ?>
            <div
                style="width: 68px; height: 68px; background: #fef2f2; border-radius: 50%; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1.25rem;">
                <i class="fa fa-exclamation-triangle" style="font-size: 2.2rem; color: #dc2626;"></i>
            </div>

            <h2 style="margin: 0 0 8px; color: #0f172a; font-size: 1.5rem; font-weight: 800;">Payment Verification
                Incomplete</h2>
            <p style="margin: 0 0 1.5rem; color: #475569; font-size: 0.95rem;">
                <?= h($verificationError ?: 'The transaction was not completed or could not be verified automatically.') ?>
            </p>

            <div
                style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 2rem; font-size: 0.85rem; color: #64748b; word-break: break-all;">
                Reference: <code><?= h($apiRef ?: 'N/A') ?></code>
            </div>

            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                <a href="/schools/pay?redirect=<?= urlencode($targetRedirect) ?>"
                    style="background: #0f766e; color: white !important; font-size: 0.9rem; font-weight: 700; padding: 10px 20px; border-radius: 6px; text-decoration: none;">
                    Try Payment Again
                </a>
                <a href="/schools/public"
                    style="background: #f1f5f9; color: #334155 !important; font-size: 0.9rem; font-weight: 600; padding: 10px 20px; border-radius: 6px; text-decoration: none; border: 1px solid #cbd5e1;">
                    Browse Public Schools (Free)
                </a>
            </div>
        <?php endif; ?>

    </div>
</div>