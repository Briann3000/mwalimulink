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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
  <meta name="description" content="Mwalimu.info connects teachers and schools across Kenya, offering job opportunities, educational resources, and a professional networking platform for educators.">
<meta name="keywords" content="teachers, schools, Kenya, teaching jobs, education, mwalimu, teacher resources, school vacancies, educators, employment">
<meta name="author" content="Mwalimu.info">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="robots" content="index, follow">
  <title>Mwalimu Link&trade;</title>

  <!-- Favicon & Icons -->
  <link rel="icon" href="/favicon.ico" type="image/x-icon">

  <!-- Pico CSS -->
  <link rel="stylesheet" href="https://unpkg.com/@picocss/pico@latest/css/pico.min.css">

  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" />

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">

  <style>
    /* ==========================================================================
       Universal Typography & Contrast Reset (Neutralizes Pico CSS on dark banners)
       ========================================================================== */
    .hero,
    .hero-content,
    .pub-hero,
    .about-hero,
    .contact-hero,
    .faq-hero,
    .overseas-hero,
    .legal-hero,
    .landing-cta-banner,
    .faq-support-banner,
    .cta-inner-card {
      color: #ffffff;
    }

    .hero h1, .hero h2, .hero h3, .hero h4, .hero h5, .hero h6,
    .hero-content h1, .hero-content h2, .hero-content h3, .hero-content h4,
    .pub-hero h1, .pub-hero h2, .pub-hero h3, .pub-hero h4,
    .about-hero h1, .about-hero h2, .about-hero h3, .about-hero h4,
    .contact-hero h1, .contact-hero h2, .contact-hero h3, .contact-hero h4,
    .faq-hero h1, .faq-hero h2, .faq-hero h3, .faq-hero h4,
    .overseas-hero h1, .overseas-hero h2, .overseas-hero h3, .overseas-hero h4,
    .legal-hero h1, .legal-hero h2, .legal-hero h3, .legal-hero h4,
    .landing-cta-banner h1, .landing-cta-banner h2, .landing-cta-banner h3, .landing-cta-banner h4,
    .faq-support-banner h1, .faq-support-banner h2, .faq-support-banner h3, .faq-support-banner h4,
    .cta-inner-card h1, .cta-inner-card h2, .cta-inner-card h3, .cta-inner-card h4,
    .banner-left h1, .banner-left h2, .banner-left h3, .banner-left h4 {
      color: #ffffff !important;
    }

    /* Global Clean Layout */
    :root {
      font-size: 14px !important;
    }

    html, body {
      margin: 0 !important;
      padding: 0 !important;
      min-height: 100% !important;
      background-color: #f8fafc !important;
      color: #1e293b !important;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
      font-size: 14px !important;
      -webkit-font-smoothing: antialiased;
    }

    body {
      display: flex !important;
      flex-direction: column !important;
      overflow-x: hidden !important;
      width: 100% !important;
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
      gap: 10px;
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
      flex-shrink: 0;
    }

    .sidebar-toggle-btn:hover {
      background: #0f766e !important;
      color: #ffffff !important;
      border-color: #0f766e !important;
    }

    /* Public Hamburger Menu Button (Visible on < 992px) */
    .public-mobile-toggle {
      display: none;
      background: #1e293b;
      border: 1px solid #475569;
      color: #ffffff;
      width: 38px;
      height: 38px;
      border-radius: 8px;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      font-size: 1.15rem;
      transition: all 0.2s ease;
    }

    .public-mobile-toggle:hover {
      background: #0f766e;
      border-color: #0f766e;
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

    /* Top Dropdown - Strict Downward Vertical Stacking */
    .top-dropdown {
      position: relative !important;
      list-style: none !important;
    }

    .app-topbar .top-dropdown-menu,
    .top-dropdown-menu {
      display: none;
      position: absolute;
      top: 100%;
      left: 0;
      background: #0f172a !important;
      border: 1px solid #334155 !important;
      border-radius: 8px !important;
      box-shadow: 0 12px 30px rgba(0,0,0,0.6) !important;
      min-width: 220px !important;
      width: max-content !important;
      z-index: 1050 !important;
      padding: 6px 0 !important;
      flex-direction: column !important;
      margin-top: 0 !important;
    }

    /* Invisible Hover Bridge: Prevents dropdown dismissal when moving cursor across gap */
    .app-topbar .top-dropdown-menu::before,
    .top-dropdown-menu::before {
      content: "" !important;
      position: absolute !important;
      top: -12px !important;
      left: 0 !important;
      right: 0 !important;
      height: 14px !important;
      background: transparent !important;
      z-index: 1051 !important;
    }

    .top-dropdown.account-menu-item .top-dropdown-menu {
      right: 0 !important;
      left: auto !important;
    }

    .app-topbar .top-dropdown:hover > .top-dropdown-menu,
    .top-dropdown:hover > .top-dropdown-menu,
    .app-topbar .top-dropdown.open > .top-dropdown-menu,
    .top-dropdown.open > .top-dropdown-menu {
      display: flex !important;
      flex-direction: column !important;
    }

    .app-topbar .top-dropdown-menu a,
    .app-topbar .top-dropdown-menu a:link,
    .app-topbar .top-dropdown-menu a:visited,
    .top-dropdown-menu a,
    .top-dropdown-menu a:link,
    .top-dropdown-menu a:visited {
      display: flex !important;
      flex-direction: row !important;
      align-items: center !important;
      width: 100% !important;
      box-sizing: border-box !important;
      white-space: nowrap !important;
      gap: 10px !important;
      padding: 10px 16px !important;
      color: #f1f5f9 !important;
      font-size: 0.84rem !important;
      font-weight: 500 !important;
      text-decoration: none !important;
      background: transparent !important;
      opacity: 1 !important;
      transition: background 0.15s ease, color 0.15s ease !important;
    }

    .app-topbar .top-dropdown-menu a:hover,
    .top-dropdown-menu a:hover {
      background: #1e293b !important;
      color: #2dd4bf !important;
    }

    /* Lock Workspace Side-by-Side on Desktop */
    .workspace-wrapper {
      display: flex !important;
      flex-direction: row !important;
      height: 100% !important;
      width: 100% !important;
      overflow: hidden !important;
      position: relative !important;
    }

    /* ==========================================================================
       SIDEBAR PANE - CRISP PURE WHITE TEXT & ICONS
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
      transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), width 0.25s ease, min-width 0.25s ease, padding 0.25s ease, opacity 0.2s ease !important;
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

    /* FULLY COLLAPSED State on Desktop */
    @media (min-width: 901px) {
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
    }

    /* Universal Drawer Overlay Backdrop */
    .sidebar-drawer-overlay,
    .mobile-nav-overlay {
      position: fixed;
      top: 60px;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(15, 23, 42, 0.65);
      backdrop-filter: blur(3px);
      z-index: 1040;
      opacity: 0;
      visibility: hidden;
      transition: opacity 0.25s ease, visibility 0.25s ease;
    }

    .sidebar-drawer-overlay.active,
    .mobile-nav-overlay.active {
      opacity: 1;
      visibility: visible;
    }

    /* Off-Canvas Slideout Drawer for Non-Workspace Standalone Pages */
    .sidebar-offcanvas-pane {
      position: fixed;
      top: 60px;
      left: 0;
      bottom: 0;
      width: 270px;
      max-width: 85vw;
      background: #0f172a;
      box-shadow: 4px 0 25px rgba(0, 0, 0, 0.4);
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

    /* Mobile Slide-Out Drawer for Public / Guest Navigation */
    .public-mobile-drawer {
      position: fixed;
      top: 60px;
      right: 0;
      bottom: 0;
      width: 300px;
      max-width: 88vw;
      background: #0f172a;
      box-shadow: -4px 0 25px rgba(0, 0, 0, 0.4);
      z-index: 1050;
      transform: translateX(100%);
      transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1);
      overflow-y: auto;
      padding: 1.5rem 1rem;
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .public-mobile-drawer.active {
      transform: translateX(0);
    }

    .public-drawer-section {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .public-drawer-title {
      font-size: 0.72rem;
      font-weight: 800;
      text-transform: uppercase;
      letter-spacing: 0.08em;
      color: #94a3b8;
      padding: 4px 8px;
    }

    .public-drawer-link {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 9px 12px;
      color: #ffffff !important;
      font-size: 0.9rem;
      font-weight: 600;
      border-radius: 6px;
      text-decoration: none;
      transition: background 0.15s ease;
    }

    .public-drawer-link:hover {
      background: #1e293b;
      color: #2dd4bf !important;
    }

    .public-drawer-link i {
      color: #2dd4bf;
      width: 20px;
      text-align: center;
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

    /* Content Pane */
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

    /* Quick Action Tiles */
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

    /* Metric Cards */
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

    /* Universal Responsive Tables */
    .mwalimu-table-card {
      background: #ffffff !important;
      border: 1px solid #e2e8f0 !important;
      border-radius: 10px !important;
      box-shadow: 0 1px 4px rgba(0,0,0,0.03) !important;
      overflow-x: auto !important;
      -webkit-overflow-scrolling: touch !important;
      margin-bottom: 1.5rem !important;
      width: 100% !important;
    }

    .mwalimu-table {
      width: 100% !important;
      min-width: 580px;
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
      white-space: nowrap !important;
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

    /* Forms */
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

    /* ==========================================================================
       UNIVERSAL BUTTON & LINK LEVELING & HIGH-CONTRAST SYSTEM
       Neutralizes Pico CSS button anomalies, width stretches, and text disappearing
       ========================================================================== */
    button,
    input[type="submit"],
    input[type="button"],
    input[type="reset"],
    a[role="button"],
    .btn-primary,
    button.primary,
    a.button.primary,
    a.btn-primary {
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      vertical-align: middle !important;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
      font-size: 0.88rem !important;
      font-weight: 600 !important;
      line-height: 1.4 !important;
      height: auto !important;
      min-height: 38px !important;
      width: auto !important;
      max-width: 100% !important;
      margin: 0 !important;
      margin-bottom: 0 !important;
      padding: 8px 18px !important;
      border-radius: 6px !important;
      border: 1px solid transparent !important;
      cursor: pointer !important;
      text-decoration: none !important;
      white-space: nowrap !important;
      box-sizing: border-box !important;
      transition: background-color 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease, transform 0.1s ease !important;
      gap: 6px !important;
      text-align: center !important;
      text-shadow: none !important;
    }

    /* Primary Teal Buttons - Guaranteed High Contrast Pure White Text */
    button.primary,
    button:not(.secondary):not(.outline):not([class*="btn-landing"]):not([class*="cat-pill"]):not([class*="toggle"]):not([class*="close"]):not([class*="filter"]):not([class*="clear"]):not([class*="share"]):not([class*="faq"]),
    input[type="submit"],
    .btn-primary,
    a.btn-primary,
    a.button.primary,
    a[role="button"]:not(.secondary):not(.outline),
    .btn-submit-contact,
    .btn-confirm-submit,
    .btn-submit-paper,
    .btn-reader-download,
    .btn-pdf-download-sm,
    .btn-fallback-open,
    .btn-cta-primary {
      background-color: #0f766e !important;
      border-color: #0f766e !important;
      color: #ffffff !important;
      box-shadow: 0 1px 3px rgba(15, 118, 110, 0.2) !important;
    }

    button.primary:hover,
    button:not(.secondary):not(.outline):not([class*="btn-landing"]):not([class*="cat-pill"]):not([class*="toggle"]):not([class*="close"]):not([class*="filter"]):not([class*="clear"]):not([class*="share"]):not([class*="faq"]):hover,
    input[type="submit"]:hover,
    .btn-primary:hover,
    a.btn-primary:hover,
    a.button.primary:hover,
    a[role="button"]:not(.secondary):not(.outline):hover,
    .btn-submit-contact:hover,
    .btn-confirm-submit:hover,
    .btn-submit-paper:hover,
    .btn-reader-download:hover,
    .btn-pdf-download-sm:hover,
    .btn-fallback-open:hover,
    .btn-cta-primary:hover {
      background-color: #115e59 !important;
      border-color: #115e59 !important;
      color: #ffffff !important;
      box-shadow: 0 3px 8px rgba(15, 118, 110, 0.3) !important;
      transform: translateY(-1px) !important;
    }

    /* Active / Focus States */
    button.primary:active,
    .btn-primary:active,
    input[type="submit"]:active,
    a.btn-primary:active {
      background-color: #134e4a !important;
      border-color: #134e4a !important;
      color: #ffffff !important;
      transform: translateY(0) !important;
    }

    /* Ensure Child Icons and Spans Inside Primary Buttons Never Turn Dark */
    button.primary *,
    .btn-primary *,
    a.btn-primary *,
    a.button.primary *,
    .btn-submit-contact *,
    .btn-confirm-submit *,
    .btn-reader-download *,
    .btn-cta-primary * {
      color: #ffffff !important;
      vertical-align: middle !important;
    }

    /* Secondary / Outline / Ghost Buttons */
    button.secondary,
    .btn-secondary,
    a.btn-secondary,
    .btn-outline,
    a.btn-outline {
      background-color: #ffffff !important;
      border: 1px solid #cbd5e1 !important;
      color: #334155 !important;
      box-shadow: 0 1px 2px rgba(0,0,0,0.03) !important;
    }

    button.secondary:hover,
    .btn-secondary:hover,
    a.btn-secondary:hover,
    .btn-outline:hover,
    a.btn-outline:hover {
      background-color: #f8fafc !important;
      border-color: #0f766e !important;
      color: #0f766e !important;
      transform: translateY(-1px) !important;
    }

    /* White Landing CTA Buttons */
    .btn-landing-white,
    .btn-cta-white {
      background-color: #ffffff !important;
      color: #0f766e !important;
      border-color: #ffffff !important;
      font-weight: 700 !important;
    }

    .btn-landing-white:hover,
    .btn-cta-white:hover {
      background-color: #f0fdfa !important;
      color: #115e59 !important;
      border-color: #f0fdfa !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
    }

    /* Full-width utility when explicitly needed */
    .btn-block,
    .btn-full-width {
      width: 100% !important;
      display: flex !important;
    }

    /* ==========================================================================
       MOBILE RESPONSIVENESS OVERRIDES (< 900px)
       ========================================================================== */
    @media (max-width: 900px) {
      /* Show hamburger toggle for public visitors */
      .public-mobile-toggle {
        display: flex !important;
      }

      /* Hide inline topbar nav on mobile */
      .topbar-nav.public-nav-desktop {
        display: none !important;
      }

      /* For logged in users on mobile, hide secondary navigation links in topbar */
      .topbar-nav.auth-nav li:not(.account-menu-item) {
        display: none !important;
      }

      /* In-Workspace on Mobile: Sidebar becomes off-canvas overlay */
      .workspace-wrapper .sidebar-pane {
        position: fixed !important;
        top: 60px !important;
        left: 0 !important;
        bottom: 0 !important;
        width: 270px !important;
        max-width: 85vw !important;
        z-index: 1050 !important;
        transform: translateX(-100%) !important;
        box-shadow: 4px 0 25px rgba(0,0,0,0.4) !important;
        border-right: none !important;
      }

      .workspace-wrapper.mobile-sidebar-open .sidebar-pane {
        transform: translateX(0) !important;
      }

      .content-pane {
        padding: 1.25rem 1rem !important;
        flex: 1 1 100% !important;
        width: 100% !important;
      }

      /* Modals */
      dialog article, .modal-dialog {
        width: 95vw !important;
        max-width: 95vw !important;
        padding: 1.25rem 1rem !important;
        margin: 1rem auto !important;
      }

      .quick-action-tile {
        padding: 1.15rem 0.75rem !important;
      }

      .quick-action-icon {
        width: 42px !important;
        height: 42px !important;
        font-size: 1.15rem !important;
      }

      .metric-card {
        padding: 1rem 1.15rem !important;
      }
    }

    @media (max-width: 480px) {
      .topbar-container {
        padding: 0 0.75rem 0 0.5rem;
      }
      .content-pane {
        padding: 1rem 0.75rem !important;
      }
    }
  </style>
  <script>
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
          <!-- Universal Sidebar Toggle for Authenticated Users -->
          <button type="button" class="sidebar-toggle-btn" id="sidebarToggleBtn" title="Toggle Sidebar" onclick="toggleMwalimuSidebar()">
            <i class="fa-solid fa-bars-staggered" id="sidebarToggleIcon"></i>
          </button>
        <?php endif; ?>

        <a href="/" style="display: flex; align-items: center; gap: 8px; text-decoration: none;">
          <img src="/mwalimu-link-logo-clean.png" alt="MwalimuLink" style="max-height: 36px; filter: brightness(1.15);">
        </a>
      </div>

      <!-- Top Navigation -->
      <?php if ($authUser): ?>
        <?php $dashRoute = ($authUser['role'] === 'school') ? '/school/dashboard' : (($authUser['role'] === 'admin') ? '/admin/dashboard' : '/teacher/dashboard'); ?>
        <ul class="topbar-nav auth-nav">
          <li>
            <a href="<?= $dashRoute ?>">
              <i class="fa fa-tachometer-alt"></i> <span>Dashboard</span>
            </a>
          </li>

          <li><a href="/"><i class="fa fa-home"></i> <span>Home</span></a></li>

          <?php if ($authUser['role'] === 'teacher'): ?>
            <li><a href="/teacher/jobs"><i class="fa fa-briefcase"></i> <span>Jobs</span></a></li>
          <?php elseif ($authUser['role'] === 'school'): ?>
            <li><a href="/school/staff"><i class="fa fa-users"></i> <span>Faculty</span></a></li>
            <li><a href="/school/search-candidates"><i class="fa fa-search"></i> <span>Candidates</span></a></li>
          <?php endif; ?>

          <li><a href="/publications"><i class="fa fa-file-lines"></i> <span>Publications</span></a></li>

          <!-- Resources Dropdown -->
          <li class="top-dropdown">
            <a href="#"><i class="fa fa-book-open"></i> Resources <i class="fa fa-chevron-down" style="font-size: 10px; margin-left: 2px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/about"><i class="fa fa-info-circle"></i> About MwalimuLink</a>
              <a href="/contacts"><i class="fa fa-envelope"></i> Contact Support</a>
              <a href="/faqs"><i class="fa fa-question-circle"></i> Educator FAQs</a>
              <a href="/faqs-overseas"><i class="fa fa-plane"></i> Teaching Overseas FAQ</a>
              <a href="/privacy"><i class="fa fa-shield-alt"></i> Privacy Policy</a>
              <a href="/terms"><i class="fa fa-file-contract"></i> Terms of Service</a>
            </div>
          </li>

          <!-- Account Dropdown -->
          <li class="top-dropdown account-menu-item">
            <a href="#" style="background: #1e293b; color: #ffffff !important; border: 1px solid #334155; border-radius: 6px;">
              <i class="fa fa-user-circle" style="color: #2dd4bf;"></i> <?= h($authUser['name']) ?> <i class="fa fa-chevron-down" style="font-size: 10px; margin-left: 4px;"></i>
            </a>
            <div class="top-dropdown-menu">
              <a href="<?= $dashRoute ?>"><i class="fa fa-home"></i> Portal Home</a>
              <?php if ($authUser['role'] === 'teacher'): ?>
                <a href="/teacher/update"><i class="fa fa-user-edit"></i> Edit Profile</a>
                <a href="/teacher/cv-builder"><i class="fa fa-file-lines"></i> CV Builder</a>
              <?php elseif ($authUser['role'] === 'school'): ?>
                <a href="/school/subscribe"><i class="fa fa-credit-card"></i> Billing</a>
              <?php endif; ?>
              <div style="border-top: 1px solid #334155; margin: 4px 0;"></div>
              <a href="/logout" style="color: #f87171 !important;"><i class="fa fa-sign-out-alt"></i> Logout</a>
            </div>
          </li>
        </ul>
      <?php else: ?>
        <!-- Public Desktop Nav -->
        <ul class="topbar-nav public-nav-desktop">
          <li><a href="/"><i class="fa fa-home"></i> Home</a></li>
          <li><a href="/about"><i class="fa fa-info-circle"></i> About</a></li>
          <li><a href="/publications"><i class="fa fa-file-lines"></i> Publications</a></li>
          <li><a href="/contacts"><i class="fa fa-envelope"></i> Contact</a></li>
          
          <li class="top-dropdown">
            <a href="#"><i class="fa fa-school"></i> Schools <i class="fa fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/schools/public"><i class="fa fa-landmark"></i> Public Schools</a>
              <a href="/schools/private"><i class="fa fa-building"></i> Private Academies</a>
              <a href="/schools/international"><i class="fa fa-globe"></i> International Schools</a>
              <a href="/register/school"><i class="fa fa-plus-circle"></i> Register Institution</a>
              <a href="/login/school"><i class="fa fa-sign-in-alt"></i> School Login</a>
            </div>
          </li>

          <li class="top-dropdown">
            <a href="#"><i class="fa fa-graduation-cap"></i> Teachers <i class="fa fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/register/teacher"><i class="fa fa-user-plus"></i> Register Free</a>
              <a href="/login/teacher"><i class="fa fa-sign-in-alt"></i> Teacher Login</a>
              <a href="/tp-hub"><i class="fa fa-award"></i> Teaching Practice (TP) Hub</a>
            </div>
          </li>

          <li class="top-dropdown">
            <a href="#"><i class="fa fa-book-open"></i> Resources <i class="fa fa-chevron-down" style="font-size: 10px;"></i></a>
            <div class="top-dropdown-menu">
              <a href="/faqs"><i class="fa fa-question-circle"></i> Educator FAQs</a>
              <a href="/faqs-overseas"><i class="fa fa-plane"></i> Teaching Overseas FAQ</a>
              <a href="/privacy"><i class="fa fa-shield-alt"></i> Privacy Policy</a>
              <a href="/terms"><i class="fa fa-file-contract"></i> Terms of Service</a>
            </div>
          </li>

          <li>
            <a href="/login" style="background: #0f766e; color: white !important; font-weight: 700; border-radius: 6px;">
              <i class="fa fa-sign-in-alt"></i> Login
            </a>
          </li>
        </ul>

        <!-- Public Mobile Hamburger Toggle -->
        <button type="button" class="public-mobile-toggle" onclick="togglePublicMobileNav()" title="Menu">
          <i class="fa-solid fa-bars" id="publicMobileIcon"></i>
        </button>
      <?php endif; ?>
    </div>
  </header>

  <?php if ($authUser): ?>
    <!-- Universal Backdrop Overlay for Workspace Mobile Drawer & Standalone Offcanvas -->
    <div id="mwalimuDrawerOverlay" class="sidebar-drawer-overlay" onclick="closeMwalimuDrawer()"></div>
    
    <!-- Off-Canvas Sidebar Drawer for Standalone Pages -->
    <div id="mwalimuOffcanvasDrawer" class="sidebar-offcanvas-pane">
      <?php include __DIR__ . '/views/partials/sidebar.php'; ?>
    </div>
  <?php else: ?>
    <!-- Public Guest Mobile Drawer & Overlay -->
    <div id="mwalimuPublicOverlay" class="mobile-nav-overlay" onclick="closePublicMobileNav()"></div>
    <div id="mwalimuPublicDrawer" class="public-mobile-drawer">
      <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #1e293b; padding-bottom: 0.75rem;">
        <span style="color: #2dd4bf; font-weight: 800; font-size: 1rem;">MwalimuLink Navigation</span>
        <button type="button" onclick="closePublicMobileNav()" style="background: transparent; border: none; color: #94a3b8; font-size: 1.2rem; cursor: pointer;">
          <i class="fa-solid fa-xmark"></i>
        </button>
      </div>

      <div class="public-drawer-section">
        <a href="/" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-home"></i> Home</a>
        <a href="/about" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-info-circle"></i> About MwalimuLink</a>
        <a href="/publications" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-file-lines"></i> Publications Hub</a>
        <a href="/contacts" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-envelope"></i> Contact Us</a>
      </div>

      <div class="public-drawer-section">
        <div class="public-drawer-title">Schools Directory</div>
        <a href="/schools/public" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-landmark"></i> Public Schools</a>
        <a href="/schools/private" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-building"></i> Private Academies</a>
        <a href="/schools/international" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-globe"></i> International Schools</a>
        <a href="/register/school" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-school"></i> Register Institution</a>
      </div>

      <div class="public-drawer-section">
        <div class="public-drawer-title">Educators Hub</div>
        <a href="/register/teacher" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-user-plus"></i> Register as Teacher (Free)</a>
        <a href="/login/teacher" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-graduation-cap"></i> Teacher Login</a>
        <a href="/tp-hub" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-award"></i> Teaching Practice (TP) Hub</a>
      </div>

      <div class="public-drawer-section">
        <div class="public-drawer-title">Resources & Legal</div>
        <a href="/faqs" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-question-circle"></i> Educator FAQs</a>
        <a href="/faqs-overseas" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-plane"></i> Teaching Overseas FAQ</a>
        <a href="/privacy" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-shield-alt"></i> Privacy Policy</a>
        <a href="/terms" class="public-drawer-link" onclick="closePublicMobileNav()"><i class="fa fa-file-contract"></i> Terms of Service</a>
      </div>

      <div style="border-top: 1px solid #1e293b; padding-top: 1rem; display: flex; flex-direction: column; gap: 8px;">
        <a href="/login" class="btn-primary" style="width: 100%; text-align: center; justify-content: center; padding: 10px; font-weight: 700;">
          <i class="fa fa-sign-in-alt"></i> Member Sign In
        </a>
      </div>
    </div>
  <?php endif; ?>

  <script>
    function initSidebarState() {
      const wrapper = document.querySelector('.workspace-wrapper');
      if (wrapper) {
        document.body.classList.add('has-workspace');
        if (window.innerWidth > 900) {
          const isCollapsed = localStorage.getItem('mwalimu_sidebar_collapsed') === 'true';
          if (isCollapsed) {
            wrapper.classList.add('sidebar-collapsed');
          }
        }
      }
    }

    function toggleMwalimuSidebar() {
      const wrapper = document.querySelector('.workspace-wrapper');
      const overlay = document.getElementById('mwalimuDrawerOverlay');

      if (wrapper) {
        if (window.innerWidth <= 900) {
          // Mobile workspace drawer toggle
          const isOpen = wrapper.classList.toggle('mobile-sidebar-open');
          if (overlay) {
            overlay.classList.toggle('active', isOpen);
          }
        } else {
          // Desktop workspace column collapse
          const willCollapse = !wrapper.classList.contains('sidebar-collapsed');
          wrapper.classList.toggle('sidebar-collapsed', willCollapse);
          localStorage.setItem('mwalimu_sidebar_collapsed', willCollapse);
        }
      } else {
        // Standalone off-canvas drawer
        const drawer = document.getElementById('mwalimuOffcanvasDrawer');
        if (drawer && overlay) {
          const isActive = drawer.classList.toggle('active');
          overlay.classList.toggle('active', isActive);
        }
      }
    }

    function closeMwalimuDrawer() {
      const wrapper = document.querySelector('.workspace-wrapper');
      const overlay = document.getElementById('mwalimuDrawerOverlay');
      const drawer = document.getElementById('mwalimuOffcanvasDrawer');

      if (wrapper) {
        wrapper.classList.remove('mobile-sidebar-open');
      }
      if (drawer) {
        drawer.classList.remove('active');
      }
      if (overlay) {
        overlay.classList.remove('active');
      }
    }

    function togglePublicMobileNav() {
      const drawer = document.getElementById('mwalimuPublicDrawer');
      const overlay = document.getElementById('mwalimuPublicOverlay');
      if (drawer && overlay) {
        const isActive = drawer.classList.toggle('active');
        overlay.classList.toggle('active', isActive);
      }
    }

    function closePublicMobileNav() {
      const drawer = document.getElementById('mwalimuPublicDrawer');
      const overlay = document.getElementById('mwalimuPublicOverlay');
      if (drawer) drawer.classList.remove('active');
      if (overlay) overlay.classList.remove('active');
    }

    document.addEventListener('DOMContentLoaded', function() {
      initSidebarState();

      // Click/Tap toggle for top navigation dropdowns
      document.querySelectorAll('.top-dropdown > a').forEach(function(trigger) {
        trigger.addEventListener('click', function(e) {
          e.preventDefault();
          const parent = trigger.closest('.top-dropdown');
          const isAlreadyOpen = parent.classList.contains('open');
          
          // Close all other dropdowns
          document.querySelectorAll('.top-dropdown.open').forEach(function(d) {
            d.classList.remove('open');
          });

          if (!isAlreadyOpen) {
            parent.classList.add('open');
          }
        });
      });

      // Dismiss open dropdowns when clicking outside
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.top-dropdown')) {
          document.querySelectorAll('.top-dropdown.open').forEach(function(d) {
            d.classList.remove('open');
          });
        }
      });
    });

    window.addEventListener('resize', function() {
      if (window.innerWidth > 900) {
        closeMwalimuDrawer();
        closePublicMobileNav();
      }
    });
  </script>

  <main style="padding: 0px; margin: 0px; width:100%;">