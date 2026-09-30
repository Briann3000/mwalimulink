<?php
// views/admin_impersonate.php - Admin User Impersonation Handler
init_session();

$action = $_GET['action'] ?? '';
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);

// Stop impersonation must work regardless of current impersonated role
if ($action === 'stop' || str_contains($reqPath, 'stop-impersonate') || ($requestUri ?? '') === 'admin/stop-impersonate') {
    admin_stop_impersonation();
    header("Location: /admin/dashboard");
    exit();
}

// Starting impersonation requires an actual admin session
require_auth('admin');

$type = $_GET['type'] ?? '';
$id = (int)($_GET['id'] ?? 0);

if (($type === 'teacher' || $type === 'school') && $id > 0) {
    $success = admin_start_impersonation($type, $id);
    if ($success) {
        if ($type === 'teacher') {
            header("Location: /teacher/dashboard");
        } else {
            header("Location: /school/dashboard");
        }
        exit();
    }
}

// Fallback if failed
header("Location: /admin/dashboard?error=impersonation_failed");
exit();
