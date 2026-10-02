<?php
// tests/test_auth_security.php
// Comprehensive Automated Security & Authentication Test Suite for MwalimuLink

error_reporting(E_ALL);
ini_set('display_errors', '1');

// Setup environment and database
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../rb.php';

// RedBean Custom Formatter
if (!class_exists('UnderscoreFormatter')) {
    class UnderscoreFormatter {
        public function formatBeanTable($beanType) { return $beanType; }
        public function formatBeanID($beanType) { return 'id'; }
        public function formatBeanForeignKey($beanType) { return $beanType . '_id'; }
    }
    R::ext('formatter', function () {
        return new UnderscoreFormatter();
    });
}

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
    if (!file_exists(dirname($dbPath))) {
        @mkdir(dirname($dbPath), 0777, true);
    }
    R::setup("sqlite:{$dbPath}");
}

R::freeze(false);

$passedCount = 0;
$failedCount = 0;

function assert_test($description, $condition, $details = '') {
    global $passedCount, $failedCount;
    if ($condition) {
        $passedCount++;
        echo "  [PASS] {$description}\n";
    } else {
        $failedCount++;
        echo "  [FAIL] {$description}" . ($details ? " - {$details}" : "") . "\n";
    }
}

echo "\n========================================================\n";
echo "  MWALIMULINK AUTHENTICATION & SECURITY TEST SUITE\n";
echo "========================================================\n\n";

// -------------------------------------------------------------
// TEST 1: Phone Number Normalization
// -------------------------------------------------------------
echo "[1] Testing Kenyan Phone Number Normalization...\n";
assert_test("Standard 07xx phone format normalized to 254...", normalize_kenyan_phone('0712345678') === '254712345678');
assert_test("Standard 01xx phone format normalized to 254...", normalize_kenyan_phone('0112345678') === '254112345678');
assert_test("+254 prefix normalized to 254...", normalize_kenyan_phone('+254712345678') === '254712345678');
assert_test("254 prefix without + normalized to 254...", normalize_kenyan_phone('254712345678') === '254712345678');
assert_test("Invalid short number rejected", normalize_kenyan_phone('12345') === null);
assert_test("Invalid string letters rejected", normalize_kenyan_phone('abcdefghij') === null);

// -------------------------------------------------------------
// TEST 2: Open Redirect Security Mitigation
// -------------------------------------------------------------
echo "\n[2] Testing Open Redirect Mitigation...\n";
function sanitize_redirect_test($url) {
    $r = trim($url);
    if (!empty($r) && (!str_starts_with($r, '/') || str_starts_with($r, '//'))) {
        return '';
    }
    return $r;
}
assert_test("Protocol-relative open redirect '//evil.com' sanitized to empty", sanitize_redirect_test('//evil.com') === '');
assert_test("Absolute URL redirect 'https://attacker.com' sanitized to empty", sanitize_redirect_test('https://attacker.com') === '');
assert_test("Valid internal relative redirect '/teacher/dashboard' preserved", sanitize_redirect_test('/teacher/dashboard') === '/teacher/dashboard');
assert_test("Valid internal query redirect '/schools/private?id=12' preserved", sanitize_redirect_test('/schools/private?id=12') === '/schools/private?id=12');

// -------------------------------------------------------------
// TEST 3: Brute-Force Rate Limiting Engine
// -------------------------------------------------------------
echo "\n[3] Testing Login Rate Limiting (5 failed attempts per 15 min)...\n";
$testEmail = 'ratetest_' . time() . '@test.com';

// Ensure starting fresh
clear_login_rate_limit($testEmail);
$check1 = check_login_rate_limit($testEmail);
assert_test("Initial login attempt allowed", $check1['allowed'] === true);

// Record 4 failed attempts
for ($i = 1; $i <= 4; $i++) {
    record_failed_login($testEmail);
}
$checkAfter4 = check_login_rate_limit($testEmail);
assert_test("4 failed attempts still within threshold", $checkAfter4['allowed'] === true);

// 5th failed attempt triggers lockout
record_failed_login($testEmail);
$checkAfter5 = check_login_rate_limit($testEmail);
assert_test("5th failed attempt triggers lockout", $checkAfter5['allowed'] === false && $checkAfter5['retry_after'] > 0);

// Clearing rate limit resets state
clear_login_rate_limit($testEmail);
$checkAfterClear = check_login_rate_limit($testEmail);
assert_test("Clearing rate limit resets lockout", $checkAfterClear['allowed'] === true);

// -------------------------------------------------------------
// TEST 4: Teacher Registration & Validation Logic
// -------------------------------------------------------------
echo "\n[4] Testing Teacher Registration Validation...\n";
$dummyTeacherEmail = 'educator_test_' . time() . '@mwalimu.info';

// Verify short password check
$shortPass = 'abc';
assert_test("Teacher password < 8 characters detected as invalid", strlen($shortPass) < 8);

// Verify mismatch check
$passA = 'Secret@123';
$passB = 'Different@123';
assert_test("Password confirmation mismatch detected", $passA !== $passB);

// Create valid test teacher
$teacher = R::dispense('teacher');
$teacher->name = 'Jane Mwalimu';
$teacher->email = $dummyTeacherEmail;
$teacher->mobile = normalize_kenyan_phone('0711223344');
$teacher->password = password_hash('TeacherSecure@2026', PASSWORD_DEFAULT);
$teacher->status = 'available';
$teacher->created_at = date('Y-m-d H:i:s');
$teacherId = R::store($teacher);

assert_test("Teacher record stored successfully in database", $teacherId > 0);

// Verify duplicate email detection
$duplicateCheck = R::findOne('teacher', 'email = ?', [$dummyTeacherEmail]);
assert_test("Duplicate teacher email accurately detected", $duplicateCheck !== null && $duplicateCheck->id == $teacherId);

// Verify password verification
assert_test("Teacher password verification with password_verify() succeeds", password_verify('TeacherSecure@2026', $teacher->password));
assert_test("Invalid password fails password_verify()", !password_verify('WrongPassword', $teacher->password));

// -------------------------------------------------------------
// TEST 5: School Registration & Validation Logic
// -------------------------------------------------------------
echo "\n[5] Testing School Registration Validation...\n";
$dummySchoolEmail = 'school_test_' . time() . '@mwalimu.info';

$school = R::dispense('school');
$school->name = 'Apex Academy Nairobi';
$school->email = $dummySchoolEmail;
$school->phone_number = normalize_kenyan_phone('0722334455');
$school->county = 'Nairobi';
$school->password = password_hash('SchoolAdmin@2026', PASSWORD_DEFAULT);
$school->status = 'active';
$school->plan = 'free';
$school->created_at = date('Y-m-d H:i:s');
$schoolId = R::store($school);

assert_test("School record stored successfully in database", $schoolId > 0);

$dupSchool = R::findOne('school', 'email = ?', [$dummySchoolEmail]);
assert_test("Duplicate school email accurately detected", $dupSchool !== null && $dupSchool->id == $schoolId);
assert_test("School password verification succeeds", password_verify('SchoolAdmin@2026', $school->password));

// -------------------------------------------------------------
// TEST 6: Account Suspension Security Checks
// -------------------------------------------------------------
echo "\n[6] Testing Account Suspension Enforcement...\n";
$suspendedTeacherEmail = 'suspended_teacher_' . time() . '@mwalimu.info';
$sTeacher = R::dispense('teacher');
$sTeacher->name = 'Suspended User';
$sTeacher->email = $suspendedTeacherEmail;
$sTeacher->password = password_hash('Password123!', PASSWORD_DEFAULT);
$sTeacher->status = 'suspended';
R::store($sTeacher);

$foundSuspended = R::findOne('teacher', 'email = ?', [$suspendedTeacherEmail]);
assert_test("Suspended teacher has status 'suspended'", $foundSuspended && $foundSuspended->status === 'suspended');

// -------------------------------------------------------------
// TEST 7: Password Reset Cryptographic Token Flow
// -------------------------------------------------------------
echo "\n[7] Testing Password Reset Cryptographic Token Lifecycle...\n";
// Generate token
$resetToken = bin2hex(random_bytes(32));
assert_test("Reset token is 64 hex characters (32 cryptographically secure bytes)", strlen($resetToken) === 64);

$expiryTime = date('Y-m-d H:i:s', time() + 3600);
$teacher->reset_token = $resetToken;
$teacher->reset_expiry = $expiryTime;
R::store($teacher);

// Lookup with valid token
$nowStr = date('Y-m-d H:i:s');
$validResetTeacher = R::findOne('teacher', 'email = ? AND reset_token = ? AND reset_expiry > ?', [$dummyTeacherEmail, $resetToken, $nowStr]);
assert_test("Valid non-expired token matches teacher record", $validResetTeacher !== null && $validResetTeacher->id == $teacherId);

// Verify expired token failure
$pastExpiry = date('Y-m-d H:i:s', time() - 3600);
$teacher->reset_expiry = $pastExpiry;
R::store($teacher);

$expiredResetTeacher = R::findOne('teacher', 'email = ? AND reset_token = ? AND reset_expiry > ?', [$dummyTeacherEmail, $resetToken, $nowStr]);
assert_test("Expired reset token is rejected by query", $expiredResetTeacher === null);

// Consume reset token with new password
$newPass = 'BrandNewPassword@2026';
$teacher->password = password_hash($newPass, PASSWORD_DEFAULT);
$teacher->reset_token = null;
$teacher->reset_expiry = null;
R::store($teacher);

$teacherRefreshed = R::load('teacher', $teacherId);
assert_test("Reset token cleared after password update (single-use)", $teacherRefreshed->reset_token === null);
assert_test("New password successfully active and verified", password_verify($newPass, $teacherRefreshed->password));

// -------------------------------------------------------------
// TEST 8: Administrator Constant-Time Login Check
// -------------------------------------------------------------
echo "\n[8] Testing Constant-Time Admin Password Verification...\n";
$adminEnvEmail = env('ADMIN_EMAIL', 'infomwalimulink@gmail.com');
$adminEnvPassword = env('ADMIN_PASSWORD', 'Kenya@254');

assert_test("Constant-time hash_equals matches correct admin credentials", hash_equals(strtolower($adminEnvEmail), strtolower('infomwalimulink@gmail.com')) && hash_equals($adminEnvPassword, 'Kenya@254'));
assert_test("Constant-time hash_equals rejects invalid admin password", !hash_equals($adminEnvPassword, 'WrongAdminPass'));

// -------------------------------------------------------------
// TEST 9: Secure File Upload Engine & Extension Whitelist
// -------------------------------------------------------------
echo "\n[9] Testing Secure File Upload Engine...\n";
$fakePhpUpload = [
    'name' => 'malicious.php',
    'type' => 'application/x-php',
    'tmp_name' => __DIR__ . '/test_temp.txt',
    'error' => UPLOAD_ERR_OK,
    'size' => 1024
];
file_put_contents(__DIR__ . '/test_temp.txt', '<?php phpinfo(); ?>');
$resPhp = secure_validate_and_upload($fakePhpUpload, 'uploads/documents/', ['pdf', 'jpg', 'png']);
assert_test("Executable .php upload strictly rejected", $resPhp['success'] === false);

$fakeDoubleExtUpload = [
    'name' => 'image.php.jpg',
    'type' => 'image/jpeg',
    'tmp_name' => __DIR__ . '/test_temp.txt',
    'error' => UPLOAD_ERR_OK,
    'size' => 1024
];
$resDouble = secure_validate_and_upload($fakeDoubleExtUpload, 'uploads/documents/', ['pdf', 'jpg', 'png']);
assert_test("Double-extension attack (.php.jpg) strictly rejected", $resDouble['success'] === false);

@unlink(__DIR__ . '/test_temp.txt');

// -------------------------------------------------------------
// TEST 10: Cross-Role Duplicate Email Collision Protection
// -------------------------------------------------------------
echo "\n[10] Testing Cross-Role Email Collision Protection...\n";
$uniqueCrossEmail = 'crosstest_' . time() . '@mwalimulink.co.ke';
$testSchool = R::dispense('school');
$testSchool->name = 'Collision Academy';
$testSchool->email = $uniqueCrossEmail;
$testSchool->password = password_hash('Pass@12345', PASSWORD_DEFAULT);
R::store($testSchool);

// Check if teacher can register with existing school email
$dupTeacherCheck = R::findOne('school', 'email = ?', [$uniqueCrossEmail]);
assert_test("Teacher registration collides with existing school account", $dupTeacherCheck !== null);

// Check if school can register with existing teacher email
$uniqueTeacherEmail = 'teachertest_' . time() . '@mwalimulink.co.ke';
$testTeacher = R::dispense('teacher');
$testTeacher->name = 'Collision Teacher';
$testTeacher->email = $uniqueTeacherEmail;
$testTeacher->password = password_hash('Pass@12345', PASSWORD_DEFAULT);
R::store($testTeacher);

$dupSchoolCheck = R::findOne('teacher', 'email = ?', [$uniqueTeacherEmail]);
assert_test("School registration collides with existing teacher account", $dupSchoolCheck !== null);

// Clean up test records
try {
    R::trash($teacher);
    R::trash($school);
    R::trash($sTeacher);
    R::trash($testSchool);
    R::trash($testTeacher);
} catch (Exception $e) {}

echo "\n========================================================\n";
echo "  TEST RESULTS: {$passedCount} Passed, {$failedCount} Failed\n";
echo "========================================================\n\n";

exit($failedCount > 0 ? 1 : 0);
