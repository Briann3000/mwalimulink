<?php
require_once __DIR__ . '/../config.php';

$pub = env('INTASEND_PUBLIC_KEY');
$sec = env('INTASEND_SECRET_KEY');

// Test 1: method = CARD-PAYMENT
$ch = curl_init('https://payment.intasend.com/api/v1/checkout/');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'public_key' => $pub,
    'amount' => 100,
    'currency' => 'KES',
    'email' => 'infomwalimulink@gmail.com',
    'phone_number' => '254768513290',
    'api_ref' => 'TEST_CARD_' . time(),
    'redirect_url' => 'https://mwalimu.info/payment/callback',
    'method' => 'CARD-PAYMENT'
]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$res = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Test 1 (method=CARD-PAYMENT) HTTP: " . $httpCode . "\n";
echo "Response: " . $res . "\n\n";

// Test 2: methods = ['CARD', 'M-PESA']
$ch2 = curl_init('https://payment.intasend.com/api/v1/checkout/');
curl_setopt($ch2, CURLOPT_POST, true);
curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode([
    'public_key' => $pub,
    'amount' => 100,
    'currency' => 'KES',
    'email' => 'infomwalimulink@gmail.com',
    'phone_number' => '254768513290',
    'api_ref' => 'TEST_CARD2_' . time(),
    'redirect_url' => 'https://mwalimu.info/payment/callback',
    'methods' => ['CARD', 'M-PESA']
]));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch2, CURLOPT_SSL_VERIFYPEER, false);
$res2 = curl_exec($ch2);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

echo "Test 2 (methods=['CARD', 'M-PESA']) HTTP: " . $httpCode2 . "\n";
echo "Response: " . $res2 . "\n";
