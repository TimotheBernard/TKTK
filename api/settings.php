<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $action): void {
    if ($method === 'GET') {
        $settings = App::settings()->get();
        unset($settings['password_hash']);
        ApiResponse::success([
            'settings' => $settings,
            'me' => Security::user(),
            'scheduler' => App::scheduler()->state(),
        ]);
    }
    if ($method === 'PUT' && $action === '') {
        ApiResponse::success(['settings' => App::settings()->update(api_body())]);
    }
    if ($method === 'POST' && $action === 'change_password') {
        $user = Security::user();
        if ($user === null) {
            throw new RuntimeException('UNAUTHORIZED');
        }
        $body = api_body();
        Security::changePassword(
            (string) $user['id'],
            (string) ($body['current_password'] ?? ''),
            (string) ($body['new_password'] ?? ''),
            (string) ($body['confirm_password'] ?? '')
        );
        ApiResponse::success(['changed' => true]);
    }
    if ($method === 'POST' && $action === 'open_watcher') {
        $data = SeleniumService::launchWatcher();
        if (empty($data['success'])) {
            throw new RuntimeException((string) (($data['error']['code'] ?? '') ?: 'BROWSER_START_FAILED'));
        }
        ApiResponse::success(['runner' => $data, 'scheduler' => App::scheduler()->state()]);
    }
    if ($method === 'POST' && $action === 'test_watcher') {
        $data = SeleniumService::checkWatcher();
        ApiResponse::success(['runner' => $data, 'scheduler' => App::scheduler()->state()]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
