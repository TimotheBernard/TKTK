<?php

declare(strict_types=1);

$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$uri = '/' . ltrim($uri, '/');

$deniedPrefixes = [
    '/data',
    '/logs',
    '/includes',
    '/services',
    '/worker',
    '/selenium/profiles',
    '/selenium/logs',
    '/bin',
    '/tests',
];

foreach ($deniedPrefixes as $prefix) {
    if ($uri === $prefix || str_starts_with($uri, $prefix . '/')) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Forbidden';
        exit;
    }
}

$root = __DIR__;
$file = $root . $uri;

if ($uri !== '/' && is_file($file)) {
    return false;
}

if ($uri === '/') {
    require $root . '/index.php';
    return true;
}

if (preg_match('#^/api/([a-z0-9_-]+\.php)$#', $uri, $m)) {
    $api = $root . '/api/' . $m[1];
    if (is_file($api)) {
        require $api;
        return true;
    }
}

$page = $root . $uri;
if (!str_ends_with($page, '.php') && is_file($page . '.php')) {
    require $page . '.php';
    return true;
}

http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Not found';
return true;
