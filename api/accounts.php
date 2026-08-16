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
    if ($method === 'POST' && $id !== '' && $action === 'test_session') {
        $account = $accounts->get($id);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        $data = SeleniumService::run([
            'account-id' => $id,
            'action' => 'check_session',
        ]);
        if (isset($data['session_status'])) {
            $accounts->update($id, [
                'session_status' => $data['session_status'],
                'session_checked_at' => now_utc(),
                'status' => $data['session_status'] === 'expired' ? 'session_expired' : ($account['status'] ?? 'idle'),
            ]);
        }
        ApiResponse::success(['account' => $accounts->get($id), 'runner' => $data]);
    }
    if ($method === 'POST' && $id !== '' && $action === 'open_tiktok') {
        $account = $accounts->get($id);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        $data = SeleniumService::launchAccount($id, (string) ($account['username'] ?? ''));
        if (empty($data['success'])) {
            throw new RuntimeException((string) (($data['error']['code'] ?? '') ?: 'BROWSER_START_FAILED'));
        }
        ApiResponse::success(['account' => $account, 'runner' => $data]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
