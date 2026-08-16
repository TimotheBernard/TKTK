<?php

declare(strict_types=1);

final class SimulationService
{
    public function requireDev(): void
    {
        if (!App::settings()->isDevMode()) {
            throw new RuntimeException('DEV_MODE_REQUIRED');
        }
    }

    /**
     * @param list<int> $delays
     */
    public function newPostFanout(array $delays = [2, 4, 5, 8, 12], ?string $detectedAt = null): array
    {
        $this->requireDev();
        $detectedAt = $detectedAt ?? now_utc();
        $artist = $this->ensureArtist('@sim_artist', 'Artiste principal');
        $accounts = [];
        $scenarios = [];
        foreach ($delays as $i => $delay) {
            $n = $i + 1;
            $label = sprintf('Account %02d', $n);
            $account = $this->ensureAccount($label, '@sim_account_' . $n);
            $this->ensureTarget($account['id'], $artist['id']);
            $scenarios[] = $this->ensureScenario($account['id'], $artist['id'], (int) $delay);
            $accounts[] = $account;
        }
        $videoId = (string) random_int(100000000000, 999999999999);
        $result = App::posts()->ingest([
            'artist_id' => $artist['id'],
            'username' => $artist['username'],
            'url' => 'https://www.tiktok.com/' . $artist['username'] . '/video/' . $videoId,
            'video_id' => $videoId,
            'caption' => 'DEV simulation',
            'published_at' => $detectedAt,
            'detected_at' => $detectedAt,
            'source' => 'simulation',
            'provider' => 'simulation',
        ]);
        return [
            'detected_at' => $detectedAt,
            'accounts' => $accounts,
            'scenarios' => $scenarios,
            'result' => $result,
        ];
    }

    public function emit(string $type, array $payload = []): array
    {
        $this->requireDev();
        return App::events()->emit($type, $payload);
    }

    public function sessionExpired(string $accountId): array
    {
        $this->requireDev();
        $account = App::accounts()->update($accountId, [
            'runtime_status' => 'session_expired',
            'connection_status' => 'expired',
            'status' => 'session_expired',
            'session_status' => 'expired',
            'error_code' => 'SESSION_EXPIRED',
        ]);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        App::events()->emit('SESSION_EXPIRED', ['account_id' => $accountId, 'simulated' => true]);
        return $account;
    }

    public function completeTask(string $taskId, bool $success): array
    {
        $this->requireDev();
        $task = App::tasks()->get($taskId);
        if ($task === null) {
            throw new RuntimeException('TASK_NOT_FOUND');
        }
        $updated = App::tasks()->update($taskId, [
            'status' => $success ? 'completed' : 'failed',
            'started_at' => $task['started_at'] ?? now_utc(),
            'completed_at' => now_utc(),
            'error_code' => $success ? null : 'UNKNOWN_ERROR',
            'last_error' => $success ? null : 'Simulated failure',
        ]);
        App::history()->append([
            'task_id' => $taskId,
            'account_id' => $task['account_id'] ?? null,
            'post_id' => $task['post_id'] ?? null,
            'event' => $success ? 'SCENARIO_COMPLETED' : 'SCENARIO_FAILED',
            'status' => $success ? 'success' : 'error',
            'details' => ['simulated' => true],
        ]);
        return $updated ?? $task;
    }

    private function ensureArtist(string $username, string $name): array
    {
        $existing = App::artists()->findByUsername($username);
        if ($existing !== null) {
            return $existing;
        }
        return App::artists()->create([
            'name' => $name,
            'username' => $username,
            'category' => 'simulation',
        ]);
    }

    private function ensureAccount(string $label, string $username): array
    {
        foreach (App::accounts()->all() as $account) {
            if (($account['label'] ?? '') === $label) {
                return $account;
            }
        }
        return App::accounts()->create([
            'label' => $label,
            'username' => $username,
            'group' => 'TESTS',
            'notes' => 'DEV simulation',
        ]);
    }

    private function ensureTarget(string $accountId, string $artistId): array
    {
        $existing = App::targets()->findCouple($accountId, $artistId);
        if ($existing !== null) {
            return $existing;
        }
        return App::targets()->create([
            'account_id' => $accountId,
            'artist_id' => $artistId,
        ]);
    }

    private function ensureScenario(string $accountId, string $artistId, int $delay): array
    {
        $existing = App::scenarios()->findCouple($accountId, $artistId);
        if ($existing !== null) {
            return App::scenarios()->update($existing['id'], [
                'timing' => ['type' => 'fixed', 'delay_seconds' => $delay],
                'enabled' => true,
                'steps' => [['type' => 'OPEN_POST']],
            ]) ?? $existing;
        }
        return App::scenarios()->create([
            'account_id' => $accountId,
            'artist_id' => $artistId,
            'label' => 'NEW_POST delay ' . $delay . 's',
            'trigger' => 'NEW_POST',
            'timing' => ['type' => 'fixed', 'delay_seconds' => $delay],
            'steps' => [['type' => 'OPEN_POST']],
            'enabled' => true,
        ]);
    }
}
