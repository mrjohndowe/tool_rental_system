<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
require_login();
$current = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#111827">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="manifest" href="manifest.webmanifest">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
    <script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('service-worker.js').catch(() => {})); }</script>
</head>
<body>
<header class="topbar">
    <div>
        <h1><?= e(APP_NAME) ?></h1>
        <p>Checkout closes at 3:00 PM. All issued tools are due back by 3:30 PM.</p>
    </div>
    <nav>
        <a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
        <a class="<?= $current === 'checkout.php' ? 'active' : '' ?>" href="checkout.php">Checkout</a>
        <a class="<?= $current === 'returns.php' ? 'active' : '' ?>" href="returns.php">Returns</a>
        <a class="<?= in_array($current,['tools.php','tool_form.php'],true) ? 'active' : '' ?>" href="tools.php">Tools</a>
        <a class="<?= in_array($current,['bundles.php','bundle_form.php'],true) ? 'active' : '' ?>" href="bundles.php">Bundles</a>
        <a class="<?= $current === 'accessories.php' ? 'active' : '' ?>" href="accessories.php">Accessories</a>
        <a class="<?= $current === 'locations.php' ? 'active' : '' ?>" href="locations.php">Locations</a>
        <a class="<?= $current === 'employees.php' ? 'active' : '' ?>" href="employees.php">Employees</a>
        <a class="<?= $current === 'history.php' ? 'active' : '' ?>" href="history.php">History</a>
        <?php if ((current_user()['role'] ?? '') === 'admin'): ?><a class="<?= $current === 'users.php' ? 'active' : '' ?>" href="users.php">Users</a><?php endif; ?>
        <span class="user-chip"><?= e(current_user()['full_name'] ?? '') ?></span>
        <a href="logout.php">Log Out</a>
    </nav>
</header>
<main class="container">
<?php foreach (consume_flashes() as $item): ?>
    <div class="alert <?= e($item['type']) ?>"><?= e($item['message']) ?></div>
<?php endforeach; ?>
