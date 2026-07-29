<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

const REMEMBER_ME_DAYS = 30;

function remember_cookie_name(): string
{
    return 'tool_checkout_remember_' . substr(hash('sha256', APP_NAME . '|' . DB_NAME), 0, 12);
}

function cookie_is_secure(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function set_remember_cookie(string $value, int $expires): void
{
    setcookie(remember_cookie_name(), $value, [
        'expires' => $expires,
        'path' => '/',
        'secure' => cookie_is_secure(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_remember_cookie(): void
{
    set_remember_cookie('', time() - 3600);
    unset($_COOKIE[remember_cookie_name()]);
}

function current_user(): ?array
{
    return isset($_SESSION['user']) && is_array($_SESSION['user']) ? $_SESSION['user'] : null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function store_user_session(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'],
        'full_name' => $user['full_name'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];
}

function create_remember_token(int $userId): void
{
    $selector = bin2hex(random_bytes(12));
    $validator = bin2hex(random_bytes(32));
    $expires = time() + (REMEMBER_ME_DAYS * 86400);
    $userAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');

    db()->prepare('DELETE FROM user_remember_tokens WHERE user_id = ? OR expires_at < NOW()')->execute([$userId]);
    db()->prepare('INSERT INTO user_remember_tokens (user_id, selector, validator_hash, user_agent_hash, expires_at) VALUES (?, ?, ?, ?, FROM_UNIXTIME(?))')
        ->execute([$userId, $selector, hash('sha256', $validator), $userAgentHash, $expires]);

    $cookieValue = $selector . ':' . $validator;
    set_remember_cookie($cookieValue, $expires);
    $_COOKIE[remember_cookie_name()] = $cookieValue;
}

function attempt_remembered_login(): bool
{
    if (is_logged_in()) return true;

    $cookie = $_COOKIE[remember_cookie_name()] ?? '';
    if (!is_string($cookie) || !str_contains($cookie, ':')) return false;

    [$selector, $validator] = explode(':', $cookie, 2);
    if (!preg_match('/^[a-f0-9]{24}$/', $selector) || !preg_match('/^[a-f0-9]{64}$/', $validator)) {
        clear_remember_cookie();
        return false;
    }

    $stmt = db()->prepare(
        'SELECT t.id AS token_id, t.validator_hash, t.user_agent_hash, t.expires_at,
                u.id, u.full_name, u.username, u.role, u.active
         FROM user_remember_tokens t
         JOIN users u ON u.id = t.user_id
         WHERE t.selector = ? LIMIT 1'
    );
    $stmt->execute([$selector]);
    $record = $stmt->fetch();

    $currentAgentHash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? 'unknown');
    $valid = $record
        && (int)$record['active'] === 1
        && strtotime((string)$record['expires_at']) > time()
        && hash_equals((string)$record['validator_hash'], hash('sha256', $validator))
        && hash_equals((string)$record['user_agent_hash'], $currentAgentHash);

    if (!$valid) {
        if ($record && isset($record['token_id'])) {
            db()->prepare('DELETE FROM user_remember_tokens WHERE id = ?')->execute([(int)$record['token_id']]);
        }
        clear_remember_cookie();
        return false;
    }

    store_user_session($record);
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$record['id']]);

    // Rotate the persistent token after every automatic sign-in.
    db()->prepare('DELETE FROM user_remember_tokens WHERE id = ?')->execute([(int)$record['token_id']]);
    create_remember_token((int)$record['id']);
    return true;
}

function require_login(): void
{
    if (!is_logged_in()) {
        attempt_remembered_login();
    }
    if (!is_logged_in()) {
        $_SESSION['login_redirect'] = $_SERVER['REQUEST_URI'] ?? 'index.php';
        header('Location: login.php');
        exit;
    }
}

function require_admin(): void
{
    require_login();
    if ((current_user()['role'] ?? '') !== 'admin') {
        flash('error', 'Administrator access is required.');
        redirect('index.php');
    }
}

function attempt_login(string $username, string $password, bool $remember = false): bool
{
    $stmt = db()->prepare('SELECT id, full_name, username, password_hash, role, active FROM users WHERE username = ? LIMIT 1');
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !(int)$user['active'] || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    store_user_session($user);
    db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')->execute([(int)$user['id']]);

    if ($remember) {
        create_remember_token((int)$user['id']);
    } else {
        clear_remember_cookie();
    }
    return true;
}

function logout_user(): void
{
    $cookie = $_COOKIE[remember_cookie_name()] ?? '';
    if (is_string($cookie) && str_contains($cookie, ':')) {
        [$selector] = explode(':', $cookie, 2);
        if (preg_match('/^[a-f0-9]{24}$/', $selector)) {
            db()->prepare('DELETE FROM user_remember_tokens WHERE selector = ?')->execute([$selector]);
        }
    }
    clear_remember_cookie();

    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool)$params['secure'], (bool)$params['httponly']);
    }
    session_destroy();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
}
