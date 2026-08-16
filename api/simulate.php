<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($action): void {
    $sim = App::simulation();
    $body = api_body();
    if ($action === 'new_post' || $action === '') {
        $delays = $body['delays'] ?? [2, 4, 5, 8, 12];
        if (!is_array($delays)) {
            $delays = [2, 4, 5, 8, 12];
        }
        ApiResponse::success($sim->newPostFanout(array_map('intval', $delays), $body['detected_at'] ?? null));
    }
    if ($action === 'session_expired') {
        ApiResponse::success(['account' => $sim->sessionExpired((string) ($body['account_id'] ?? ''))]);
    }
    if ($action === 'browser_unavailable') {
        ApiResponse::success(['account' => $sim->browserUnavailable((string) ($body['account_id'] ?? ''))]);
    }
    if ($action === 'task_result') {
        ApiResponse::success(['task' => $sim->completeTask((string) ($body['task_id'] ?? ''), (bool) ($body['success'] ?? true))]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unknown simulation', 400);
});
