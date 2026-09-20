<?php
// scratch/test_http_routes.php - HTTP Route Simulation Test

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

function simulate_route($uri, $sessionUser = null, $method = 'GET', $postData = [])
{
    // Start session if not started
    if (session_status() === PHP_SESSION_NONE) {
        @session_start();
    }

    $_SESSION = [];
    if ($sessionUser) {
        $_SESSION['auth'] = $sessionUser;
    }

    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = [];
    $_POST = $postData;

    $parts = parse_url($uri);
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $_GET);
    }

    // Capture headers and output
    ob_start();

    // We run the gating check directly as require_directory_access would do
    $redirectHeader = null;
    try {
        if ($uri === '/schools/public') {
            // Public school directory: no gating
            $status = 200;
            $body = "Public Schools Directory Content";
        } elseif (str_starts_with($uri, '/schools/private') || str_starts_with($uri, '/schools/international')) {
            if (!is_logged_in()) {
                $redirectHeader = '/login?redirect=' . urlencode($uri) . '&notice=auth_required';
                $status = 302;
                $body = "Redirecting to login";
            } elseif (!has_directory_access()) {
                $redirectHeader = '/schools/pay?redirect=' . urlencode($uri);
                $status = 302;
                $body = "Redirecting to pay";
            } else {
                $status = 200;
                $body = (str_starts_with($uri, '/schools/private')) ? "Private Schools Directory Table" : "International Schools Directory Table";
            }
        } elseif ($uri === '/schools/pay') {
            if (!is_logged_in()) {
                $redirectHeader = '/login?redirect=' . urlencode($uri) . '&notice=auth_required';
                $status = 302;
                $body = "Redirecting to login";
            } else {
                $status = 200;
                $body = "Premium School Directories Access KES 100 Checkout";
            }
        } else {
            $status = 200;
            $body = "Other Route";
        }
    } catch (\Exception $e) {
        $status = 500;
        $body = $e->getMessage();
    }
    ob_end_clean();

    return [
        'status' => $status,
        'redirect' => $redirectHeader,
        'body' => $body
    ];
}

echo "=== TESTING HTTP ROUTE GATING SIMULATION ===\n\n";

// 1. Public Schools Route for Guest
$res1 = simulate_route('/schools/public');
echo "1. Guest visiting /schools/public: Status {$res1['status']} (Expected: 200)\n";
assert($res1['status'] === 200);

// 2. Private Schools Route for Guest
$res2 = simulate_route('/schools/private');
echo "2. Guest visiting /schools/private: Redirect {$res2['redirect']} (Expected: /login?redirect=%2Fschools%2Fprivate&notice=auth_required)\n";
assert(str_contains($res2['redirect'], '/login?redirect='));

// 3. International Schools Route for Guest
$res3 = simulate_route('/schools/international');
echo "3. Guest visiting /schools/international: Redirect {$res3['redirect']} (Expected: /login?redirect=%2Fschools%2Finternational&notice=auth_required)\n";
assert(str_contains($res3['redirect'], '/login?redirect='));

// 4. Private Schools Route for Logged-In Unpaid Teacher
$unpaidTeacher = [
    'logged_in' => true,
    'user_id' => 9999,
    'role' => 'teacher',
    'email' => 'unpaid_sim_teacher@test.com'
];
$res4 = simulate_route('/schools/private', $unpaidTeacher);
echo "4. Unpaid Teacher visiting /schools/private: Redirect {$res4['redirect']} (Expected: /schools/pay?redirect=%2Fschools%2Fprivate)\n";
assert(str_contains($res4['redirect'], '/schools/pay?redirect='));

// 5. Checkout Page for Logged-In Teacher
$res5 = simulate_route('/schools/pay', $unpaidTeacher);
echo "5. Logged-in Teacher visiting /schools/pay: Status {$res5['status']} (Expected: 200)\n";
assert($res5['status'] === 200);

// 6. Private Schools Route for Logged-In Paid Teacher
$paidTeacher = R::dispense('teacher');
$paidTeacher->name = 'Paid Sim Teacher';
$paidTeacher->email = 'paid_sim_teacher@test.com';
$paidTeacherId = R::store($paidTeacher);
grant_directory_access($paidTeacherId, 'teacher', $paidTeacher->email, 100, 'SIM_TEST_REF');

$paidTeacherSession = [
    'logged_in' => true,
    'user_id' => $paidTeacherId,
    'role' => 'teacher',
    'email' => $paidTeacher->email
];
$res6 = simulate_route('/schools/private', $paidTeacherSession);
echo "6. Paid Teacher visiting /schools/private: Status {$res6['status']} (Expected: 200)\n";
assert($res6['status'] === 200);

// 7. International Schools Route for Logged-In Paid Teacher
$res7 = simulate_route('/schools/international', $paidTeacherSession);
echo "7. Paid Teacher visiting /schools/international: Status {$res7['status']} (Expected: 200)\n";
assert($res7['status'] === 200);

echo "\nALL ROUTING SIMULATION CHECKS PASSED SUCCESSFULLY!\n";
