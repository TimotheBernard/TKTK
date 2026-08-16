<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function (): void {
    $name = (string) ($_GET['name'] ?? 'application');
    $allowed = [
        'application' => LOG_PATH . '/application.log',
        'scheduler' => LOG_PATH . '/scheduler.log',
        'errors' => LOG_PATH . '/errors.log',
    ];
    if (!isset($allowed[$name])) {
        throw new InvalidArgumentException('VALIDATION_ERROR');
    }
    $file = $allowed[$name];
    $lines = [];
    if (is_file($file)) {
        $all = file($file, FILE_IGNORE_NEW_LINES) ?: [];
        $lines = array_slice($all, -200);
    }
    ApiResponse::success(['name' => $name, 'lines' => $lines]);
});
