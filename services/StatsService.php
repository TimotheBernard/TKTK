<?php

declare(strict_types=1);

final class StatsService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function compute(): array
    {
        $accounts = App::accounts()->all();
        $artists = App::artists()->all();
        $posts = App::posts()->all();
        $tasks = App::tasks()->all();
        $history = App::history()->all();

        $completed = array_values(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'completed'));
        $failed = array_values(array_filter($tasks, fn ($t) => ($t['status'] ?? '') === 'failed'));
        $durations = [];
        foreach ($completed as $task) {
            if (!empty($task['started_at']) && !empty($task['completed_at'])) {
                $durations[] = (int) round((parse_utc((string) $task['completed_at'])->format('U.u') - parse_utc((string) $task['started_at'])->format('U.u')) * 1000);
            }
        }

        $perAccount = [];
        foreach ($accounts as $account) {
            $id = (string) $account['id'];
            $own = array_values(array_filter($tasks, fn ($t) => ($t['account_id'] ?? '') === $id));
            $ownCompleted = array_values(array_filter($own, fn ($t) => ($t['status'] ?? '') === 'completed'));
            $ownFailed = array_values(array_filter($own, fn ($t) => ($t['status'] ?? '') === 'failed'));
            $perAccount[$id] = [
                'label' => $account['label'] ?? $id,
                'operations' => count($own),
                'completed' => count($ownCompleted),
                'failed' => count($ownFailed),
                'avg_execution_ms' => $this->avgMs($ownCompleted),
            ];
        }

        $stats = [
            'version' => STORAGE_VERSION,
            'updated_at' => now_utc(),
            'totals' => [
                'accounts' => count($accounts),
                'accounts_enabled' => count(array_filter($accounts, fn ($a) => !empty($a['enabled']))),
                'artists' => count($artists),
                'posts_detected' => count($posts),
                'tasks_generated' => count($tasks),
                'tasks_completed' => count($completed),
                'tasks_failed' => count($failed),
                'errors' => count(array_filter($history, fn ($h) => ($h['status'] ?? '') === 'error' || ($h['status'] ?? '') === 'failed')),
                'avg_execution_ms' => $durations === [] ? 0 : (int) round(array_sum($durations) / count($durations)),
            ],
            'per_account' => $perAccount,
        ];
        $this->storage->write('stats', $stats);
        return $stats;
    }

    private function avgMs(array $tasks): int
    {
        $durations = [];
        foreach ($tasks as $task) {
            if (!empty($task['started_at']) && !empty($task['completed_at'])) {
                $durations[] = (int) round((parse_utc((string) $task['completed_at'])->format('U.u') - parse_utc((string) $task['started_at'])->format('U.u')) * 1000);
            }
        }
        return $durations === [] ? 0 : (int) round(array_sum($durations) / count($durations));
    }
}
