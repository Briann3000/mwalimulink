<?php
require_once __DIR__ . '/../config.php';

$secretKey = env('INTASEND_SECRET_KEY');
$publicKey = env('INTASEND_PUBLIC_KEY');

function check_intasend_status($invoiceId, $publicKey, $secretKey)
{
    $ch = curl_init('https://payment.intasend.com/api/v1/payment/status/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'public_key' => $publicKey,
        'invoice_id' => $invoiceId
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json',
        'Authorization: Bearer ' . $secretKey
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($res, true);
    return [$httpCode, $data];
}

list($code, $resData) = check_intasend_status('KOOR68P', $publicKey, $secretKey);
echo "Status code: " . $code . "\n";
echo "Invoice state: " . ($resData['invoice']['state'] ?? 'unknown') . "\n";
echo "Failed reason: " . ($resData['invoice']['failed_reason'] ?? 'none') . "\n";
