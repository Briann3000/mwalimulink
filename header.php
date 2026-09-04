<?php
if (function_exists('init_session')) {
    init_session();
} elseif (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Mwalimu.info connects teachers and schools across Kenya, offering job opportunities, educational resources, and a professional networking platform for educators.">
<meta name="keywords" content="teachers, schools, Kenya, teaching jobs, education, mwalimu, teacher resources, school vacancies, educators, employment">
<meta name="author" content="Mwalimu.info">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="index, follow">
  <title>Mwalimu Link&trade;</title>

  <!-- Favicon & Icons -->
  <link rel="icon" href="favicon.ico" type="image/x-icon">

  <!-- Pico CSS -->
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    /* Global Clean Layout */
    html, body {
      margin: 0 !important;
      padding: 0 !important;
      min-height: 100% !important;
      background-color: #f8fafc !important;
      color: #1e293b !important;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }

    body {
      display: flex !important;
      flex-direction: column !important;
    }

    /* Main container: expands and scrolls naturally on standalone pages */
    body > main {
      flex: 1 0 auto !important;
      width: 100% !important;
      padding: 0 !important;
      margin: 0 !important;
    }

    /* When a Workspace page is active, lock viewport so inner content-pane scrolls cleanly and hide floating public footer */
    body:has(.workspace-wrapper),
    body.has-workspace {
      height: 100vh !important;
      overflow: hidden !important;
    }

    body:has(.workspace-wrapper) > main,
    body.has-workspace > main {
      height: calc(100vh - 60px) !important;
      overflow: hidden !important;
      flex: 1 1 auto !important;
    }

    body:has(.workspace-wrapper) .site-footer,
    body.has-workspace .site-footer,
    body:has(.workspace-wrapper) .affiliates-bottom-bar,
    body.has-workspace .affiliates-bottom-bar {
      display: none !important;
    }

    /* Force Pico card elements to always render clean pure white */
    article, .card, article.card, dialog article {
      background: #ffffff !important;
      color: #1e293b !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 10px !important;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
      margin-bottom: 1.5rem;
    }

    article.card header, .card header {
      background: transparent !important;
      border-bottom: 1px solid #f1f5f9 !important;
      padding-bottom: 0.75rem !important;
      margin-bottom: 1rem !important;
    }

    h1, h2, h3, h4, h5, h6 {
      font-family: 'Inter', sans-serif !important;
      font-weight: 700 !important;
      color: #0f172a !important;
    }

    p, span, li, td, th, label {
      color: #334155;
    }

    a {
      color: #0f766e;
      text-decoration: none;
      transition: color 0.15s ease;
    }

    a:hover {
      color: #115e59;
    }

    /* App Header Navbar (#0f172a / Slate 900 with High-Contrast White Text) */
    .app-topbar {
      background: #0f172a !important;
      border-bottom: 2px solid #0f766e !important;
      position: sticky;
      top: 0;
      z-index: 1000;
      color: #ffffff !important;
      height: 60px;
    }

    .topbar-container {
      max-width: 100%;
      height: 100%;
      margin: 0 auto;
      padding: 0 1rem 0 0.75rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .topbar-left {
      display: flex;
      align-items: center;
      gap: 12px;
      flex-shrink: 0;
    }

    /* Sidebar Toggle Button in Topbar */
    .sidebar-toggle-btn {
      background: #1e293b !important;
      border: 1px solid #475569 !important;
      color: #ffffff !important;
      width: 38px;
      height: 38px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.1rem;
      transition: all 0.2s ease;
    }

    .sidebar-toggle-btn:hover {
      background: #0f766e !important;
      color: #ffffff !important;
      border-color: #0f766e !important;
    }

    /* ==========================================================================
       TOPBAR NAVIGATION - 100% PURE WHITE TEXT & ICONS
       ========================================================================== */
    .topbar-nav {
      display: flex !important;
      align-items: center !important;
      gap: 0.4rem !important;
      margin: 0 !important;
      padding: 0 !important;
      list-style: none !important;
      list-style-type: none !important;
      flex-wrap: nowrap !important;
      flex-shrink: 0 !important;
    }

    .topbar-nav li {
      list-style: none !important;
      list-style-type: none !important;
      margin: 0 !important;
      padding: 0 !important;
      flex-shrink: 0 !important;
      white-space: nowrap !important;
    }

    .topbar-nav li::before, .topbar-nav li::marker {
      display: none !important;
      content: none !important;
    }

    /* Target all links inside topbar-nav with maximum specificity */
    .app-topbar .topbar-nav li a,
    .app-topbar .topbar-nav li a:link,
    .app-topbar .topbar-nav li a:visited,
    .app-topbar .topbar-nav li a span,
    .app-topbar .topbar-nav a {
      color: #ffffff !important;
      font-size: 0.86rem !important;
      font-weight: 600 !important;
      padding: 7px 12px !important;
      border-radius: 6px !important;
      display: inline-flex !important;
      align-items: center !important;
      gap: 6px !important;
      text-decoration: none !important;
      white-space: nowrap !important;
      flex-shrink: 0 !important;
      background: transparent !important;
      transition: all 0.15s ease !important;
      opacity: 0.95 !important;
    }

    .app-topbar .topbar-nav li a:hover,
    .app-topbar .topbar-nav a:hover {
      color: #ffffff !important;
      background: #1e293b !important;
      opacity: 1 !important;
    }

    .app-topbar .topbar-nav li a i,
    .app-topbar .topbar-nav a i {
      color: #2dd4bf !important;
      font-size: 0.95rem !important;
    }

    .app-topbar .topbar-nav a.active {
      color: #ffffff !important;
      background: #0f766e !important;
      opacity: 1 !important;
    }

    .app-topbar .topbar-nav a.active i {
      color: #ffffff !important;
    }

    /* Top Dropdown */
    .top-dropdown {
      position: relative !important;
      list-style: none !important;
    }

    .top-dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      right: 0;
      background: #0f172a !important;
      border: 1px solid #334155 !important;
      border-radius: 8px !important;
      box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
      min-width: 200px !important;
      z-index: 1000 !important;
      padding: 6px 0 !important;
    }

    .top-dropdown-menu a,
    .top-dropdown-menu a:link,
    .top-dropdown-menu a:visited {
      display: flex !important;
      align-items: center !important;
      gap: 8px !important;
      padding: 9px 16px !important;
      color: #f1f5f9 !important;
      font-size: 0.84rem !important;
      text-decoration: none !important;
      background: transparent !important;
    }

    .top-dropdown-menu a:hover {
      background: #1e293b !important;
      color: #2dd4bf !important;
    }

    .top-dropdown:hover .top-dropdown-menu {
      display: block !important;
    }

    /* Lock Workspace ALWAYS Side-by-Side (No vertical stacking above content) */
    .workspace-wrapper {
      display: flex !important;
      flex-direction: row !important;
      height: 100% !important;
      width: 100% !important;
      overflow: hidden !important;
      position: relative !important;
    }

    /* ==========================================================================
       SIDEBAR PANE - 100% CRISP PURE WHITE TEXT & ICONS
       ========================================================================== */
    .sidebar-pane {
      width: 260px !important;
      min-width: 260px !important;
      max-width: 260px !important;
      background: #0f172a !important;
      border-right: 1px solid #1e293b !important;
      flex-shrink: 0 !important;
      height: 100% !important;
      overflow-y: auto !important;
      overflow-x: hidden !important;
      padding: 1.25rem 0.75rem !important;
      display: flex !important;
      flex-direction: column !important;
      gap: 4px !important;
      transition: width 0.25s cubic-bezier(0.4, 0, 0.2, 1), min-width 0.25s ease, max-width 0.25s ease, padding 0.25s ease, opacity 0.2s ease, transform 0.25s ease !important;
      scrollbar-width: thin !important;
      scrollbar-color: #334155 transparent !important;
      z-index: 1050 !important;
    }

    .sidebar-pane::-webkit-scrollbar {
      width: 5px;
    }
    .sidebar-pane::-webkit-scrollbar-thumb {
      background: #334155;
      border-radius: 4px;
    }

    /* FULLY COLLAPSED (Closed) State in Workspace: 0px width */
    .workspace-wrapper.sidebar-collapsed .sidebar-pane {
      width: 0 !important;
      min-width: 0 !important;
      max-width: 0 !important;
      padding-left: 0 !important;
      padding-right: 0 !important;
      overflow: hidden !important;
      border-right: none !important;
      opacity: 0 !important;
      pointer-events: none !important;
    }

    /* Off-Canvas Slideout Drawer for Non-Workspace Pages */
    .sidebar-drawer-overlay {
      position: fixed;
      top: 60px;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.6);
      backdrop-filter: blur(2px);
      z-index: 1040;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.2s ease, visibility 0.2s ease;
    }

    .sidebar-drawer-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    .sidebar-offcanvas-pane {
      position: fixed;
      top: 60px;
      left: 0;
      bottom: 0;
      width: 260px;
      background: #0f172a;
      box-shadow: 4px 0 20px rgba(0, 0, 0, 0.3);
      z-index: 1050;
      transform: translateX(-100%);
      transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      overflow-y: auto;
      padding: 0;
      display: flex;
      flex-direction: column;
    }

    .sidebar-offcanvas-pane.active {
      transform: translateX(0);
    }

    .sidebar-offcanvas-pane .sidebar-pane {
      width: 100% !important;
      min-width: 100% !important;
      max-width: 100% !important;
      border-right: none !important;
      height: 100% !important;
    }

    .sidebar-section-title {
      font-size: 0.7rem !important;
      font-weight: 800 !important;
      text-transform: uppercase !important;
      letter-spacing: 0.08em !important;
      color: #94a3b8 !important;
      padding: 14px 10px 6px !important;
      white-space: nowrap !important;
    }

    /* Maximum CSS Specificity for Sidebar Links & Spans */
    .sidebar-pane a.sidebar-item-link,
    .sidebar-pane a.sidebar-item-link:link,
    .sidebar-pane a.sidebar-item-link:visited,
    .sidebar-pane a.sidebar-item-link span {
      display: flex !important;
      align-items: center !important;
      font-size: 0.86rem !important;
      font-weight: 600 !important;
      color: #ffffff !important;
      text-decoration: none !important;
      white-space: nowrap !important;
      opacity: 0.9 !important;
    }

    .sidebar-pane a.sidebar-item-link {
      gap: 10px !important;
      padding: 9px 12px !important;
      border-radius: 6px !important;
      transition: all 0.15s ease !important;
      background: transparent !important;
    }

    .sidebar-pane a.sidebar-item-link:hover {
      background: #1e293b !important;
      opacity: 1 !important;
    }

    .sidebar-pane a.sidebar-item-link:hover span {
      color: #ffffff !important;
      opacity: 1 !important;
    }

    /* Active State: Bright Pure White on Forest Emerald Background */
    .sidebar-pane a.sidebar-item-link.active {
      background: #0f766e !important;
      border-left: 3px solid #2dd4bf !important;
      opacity: 1 !important;
    }

    .sidebar-pane a.sidebar-item-link.active span {
      color: #ffffff !important;
      font-weight: 700 !important;
      opacity: 1 !important;
    }

    .sidebar-pane a.sidebar-item-link i {
      width: 20px !important;
      text-align: center !important;
      color: #2dd4bf !important;
      font-size: 0.95rem !important;
      flex-shrink: 0 !important;
    }

    .sidebar-pane a.sidebar-item-link.active i {
      color: #ffffff !important;
    }

    /* Independent Content Pane with its own scrollbar (Only scrollbar on page) */
    .content-pane {
      flex: 1 1 auto !important;
      min-width: 0 !important;
      height: 100% !important;
      overflow-y: auto !important;
      overflow-x: hidden !important;
      background: #f8fafc !important;
      padding: 2rem 2.5rem !important;
      scrollbar-width: thin !important;
      scrollbar-color: #cbd5e1 transparent !important;
    }

    .content-pane::-webkit-scrollbar {
      width: 6px;
    }
    .content-pane::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 4px;
    }

    /* Quick Action Tiles Card Styling */
    .quick-action-tile {
      background: #ffffff !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 10px !important;
      padding: 1.5rem 1rem !important;
      display: flex !important;
      flex-direction: column !important;
      align-items: center !important;
      justify-content: center !important;
      text-decoration: none !important;
      transition: all 0.2s ease !important;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
    }

    .quick-action-tile:hover {
      border-color: #0f766e !important;
      box-shadow: 0 4px 14px rgba(15, 118, 110, 0.12) !important;
      transform: translateY(-3px) !important;
    }

    .quick-action-icon {
      width: 48px !important;
      height: 48px !important;
      border-radius: 50% !important;
      background: #f1f5f9 !important;
      color: #0f766e !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      font-size: 1.3rem !important;
      margin-bottom: 0.75rem !important;
      transition: all 0.2s ease !important;
    }

    .quick-action-tile:hover .quick-action-icon {
      background: #0f766e !important;
      color: #ffffff !important;
    }

    /* Metric Cards Styling */
    .metric-card {
      background: #ffffff !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 10px !important;
      box-shadow: 0 1px 3px rgba(0,0,0,0.03) !important;
      padding: 1.25rem 1.5rem !important;
      transition: all 0.2s ease !important;
    }

    .metric-card:hover {
      transform: translateY(-2px) !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.06) !important;
    }

    /* Directory Table Uniformity - Clean High Contrast White Tables */
    .mwalimu-table-card {
      background: #ffffff !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 10px !important;
      box-shadow: 0 1px 4px rgba(0,0,0,0.03) !important;
      overflow: hidden !important;
      margin-bottom: 1.5rem !important;
    }

    .mwalimu-table {
      width: 100% !important;
      border-collapse: collapse !important;
      font-size: 0.88rem !important;
      text-align: left !important;
      margin: 0 !important;
    }

    .mwalimu-table thead tr {
      background: #f8fafc !important;
      border-bottom: 1px solid #e2e8f0 !important;
    }

    .mwalimu-table th {
      padding: 14px 16px !important;
      font-weight: 700 !important;
      color: #475569 !important;
      font-size: 0.82rem !important;
      text-transform: uppercase !important;
      letter-spacing: 0.04em !important;
      background: #f8fafc !important;
    }

    .mwalimu-table td {
      padding: 14px 16px !important;
      border-bottom: 1px solid #f1f5f9 !important;
      color: #334155 !important;
      background: #ffffff !important;
    }

    .mwalimu-table tr:hover td {
      background: #f8fafc !important;
    }

    /* Form and Button Uniformity - Fully Neutralize Pico Auto-Styling */
    input, select, textarea {
      font-family: inherit !important;
      font-size: 0.88rem !important;
    }

    input:not([type="checkbox"]):not([type="radio"]):not([type="submit"]):not([type="button"]),
    select,
    textarea {
      background: #ffffff !important;
      background-color: #ffffff !important;
      color: #0f172a !important;
      border: 1px solid #cbd5e1 !important;
      border-radius: 6px !important;
      padding: 8px 12px !important;
      box-shadow: none !important;
      color-scheme: light !important;
    }

    input[type="date"], input[type="time"], input[type="datetime-local"] {
      color-scheme: light !important;
      background: #ffffff !important;
      color: #0f172a !important;
    }

    input:focus, select:focus, textarea:focus {
      border-color: #0f766e !important;
      outline: none !important;
      box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.15) !important;
    }

    button.primary, a.button.primary, .btn-primary {
      background: #0f766e !important;
      border-color: #0f766e !important;
      color: #ffffff !important;
      font-weight: 600 !important;
      border-radius: 6px !important;
      padding: 8px 16px !important;
      cursor: pointer;
      text-decoration: none !important;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }

    button.primary:hover, a.button.primary:hover, .btn-primary:hover {
      background: #115e59 !important;
    }

    @media (max-width: 768px) {
      .content-pane {
        padding: 1.25rem 1rem !important;
      }
    }
  </style>
  <script>
    // Auto-unregister stale service worker that was intercepting localhost fetch requests
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.getRegistrations().then(function(registrations) {
        for (let registration of registrations) {
          registration.unregister();
        }
      });
    }
  </script>
</head>
<body>
  <?php $authUser = auth_user(); ?>
  <header class="app-topbar">
    <div class="topbar-container">
      <div class="topbar-left">
        <?php if ($authUser): ?>
          <!-- Clean Sidebar Toggle Button positioned cleanly before the logo -->
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar" onclick="toggleMwalimuSidebar()">
            <i class="fa-solid fa-bars-staggered" id="sidebarToggleIcon"></i>
          </button>
        <?php endif; ?>

        <a href="/" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
          <img src="/mwalimu-link-logo-clean.png" alt="MwalimuLink" style="max-height: 38px; filter: brightness(1.15);">
        </a>
      </div>

      <!-- Top Navigation -->
      <ul class="topbar-nav">
        <?php if ($authUser): ?>
          <!-- Logged In Menu: Show role badge and dashboard tools -->
          <?php $dashRoute = ($authUser['role'] === 'school') ? '/school/dashboard' : (($authUser['role'] === 'admin') ? '/admin/dashboard' : '/teacher/dashboard'); ?>
          
          <li>
            <a href="<?= $dashRoute ?>">
              <i class="fa fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
          </li>

          <?php if ($authUser['role'] === 'teacher'): ?>
            <li><a href="/teacher/jobs"><i class="fa fa-briefcase"></i> <span>Jobs</span></a></li>
            <li><a href="/tp-hub"><i class="fa fa-graduation-cap"></i> <span>TP Hub</span></a></li>
          <?php elseif ($authUser['role'] === 'school'): ?>
            <li><a href="/school/staff"><i class="fa fa-users"></i> <span>Faculty</span></a></li>
            <li><a href="/school/search-candidates"><i class="fa fa-search"></i> <span>Candidates</span></a></li>
          <?php endif; ?>

          <!-- Resources Dropdown in Header -->
          <li class="top-dropdown">
            <a href="#"><i class="fa fa-book-open"></i> Resources <i class="fa fa-chevron-down" style="font-size: 10px; margin-left: 2px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/faqs"><i class="fa fa-question-circle"></i> Educator FAQs</a>
              <a href="/faqs-overseas"><i class="fa fa-plane"></i> Teaching Overseas</a>
              <a href="/blog"><i class="fa fa-newspaper"></i> Articles & Guides</a>
              <a href="/contacts"><i class="fa fa-envelope"></i> Support & Contacts</a>
            </div>
          </li>

          <!-- Account Dropdown -->
          <li class="top-dropdown">
            <a href="#" style="background: #1e293b; color: #ffffff !important; border: 1px solid #334155; border-radius: 6px;">
              <i class="fa fa-user-circle" style="color: #2dd4bf;"></i> <?= h($authUser['name']) ?> <i class="fa fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
            </a>
            <div class="top-dropdown-menu">
              <a href="<?= $dashRoute ?>"><i class="fa fa-home"></i> Portal Home</a>
              <?php if ($authUser['role'] === 'teacher'): ?>
                <a href="/teacher/update"><i class="fa fa-user-edit"></i> Edit Profile</a>
              <?php elseif ($authUser['role'] === 'school'): ?>
                <a href="/school/subscribe"><i class="fa fa-credit-card"></i> Billing</a>
              <?php endif; ?>
              <div style="border-top: 1px solid #334155; margin: 4px 0;"></div>
              <a href="/logout" style="color: #f87171 !important;"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
          </li>

        <?php else: ?>
          <!-- Guest / Public Menu -->
          <li><a href="/"><i class="fa fa-home"></i> Home</a></li>
          <li><a href="/about"><i class="fa fa-info-circle"></i> About</a></li>
          
          <li class="top-dropdown">
            <a href="#"><i class="fa fa-school"></i> Schools <i class="fa fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/schools/public">Public Schools</a>
              <a href="/schools/private">Private Schools</a>
              <a href="/schools/international">International Schools</a>
              <a href="/register/school">Register School</a>
              <a href="/login/school">School Login</a>
            </div>
          </li>

          <li class="top-dropdown">
            <a href="#"><i class="fa fa-graduation-cap"></i> Teachers <i class="fa fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/register/teacher">Register Free</a>
              <a href="/login/teacher">Teacher Login</a>
            </div>
          </li>

          <li class="top-dropdown">
            <a href="#"><i class="fa fa-book"></i> Resources <i class="fa fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/faqs">Educator FAQs</a>
              <a href="/faqs-overseas">Teaching Overseas</a>
              <a href="/blog">Articles & Guides</a>
              <a href="/contacts">Contacts</a>
            </div>
          </li>

          <li>
            <a href="/login" style="background: #0f766e; color: white !important; font-weight: 700; border-radius: 6px;">
              <i class="fa fa-sign-in-alt"></i> Login
            </a>
          </li>
        <?php endif; ?>
      </ul>
    </div>
  </header>

  <?php if ($authUser): ?>
    <!-- Off-Canvas Sidebar Drawer for Standalone / Non-Workspace Pages -->
    <div id="mwalimuDrawerOverlay" class="sidebar-drawer-overlay" onclick="closeMwalimuDrawer()"></div>
    <div id="mwalimuOffcanvasDrawer" class="sidebar-offcanvas-pane">
      <?php include __DIR__ . '/views/partials/sidebar.php'; ?>
    </div>
  <?php endif; ?>

  <script>
    // Universal Sidebar & Workspace Layout Initialization
    function initSidebarState() {
      const wrapper = document.querySelector('.workspace-wrapper');
      if (wrapper) {
        document.body.classList.add('has-workspace');
        const isCollapsed = localStorage.getItem('mwalimu_sidebar_collapsed') === 'true';
        if (isCollapsed) {
          wrapper.classList.add('sidebar-collapsed');
        }
      }
    }

    // Unified toggle function that works on BOTH workspace pages & standalone pages
    function toggleMwalimuSidebar() {
      const wrapper = document.querySelector('.workspace-wrapper');
      if (wrapper) {
        // We are on a Workspace page: toggle in-page column collapse
        const willCollapse = !wrapper.classList.contains('sidebar-collapsed');
        wrapper.classList.toggle('sidebar-collapsed', willCollapse);
        localStorage.setItem('mwalimu_sidebar_collapsed', willCollapse);
      } else {
        // We are on a Standalone page: toggle off-canvas slideout drawer
        const drawer = document.getElementById('mwalimuOffcanvasDrawer');
        const overlay = document.getElementById('mwalimuDrawerOverlay');
        if (drawer && overlay) {
          const isActive = drawer.classList.contains('active');
          if (isActive) {
            closeMwalimuDrawer();
          } else {
            drawer.classList.add('active');
            overlay.classList.add('active');
          }
        }
      }
    }

    function closeMwalimuDrawer() {
      const drawer = document.getElementById('mwalimuOffcanvasDrawer');
      const overlay = document.getElementById('mwalimuDrawerOverlay');
      if (drawer) drawer.classList.remove('active');
      if (overlay) overlay.classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', initSidebarState);
  </script>

  <main style="padding: 0px; margin: 0px; width:100%;">