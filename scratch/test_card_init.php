<?php
if (file_exists(__DIR__ . '/../rb.php')) {
    require_once __DIR__ . '/../rb.php';
} elseif (file_exists(__DIR__ . '/../rb-mysql.php')) {
    require_once __DIR__ . '/../rb-mysql.php';
}
require_once __DIR__ . '/../config.php';

init_session();
$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => 1,
    'role' => 'teacher',
    'email' => 'infomwalimulink@gmail.com',
    'name' => 'Brian Muriuki'
];

$_POST['method'] = 'checkout';
$_POST['purpose'] = 'directory_access';
$_POST['phone'] = '254768513290';

ob_start();
include __DIR__ . '/../views/api_init_payment.php';
$output = ob_get_clean();

echo "CARD INIT RESPONSE: " . $output . "\n";
$data = json_decode($output, true);
if (!empty($data['url'])) {
    echo "SUCCESS: Generated Hosted Card Checkout URL: " . $data['url'] . "\n";
} else {
    echo "NO URL RETURNED\n";
}
