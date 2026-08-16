<?php

declare(strict_types=1);

final class AccountService
{
    public function __construct(private JsonStorage $storage)
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
        $id = generate_id('account');
        $now = now_utc();
        $account = [
            'id' => $id,
            'label' => $label,
            'username' => $username,
            'profile_url' => $username !== '' ? 'https://www.tiktok.com/' . $username : '',
            'enabled' => (bool) ($input['enabled'] ?? true),
            'status' => 'idle',
            'browser_profile' => $id,
            'session_status' => 'unknown',
            'session_checked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'last_activity' => null,
            'notes' => (string) ($input['notes'] ?? ''),
            'error_code' => null,
            'error_message' => null,
        ];
        $this->ensureProfileDir($id);
        return $this->storage->insert('accounts', $account);
    }

    public function update(string $id, array $changes): ?array
    {
        $allowed = ['label', 'username', 'profile_url', 'enabled', 'notes', 'status', 'session_status', 'session_checked_at', 'last_activity', 'error_code', 'error_message'];
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
        if (isset($patch['enabled']) && $patch['enabled'] === false) {
            $patch['status'] = 'disabled';
        }
        if (isset($patch['enabled']) && $patch['enabled'] === true) {
            $current = $this->get($id);
            if ($current && ($current['status'] ?? '') === 'disabled') {
                $patch['status'] = 'idle';
            }
        }
        $patch['updated_at'] = now_utc();
        return $this->storage->update('accounts', $id, $patch);
    }

    public function delete(string $id): bool
    {
        $running = App::tasks()->forAccount($id, ['running']);
        if ($running !== []) {
            throw new RuntimeException('ACCOUNT_BUSY');
        }
        App::targets()->deleteByAccount($id);
        App::rules()->deleteByAccount($id);
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
        ];
        foreach ($items as $item) {
            if (!empty($item['enabled']) && ($item['status'] ?? '') !== 'disabled') {
                $counts['active']++;
            }
            if (($item['status'] ?? '') === 'busy') {
                $counts['busy']++;
            }
            if (($item['session_status'] ?? '') === 'connected') {
                $counts['sessions_valid']++;
            }
            if (in_array($item['session_status'] ?? '', ['expired', 'reauthentication_required'], true) || ($item['status'] ?? '') === 'session_expired') {
                $counts['sessions_expired']++;
            }
            if (empty($item['enabled']) || ($item['status'] ?? '') === 'disabled') {
                $counts['disabled']++;
            }
            if (($item['status'] ?? '') === 'error') {
                $counts['error']++;
            }
        }
        return $counts;
    }

    private function ensureProfileDir(string $id): void
    {
        $dir = SELENIUM_PROFILES_PATH . '/' . $id;
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
    }
}
