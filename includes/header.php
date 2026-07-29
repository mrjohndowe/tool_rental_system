<?php
declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/functions.php';
$current = basename($_SERVER['PHP_SELF']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="topbar">
    <div>
        <h1><?= e(APP_NAME) ?></h1>
        <p>All issued tools are due back by the end of the workday.</p>
    </div>
    <nav>
        <a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="index.php">Dashboard</a>
        <a class="<?= $current === 'checkout.php' ? 'active' : '' ?>" href="checkout.php">Checkout</a>
        <a class="<?= $current === 'returns.php' ? 'active' : '' ?>" href="returns.php">Returns</a>
        <a class="<?= $current === 'tools.php' ? 'active' : '' ?>" href="tools.php">Tools</a>
        <a class="<?= $current === 'employees.php' ? 'active' : '' ?>" href="employees.php">Employees</a>
        <a class="<?= $current === 'history.php' ? 'active' : '' ?>" href="history.php">History</a>
    </nav>
</header>
<main class="container">
<?php foreach (consume_flashes() as $item): ?>
    <div class="alert <?= e($item['type']) ?>"><?= e($item['message']) ?></div>
<?php endforeach; ?>
