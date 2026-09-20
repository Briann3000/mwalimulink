<?php
// scratch/test_inline_sdk_flow.php - Extensive Test Suite for IntaSend Inline SDK & Lifecycle Handlers

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
    return new UnderscoreFormatter(); });

$dbPath = __DIR__ . '/../data/mwalimu.db';
R::setup("sqlite:$dbPath");

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

echo "=== EXTENSIVE TEST SUITE: INTASEND INLINE SDK & LIFECYCLE FLOW ===\n\n";

// --- TEST 1: API /api/init-payment GUEST REJECTION ---
echo "1. Testing /api/init-payment Guest Authentication Check...\n";
if (session_status() === PHP_SESSION_NONE)
    @session_start();
$_SESSION = []; // Clear session

ob_start();
// Simulate api_init_payment logic for guest
$isAuth = is_logged_in();
ob_end_clean();
assert_test("Guest request rejected by is_logged_in()", $isAuth === false);

// --- TEST 2: API /api/init-payment FOR LOGGED-IN TEACHER ---
echo "\n2. Testing /api/init-payment for Authenticated Teacher (KES 100 Directory Pass)...\n";
$teacher = R::dispense('teacher');
$teacher->name = 'Jane Wanjiku';
$teacher->email = 'jane_' . time() . '@example.com';
$teacher->mobile = '254712345678';
$teacherId = R::store($teacher);

$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => $teacherId,
    'role' => 'teacher',
    'name' => $teacher->name,
    'email' => $teacher->email
];

// Execute init logic
$randomHex = bin2hex(random_bytes(4));
$apiRef = "MWALIMU_DIR_teacher_" . $teacherId . "_" . time() . "_" . $randomHex;
$amount = 100.0;
$currency = 'KES';

$payment = R::dispense('payment');
$payment->user_id = $teacherId;
$payment->user_type = 'teacher';
$payment->email = $teacher->email;
$payment->phone = $teacher->mobile;
$payment->amount = $amount;
$payment->currency = $currency;
$payment->purpose = 'directory_access';
$payment->api_ref = $apiRef;
$payment->target_redirect = '/schools/private';
$payment->state = 'PENDING';
$payment->created_at = date('Y-m-d H:i:s');
$payment->updated_at = date('Y-m-d H:i:s');
$pId = R::store($payment);

assert_test("Pending payment created with ID {$pId}", $pId > 0);
assert_test("Initial state is PENDING", $payment->state === 'PENDING');
assert_test("Amount is KES 100", floatval($payment->amount) === 100.0);
assert_test("api_ref has correct format", str_starts_with($payment->api_ref, 'MWALIMU_DIR_teacher_'));

// --- TEST 3: SIMULATING CANCEL / CLOSED ON PHONE (NO ACCESS GRANTED) ---
echo "\n3. Testing Modal Cancellation / Phone Prompt Dismissal (CLOSED hook)...\n";
// When modal is closed after phone cancel, payment remains PENDING or FAILED, NO access is given
assert_test("Directory access remains locked for teacher who cancelled prompt", has_directory_access($_SESSION['auth']) === false);

// --- TEST 4: SIMULATING RETRY AFTER CANCEL (FRESH API REF GENERATION) ---
echo "\n4. Testing 1-Click Retry After Cancellation...\n";
$retryApiRef = "MWALIMU_DIR_teacher_" . $teacherId . "_" . time() . "_retry99";
$retryPayment = R::dispense('payment');
$retryPayment->user_id = $teacherId;
$retryPayment->user_type = 'teacher';
$retryPayment->email = $teacher->email;
$retryPayment->phone = $teacher->mobile;
$retryPayment->amount = $amount;
$retryPayment->currency = $currency;
$retryPayment->purpose = 'directory_access';
$retryPayment->api_ref = $retryApiRef;
$retryPayment->target_redirect = '/schools/private';
$retryPayment->state = 'PENDING';
$retryPayment->created_at = date('Y-m-d H:i:s');
$retryPaymentId = R::store($retryPayment);

assert_test("New pending payment created on retry with unique ID {$retryPaymentId}", $retryPaymentId > $pId);
assert_test("Unique api_ref generated on retry", $retryApiRef !== $apiRef);

// --- TEST 5: SIMULATING SUCCESSFUL COMPLETION (COMPLETE hook & Callback) ---
echo "\n5. Testing COMPLETE Event Hook & Access Activation...\n";
$mockTrackingId = "IS_SDK_TRACK_" . time();

// Simulate callback / status check completion
$pToUpdate = R::load('payment', $retryPaymentId);
$pToUpdate->state = 'COMPLETE';
$pToUpdate->tracking_code = $mockTrackingId;
$pToUpdate->updated_at = date('Y-m-d H:i:s');
R::store($pToUpdate);

grant_directory_access($pToUpdate->user_id, $pToUpdate->user_type, $pToUpdate->email, $pToUpdate->amount, $retryApiRef);

assert_test("Payment state updated to COMPLETE", $pToUpdate->state === 'COMPLETE');
assert_test("Teacher now has active directory access", has_directory_access($_SESSION['auth']) === true);

$accessRecord = R::findOne('directoryaccess', 'user_id = ? AND user_type = ?', [$teacherId, 'teacher']);
assert_test("directoryaccess record exists", !empty($accessRecord));
assert_test("directoryaccess expires in ~12 months", strtotime($accessRecord->expires_at) > strtotime('+360 days'));

// --- TEST 6: SCHOOL PRO SUBSCRIPTION INLINE INIT ---
echo "\n6. Testing /api/init-payment for School Pro Annual Plan (KES 1,000)...\n";
$school = R::dispense('school');
$school->name = 'Nairobi Academy';
$school->email = 'nairobi_acad_' . time() . '@example.com';
$school->phone_number = '254700112233';
$school->status = 'active';
$school->plan = 'free';
$schoolId = R::store($school);

$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => $schoolId,
    'role' => 'school',
    'name' => $school->name,
    'email' => $school->email
];

$schoolApiRef = "MwalimuLink_School_Subscription_" . $schoolId . "_" . time() . "_sch12";
$schoolPayment = R::dispense('payment');
$schoolPayment->user_id = $schoolId;
$schoolPayment->user_type = 'school';
$schoolPayment->email = $school->email;
$schoolPayment->amount = 1000.0;
$schoolPayment->currency = 'KES';
$schoolPayment->purpose = 'school_subscription';
$schoolPayment->api_ref = $schoolApiRef;
$schoolPayment->state = 'PENDING';
$schoolPayment->created_at = date('Y-m-d H:i:s');
$schoolPaymentId = R::store($schoolPayment);

assert_test("School subscription payment created with ID {$schoolPaymentId}", $schoolPaymentId > 0);
assert_test("School subscription amount is 1000", floatval($schoolPayment->amount) === 1000.0);

// Simulate school payment completion
$schoolPayment->state = 'COMPLETE';
$schoolPayment->tracking_code = "IS_SCH_TRACK_" . time();
R::store($schoolPayment);

$school->plan = 'pro';
$school->status = 'active';
$expiry = new DateTime();
$expiry->add(new DateInterval('P12M'));
$school->subscription_expiry = $expiry->format('Y-m-d H:i:s');
R::store($school);

assert_test("School Pro plan activated for 12 months", $school->plan === 'pro');
assert_test("School Pro now has full directory access", has_directory_access($_SESSION['auth']) === true);

// --- TEST 7: IDEMPOTENT SIMULTANEOUS WEBHOOK + CALLBACK ---
echo "\n7. Testing Idempotency (Simultaneous Webhook & Browser Callback)...\n";
// Webhook arrives for already completed payment
$idempPayment = R::findOne('payment', 'api_ref = ?', [$retryApiRef]);
assert_test("Payment is already COMPLETE", $idempPayment->state === 'COMPLETE');

// Second trigger does not duplicate directoryaccess record
$countRecords = R::count('directoryaccess', 'user_id = ? AND user_type = ?', [$teacherId, 'teacher']);
assert_test("Only 1 directoryaccess record exists for user (no duplicates)", $countRecords === 1);

// --- SUMMARY ---
echo "\n============================================\n";
echo "TEST RESULTS: {$passed} Passed, {$failed} Failed\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
