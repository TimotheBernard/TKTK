<?php

declare(strict_types=1);

function env_value(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

function env_bool(string $key, bool $default = false): bool
{
    $value = env_value($key);
    if ($value === null) {
        return $default;
    }
    return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
}

function load_dotenv(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (!str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = explode('=', $line, 2);
        $key = trim($key);
        $value = trim($value);
        $value = trim($value, "\"'");
        if ($key === '') {
            continue;
        }
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function now_utc(): string
{
    $micro = sprintf('%.6F', microtime(true));
    $dt = DateTimeImmutable::createFromFormat('U.u', $micro, new DateTimeZone('UTC'));
    if ($dt === false) {
        $dt = new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }
    return $dt->format('Y-m-d\TH:i:s.v\Z');
}

function parse_utc(string $value): DateTimeImmutable
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s.v\Z', $value, new DateTimeZone('UTC'));
    if ($dt instanceof DateTimeImmutable) {
        return $dt;
    }
    $dt = new DateTimeImmutable($value);
    return $dt->setTimezone(new DateTimeZone('UTC'));
}

function add_seconds(string $iso, int $seconds): string
{
    return parse_utc($iso)->modify('+' . $seconds . ' seconds')->format('Y-m-d\TH:i:s.v\Z');
}

function generate_id(string $prefix): string
{
    $prefix = preg_replace('/s$/', '', $prefix) ?? $prefix;
    return $prefix . '_' . bin2hex(random_bytes(4));
}

function normalize_username(string $username): string
{
    $username = trim($username);
    $username = ltrim($username, '@');
    $username = strtolower($username);
    return $username === '' ? '' : '@' . $username;
}

function json_collection(array $items = []): array
{
    return [
        'version' => STORAGE_VERSION,
        'updated_at' => now_utc(),
        'items' => array_values($items),
    ];
}

function request_json(): array
{
    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function request_method(): string
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $override = $_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'] ?? '';
    if ($override === '' && isset($_GET['_method'])) {
        $override = (string) $_GET['_method'];
    }
    if ($override === '') {
        $body = $GLOBALS['_REQUEST_JSON'] ?? null;
        if (is_array($body) && isset($body['_method'])) {
            $override = (string) $body['_method'];
        }
    }
    if (is_string($override) && $override !== '') {
        return strtoupper($override);
    }
    return $method;
}

function request_path(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    $path = is_string($path) ? $path : '/';
    if ($path !== '/' && str_ends_with($path, '/')) {
        $path = rtrim($path, '/');
    }
    return $path === '' ? '/' : $path;
}

function scenario_delay_seconds(array $timing): int
{
    $type = (string) ($timing['type'] ?? 'fixed');
    return match ($type) {
        'minutes' => max(0, (int) ($timing['minutes'] ?? 0) * 60),
        'hours' => max(0, (int) ($timing['hours'] ?? 0) * 3600),
        'window' => random_int(0, max(0, (int) ($timing['window_seconds'] ?? 86400))),
        'random_window' => (static function (array $timing): int {
            $min = max(0, (int) ($timing['min_seconds'] ?? 0));
            $max = max($min, (int) ($timing['max_seconds'] ?? $min));
            return random_int($min, $max);
        })($timing),
        default => max(0, (int) ($timing['delay_seconds'] ?? 0)),
    };
}

function account_groups(): array
{
    return ['PRINCIPAUX', 'PROMOTION', 'SECONDAIRES', 'TESTS', 'AUTRES'];
}

function runtime_statuses(): array
{
    return ['idle', 'queued', 'starting', 'busy', 'cooldown', 'session_expired', 'disconnected', 'error', 'disabled'];
}

function connection_statuses(): array
{
    return ['connected', 'disconnected', 'expired', 'unknown', 'reauthentication_required'];
}
