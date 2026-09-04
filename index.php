<?php
// Let PHP built-in server serve static files (images, css, js) directly
if (php_sapi_name() === 'cli-server') {
    $reqPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $filePath = __DIR__ . $reqPath;
    if ($reqPath !== '/' && $reqPath !== '/index.php' && is_file($filePath) && !str_ends_with($filePath, '.php')) {
        return false;
    }
}

if (!function_exists('redirectTohttps')) {
    function redirectTohttps() {
      $host = $_SERVER['HTTP_HOST'] ?? '';
      // Skip HTTPS redirect on local development
      if (str_contains($host, 'localhost') || str_contains($host, '127.0.0.1')) {
        return;
      }
      if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] != 'on') {
        $redirect = "https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        header("Location: $redirect");
        exit();
      }
    }
}

redirectTohttps();
// Load environment configuration
require_once 'config.php';

// Configure error reporting
if (env('APP_DEBUG', 'false') === 'true') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
}

// Initialize secure session
init_session();

ob_start(); // Start output buffering

// Include RedBeanPHP
require_once 'rb.php';

/** Custom Formatter to Allow Underscores in RedBeanPHP */
class UnderscoreFormatter
{
    public function formatBeanTable($beanType) { return $beanType; }
    public function formatBeanID($beanType) { return 'id'; }
    public function formatBeanForeignKey($beanType) { return $beanType . '_id'; }
}

R::ext('formatter', function() {
    return new UnderscoreFormatter();
});

// Setup Database (MySQL or SQLite)
$dbDriver = env('DB_CONNECTION', 'sqlite');

if ($dbDriver === 'mysql') {
    $dbHost = env('DB_HOST', 'localhost');
    $dbPort = env('DB_PORT', '3306');
    $dbName = env('DB_DATABASE', 'mwalimu');
    $dbUser = env('DB_USERNAME', 'root');
    $dbPass = env('DB_PASSWORD', '');
    
    R::setup("mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4", $dbUser, $dbPass);
} else {
    $dbPath = __DIR__ . '/' . env('DB_PATH', 'data/mwalimu.db');
    R::setup("sqlite:$dbPath");
}

// Freeze schema in production to prevent runtime mutations and improve speed
$isProduction = env('APP_ENV', 'production') === 'production';
R::freeze($isProduction);

require 'header.php';

// -------------------------------------------------------------------
// Clean URL Routing Engine
// -------------------------------------------------------------------

// Determine request URI path
$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestUri = trim($requestUri, '/');
if (str_ends_with($requestUri, 'index.php')) {
    $requestUri = substr($requestUri, 0, -9);
    $requestUri = trim($requestUri, '/');
}

// Map clean modern URLs to view files
$modernRoutes = [
    '' => 'views/landing.php',
    'home' => 'views/landing.php',
    'about' => 'views/about_us.php',
    'contacts' => 'views/contacts.php',
    'faqs' => 'views/faqs.php',
    'faqs-overseas' => 'views/faqs-on-teaching-overseas.php',
    'privacy' => 'views/privacy_policy.php',
    'terms' => 'views/terms_and_conditions.php',
    'news' => 'views/news.php',
    'blog' => 'views/blog.php',
    'blog/view' => 'views/blog_view.php',
    'blog/admin' => 'views/blog_admin.php',

    // Authentication & Registration
    'login' => 'views/dual_login.php',
    'login/teacher' => 'views/teacher_login.php',
    'login/school' => 'views/school_login.php',
    'login/admin' => 'views/admin_login.php',
    'logout' => 'views/logout.php',
    'register' => 'views/register_choice.php',
    'register/teacher' => 'views/teacher_register.php',
    'register/school' => 'views/school_register.php',
    'reset-password/teacher' => 'views/reset_teacher_password.php',
    'reset-password/school' => 'views/reset_school_password.php',

    // Dashboards & Portals
    'teacher/dashboard' => 'views/teacher_dashboard.php',
    'teacher/profile' => 'views/teacher_profile.php',
    'teacher/update' => 'views/teacher_update.php',
    'teacher/jobs' => 'views/teacher_job_search.php',
    'teacher/apply' => 'views/teacher_job_apply.php',
    'tp-hub' => 'views/tp_hub.php',

    'school/dashboard' => 'views/school_dashboard.php',
    'school/staff' => 'views/school_staff.php',
    'school/post-job' => 'views/school_post_job.php',
    'school/search-candidates' => 'views/school_search_candidate.php',
    'school/subscribe' => 'views/school_subscribe.php',
    'school/pay' => 'views/pay_subscription.php',
    'school/callback' => 'views/subscription_callback.php',

    // API & Webhooks
    'api/verification-callback' => 'views/api_verification_webhook.php',

    'admin/dashboard' => 'views/admin_dashboard.php',
    'admin/verifications' => 'views/admin_verifications.php',

    // School Directories
    'schools/public' => 'views/public_school_search.php',
    'schools/public/detail' => 'views/public_school_detail.php',
    'schools/private' => 'views/private_school_search.php',
    'schools/private/detail' => 'views/private_school_detail.php',
    'schools/international' => 'views/international_school_search.php',
    'schools/international/detail' => 'views/international_school_details.php',
];

// Legacy parameter fallback map
$legacyMap = [
    'landing' => 'views/landing.php',
    'teacher_register' => 'views/teacher_register.php',
    'school_register' => 'views/school_register.php',
    'teacher_login' => 'views/teacher_login.php',
    'school_login' => 'views/school_login.php',
    'dual_login' => 'views/dual_login.php',
    'teacher_dashboard' => 'views/teacher_dashboard.php',
    'school_dashboard' => 'views/school_dashboard.php',
    'admin_dashboard' => 'views/admin_dashboard.php',
    'admin_login' => 'views/admin_login.php',
    'public_school_search' => 'views/public_school_search.php',
    'private_school_search' => 'views/private_school_search.php',
    'international_school_search' => 'views/international_school_search.php',
    'school_search_candidate' => 'views/school_search_candidate.php',
    'pay_subscription' => 'views/pay_subscription.php',
    'subscription_callback' => 'views/subscription_callback.php',
    'reset_teacher_password' => 'views/reset_teacher_password.php',
    'reset_school_password' => 'views/reset_school_password.php',
    'logout' => 'views/logout.php'
];

// Fallback legacy action support for transition
$action = $_GET['action'] ?? null;
$viewFile = null;

if (!empty($action)) {
    if (isset($modernRoutes[$action])) {
        $viewFile = $modernRoutes[$action];
    } elseif (isset($legacyMap[$action])) {
        $viewFile = $legacyMap[$action];
    } else {
        $legacyFile = 'views/' . $action . '.php';
        if (file_exists(__DIR__ . '/' . $legacyFile)) {
            $viewFile = $legacyFile;
        }
    }
}

if (!$viewFile && array_key_exists($requestUri, $modernRoutes)) {
    $viewFile = $modernRoutes[$requestUri];
}

if ($viewFile && file_exists(__DIR__ . '/' . $viewFile)) {
    include __DIR__ . '/' . $viewFile;
} else {
    echo '<article class="container" style="max-width: 600px; margin: 3rem auto; text-align: center;">
        <div class="alert alert-error">
            <h2>404 - Page Not Found</h2>
            <p>The requested URL <code>/' . h($requestUri) . '</code> does not exist.</p>
            <a href="/" class="primary">Return to Home</a>
        </div>
    </article>';
}

require 'footer.php'; // Include footer at the end of every page
ob_end_flush(); // Send the buffered content to the browser
?>
