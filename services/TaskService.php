<?php

declare(strict_types=1);

final class TaskService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read('tasks')['items'] ?? [];
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('tasks', $id);
    }

    public function forAccount(string $accountId, array $statuses = []): array
    {
        return array_values(array_filter($this->all(), function ($task) use ($accountId, $statuses) {
            if (($task['account_id'] ?? '') !== $accountId) {
                return false;
            }
            if ($statuses !== [] && !in_array($task['status'] ?? '', $statuses, true)) {
                return false;
            }
            return true;
        }));
    }

    public function generateFromNewPost(array $payload): array
    {
        $postId = (string) ($payload['post_id'] ?? '');
        $artistId = (string) ($payload['artist_id'] ?? '');
        $detectedAt = (string) ($payload['detected_at'] ?? now_utc());
        $post = App::posts()->get($postId);
        if ($post === null) {
            throw new RuntimeException('POST_NOT_FOUND');
        }
        $rules = App::rules()->applicableForArtist($artistId);
        $created = [];
        foreach ($rules as $rule) {
            $accountId = (string) $rule['account_id'];
            if ($this->existsForPostAccount($postId, $accountId)) {
                continue;
            }
            $delay = (int) ($rule['delay_seconds'] ?? 0);
            $scheduledAt = add_seconds($detectedAt, $delay);
            $now = now_utc();
            $task = $this->storage->insert('tasks', [
                'id' => generate_id('task'),
                'account_id' => $accountId,
                'post_id' => $postId,
                'rule_id' => $rule['id'],
                'created_at' => $now,
                'scheduled_at' => $scheduledAt,
                'original_scheduled_at' => $scheduledAt,
                'started_at' => null,
                'completed_at' => null,
                'status' => 'pending',
                'attempts' => 0,
                'last_error' => null,
                'error_code' => null,
            ]);
            $created[] = $task;
            Logger::app('TASK_CREATED ' . $accountId . ' scheduled_at=' . $scheduledAt);
            App::history()->append([
                'task_id' => $task['id'],
                'account_id' => $accountId,
                'post_id' => $postId,
                'event' => 'task_created',
                'status' => 'success',
                'details' => ['scheduled_at' => $scheduledAt, 'delay_seconds' => $delay],
            ]);
        }
        App::posts()->update($postId, [
            'status' => $created === [] ? 'ignored' : 'queued',
        ]);
        return $created;
    }

    public function existsForPostAccount(string $postId, string $accountId): bool
    {
        foreach ($this->all() as $task) {
            if (($task['post_id'] ?? '') === $postId && ($task['account_id'] ?? '') === $accountId) {
                return true;
            }
        }
        return false;
    }

    public function update(string $id, array $changes): ?array
    {
        return $this->storage->update('tasks', $id, $changes);
    }

    public function cancel(string $id): ?array
    {
        $task = $this->get($id);
        if ($task === null) {
            return null;
        }
        if (in_array($task['status'] ?? '', ['completed', 'cancelled'], true)) {
            return $task;
        }
        return $this->update($id, ['status' => 'cancelled', 'completed_at' => now_utc()]);
    }

    public function retry(string $id): ?array
    {
        $task = $this->get($id);
        if ($task === null) {
            return null;
        }
        return $this->update($id, [
            'status' => 'ready',
            'started_at' => null,
            'completed_at' => null,
            'last_error' => null,
            'error_code' => null,
        ]);
    }

    public function supervision(): array
    {
        $accounts = [];
        foreach (App::accounts()->all() as $account) {
            $accounts[$account['id']] = $account;
        }
        $posts = [];
        foreach (App::posts()->all() as $post) {
            $posts[$post['id']] = $post;
        }
        $artists = [];
        foreach (App::artists()->all() as $artist) {
            $artists[$artist['id']] = $artist;
        }
        $now = parse_utc(now_utc());
        $rows = [];
        $countdowns = [];
        foreach ($this->all() as $task) {
            $account = $accounts[$task['account_id'] ?? ''] ?? null;
            $post = $posts[$task['post_id'] ?? ''] ?? null;
            $artist = $post ? ($artists[$post['artist_id'] ?? ''] ?? null) : null;
            $scheduled = (string) ($task['scheduled_at'] ?? '');
            $eta = null;
            if (in_array($task['status'] ?? '', ['pending', 'ready'], true) && $scheduled !== '') {
                $eta = (parse_utc($scheduled)->format('U.u') - $now->format('U.u'));
            }
            $rows[] = [
                'task' => $task,
                'account_label' => $account['label'] ?? ($task['account_id'] ?? ''),
                'target_label' => $artist['name'] ?? ($post['username'] ?? ''),
                'countdown_s' => $eta,
            ];
            if ($eta !== null && $eta > 0 && $account) {
                $current = $countdowns[$account['id']] ?? null;
                if ($current === null || $eta < $current['countdown_s']) {
                    $countdowns[$account['id']] = [
                        'account_id' => $account['id'],
                        'label' => $account['label'],
                        'countdown_s' => $eta,
                        'task_id' => $task['id'],
                    ];
                }
            }
        }
        usort($rows, fn ($a, $b) => strcmp((string) ($a['task']['scheduled_at'] ?? ''), (string) ($b['task']['scheduled_at'] ?? '')));
        return [
            'items' => $rows,
            'countdowns' => array_values($countdowns),
        ];
    }

    public function counts(): array
    {
        $items = $this->all();
        $now = parse_utc(now_utc())->format('U.u');
        $counts = [
            'pending' => 0,
            'ready' => 0,
            'imminent' => 0,
            'running' => 0,
            'completed' => 0,
            'failed' => 0,
            'cancelled' => 0,
        ];
        foreach ($items as $task) {
            $status = $task['status'] ?? '';
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
            if (in_array($status, ['pending', 'ready'], true)) {
                $scheduled = parse_utc((string) $task['scheduled_at'])->format('U.u');
                if ($scheduled - $now <= 5) {
                    $counts['imminent']++;
                }
            }
        }
        $counts['scheduled'] = $counts['pending'] + $counts['ready'];
        return $counts;
    }
}
