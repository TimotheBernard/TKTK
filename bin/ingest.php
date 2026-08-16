<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$raw = file_get_contents('php://stdin') ?: '';
$input = json_decode($raw, true);
if (!is_array($input)) {
    fwrite(STDOUT, json_encode(['success' => false, 'error' => ['code' => 'VALIDATION_ERROR']]) . PHP_EOL);
    exit(1);
}
try {
    $result = App::posts()->ingest($input);
    fwrite(STDOUT, json_encode(['success' => true, 'data' => $result]) . PHP_EOL);
} catch (Throwable $e) {
    fwrite(STDOUT, json_encode(['success' => false, 'error' => ['code' => $e->getMessage(), 'message' => $e->getMessage()]]) . PHP_EOL);
    exit(1);
}
