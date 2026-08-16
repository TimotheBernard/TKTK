<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $action, $id): void {
    $tasks = App::tasks();
    if ($method === 'GET' && ($action === 'supervision' || ($_GET['view'] ?? '') === 'supervision')) {
        ApiResponse::success($tasks->supervision() + ['counts' => $tasks->counts()]);
    }
    if ($method === 'GET' && $id === '') {
        $accountId = (string) ($_GET['account_id'] ?? '');
        $status = (string) ($_GET['status'] ?? '');
        $items = $accountId !== '' ? $tasks->forAccount($accountId) : $tasks->all();
        if ($status !== '') {
            $items = array_values(array_filter($items, fn ($t) => ($t['status'] ?? '') === $status));
        }
        ApiResponse::items($items, ['counts' => $tasks->counts()]);
    }
    if ($method === 'GET' && $id !== '') {
        $task = $tasks->get($id);
        if ($task === null) {
            throw new RuntimeException('TASK_NOT_FOUND');
        }
        ApiResponse::success(['task' => $task]);
    }
    if ($method === 'POST' && $id !== '' && $action === 'cancel') {
        $task = $tasks->cancel($id);
        if ($task === null) {
            throw new RuntimeException('TASK_NOT_FOUND');
        }
        ApiResponse::success(['task' => $task]);
    }
    if ($method === 'POST' && $id !== '' && $action === 'retry') {
        $task = $tasks->retry($id);
        if ($task === null) {
            throw new RuntimeException('TASK_NOT_FOUND');
        }
        ApiResponse::success(['task' => $task]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
