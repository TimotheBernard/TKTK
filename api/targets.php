<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $id): void {
    $targets = App::targets();
    if ($method === 'GET') {
        $accountId = (string) ($_GET['account_id'] ?? '');
        $artistId = (string) ($_GET['artist_id'] ?? '');
        $items = $targets->all();
        if ($accountId !== '') {
            $items = array_values(array_filter($items, fn ($t) => ($t['account_id'] ?? '') === $accountId));
        }
        if ($artistId !== '') {
            $items = array_values(array_filter($items, fn ($t) => ($t['artist_id'] ?? '') === $artistId));
        }
        ApiResponse::items($items, ['orphans' => $targets->orphans()]);
    }
    if ($method === 'POST') {
        ApiResponse::success(['target' => $targets->create(api_body())], 201);
    }
    if ($method === 'PUT' && $id !== '') {
        $target = $targets->update($id, api_body());
        if ($target === null) {
            throw new RuntimeException('TARGET_NOT_FOUND');
        }
        ApiResponse::success(['target' => $target]);
    }
    if ($method === 'DELETE' && $id !== '') {
        if (!$targets->delete($id)) {
            throw new RuntimeException('TARGET_NOT_FOUND');
        }
        ApiResponse::success(['deleted' => true]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
