<?php

declare(strict_types=1);

final class ApiResponse
{
    public static function success(mixed $data = [], int $status = 200): never
    {
        self::send([
            'success' => true,
            'data' => $data,
            'error' => null,
        ], $status);
    }

    public static function error(string $code, string $message, int $status = 400): never
    {
        self::send([
            'success' => false,
            'data' => null,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ], $status);
    }

    public static function items(array $items, array $extra = []): never
    {
        self::success(array_merge([
            'items' => array_values($items),
            'count' => count($items),
        ], $extra));
    }

    /** @param array<string, mixed> $payload */
    private static function send(array $payload, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
