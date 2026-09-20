<?php
$envFile = file_get_contents(__DIR__ . '/../.env');
preg_match('/INTASEND_PUBLIC_KEY=(.*)/', $envFile, $mPub);
preg_match('/INTASEND_SECRET_KEY=(.*)/', $envFile, $mSec);
$pub = trim($mPub[1] ?? '');
$sec = trim($mSec[1] ?? '');

// Test Status API
$ch = curl_init('https://payment.intasend.com/api/v1/payment/status/');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'public_key' => $pub,
    'invoice_id' => 'KOOR68P'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer ' . $sec
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Status API HTTP Code: " . $httpCode . "\n";
echo "Status API Response: " . $res . "\n";
