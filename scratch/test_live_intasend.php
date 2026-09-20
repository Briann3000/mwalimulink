<?php
require_once __DIR__ . '/../config.php';

$publicKey = env('INTASEND_PUBLIC_KEY');
$url = 'https://payment.intasend.com/api/v1/checkout/';

$payload = [
    'public_key' => $publicKey,
    'amount' => 100,
    'currency' => 'KES',
    'email' => 'infomwalimulink@gmail.com',
    'first_name' => 'Brian',
    'last_name' => 'Mwalimu',
    'phone' => '254768513290',
    'api_ref' => 'MWALIMU_TEST_' . time(),
    'redirect_url' => 'https://mwalimu.info/payment/callback'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP {$httpCode}\n";
echo "Response: {$response}\n";
