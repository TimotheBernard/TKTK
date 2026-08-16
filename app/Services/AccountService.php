<?php

declare(strict_types=1);

final class AccountService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read('accounts')['items'] ?? [];
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('accounts', $id);
    }

    public function create(array $input): array
    {
        $label = trim((string) ($input['label'] ?? ''));
        $username = normalize_username((string) ($input['username'] ?? ''));
        if ($label === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        $group = strtoupper(trim((string) ($input['group'] ?? 'AUTRES')));
        if (!in_array($group, account_groups(), true)) {
            $group = 'AUTRES';
        }
        $id = generate_id('account');
        $now = now_utc();
        $account = [
            'id' => $id,
            'label' => $label,
            'username' => $username,
            'display_name' => trim((string) ($input['display_name'] ?? $label)),
            'profile_picture' => (string) ($input['profile_picture'] ?? ''),
            'profile_url' => $username !== '' ? 'https://www.tiktok.com/' . $username : '',
            'group' => $group,
            'favorite' => (bool) ($input['favorite'] ?? false),
            'enabled' => (bool) ($input['enabled'] ?? true),
            'connection_status' => 'unknown',
            'runtime_status' => 'idle',
            'browser_profile' => $id,
            'current_task_id' => null,
            'next_task_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'last_activity' => null,
            'notes' => (string) ($input['notes'] ?? ''),
            'error_code' => null,
            'error_message' => null,
            'status' => 'idle',
            'session_status' => 'unknown',
            'session_checked_at' => null,
        ];
        $this->ensureProfileDir($id);
        $created = $this->storage->insert('accounts', $account);
        App::history()->append([
            'account_id' => $id,
            'event' => 'ACCOUNT_ADDED',
            'status' => 'success',
            'details' => ['username' => $username],
        ]);
        return $created;
    }

    public function update(string $id, array $changes): ?array
    {
        $allowed = [
            'label', 'username', 'display_name', 'profile_picture', 'profile_url', 'group', 'favorite',
            'enabled', 'notes', 'runtime_status', 'connection_status', 'current_task_id', 'next_task_id',
            'last_activity', 'error_code', 'error_message', 'status', 'session_status', 'session_checked_at',
        ];
        $patch = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $changes)) {
                $patch[$key] = $changes[$key];
            }
        }
        if (isset($patch['username'])) {
            $patch['username'] = normalize_username((string) $patch['username']);
            $patch['profile_url'] = $patch['username'] !== '' ? 'https://www.tiktok.com/' . $patch['username'] : ($patch['profile_url'] ?? '');
        }
        if (isset($patch['group'])) {
            $group = strtoupper((string) $patch['group']);
            $patch['group'] = in_array($group, account_groups(), true) ? $group : 'AUTRES';
        }
        if (isset($patch['enabled']) && $patch['enabled'] === false) {
            $patch['runtime_status'] = 'disabled';
            $patch['status'] = 'disabled';
        }
        if (isset($patch['enabled']) && $patch['enabled'] === true) {
            $current = $this->get($id);
            if ($current && (($current['runtime_status'] ?? '') === 'disabled' || ($current['status'] ?? '') === 'disabled')) {
                $patch['runtime_status'] = 'idle';
                $patch['status'] = 'idle';
            }
        }
        if (isset($patch['runtime_status'])) {
            $patch['status'] = $this->legacyStatus((string) $patch['runtime_status']);
        }
        if (isset($patch['connection_status'])) {
            $patch['session_status'] = $this->legacySession((string) $patch['connection_status']);
        }
        if (isset($patch['status']) && !isset($changes['runtime_status'])) {
            $patch['runtime_status'] = $this->runtimeFromLegacy((string) $patch['status']);
        }
        if (isset($patch['session_status']) && !isset($changes['connection_status'])) {
            $patch['connection_status'] = $this->connectionFromLegacy((string) $patch['session_status']);
        }
        $patch['updated_at'] = now_utc();
        return $this->storage->update('accounts', $id, $patch);
    }

    public function delete(string $id): bool
    {
        $running = App::tasks()->forAccount($id, ['running', 'starting']);
        if ($running !== []) {
            throw new RuntimeException('ACCOUNT_BUSY');
        }
        App::targets()->deleteByAccount($id);
        App::scenarios()->deleteByAccount($id);
        return $this->storage->delete('accounts', $id);
    }

    public function counts(): array
    {
        $items = $this->all();
        $counts = [
            'total' => count($items),
            'active' => 0,
            'busy' => 0,
            'sessions_valid' => 0,
            'sessions_expired' => 0,
            'disabled' => 0,
            'error' => 0,
            'idle' => 0,
        ];
        foreach ($items as $item) {
            $runtime = (string) ($item['runtime_status'] ?? $item['status'] ?? 'idle');
            $connection = (string) ($item['connection_status'] ?? $item['session_status'] ?? 'unknown');
            if (!empty($item['enabled']) && $runtime !== 'disabled') {
                $counts['active']++;
            }
            if (in_array($runtime, ['busy', 'starting'], true)) {
                $counts['busy']++;
            }
            if ($runtime === 'idle') {
                $counts['idle']++;
            }
            if ($connection === 'connected') {
                $counts['sessions_valid']++;
            }
            if (in_array($connection, ['expired', 'reauthentication_required'], true) || $runtime === 'session_expired') {
                $counts['sessions_expired']++;
            }
            if (empty($item['enabled']) || $runtime === 'disabled') {
                $counts['disabled']++;
            }
            if ($runtime === 'error') {
                $counts['error']++;
            }
        }
        return $counts;
    }

    public function enrich(array $account): array
    {
        $id = (string) ($account['id'] ?? '');
        $targets = App::targets()->forAccount($id);
        $scenarios = App::scenarios()->forAccount($id);
        $tasks = App::tasks()->forAccount($id);
        $pending = array_values(array_filter($tasks, fn ($t) => in_array($t['status'] ?? '', ['pending', 'ready', 'queued'], true)));
        usort($pending, fn ($a, $b) => strcmp((string) ($a['scheduled_at'] ?? ''), (string) ($b['scheduled_at'] ?? '')));
        $running = array_values(array_filter($tasks, fn ($t) => in_array($t['status'] ?? '', ['running', 'starting'], true)));
        $account['followed_count'] = count($targets);
        $account['scenario_count'] = count($scenarios);
        $account['current_task'] = $running[0] ?? null;
        $account['next_task'] = $pending[0] ?? null;
        $account['targets'] = $targets;
        $account['scenarios'] = $scenarios;
        $account['runtime_status'] = $account['runtime_status'] ?? $this->runtimeFromLegacy((string) ($account['status'] ?? 'idle'));
        $account['connection_status'] = $account['connection_status'] ?? $this->connectionFromLegacy((string) ($account['session_status'] ?? 'unknown'));
        $account['group'] = $account['group'] ?? 'AUTRES';
        $account['favorite'] = (bool) ($account['favorite'] ?? false);
        $account['display_name'] = $account['display_name'] ?? $account['label'] ?? '';
        return $account;
    }

    private function ensureProfileDir(string $id): void
    {
        $dir = PROFILES_PATH . '/' . $id;
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }

    private function legacyStatus(string $runtime): string
    {
        return match ($runtime) {
            'disabled' => 'disabled',
            'busy', 'starting', 'queued' => 'busy',
            'session_expired' => 'session_expired',
            'error', 'disconnected' => 'error',
            default => 'idle',
        };
    }

    private function legacySession(string $connection): string
    {
        return match ($connection) {
            'connected' => 'connected',
            'expired', 'reauthentication_required' => 'expired',
            default => 'unknown',
        };
    }

    private function runtimeFromLegacy(string $status): string
    {
        return match ($status) {
            'busy' => 'busy',
            'disabled' => 'disabled',
            'session_expired' => 'session_expired',
            'error', 'offline' => 'error',
            default => 'idle',
        };
    }

    private function connectionFromLegacy(string $session): string
    {
        return match ($session) {
            'connected' => 'connected',
            'expired', 'reauthentication_required' => 'expired',
            default => 'unknown',
        };
    }
}
