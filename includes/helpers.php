<?php

declare(strict_types=1);

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
