<?php

declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_name('tktk_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
        }
    }

    public static function csrfToken(): string
    {
        self::startSession();
        return (string) $_SESSION['csrf'];
    }

    public static function user(): ?array
    {
        self::startSession();
        return isset($_SESSION['user_id']) ? [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'] ?? '',
        ] : null;
    }

    public static function requireLogin(): void
    {
        if (self::user() === null) {
            if (self::isApi()) {
                ApiResponse::error('UNAUTHORIZED', 'Authentication required', 401);
            }
            header('Location: /login.php');
            exit;
        }
    }

    public static function requireCsrf(): void
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '') {
            $body = $GLOBALS['_REQUEST_JSON'] ?? [];
            $token = is_array($body) ? (string) ($body['_csrf'] ?? '') : '';
        }
        if (!hash_equals(self::csrfToken(), (string) $token)) {
            ApiResponse::error('CSRF_FAILED', 'Invalid CSRF token', 403);
        }
    }

    public static function login(string $username, string $password): bool
    {
        $username = trim($username);
        $users = App::storage()->read('users')['items'] ?? [];
        foreach ($users as $user) {
            if (!is_array($user) || ($user['username'] ?? '') !== $username) {
                continue;
            }
            if (!password_verify($password, (string) ($user['password_hash'] ?? ''))) {
                return false;
            }
            self::startSession();
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            App::storage()->update('users', (string) $user['id'], ['last_login_at' => now_utc()]);
            Logger::app('LOGIN ' . $username);
            return true;
        }
        return false;
    }

    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/');
        }
        session_destroy();
    }

    public static function changePassword(string $userId, string $current, string $new, string $confirm): void
    {
        if (strlen($new) < 8) {
            throw new RuntimeException('PASSWORD_TOO_SHORT');
        }
        if ($new !== $confirm) {
            throw new RuntimeException('PASSWORD_MISMATCH');
        }
        $user = App::storage()->findById('users', $userId);
        if ($user === null) {
            throw new RuntimeException('UNAUTHORIZED');
        }
        if (!password_verify($current, (string) ($user['password_hash'] ?? ''))) {
            throw new RuntimeException('INVALID_PASSWORD');
        }
        App::storage()->update('users', $userId, [
            'password_hash' => password_hash($new, PASSWORD_DEFAULT),
            'password_changed_at' => now_utc(),
        ]);
        Logger::app('PASSWORD_CHANGED ' . (string) ($user['username'] ?? $userId));
    }

    public static function isApi(): bool
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return str_contains($uri, '/api/');
    }
}
