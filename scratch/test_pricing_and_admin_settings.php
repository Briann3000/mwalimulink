<?php
// scratch/test_pricing_and_admin_settings.php - Test suite for database pricing engine and admin panel

if (file_exists(__DIR__ . '/../rb.php')) {
    require_once __DIR__ . '/../rb.php';
} elseif (file_exists(__DIR__ . '/../rb-mysql.php')) {
    require_once __DIR__ . '/../rb-mysql.php';
}
require_once __DIR__ . '/../config.php';

if (!R::testConnection()) {
    R::setup('sqlite:' . __DIR__ . '/test_pricing.db');
}

echo "=== DATABASE PRICING & ADMIN SETTINGS TEST SUITE ===\n\n";

$passCount = 0;
$failCount = 0;

function assert_test($condition, $name)
{
    global $passCount, $failCount;
    if ($condition) {
        echo "  [PASS] $name\n";
        $passCount++;
    } else {
        echo "  [FAIL] $name\n";
        $failCount++;
    }
}

// 1. Test Default Fallbacks when DB is clean
R::exec("DELETE FROM systemsetting WHERE setting_key IN ('directory_fee', 'school_pro_fee', 'directory_access_months', 'school_pro_months', 'test_custom_key')");
$GLOBALS['_MWALIMU_SETTINGS_CACHE'] = [];

assert_test(floatval(get_setting('directory_fee', 100)) === 100.0, "get_setting('directory_fee') falls back to default 100");
assert_test(floatval(get_setting('school_pro_fee', 1000)) === 1000.0, "get_setting('school_pro_fee') falls back to default 1000");
assert_test(intval(get_setting('directory_access_months', 12)) === 12, "get_setting('directory_access_months') falls back to default 12");

// 2. Test set_setting() and Audit Logging
$_SESSION['auth'] = [
    'logged_in' => true,
    'user_id' => 1,
    'role' => 'admin',
    'email' => 'admin@mwalimu.info',
    'name' => 'System Admin'
];

$setResult = set_setting('directory_fee', 150, 'Directory Access Pass Fee (KES)', 'admin@mwalimu.info');
assert_test($setResult === true, "set_setting('directory_fee', 150) returned true");

$updatedFee = floatval(get_setting('directory_fee', 100));
assert_test($updatedFee === 150.0, "get_setting('directory_fee') returns updated value 150");

$settingRow = R::findOne('systemsetting', 'setting_key = "directory_fee"');
assert_test($settingRow !== null && floatval($settingRow->setting_value) === 150.0, "systemsetting row exists in database with 150");

// Verify audit trail entry was created
$auditRow = R::findOne('adminauditlog', 'category = "pricing" AND action_type = "SETTING_UPDATED" ORDER BY id DESC');
assert_test($auditRow !== null, "adminauditlog entry was created for pricing change");
assert_test(str_contains($auditRow->details, "directory_fee"), "Audit log mentions 'directory_fee'");
assert_test($auditRow->actor_email === 'admin@mwalimu.info', "Audit log records admin email");

// 3. Test Dynamic Duration in grant_directory_access()
set_setting('directory_access_months', 6, '6-month promo pass', 'admin@mwalimu.info');
$testTeacherEmail = 'promo.teacher@mwalimu.info';
R::exec("DELETE FROM directoryaccess WHERE email = ?", [$testTeacherEmail]);

grant_directory_access(888, 'teacher', $testTeacherEmail, null, 'PROMO_REF_123');
$access = R::findOne('directoryaccess', 'email = ?', [$testTeacherEmail]);

assert_test($access !== null, "DirectoryAccess record created for promo teacher");
assert_test(floatval($access->last_amount) === 150.0, "DirectoryAccess used dynamic setting fee (150)");

$expiryTimestamp = strtotime($access->expires_at);
$expectedApprox = time() + (6 * 30 * 86400);
$diffDays = abs($expiryTimestamp - $expectedApprox) / 86400;
assert_test($diffDays < 10, "DirectoryAccess expires in ~6 months as configured in setting");

// 4. Test School Pro Subscription Setting
set_setting('school_pro_fee', 2500, 'School Pro Annual Fee', 'admin@mwalimu.info');
set_setting('school_pro_months', 12, 'School Pro Duration', 'admin@mwalimu.info');
assert_test(floatval(get_setting('school_pro_fee', 1000)) === 2500.0, "School Pro fee updated to 2500");

// 5. Reset to standard baseline (100 KES, 1000 KES, 12 Months)
set_setting('directory_fee', 100, 'Directory Access Pass Fee (KES)', 'admin@mwalimu.info');
set_setting('directory_access_months', 12, 'Directory Pass Duration (Months)', 'admin@mwalimu.info');
set_setting('school_pro_fee', 1000, 'School Pro Subscription Fee (KES)', 'admin@mwalimu.info');
set_setting('school_pro_months', 12, 'School Pro Duration (Months)', 'admin@mwalimu.info');
set_setting('payment_currency', 'KES', 'Default Currency', 'admin@mwalimu.info');

assert_test(floatval(get_setting('directory_fee')) === 100.0, "Reset directory_fee is 100");
assert_test(floatval(get_setting('school_pro_fee')) === 1000.0, "Reset school_pro_fee is 1000");

// Clean up test records
R::exec("DELETE FROM directoryaccess WHERE email = ?", [$testTeacherEmail]);

echo "\n============================================\n";
echo "SUMMARY: Passed: $passCount, Failed: $failCount\n";
echo "============================================\n";
