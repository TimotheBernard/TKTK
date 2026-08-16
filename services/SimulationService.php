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
     * CDC example: post at T0, N accounts with delays in seconds.
     *
     * @param list<int> $delays
     */
    public function newPostFanout(array $delays = [2, 4, 5, 8, 12], ?string $detectedAt = null): array
    {
        $this->requireDev();
        $detectedAt = $detectedAt ?? now_utc();
        $artist = $this->ensureArtist('artist_sim_main', 'Artiste principal', '@sim_artist');
        $accounts = [];
        $rules = [];
        foreach ($delays as $i => $delay) {
            $n = $i + 1;
            $label = sprintf('Account %02d', $n);
            $account = $this->ensureAccount('sim_acc_' . $n, $label, '@sim_account_' . $n);
            $this->ensureTarget($account['id'], $artist['id']);
            $rules[] = $this->ensureRule($account['id'], $artist['id'], (int) $delay);
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
            'rules' => $rules,
            'result' => $result,
        ];
    }

    public function sessionExpired(string $accountId): array
    {
        $this->requireDev();
        $account = App::accounts()->update($accountId, [
            'status' => 'session_expired',
            'session_status' => 'expired',
            'error_code' => 'SESSION_EXPIRED',
        ]);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        App::history()->append([
            'account_id' => $accountId,
            'event' => 'session_expired',
            'status' => 'error',
            'details' => ['simulated' => true],
        ]);
        return $account;
    }

    public function browserUnavailable(string $accountId): array
    {
        $this->requireDev();
        $account = App::accounts()->update($accountId, [
            'status' => 'error',
            'error_code' => 'BROWSER_START_FAILED',
            'error_message' => 'Simulated browser unavailable',
        ]);
        if ($account === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
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
            'event' => $success ? 'task_completed' : 'task_failed',
            'status' => $success ? 'success' : 'error',
            'details' => ['simulated' => true],
        ]);
        return $updated ?? $task;
    }

    private function ensureArtist(string $seedId, string $name, string $username): array
    {
        foreach (App::artists()->all() as $artist) {
            if (($artist['username'] ?? '') === $username) {
                return $artist;
            }
        }
        return App::artists()->create([
            'name' => $name,
            'username' => $username,
            'category' => 'simulation',
        ]);
    }

    private function ensureAccount(string $seed, string $label, string $username): array
    {
        foreach (App::accounts()->all() as $account) {
            if (($account['label'] ?? '') === $label) {
                return $account;
            }
        }
        return App::accounts()->create([
            'label' => $label,
            'username' => $username,
            'notes' => 'DEV simulation ' . $seed,
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

    private function ensureRule(string $accountId, string $artistId, int $delay): array
    {
        $existing = App::rules()->findCouple($accountId, $artistId);
        if ($existing !== null) {
            return App::rules()->update($existing['id'], ['delay_seconds' => $delay, 'enabled' => true]) ?? $existing;
        }
        return App::rules()->create([
            'account_id' => $accountId,
            'artist_id' => $artistId,
            'delay_seconds' => $delay,
            'enabled' => true,
        ]);
    }
}
