<?php

declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443);
        session_name('tktknueva_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $secure,
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
            header('Location: /login');
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
        if ($token === '' && isset($_POST['_csrf'])) {
            $token = (string) $_POST['_csrf'];
        }
        if (!hash_equals(self::csrfToken(), (string) $token)) {
            if (self::isApi() || request_method() !== 'GET') {
                if (self::isApi()) {
                    ApiResponse::error('CSRF_FAILED', 'Invalid CSRF token', 403);
                }
                http_response_code(403);
                echo 'Invalid CSRF token';
                exit;
            }
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
            $_SESSION['csrf'] = bin2hex(random_bytes(16));
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

    public static function createUser(string $username, string $password): array
    {
        $username = trim($username);
        if ($username === '' || strlen($password) < 8) {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        foreach (App::storage()->read('users')['items'] ?? [] as $user) {
            if (($user['username'] ?? '') === $username) {
                throw new RuntimeException('DUPLICATE_USER');
            }
        }
        return App::storage()->insert('users', [
            'id' => generate_id('user'),
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => now_utc(),
            'last_login_at' => null,
        ]);
    }

    public static function isApi(): bool
    {
        $path = request_path();
        return str_starts_with($path, '/api');
    }
}
