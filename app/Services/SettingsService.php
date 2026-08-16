<?php

declare(strict_types=1);

final class SettingsService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function defaults(): array
    {
        return [
            'version' => STORAGE_VERSION,
            'scheduler_enabled' => true,
            'selenium_enabled' => true,
            'python_path' => env_value('PYTHON_PATH', 'python3'),
            'chrome_path' => env_value('CHROME_PATH', '') ?? '',
            'chromedriver_path' => env_value('CHROMEDRIVER_PATH', '') ?? '',
            'watch_interval_seconds' => 15,
            'watch_enabled' => true,
            'watcher_profile' => '_watcher',
            'watch_posts_limit' => 8,
            'browser_timeout_seconds' => 30,
            'browser_warmup_seconds' => 8,
            'verbose_logs' => true,
            'dev_mode' => env_bool('DEV_MODE', true),
            'timezone' => 'UTC',
            'dashboard_poll_ms' => 1000,
            'worker_poll_ms' => 100,
            'stale_running_timeout_seconds' => 120,
            'max_attempts' => 5,
            'backup_keep' => 50,
            'tiktok_api_enabled' => env_bool('TIKTOK_API_ENABLED', false),
            'tiktok_client_key' => env_value('TIKTOK_CLIENT_KEY', '') ?? '',
        ];
    }

    public function get(): array
    {
        $doc = $this->storage->read('settings');
        $merged = array_merge($this->defaults(), $doc);
        if (env_value('DEV_MODE') !== null) {
            $merged['dev_mode'] = env_bool('DEV_MODE', (bool) $merged['dev_mode']);
        }
        return $merged;
    }

    public function update(array $changes): array
    {
        $allowed = array_keys($this->defaults());
        return $this->storage->mutate('settings', function (array $doc) use ($changes, $allowed) {
            $merged = array_merge($this->defaults(), $doc);
            foreach ($changes as $key => $value) {
                if (!in_array($key, $allowed, true) || $key === 'version' || $key === 'tiktok_client_key') {
                    continue;
                }
                $merged[$key] = $value;
            }
            return $merged;
        });
    }

    public function isDevMode(): bool
    {
        if (env_value('DEV_MODE') !== null) {
            return env_bool('DEV_MODE', false);
        }
        $value = $this->get()['dev_mode'] ?? false;
        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }
        return (bool) $value;
    }
}
