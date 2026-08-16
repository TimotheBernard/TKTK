<?php

declare(strict_types=1);

final class SchedulerService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function state(): array
    {
        $worker = $this->storage->read('worker_state');
        $watcher = $this->storage->read('watcher_state');
        $heartbeat = (string) ($worker['heartbeat_at'] ?? '');
        $alive = false;
        if ($heartbeat !== '') {
            try {
                $age = parse_utc(now_utc())->getTimestamp() - parse_utc($heartbeat)->getTimestamp();
                $alive = $age < 5;
            } catch (Throwable) {
                $alive = false;
            }
        }
        return [
            'worker' => $worker,
            'watcher' => $watcher,
            'alive' => $alive,
        ];
    }
}

final class ResourceManager
{
    public function snapshot(): array
    {
        $accounts = App::accounts()->all();
        $counts = App::accounts()->counts();
        $tasks = App::tasks()->counts();
        $worker = App::scheduler()->state();
        $activeBrowsers = (int) (($worker['worker']['active_browsers'] ?? 0));
        $cpu = $this->cpuPercent();
        $ram = $this->ramPercent();
        $payload = [
            'accounts_registered' => $counts['total'],
            'accounts_connected' => $counts['sessions_valid'],
            'accounts_expired' => $counts['sessions_expired'],
            'browsers_active' => $activeBrowsers,
            'tasks_pending' => ($tasks['pending'] ?? 0) + ($tasks['ready'] ?? 0),
            'tasks_running' => $tasks['running'] ?? 0,
            'cpu_percent' => $cpu,
            'ram_percent' => $ram,
            'worker_alive' => $worker['alive'],
            'accounts_idle' => $counts['idle'],
        ];
        App::storage()->mutate('resource_state', function (array $doc) use ($payload) {
            return array_merge($doc, $payload);
        });
        return $payload;
    }

    private function cpuPercent(): float
    {
        $load = @sys_getloadavg();
        if (!is_array($load) || !isset($load[0])) {
            return 0.0;
        }
        $cores = (int) @shell_exec('nproc') ?: 1;
        return round(min(100, ($load[0] / max(1, $cores)) * 100), 1);
    }

    private function ramPercent(): float
    {
        $meminfo = @file_get_contents('/proc/meminfo');
        if (!is_string($meminfo)) {
            return 0.0;
        }
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $avail);
        if (!isset($total[1], $avail[1]) || (int) $total[1] === 0) {
            return 0.0;
        }
        $used = (int) $total[1] - (int) $avail[1];
        return round(($used / (int) $total[1]) * 100, 1);
    }
}

final class BrowserService
{
    /** @param array<string, scalar> $flags */
    public static function run(array $flags): array
    {
        $settings = App::settings()->get();
        $python = trim((string) ($settings['python_path'] ?? 'python3')) ?: 'python3';
        $runner = BROWSER_PATH . '/runner.py';
        $cmd = escapeshellarg($python) . ' ' . escapeshellarg($runner);
        foreach ($flags as $name => $value) {
            $safe = preg_replace('/[^a-z0-9_\-]/i', '', (string) $name) ?? '';
            if ($safe === '' || $value === null || $value === '') {
                continue;
            }
            $cmd .= ' --' . $safe . ' ' . escapeshellarg((string) $value);
        }
        $output = [];
        $code = 0;
        exec($cmd . ' 2>&1', $output, $code);
        $raw = implode("\n", $output);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $decoded['exit_code'] = $code;
            return $decoded;
        }
        return [
            'success' => $code === 0,
            'raw' => $raw,
            'exit_code' => $code,
            'error' => $code === 0 ? null : ['code' => 'BROWSER_START_FAILED', 'message' => $raw !== '' ? $raw : 'Chrome runner failed'],
        ];
    }

    public static function launchAccount(string $accountId, string $username = ''): array
    {
        $url = $username !== ''
            ? 'https://www.tiktok.com/' . normalize_username($username)
            : 'https://www.tiktok.com/';
        $result = self::run([
            'account-id' => $accountId,
            'action' => 'launch',
            'url' => $url,
        ]);
        if (!empty($result['success'])) {
            App::accounts()->update($accountId, [
                'connection_status' => 'connected',
                'session_status' => 'connected',
            ]);
            App::history()->append([
                'account_id' => $accountId,
                'event' => 'SESSION_CONNECTED',
            ]);
        }
        return $result;
    }

    public static function checkSession(string $accountId): array
    {
        $result = self::run([
            'account-id' => $accountId,
            'action' => 'check_session',
        ]);
        $status = (string) ($result['session_status'] ?? 'unknown');
        $connection = $status === 'connected' ? 'connected' : ($status === 'expired' ? 'expired' : 'unknown');
        $patch = [
            'connection_status' => $connection,
            'session_status' => $status === 'expired' ? 'expired' : $status,
            'session_checked_at' => now_utc(),
        ];
        if ($connection === 'expired') {
            $patch['runtime_status'] = 'session_expired';
        }
        App::accounts()->update($accountId, $patch);
        if ($connection === 'expired') {
            App::events()->emit('SESSION_EXPIRED', ['account_id' => $accountId]);
        }
        return $result;
    }

    public static function launchWatcher(): array
    {
        $profile = (string) (App::settings()->get()['watcher_profile'] ?? '_watcher');
        return self::run([
            'profile' => $profile,
            'action' => 'launch',
            'url' => 'https://www.tiktok.com/',
        ]);
    }

    public static function checkWatcher(): array
    {
        $profile = (string) (App::settings()->get()['watcher_profile'] ?? '_watcher');
        $result = self::run([
            'profile' => $profile,
            'action' => 'check_session',
        ]);
        $status = (string) ($result['session_status'] ?? 'unknown');
        App::storage()->mutate('watcher_state', function (array $doc) use ($status, $result) {
            $doc['session_status'] = $status;
            $doc['last_error'] = empty($result['success']) ? (($result['error']['message'] ?? null) ?: 'check failed') : null;
            $doc['status'] = 'idle';
            return $doc;
        });
        return $result;
    }
}

final class RecorderService
{
    public function abstractize(array $rawEvents): array
    {
        $steps = [];
        foreach ($rawEvents as $event) {
            $type = strtoupper((string) ($event['type'] ?? ''));
            if (ActionRegistry::get($type) === null) {
                continue;
            }
            $step = ['type' => $type];
            foreach (['seconds', 'value', 'url', 'username'] as $key) {
                if (isset($event[$key])) {
                    $step[$key] = $event[$key];
                }
            }
            $steps[] = $step;
        }
        return $steps;
    }
}

final class StatsService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function compute(): array
    {
        $stats = [
            'accounts' => count(App::accounts()->all()),
            'artists' => count(App::artists()->all()),
            'scenarios' => count(App::scenarios()->all()),
            'posts' => count(App::posts()->all()),
            'publications' => count(App::publications()->all()),
            'tasks' => App::tasks()->counts(),
            'computed_at' => now_utc(),
        ];
        $this->storage->mutate('stats', fn () => array_merge(['items' => []], $stats));
        return $stats;
    }
}
