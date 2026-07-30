<?php

declare(strict_types=1);
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (!is_logged_in()) attempt_remembered_login();
if (is_logged_in()) redirect('index.php');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $username = trim($_POST['username'] ?? '');
        $password = (string)($_POST['password'] ?? '');
        if ($username === '' || $password === '') throw new RuntimeException('Username and password are required.');
        $remember = isset($_POST['remember_me']) && $_POST['remember_me'] === '1';
        if (!attempt_login($username, $password, $remember)) throw new RuntimeException('Invalid username or password.');
        $target = $_SESSION['login_redirect'] ?? 'index.php';
        unset($_SESSION['login_redirect']);
        redirect($target);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Log In — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="assets/style.css">
</head>

<body class="login-page">
    <main class="login-card">
        <h1><?= e(APP_NAME) ?></h1>
        <p class="muted">Authorized tool-room users only.</p>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>
        <form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><label>Username</label><input name="username" autocomplete="username" required autofocus><label>Password</label><input type="password" name="password" autocomplete="current-password" required><label class="remember-option"><input type="checkbox" name="remember_me" value="1"> <span>Keep me logged in on this device for 30 days</span></label><button class="login-button">Log In</button></form>
        <p class="muted login-help">The initial administrator credentials are listed in README.md. Change the password after signing in.</p>
    </main>
</body>

</html>