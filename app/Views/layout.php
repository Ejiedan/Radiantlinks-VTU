<?php use App\Core\Security; ?>
<?php $authed = !empty($_SESSION['user']); ?>
<?php $isAdmin = $authed && in_array($_SESSION['user']['role'] ?? '', ['owner', 'admin'], true); ?>
<?php $adminPage = $isAdmin && str_starts_with(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', app_base_url() . '/admin'); ?>
<?php $adminPath = $adminPage ? (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/') : ''; ?>
<!doctype html>
<html lang="en" data-app-base="<?= Security::e(app_base_url()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= Security::e($title ?? ($appSettings['site_title'] ?? 'Radiantlinks VTU')) ?></title>
    <link rel="stylesheet" href="/assets/app.css?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/app.css') ?>">
    <?php if ($adminPage): ?><link rel="stylesheet" href="/assets/mdi/css/materialdesignicons.min.css"><link rel="stylesheet" href="/assets/admin.css?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/admin.css') ?>"><?php endif; ?>
</head>
<body class="<?= $adminPage ? 'admin-mode' : '' ?>">
<div class="<?= $authed ? 'app-shell' : 'public-shell' ?>">
    <?php if ($authed): ?>
    <?php if ($adminPage): ?>
    <aside class="sidebar admin-sidebar" id="admin-sidebar" aria-label="Admin navigation">
        <a class="admin-brand" href="/admin" aria-label="Admin dashboard">
            <?php if (!empty($appSettings['site_logo_url'])): ?><img src="<?= Security::e(stored_media_url((string) $appSettings['site_logo_url'])) ?>" alt=""><?php else: ?><span class="admin-brand-mark"><i class="mdi mdi-rocket"></i></span><?php endif; ?>
            <span class="admin-brand-name"><?= Security::e($appSettings['site_title'] ?? 'Radiantlinks VTU') ?><small>CONTROL CENTER</small></span>
        </a>
        <nav class="admin-navigation">
            <span class="admin-nav-label">Overview</span>
            <a class="admin-nav-link <?= $adminPath === app_base_url() . '/admin' ? 'active' : '' ?>" href="/admin"><i class="mdi mdi-view-dashboard-outline"></i><span>Dashboard</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/transactions') ? 'active' : '' ?>" href="/admin/transactions"><i class="mdi mdi-receipt-text-outline"></i><span>Transactions</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/funding') ? 'active' : '' ?>" href="/admin/funding"><i class="mdi mdi-wallet-outline"></i><span>Wallet Funding</span></a>
            <span class="admin-nav-label">Management</span>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/users') ? 'active' : '' ?>" href="/admin/users"><i class="mdi mdi-account-group-outline"></i><span>Users</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/kyc') ? 'active' : '' ?>" href="/admin/kyc"><i class="mdi mdi-shield-account-outline"></i><span>KYC Verification</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/commerce') ? 'active' : '' ?>" href="/admin/commerce"><i class="mdi mdi-crown-outline"></i><span>Packages & Discounts</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/referrals') ? 'active' : '' ?>" href="/admin/referrals"><i class="mdi mdi-account-multiple-plus-outline"></i><span>Referrals</span></a>
            <span class="admin-nav-label">Services</span>
            <details class="admin-nav-group" <?= str_contains($adminPath, '/admin/plans') || str_contains($adminPath, '/admin/services') || str_contains($adminPath, '/admin/pins') ? 'open' : '' ?>>
                <summary><i class="mdi mdi-apps"></i><span>Services & APIs</span><i class="mdi mdi-chevron-down admin-nav-chevron"></i></summary>
                <a href="/admin/service-settings/data">Data Settings</a>
                <a href="/admin/service-settings/airtime">Airtime Settings</a>
                <a href="/admin/service-settings/cable">Cable TV Settings</a>
                <a href="/admin/service-settings/electricity">Electricity Settings</a>
                <a href="/admin/service-settings/exam_epin">Exam ePIN Settings</a>
                <a href="/admin/service-settings/data_pin">Data PIN Settings</a>
                <a href="/admin/service-settings/airtime_pin">Airtime PIN Settings</a>
                <a href="/admin/plans">All Plans</a>
                <a href="/admin/services">Service Switches</a>
                <a href="/admin/pins">PIN Manager</a>
            </details>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/providers') ? 'active' : '' ?>" href="/admin/providers"><i class="mdi mdi-api"></i><span>API Providers</span></a>
            <span class="admin-nav-label">Workspace</span>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/campaigns') ? 'active' : '' ?>" href="/admin/campaigns"><i class="mdi mdi-bullhorn-outline"></i><span>Campaigns</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/homepage') ? 'active' : '' ?>" href="/admin/homepage"><i class="mdi mdi-monitor-dashboard"></i><span>Homepage</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/settings/general') ? 'active' : '' ?>" href="/admin/settings/general"><i class="mdi mdi-cog-outline"></i><span>General Settings</span></a>
            <a class="admin-nav-link <?= str_contains($adminPath, '/admin/settings/payment') ? 'active' : '' ?>" href="/admin/settings/payment"><i class="mdi mdi-credit-card-outline"></i><span>Payment Settings</span></a>
        </nav>
        <div class="admin-sidebar-foot"><a href="/dashboard"><i class="mdi mdi-open-in-new"></i><span>Customer Dashboard</span></a><form method="post" action="/logout"><input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>"><button type="submit"><i class="mdi mdi-logout"></i><span>Sign out</span></button></form></div>
    </aside>
    <button type="button" class="admin-nav-scrim" data-sidebar-close aria-label="Close navigation" hidden></button>
    <?php else: ?>
    <aside class="sidebar">
        <div class="sidebar-head">
            <div class="brand"><?= Security::e($appSettings['site_title'] ?? 'Radiantlinks VTU') ?></div>
            <button type="button" class="sidebar-toggle" data-sidebar-toggle aria-label="Toggle menu">☰</button>
        </div>
        <nav>
            <a href="/dashboard">Dashboard</a>
            <a href="/wallet">Wallet</a>
            <a href="/verification">Verification</a>
            <a href="/history">History</a>
            <a href="/packages">Packages</a>
            <a href="/referrals">Referrals</a>
            <a href="/service/airtime">Airtime</a>
            <a href="/service/data">Data</a>
            <a href="/service/exam_epin">Exam ePIN</a>
            <a href="/service/data_pin">Data PIN</a>
            <a href="/service/airtime_pin">Airtime PIN</a>
            <a href="/service/electricity">Electricity</a>
            <?php if ($isAdmin): ?>
            <span class="nav-section-title">Admin</span>
            <a href="/admin">Dashboard</a>
            <details class="nav-group" open>
                <summary>Services</summary>
                <a href="/admin/service-settings/data">Data Settings</a>
                <a href="/admin/service-settings/airtime">Airtime Settings</a>
                <a href="/admin/service-settings/cable">Cable Settings</a>
                <a href="/admin/service-settings/electricity">Electricity Settings</a>
                <a href="/admin/service-settings/exam_epin">Exam ePIN Settings</a>
                <a href="/admin/service-settings/data_pin">Data PIN Settings</a>
                <a href="/admin/service-settings/airtime_pin">Airtime PIN Settings</a>
                <a href="/admin/plans">All Service Plans</a>
                <a href="/admin/services">Service Switches</a>
                <a href="/admin/pins">PIN Manager</a>
            </details>
            <details class="nav-group">
                <summary>Business</summary>
                <a href="/admin/providers">All API Providers</a>
                <a href="/admin/commerce">Packages & Discounts</a>
                <a href="/admin/referrals">Referrals</a>
                <a href="/admin/campaigns">Campaigns</a>
            </details>
            <details class="nav-group">
                <summary>Operations</summary>
                <a href="/admin/users">Users</a>
                <a href="/admin/kyc">KYC Verification</a>
                <a href="/admin/funding">Funding</a>
                <a href="/admin/transactions">Transactions</a>
                <a href="/admin/homepage">Homepage</a>
                <a href="/admin/settings/general">General Settings</a>
                <a href="/admin/settings/payment">Payment Settings</a>
            </details>
            <?php endif; ?>
        </nav>
        <form method="post" action="/logout" class="logout-form">
            <input type="hidden" name="_csrf" value="<?= Security::e(Security::csrfToken()) ?>">
            <button>Logout</button>
        </form>
    </aside>
    <?php endif; ?>
    <?php endif; ?>
    <main class="content">
        <?php if ($adminPage): ?>
        <header class="admin-topbar">
            <button type="button" class="admin-icon-button admin-menu-button" data-sidebar-toggle aria-label="Toggle navigation" aria-controls="admin-sidebar" aria-expanded="false"><i class="mdi mdi-menu"></i></button>
            <form class="admin-top-search" action="/admin/users" method="get" role="search"><i class="mdi mdi-magnify"></i><input type="search" name="q" placeholder="Search users" aria-label="Search users" value="<?= Security::e((string) ($_GET['q'] ?? '')) ?>"></form>
            <div class="admin-top-actions"><a href="/admin/transactions" class="admin-icon-button" title="Transactions" aria-label="Transactions"><i class="mdi mdi-bell-outline"></i></a><a href="/" class="admin-icon-button" title="View website" aria-label="View website"><i class="mdi mdi-web"></i></a><span class="admin-avatar" aria-hidden="true"><?= Security::e(strtoupper(substr((string) ($_SESSION['user']['first_name'] ?? $_SESSION['user']['email'] ?? 'A'), 0, 1))) ?></span><span class="admin-identity"><strong><?= Security::e(trim((string) ($_SESSION['user']['first_name'] ?? '') . ' ' . (string) ($_SESSION['user']['last_name'] ?? '')) ?: (string) ($_SESSION['user']['email'] ?? 'Admin')) ?></strong><small><?= Security::e(ucfirst((string) ($_SESSION['user']['role'] ?? 'Admin'))) ?></small></span></div>
        </header>
        <div class="admin-content"><div class="admin-page-heading"><div><span>Administration</span><h1><?= Security::e($title ?? 'Dashboard') ?></h1></div><a href="/dashboard">Customer view <i class="mdi mdi-arrow-top-right"></i></a></div>
        <?php endif; ?>
        <?php require $viewFile; ?>
        <?php if ($adminPage): ?></div><?php endif; ?>
    </main>
    <?php if ($authed && !$isAdmin): ?>
    <nav class="mobile-bottom-nav" aria-label="Mobile navigation">
        <a href="/dashboard">Home</a>
        <a href="/history">History</a>
        <a href="/wallet">Fund</a>
        <a href="/verification">Verify</a>
        <a href="/packages">Package</a>
        <a href="/referrals">Referrals</a>
    </nav>
    <?php endif; ?>
</div>
<script>
    window.APP_BASE = <?= json_encode(app_base_url(), JSON_UNESCAPED_SLASHES) ?>;
</script>
<script src="/assets/app.js?v=<?= (int) @filemtime(BASE_PATH . '/public/assets/app.js') ?>"></script>
</body>
</html>
