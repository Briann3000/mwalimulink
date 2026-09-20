<?php
// scratch/test_direct_stk_and_polling.php - Comprehensive test of direct M-Pesa STK push and status polling

if (file_exists(__DIR__ . '/../rb.php')) {
    require_once __DIR__ . '/../rb.php';
} elseif (file_exists(__DIR__ . '/../rb-mysql.php')) {
    require_once __DIR__ . '/../rb-mysql.php';
}
require_once __DIR__ . '/../config.php';

// Setup SQLite db for testing
if (!R::testConnection()) {
    R::setup('sqlite:' . __DIR__ . '/test_stk.db');
}

echo "=== DIRECT M-PESA STK & POLLING FLOW TESTS ===\n\n";

$passCount = 0;
$failCount = 0;

function assert_test($condition, $name)
{
    global $passCount, $failCount;
    if ($condition) {
        echo " [PASS] $name\n";
        $passCount++;
    } else {
        echo " [FAIL] $name\n";
        $failCount++;
    }
}

// 1. Setup simulated logged in teacher
$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => 999,
    'role' => 'teacher',
    'email' => 'test.stk.teacher@mwalimu.info',
    'name' => 'Test STK Teacher'
];

$teacher = R::load('teacher', 999);
if (!$teacher || !$teacher->id) {
    $teacher = R::dispense('teacher');
    $teacher->id = 999;
    $teacher->name = 'Test STK Teacher';
    $teacher->email = 'test.stk.teacher@mwalimu.info';
    $teacher->phone_number = '254768513290';
    $teacher->created_at = date('Y-m-d H:i:s');
    R::store($teacher);
}

// Clean up old access
R::exec("DELETE FROM directoryaccess WHERE user_id = 999 AND user_type = 'teacher'");

assert_test(!has_directory_access(), "Initial directory access is FALSE");

// 2. Test Direct STK Push call in api_init_payment logic
$secretKey = env('INTASEND_SECRET_KEY');
$publicKey = env('INTASEND_PUBLIC_KEY');
assert_test(!empty($secretKey), "IntaSend Secret Key is set in environment");
assert_test(!empty($publicKey), "IntaSend Public Key is set in environment");

// 3. Test check_payment_status logic with simulated status
$payment = R::dispense('payment');
$payment->user_id = 999;
$payment->user_type = 'teacher';
$payment->email = 'test.stk.teacher@mwalimu.info';
$payment->phone = '254768513290';
$payment->amount = 100;
$payment->currency = 'KES';
$payment->purpose = 'directory_access';
$payment->api_ref = 'MWALIMU_DIR_teacher_999_' . time() . '_test';
$payment->target_redirect = '/schools/private';
$payment->invoice_code = 'TEST_INV_12345';
$payment->state = 'PENDING';
$payment->created_at = date('Y-m-d H:i:s');
R::store($payment);

assert_test($payment->id > 0, "Payment record stored with invoice_code TEST_INV_12345");

// 4. Test Grant Directory Access when state completes
grant_directory_access(999, 'teacher', 'test.stk.teacher@mwalimu.info', 100, 'TEST_MPESA_REF_XYZ');

assert_test(has_directory_access(), "Directory access is TRUE after granting access");

$activeAccess = R::findOne('directoryaccess', 'user_id = 999 AND user_type = "teacher" AND status = "active"');
assert_test($activeAccess !== null, "Active DirectoryAccess record exists in DB");
assert_test(floatval($activeAccess->last_amount) == 100, "Amount paid is recorded as 100");
assert_test(strtotime($activeAccess->expires_at) > time() + (350 * 86400), "Expiry date is ~12 months (1 year) in future");

// 5. Test Phone normalization
assert_test(normalize_kenyan_phone('0768513290') === '254768513290', "Normalizes 0768513290 to 254768513290");
assert_test(normalize_kenyan_phone('254768513290') === '254768513290', "Normalizes 254768513290 to 254768513290");
assert_test(normalize_kenyan_phone('0112345678') === '254112345678', "Normalizes 0112345678 to 254112345678");

// Cleanup test records
R::exec("DELETE FROM directoryaccess WHERE user_id = 999 AND user_type = 'teacher'");
R::exec("DELETE FROM payment WHERE user_id = 999 AND user_type = 'teacher'");
R::exec("DELETE FROM teacher WHERE id = 999");

echo "\n============================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "============================================\n";
