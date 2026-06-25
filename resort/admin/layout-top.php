<?php
// admin/layout-top.php
if (!isset($admin_page))  $admin_page  = '';
if (!isset($admin_title)) $admin_title = 'Admin Panel';

function admin_nav_link($href, $icon, $label, $current_page, $page_key) {
    $active = ($current_page === $page_key) ? ' active' : '';
    echo "<a href=\"$href\" class=\"sidebar-link$active\"><i class=\"fas $icon\"></i> $label</a>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= htmlspecialchars($admin_title) ?> — Subic Resort Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
    <style>
        .admin-badge-dot{display:inline-block;width:8px;height:8px;border-radius:50%;background:var(--ember);margin-left:.4rem;vertical-align:middle}
        .topbar-avatar{width:36px;height:36px;border-radius:50%;background:var(--ochre);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-family:var(--font-display);font-size:.95rem;cursor:pointer;flex-shrink:0}
        .search-box{display:flex;align-items:center;gap:.5rem;background:#F4F6F8;border:1px solid #E0E4E8;border-radius:var(--radius);padding:.45rem .85rem;font-size:.85rem;color:var(--text-mid)}
        .search-box input{background:none;border:none;outline:none;font-size:.85rem;color:var(--text-dark);width:200px}
        .sidebar-user{padding:1rem 1.5rem;border-top:1px solid rgba(255,255,255,.08);display:flex;align-items:center;gap:.75rem}
        .sidebar-user-name{font-size:.85rem;font-weight:600;color:white}
        .sidebar-user-role{font-size:.72rem;color:var(--ochre);text-transform:uppercase;letter-spacing:.06em}
    </style>
</head>
<body>
<div class="admin-wrapper">

<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div style="display:flex;align-items:center;gap:.6rem">
            <div style="width:32px;height:32px;background:var(--ochre);border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-size:.9rem"><i class="fas fa-mountain"></i></div>
            <div>
                <div class="sidebar-brand-name">Subic Resort</div>
                <div class="sidebar-brand-sub">Admin Panel</div>
            </div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <div class="sidebar-section">Main</div>
        <?php admin_nav_link(SITE_URL.'/admin/dashboard.php', 'fa-tachometer-alt', 'Dashboard', $admin_page, 'dashboard'); ?>

        <div class="sidebar-section">Reservations</div>
        <?php admin_nav_link(SITE_URL.'/admin/bookings.php',  'fa-calendar-check', 'Bookings',  $admin_page, 'bookings'); ?>
        <?php admin_nav_link(SITE_URL.'/admin/billing.php',   'fa-file-invoice-dollar', 'Billing', $admin_page, 'billing'); ?>

        <div class="sidebar-section">Property & People</div>
        <?php admin_nav_link(SITE_URL.'/admin/rooms.php','fa-bed',        'Manage Rooms', $admin_page, 'rooms'); ?>
        <?php admin_nav_link(SITE_URL.'/admin/guests.php',     'fa-users',      'Guests',       $admin_page, 'guests'); ?>

        <div class="sidebar-section">Management</div>
        <?php admin_nav_link(SITE_URL.'/admin/reports.php',    'fa-chart-pie',  'Reports',      $admin_page, 'reports'); ?>
        <?php admin_nav_link(SITE_URL.'/admin/settings.php',   'fa-cogs',       'Settings',     $admin_page, 'settings'); ?>
    </nav>

    <div class="sidebar-user">
        <div style="width:32px;height:32px;border-radius:50%;background:var(--ochre);display:flex;align-items:center;justify-content:center;color:white;font-weight:700;font-size:.85rem;flex-shrink:0">
            <?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?>
        </div>
        <div style="flex:1;min-width:0">
            <div class="sidebar-user-name"><?= htmlspecialchars($_SESSION['admin_username'] ?? 'Admin') ?></div>
            <div class="sidebar-user-role">Staff</div>
        </div>
        <a href="<?= SITE_URL ?>/admin/logout.php" title="Logout" style="color:rgba(255,255,255,.35);font-size:.85rem;"><i class="fas fa-sign-out-alt"></i></a>
    </div>
</aside>

<div class="admin-main">
    <div class="admin-topbar">
        <div style="display:flex;align-items:center;gap:1rem">
            <button onclick="document.getElementById('adminSidebar').classList.toggle('open')" style="background:none;border:none;cursor:pointer;color:var(--text-mid);font-size:1.1rem;" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <span class="admin-page-title"><?= htmlspecialchars($admin_title) ?></span>
        </div>
        <div style="display:flex;align-items:center;gap:1rem">
            <a href="<?= SITE_URL ?>/index.php" target="_blank" class="btn btn-outline btn-sm"><i class="fas fa-external-link-alt"></i> View Site</a>
            <div class="topbar-avatar"><?= strtoupper(substr($_SESSION['admin_username'] ?? 'A', 0, 1)) ?></div>
        </div>
    </div>
    <div class="admin-content">