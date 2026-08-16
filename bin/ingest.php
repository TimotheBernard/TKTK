<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$raw = stream_get_contents(STDIN) ?: ($argv[1] ?? '');
$payload = json_decode($raw, true);
if (!is_array($payload)) {
    fwrite(STDERR, json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR', 'message' => 'Invalid JSON']]) . PHP_EOL);
    exit(1);
}

try {
    $result = App::posts()->ingest($payload);
    echo json_encode(['success' => true, 'data' => $result, 'error' => null], JSON_UNESCAPED_SLASHES) . PHP_EOL;
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, json_encode([
        'success' => false,
        'data' => null,
        'error' => ['code' => $e->getMessage(), 'message' => $e->getMessage()],
    ]) . PHP_EOL);
    exit(1);
}
