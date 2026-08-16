<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $action, $id): void {
    $accounts = App::accounts();
    if ($method === 'GET' && $id === '') {
        ApiResponse::items($accounts->all(), ['counts' => $accounts->counts()]);
    }
    if ($method === 'GET' && $id !== '') {
        $account = $accounts->get($id);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        ApiResponse::success([
            'account' => $account,
            'targets' => App::targets()->forAccount($id),
            'rules' => App::rules()->forAccount($id),
            'tasks' => App::tasks()->forAccount($id),
            'history' => App::history()->forAccount($id),
        ]);
    }
    if ($method === 'POST' && $action === '' && $id === '') {
        ApiResponse::success(['account' => $accounts->create(api_body())], 201);
    }
    if ($method === 'PUT' && $id !== '') {
        $account = $accounts->update($id, api_body());
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        ApiResponse::success(['account' => $account]);
    }
    if ($method === 'DELETE' && $id !== '') {
        if (!$accounts->delete($id)) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        ApiResponse::success(['deleted' => true]);
    }
    if ($method === 'POST' && $id !== '' && $action === 'enable') {
        $account = $accounts->update($id, ['enabled' => true]);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        ApiResponse::success(['account' => $account]);
    }
    if ($method === 'POST' && $id !== '' && $action === 'disable') {
        $account = $accounts->update($id, ['enabled' => false]);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        ApiResponse::success(['account' => $account]);
    }
    if ($method === 'POST' && $id !== '' && in_array($action, ['test_session', 'open_tiktok'], true)) {
        $account = $accounts->get($id);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        $cmd = App::settings()->get()['python_path'] . ' ' . escapeshellarg(SELENIUM_PATH . '/runner.py')
            . ' --account-id ' . escapeshellarg($id)
            . ' --action ' . escapeshellarg($action === 'open_tiktok' ? 'open_profile' : 'check_session');
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        $raw = implode("\n", $output);
        $decoded = json_decode($raw, true);
        $data = is_array($decoded) ? $decoded : ['raw' => $raw, 'exit_code' => $code];
        if (isset($data['session_status'])) {
            $accounts->update($id, [
                'session_status' => $data['session_status'],
                'session_checked_at' => now_utc(),
                'status' => $data['session_status'] === 'expired' ? 'session_expired' : ($account['status'] ?? 'idle'),
            ]);
        }
        ApiResponse::success(['account' => $accounts->get($id), 'runner' => $data]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
