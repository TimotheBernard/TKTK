<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method): void {
    if ($method === 'GET') {
        $settings = App::settings()->get();
        unset($settings['password_hash']);
        ApiResponse::success([
            'settings' => $settings,
            'me' => Security::user(),
            'scheduler' => App::scheduler()->state(),
        ]);
    }
    if ($method === 'PUT') {
        ApiResponse::success(['settings' => App::settings()->update(api_body())]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
