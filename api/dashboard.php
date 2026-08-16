<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function (): void {
    ApiResponse::success([
        'accounts' => App::accounts()->all(),
        'account_counts' => App::accounts()->counts(),
        'artists' => App::artists()->all(),
        'posts' => array_slice(App::posts()->all(), 0, 20),
        'tasks' => App::tasks()->supervision(),
        'task_counts' => App::tasks()->counts(),
        'history' => App::history()->recent(25),
        'orphans' => App::targets()->orphans(),
        'scheduler' => App::scheduler()->state(),
        'settings' => [
            'dev_mode' => App::settings()->isDevMode(),
            'dashboard_poll_ms' => App::settings()->get()['dashboard_poll_ms'] ?? 1000,
        ],
        'now' => now_utc(),
    ]);
});
