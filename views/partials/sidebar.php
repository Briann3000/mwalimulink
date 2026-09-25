<?php
// views/partials/sidebar.php - Unified MwalimuLink Sidebar Component
$authUser = auth_user();
if (!$authUser) {
    return; // Guest users do not render fixed sidebar
}

$role = $authUser['role'] ?? 'teacher';
$currentUri = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');

// Helper to check active route
$isActive = function ($path) use ($currentUri) {
    $path = trim($path, '/');
    if ($currentUri === $path)
        return 'active';
    if ($path !== '' && str_starts_with($currentUri, $path))
        return 'active';
    return '';
};
?>

<aside class="sidebar-pane">
    <div class="sidebar-section-title">Core Navigation</div>

    <?php if ($role === 'teacher'): ?>
        <a href="/teacher/dashboard" class="sidebar-item-link <?= $isActive('teacher/dashboard') ?>">
            <i class="fa fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="/teacher/applications" class="sidebar-item-link <?= $isActive('teacher/applications') ?>">
            <i class="fa fa-list-check"></i> <span>My Applications</span>
        </a>
        <a href="/teacher/jobs"
            class="sidebar-item-link <?= ($isActive('teacher/jobs') || $isActive('teacher/apply')) ? 'active' : '' ?>">
            <i class="fa fa-briefcase"></i> <span>Browse Jobs</span>
        </a>
        <a href="/tp-hub" class="sidebar-item-link <?= $isActive('tp-hub') ?>">
            <i class="fa fa-graduation-cap"></i> <span>TP & Placement Hub</span>
        </a>
        <a href="/teacher/update" class="sidebar-item-link <?= $isActive('teacher/update') ?>">
            <i class="fa fa-user-edit"></i> <span>Edit Profile</span>
        </a>
        <a href="/teacher/cv-builder"
            class="sidebar-item-link <?= ($isActive('teacher/cv-builder') || $isActive('teacher/cv-preview')) ? 'active' : '' ?>">
            <i class="fa fa-file-lines"></i> <span>CV Builder & Polisher</span>
        </a>

    <?php elseif ($role === 'school'): ?>
        <a href="/school/dashboard" class="sidebar-item-link <?= $isActive('school/dashboard') ?>">
            <i class="fa fa-tachometer-alt"></i> <span>Overview</span>
        </a>
        <a href="/school/applicants" class="sidebar-item-link <?= $isActive('school/applicants') ?>">
            <i class="fa fa-user-check"></i> <span>Applicants Pipeline</span>
        </a>
        <a href="/school/post-job" class="sidebar-item-link <?= $isActive('school/post-job') ?>">
            <i class="fa fa-plus-circle"></i> <span>Post Vacancy</span>
        </a>
        <a href="/school/search-candidates" class="sidebar-item-link <?= $isActive('school/search-candidates') ?>">
            <i class="fa fa-search"></i> <span>Find Teachers</span>
        </a>
        <a href="/school/staff" class="sidebar-item-link <?= $isActive('school/staff') ?>">
            <i class="fa fa-users"></i> <span>Employed Staff</span>
        </a>
        <a href="/tp-hub" class="sidebar-item-link <?= $isActive('tp-hub') ?>">
            <i class="fa fa-graduation-cap"></i> <span>TP Placements</span>
        </a>
        <a href="/school/subscribe" class="sidebar-item-link <?= $isActive('school/subscribe') ?>">
            <i class="fa fa-credit-card"></i> <span>Manage Plan</span>
        </a>

    <?php elseif ($role === 'admin'): ?>
        <a href="/admin/dashboard" class="sidebar-item-link <?= $isActive('admin/dashboard') ?>">
            <i class="fa fa-tachometer-alt"></i> <span>Dashboard</span>
        </a>
        <a href="/admin/teachers" class="sidebar-item-link <?= $isActive('admin/teachers') ?>">
            <i class="fa fa-user-graduate"></i> <span>Manage Teachers</span>
        </a>
        <a href="/admin/schools" class="sidebar-item-link <?= $isActive('admin/schools') ?>">
            <i class="fa fa-school"></i> <span>Manage Schools</span>
        </a>
        <a href="/admin/verifications" class="sidebar-item-link <?= $isActive('admin/verifications') ?>">
            <i class="fa fa-shield-halved"></i> <span>Clearance Queue</span>
        </a>
        <a href="/admin/jobs" class="sidebar-item-link <?= $isActive('admin/jobs') ?>">
            <i class="fa fa-briefcase"></i> <span>Job Vacancies</span>
        </a>
        <a href="/admin/aggregation" class="sidebar-item-link <?= $isActive('admin/aggregation') ?>">
            <i class="fa fa-satellite-dish"></i> <span>Job Aggregation</span>
        </a>
        <a href="/admin/publications"
            class="sidebar-item-link <?= ($isActive('admin/publications') || $isActive('blog/admin')) ? 'active' : '' ?>">
            <i class="fa fa-book-open"></i> <span>Manage Publications</span>
        </a>
        <a href="/admin/pricing"
            class="sidebar-item-link <?= ($isActive('admin/pricing') || $isActive('admin/settings')) ? 'active' : '' ?>">
            <i class="fa fa-tags"></i> <span>Pricing & Settings</span>
        </a>
        <a href="/admin/audit"
            class="sidebar-item-link <?= ($isActive('admin/audit') || $isActive('admin/audit-logs')) ? 'active' : '' ?>">
            <i class="fa fa-clipboard-list"></i> <span>Audit Trail</span>
        </a>
    <?php endif; ?>

    <div class="sidebar-section-title">Community</div>
    <a href="/forum" class="sidebar-item-link <?= ($isActive('forum') && !$isActive('admin/forum')) ? 'active' : '' ?>">
        <i class="fa fa-comments"></i> <span>Teachers Forum</span>
    </a>
    <?php
    $unreadMsgs = get_unread_message_count($authUser['user_id'] ?? 0, $role);
    ?>
    <a href="/messages" class="sidebar-item-link <?= $isActive('messages') ?>">
        <i class="fa fa-envelope"></i> <span>Messages</span>
        <?php if ($unreadMsgs > 0): ?>
            <span
                style="background: #0f766e; color: white; font-size: 0.7rem; font-weight: 800; padding: 2px 7px; border-radius: 10px; margin-left: auto;"><?= $unreadMsgs ?></span>
        <?php endif; ?>
    </a>
    <?php if ($role === 'admin'): ?>
        <a href="/admin/forum" class="sidebar-item-link <?= $isActive('admin/forum') ?>">
            <i class="fa fa-shield-halved"></i> <span>Forum Moderation</span>
        </a>
    <?php endif; ?>

    <div class="sidebar-section-title">School Directories</div>
    <a href="/schools/public" class="sidebar-item-link <?= $isActive('schools/public') ?>">
        <i class="fa fa-landmark"></i> <span>Public Schools</span>
    </a>
    <a href="/schools/private" class="sidebar-item-link <?= $isActive('schools/private') ?>">
        <i class="fa fa-building"></i> <span>Private Schools</span>
    </a>
    <a href="/schools/international" class="sidebar-item-link <?= $isActive('schools/international') ?>">
        <i class="fa fa-globe"></i> <span>International Schools</span>
    </a>

    <div style="margin-top: auto; padding-top: 1rem; border-top: 1px solid #1e293b;">
        <a href="/logout" class="sidebar-item-link" style="color: #f87171 !important;">
            <i class="fa fa-sign-out-alt"></i> <span>Sign Out</span>
        </a>
    </div>
</aside>