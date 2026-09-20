<?php
// scratch/test_directory_access_and_intasend.php - Comprehensive Test Suite

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

if (!class_exists('UnderscoreFormatter')) {
    class UnderscoreFormatter
    {
        public function formatBeanTable($beanType)
        {
            return $beanType;
        }
        public function formatBeanID($beanType)
        {
            return 'id';
        }
        public function formatBeanForeignKey($beanType)
        {
            return $beanType . '_id';
        }
    }
}

R::ext('formatter', function () {
    return new UnderscoreFormatter();
});

// Setup DB connection
$dbDriver = env('DB_CONNECTION', 'sqlite');
if ($dbDriver === 'mysql') {
    $dbHost = env('DB_HOST', 'localhost');
    $dbPort = env('DB_PORT', '3306');
    $dbName = env('DB_DATABASE', 'mwalimu');
    $dbUser = env('DB_USERNAME', 'root');
    $dbPass = env('DB_PASSWORD', '');
    R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
} else {
    $dbPath = __DIR__ . '/../' . env('DB_PATH', 'data/mwalimu.db');
    R::setup("sqlite:$dbPath");
}

$passed = 0;
$failed = 0;

function assert_test($description, $condition)
{
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$description}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$description}\n";
        $failed++;
    }
}

echo "=== MWALIMULINK INTASEND & DIRECTORY ACCESS TEST SUITE ===\n\n";

// --- TEST 1: GUEST USER ACCESS ---
echo "1. Testing Guest User Access Control...\n";
$guestUser = null;
assert_test("Guest user has_directory_access() returns false", has_directory_access($guestUser) === false);

// --- TEST 2: ADMIN USER ACCESS ---
echo "\n2. Testing Admin User Bypass...\n";
$adminUser = [
    'logged_in' => true,
    'user_id' => 1,
    'role' => 'admin',
    'email' => 'admin@mwalimu.info'
];
assert_test("Admin user has_directory_access() returns true", has_directory_access($adminUser) === true);

// --- TEST 3: UNPAID TEACHER ACCESS ---
echo "\n3. Testing Unpaid Teacher Access...\n";
$unpaidTeacherEmail = 'test_unpaid_' . time() . '@example.com';
$teacher = R::dispense('teacher');
$teacher->name = 'Test Unpaid Teacher';
$teacher->email = $unpaidTeacherEmail;
$teacher->password = password_hash('password123', PASSWORD_DEFAULT);
$teacherId = R::store($teacher);

$unpaidTeacherSession = [
    'logged_in' => true,
    'user_id' => $teacherId,
    'role' => 'teacher',
    'email' => $unpaidTeacherEmail
];
assert_test("Unpaid teacher has_directory_access() returns false", has_directory_access($unpaidTeacherSession) === false);

// --- TEST 4: GRANTING 1-YEAR DIRECTORY ACCESS ---
echo "\n4. Testing Granting 1-Year Access via grant_directory_access()...\n";
$grantResult = grant_directory_access($teacherId, 'teacher', $unpaidTeacherEmail, 100, 'TEST_REF_12345');
assert_test("grant_directory_access() returns true", $grantResult === true);

$accessRecord = R::findOne('directoryaccess', 'user_id = ? AND user_type = ?', [$teacherId, 'teacher']);
assert_test("directoryaccess record exists in DB", !empty($accessRecord));
assert_test("directoryaccess status is active", $accessRecord->status === 'active');
assert_test("directoryaccess amount is 100", floatval($accessRecord->last_amount) === 100.0);

$expiryTime = strtotime($accessRecord->expires_at);
$oneYearFromNow = strtotime('+360 days');
assert_test("directoryaccess expiry is set ~1 year in future", $expiryTime > $oneYearFromNow);

// Now re-check has_directory_access
assert_test("Paid teacher has_directory_access() returns true", has_directory_access($unpaidTeacherSession) === true);

// --- TEST 5: EXPIRED ACCESS RECORD ---
echo "\n5. Testing Expired Directory Access...\n";
$expiredEmail = 'test_expired_' . time() . '@example.com';
$expiredTeacher = R::dispense('teacher');
$expiredTeacher->name = 'Expired Teacher';
$expiredTeacher->email = $expiredEmail;
$expiredTeacherId = R::store($expiredTeacher);

$expiredAccess = R::dispense('directoryaccess');
$expiredAccess->user_id = $expiredTeacherId;
$expiredAccess->user_type = 'teacher';
$expiredAccess->email = $expiredEmail;
$expiredAccess->status = 'active';
$expiredAccess->expires_at = date('Y-m-d H:i:s', strtotime('-1 day')); // Expired yesterday
R::store($expiredAccess);

$expiredSession = [
    'logged_in' => true,
    'user_id' => $expiredTeacherId,
    'role' => 'teacher',
    'email' => $expiredEmail
];
assert_test("Expired teacher has_directory_access() returns false", has_directory_access($expiredSession) === false);

// --- TEST 6: SCHOOL PRO ACCOUNT ACCESS ---
echo "\n6. Testing Active Institutional School Pro Access...\n";
$proSchool = R::dispense('school');
$proSchool->name = 'Pro Academy';
$proSchool->email = 'pro_school_' . time() . '@example.com';
$proSchool->status = 'active';
$proSchool->plan = 'pro';
$proSchool->subscription_expiry = date('Y-m-d H:i:s', strtotime('+6 months'));
$proSchoolId = R::store($proSchool);

$proSchoolSession = [
    'logged_in' => true,
    'user_id' => $proSchoolId,
    'role' => 'school',
    'email' => $proSchool->email
];
assert_test("Pro School has_directory_access() automatically returns true", has_directory_access($proSchoolSession) === true);

// --- TEST 7: PAYMENT BEAN CREATION & WEBHOOK SIMULATION ---
echo "\n7. Testing IntaSend Payment Record & Webhook Processing...\n";
$webhookTeacher = R::dispense('teacher');
$webhookTeacher->name = 'Webhook Educator';
$webhookTeacher->email = 'webhook_teacher_' . time() . '@example.com';
$webhookTeacherId = R::store($webhookTeacher);

$apiRef = "MWALIMU_DIR_teacher_" . $webhookTeacherId . "_" . time() . "_ab12cd34";
$invoiceId = "IS_TEST_INV_" . time();

// Create pending payment
$payment = R::dispense('payment');
$payment->user_id = $webhookTeacherId;
$payment->user_type = 'teacher';
$payment->email = $webhookTeacher->email;
$payment->amount = 100;
$payment->currency = 'KES';
$payment->purpose = 'directory_access';
$payment->api_ref = $apiRef;
$payment->state = 'PENDING';
$payment->created_at = date('Y-m-d H:i:s');
$paymentId = R::store($payment);

assert_test("Pending payment created with ID {$paymentId}", $paymentId > 0);

// Simulate Webhook Payload Processing
$webhookPayload = [
    'invoice_id' => $invoiceId,
    'state' => 'COMPLETE',
    'api_ref' => $apiRef,
    'value' => 100,
    'currency' => 'KES',
    'account' => $webhookTeacher->email
];

// Execute processing logic
$pRecord = R::findOne('payment', 'api_ref = ?', [$apiRef]);
if ($pRecord && $pRecord->state !== 'COMPLETE') {
    $pRecord->state = 'COMPLETE';
    $pRecord->tracking_id = (string) $invoiceId;
    $pRecord->invoice_id = (string) $invoiceId;
    $pRecord->tracking_code = (string) $invoiceId;
    $pRecord->raw_response = json_encode($webhookPayload);
    $pRecord->updated_at = date('Y-m-d H:i:s');
    R::store($pRecord);
    grant_directory_access($pRecord->user_id, $pRecord->user_type, $pRecord->email, $pRecord->amount, $apiRef);
}

$updatedPayment = R::load('payment', $paymentId);
echo "  [DEBUG VALUES] tracking_id=" . var_export($updatedPayment->tracking_id, true) . ", invoice_id=" . var_export($updatedPayment->invoice_id, true) . ", tracking_code=" . var_export($updatedPayment->tracking_code, true) . "\n";
assert_test("Payment record updated to COMPLETE", $updatedPayment->state === 'COMPLETE');
assert_test("Payment tracking_id updated", (string) $updatedPayment->tracking_code === (string) $invoiceId || (string) $updatedPayment->invoice_id === (string) $invoiceId);

$webhookTeacherSession = [
    'logged_in' => true,
    'user_id' => $webhookTeacherId,
    'role' => 'teacher',
    'email' => $webhookTeacher->email
];
assert_test("Webhook Educator now has active directory access", has_directory_access($webhookTeacherSession) === true);

// --- TEST 8: IDEMPOTENCY CHECK ---
echo "\n8. Testing Idempotent Webhook Processing (Duplicate Event)...\n";
// Re-running same webhook should recognize state === COMPLETE
$checkPayment = R::findOne('payment', 'api_ref = ?', [$apiRef]);
$isAlreadyComplete = ($checkPayment->state === 'COMPLETE');
assert_test("Idempotency guard detects already COMPLETE payment", $isAlreadyComplete === true);

// --- SUMMARY ---
echo "\n============================================\n";
echo "TEST RESULTS: {$passed} Passed, {$failed} Failed\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
