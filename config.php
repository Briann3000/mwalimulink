<?php
// config.php - Application Configuration and Environment Loader

function env($key, $default = null) {
    static $env = null;
    if ($env === null) {
        $env = [];
        $envFile = __DIR__ . '/.env';
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line) || str_starts_with($line, '#')) continue;
                if (strpos($line, '=') !== false) {
                    list($name, $value) = explode('=', $line, 2);
                    $name = trim($name);
                    $value = trim($value, " \t\n\r\0\x0B\"\'");
                    $env[$name] = $value;
                }
            }
        }
    }
    return $env[$key] ?? getenv($key) ?: $default;
}

if (!function_exists('h')) {
    function h($str) {
        return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
    }
}

// -------------------------------------------------------------------
// Session & Authentication Helper Functions
// -------------------------------------------------------------------

function init_session() {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) {
            $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
            session_set_cookie_params([
                'lifetime' => 86400 * 30, // 30 days
                'path' => '/',
                'domain' => '',
                'secure' => $isHttps,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            session_start();
        } else {
            @session_start();
        }
    }
}

function auth_login($user, $role) {
    init_session();
    if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
        @session_regenerate_id(true); // Prevent session fixation
    }
    $_SESSION['auth'] = [
        'logged_in' => true,
        'user_id' => $user->id ?? 0,
        'name' => $user->name ?? ($role === 'admin' ? 'Administrator' : ''),
        'email' => $user->email ?? '',
        'role' => $role, // 'teacher' | 'school' | 'admin'
        'created_at' => time()
    ];
}

function auth_logout() {
    init_session();
    $_SESSION = [];
    if (!headers_sent() && ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_destroy();
    }
}

function auth_user() {
    init_session();
    return $_SESSION['auth'] ?? null;
}

function is_logged_in() {
    init_session();
    return !empty($_SESSION['auth']['logged_in']);
}

function has_role($role) {
    init_session();
    return is_logged_in() && isset($_SESSION['auth']['role']) && $_SESSION['auth']['role'] === $role;
}

function require_auth($role = null) {
    init_session();
    if (!is_logged_in()) {
        $loginRoute = ($role === 'school') ? 'school_login' : (($role === 'admin') ? 'admin_login' : 'teacher_login');
        header("Location: index.php?action=$loginRoute");
        exit();
    }
    if ($role !== null && !has_role($role)) {
        header("Location: index.php?action=landing");
        exit();
    }
}

// -------------------------------------------------------------------
// CSRF Protection Helpers
// -------------------------------------------------------------------

function csrf_token() {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    $token = csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . h($token) . '">';
}

function validate_csrf() {
    init_session();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? '';
        if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
            die("Security Error: Invalid or expired CSRF token.");
        }
    }
}

// -------------------------------------------------------------------
// Kenyan Geographic & Educational Metadata Helpers
// -------------------------------------------------------------------

function kenyan_counties() {
    return [
        'Baringo', 'Bomet', 'Bungoma', 'Busia', 'Elgeyo Marakwet', 'Embu',
        'Garissa', 'Homa Bay', 'Isiolo', 'Kajiado', 'Kakamega', 'Kericho',
        'Kiambu', 'Kilifi', 'Kirinyaga', 'Kisii', 'Kisumu', 'Kitui',
        'Kwale', 'Laikipia', 'Lamu', 'Machakos', 'Makueni', 'Mandera',
        'Marsabit', 'Meru', 'Migori', 'Mombasa', 'Murang\'a', 'Nairobi',
        'Nakuru', 'Nandi', 'Narok', 'Nyamira', 'Nyandarua', 'Nyeri',
        'Samburu', 'Siaya', 'Taita Taveta', 'Tana River', 'Tharaka Nithi',
        'Trans Nzoia', 'Turkana', 'Uasin Gishu', 'Vihiga', 'Wajir', 'West Pokot'
    ];
}

function kenyan_grade_levels() {
    return [
        'ECDE / Kindergarten / Pre-Primary',
        'Primary School (CBC Grade 1 - 6)',
        'Junior Secondary (JSS Grade 7 - 9)',
        'Senior Secondary (Form 1 - 4)',
        'International Curriculum (IGCSE / IB / Cambridge)',
        'Special Needs Education (SNE)',
        'Tertiary / College'
    ];
}

function kenyan_school_categories() {
    return [
        'Private Primary & Kindergarten',
        'Private Secondary School',
        'Junior Secondary School (JSS)',
        'International School (IGCSE / IB / American)',
        'Kindergarten / Early Childhood Center',
        'Special Needs School',
        'Public Primary School',
        'Public Secondary School'
    ];
}

function normalize_kenyan_phone($phone) {
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    if (str_starts_with($cleaned, '0') && (strlen($cleaned) === 10)) {
        return '254' . substr($cleaned, 1);
    }
    if (str_starts_with($cleaned, '254') && (strlen($cleaned) === 12)) {
        return $cleaned;
    }
    if (strlen($cleaned) === 9 && (str_starts_with($cleaned, '7') || str_starts_with($cleaned, '1'))) {
        return '254' . $cleaned;
    }
    return null; // Invalid format
}

function validate_tsc_number($tsc) {
    $tsc = trim((string)$tsc);
    if (empty($tsc)) {
        return ['valid' => true, 'formatted' => null, 'is_registered' => false];
    }
    // Kenyan TSC numbers are typically 5 to 7 digits (e.g. 123456, 987654) or alphanumeric prefix (e.g. TSC/123456 or 123456)
    $digitsOnly = preg_replace('/[^0-9]/', '', $tsc);
    if (strlen($digitsOnly) >= 4 && strlen($digitsOnly) <= 8) {
        return ['valid' => true, 'formatted' => $digitsOnly, 'is_registered' => true];
    }
    return ['valid' => false, 'formatted' => null, 'is_registered' => false, 'error' => 'TSC Number must be between 4 and 8 digits.'];
}


