<?php
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

// Define the action parameter to determine the route
$action = $_GET['action'] ?? 'landing';

// Map actions to corresponding files in the "views" folder
$routes = [
    'get_in_touch' => 'views/get_in_touch.php',
    'faqs-on-teaching-overseas' => 'views/faqs-on-teaching-overseas.php',
    'international_school_search' => 'views/international_school_search.php',
    'international_school_details' => 'views/international_school_details.php',
    'international_school_add' => 'views/international_school_add.php',
    'blog' => 'views/blog.php',
    'blog_view' => 'views/blog_view.php',
    'blog_admin' => 'views/blog_admin.php',
    'blog_setup' => 'views/blog_setup.php',
    'populate_table' => 'views/populate_table.php',
    'public_school_search' => 'views/public_school_search.php',
    'public_school_detail' => 'views/public_school_detail.php',
    'private_school_search' => 'views/private_school_search.php',
    'private_school_detail' => 'views/private_school_detail.php',
    'public_school_add' => 'views/public_school_add.php',
    'contacts' => 'views/contacts.php',
    'about_us' => 'views/about_us.php',
    'school_list_admin' => 'views/school_list_admin.php',
    'school_list' => 'views/public_school_search.php',
    'home' => 'views/landing.php',
    'terms_and_conditions' => 'views/terms_and_conditions.php',
    'privacy_policy' => 'views/privacy_policy.php',
    'reset_school_password' => 'views/reset_school_password.php',
    'reset_teacher_password' => 'views/reset_teacher_password.php',
    'dual_login' => 'views/dual_login.php',
    'logout' => 'views/logout.php',
    'landing' => 'views/landing.php',
    'faqs' => 'views/faqs.php',
    'news' => 'views/news.php',
    'subscribe_link' => 'views/subscribe_link.php',
    'teacher_login' => 'views/teacher_login.php',
    'teacher_register' => 'views/teacher_register.php',
    'teacher_update' => 'views/teacher_update.php',
    'teacher_dashboard' => 'views/teacher_dashboard.php',
    'teacher_job_search' => 'views/teacher_job_search.php',
    'teacher_profile' => 'views/teacher_profile.php',
    'teacher_job_apply' => 'views/teacher_job_apply.php',
    'school_login' => 'views/school_login.php',
    'school_register' => 'views/school_register.php',
    'school_subscribe' => 'views/school_subscribe.php',
    'school_dashboard' => 'views/school_dashboard.php',
    'school_post_job' => 'views/school_post_job.php',
    'school_search_candidate' => 'views/school_search_candidate.php',
    'admin_login' => 'views/admin_login.php',
    'admin_dashboard' => 'views/admin_dashboard.php',
    'pay_subscription' => 'views/pay_subscription.php', // Add pay_subscription route
    'subscription_callback' => 'views/subscription_callback.php' // Add subscription_callback route
];

// Check if the requested action exists in the routes array
if (array_key_exists($action, $routes)) {
    include $routes[$action]; // Include the specific page content
} else {
    // If route is not found, show a 404 error page
    echo '<article class="container">
    <div class="alert alert-error">
        <h1>404 - Page Not Found</h1>
    </div>
</article>';
}

require 'footer.php'; // Include footer at the end of every page
ob_end_flush(); // Send the buffered content to the browser
?>
