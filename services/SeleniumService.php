<?php

declare(strict_types=1);

final class SeleniumService
{
    /** @param array<string, scalar> $flags */
    public static function run(array $flags): array
    {
        $settings = App::settings()->get();
        $python = trim((string) ($settings['python_path'] ?? 'python3'));
        if ($python === '') {
            $python = 'python3';
        }
        $cmd = escapeshellarg($python) . ' ' . escapeshellarg(SELENIUM_PATH . '/runner.py');
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
        return self::run([
            'account-id' => $accountId,
            'action' => 'launch',
            'url' => $url,
        ]);
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
