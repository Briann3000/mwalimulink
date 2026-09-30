<?php
// views/dual_login.php - Deprecated: Redirects to dedicated login portals
$role = $_GET['role'] ?? 'teacher';
$redirect = trim($_GET['redirect'] ?? '');
$target = ($role === 'school') ? '/login/school' : '/login/teacher';
if (!empty($redirect)) {
    $target .= '?redirect=' . urlencode($redirect);
}
header("Location: $target");
exit();