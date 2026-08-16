<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$storage = App::storage();
$rules = $storage->read('rules')['items'] ?? [];
$scenarios = $storage->read('scenarios')['items'] ?? [];
if ($rules !== [] && $scenarios === []) {
    App::scenarios()->all();
    echo "Migrated rules → scenarios\n";
}
foreach (App::accounts()->all() as $account) {
    $patch = [];
    if (!isset($account['runtime_status'])) {
        $patch['runtime_status'] = $account['status'] ?? 'idle';
    }
    if (!isset($account['connection_status'])) {
        $patch['connection_status'] = $account['session_status'] ?? 'unknown';
    }
    if (!isset($account['group'])) {
        $patch['group'] = 'AUTRES';
    }
    if ($patch !== []) {
        App::accounts()->update((string) $account['id'], $patch);
    }
}
echo "Storage migration complete\n";
