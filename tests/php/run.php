<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tmp = sys_get_temp_dir() . '/tktk-test-' . bin2hex(random_bytes(4));
mkdir($tmp, 0770, true);
foreach (glob($root . '/data/*.json') as $file) {
    copy($file, $tmp . '/' . basename($file));
}
putenv('TKTK_DATA_PATH=' . $tmp);
putenv('TKTK_LOG_PATH=' . $tmp);
$_SERVER['TKTK_DATA_PATH'] = $tmp;

require $root . '/includes/bootstrap.php';

$failed = 0;
function assert_true(bool $cond, string $msg): void
{
    global $failed;
    if ($cond) {
        echo "OK  $msg\n";
        return;
    }
    $failed++;
    echo "FAIL $msg\n";
}

$detected = '2026-08-16T18:42:10.000Z';
assert_true(add_seconds($detected, 2) === '2026-08-16T18:42:12.000Z', 'delay +2s');
assert_true(add_seconds($detected, 5) === '2026-08-16T18:42:15.000Z', 'delay +5s');

$sim = App::simulation();
$result = $sim->newPostFanout([2, 4, 5, 5, 8], $detected);
$tasks = $result['result']['tasks'] ?? [];
assert_true(count($tasks) === 5, '5 tasks created');

$scheduled = array_map(fn ($t) => $t['scheduled_at'], $tasks);
sort($scheduled);
assert_true($scheduled === [
    '2026-08-16T18:42:12.000Z',
    '2026-08-16T18:42:14.000Z',
    '2026-08-16T18:42:15.000Z',
    '2026-08-16T18:42:15.000Z',
    '2026-08-16T18:42:18.000Z',
], 'scheduled_at preserved including identical timestamps');

$same = array_values(array_filter($scheduled, fn ($s) => $s === '2026-08-16T18:42:15.000Z'));
assert_true(count($same) === 2, 'two tasks share 18:42:15');

$dup = App::posts()->ingest([
    'artist_id' => $result['result']['post']['artist_id'],
    'username' => $result['result']['post']['username'],
    'url' => $result['result']['post']['url'],
    'video_id' => $result['result']['post']['video_id'],
    'source' => 'manual',
    'provider' => 'manual',
]);
assert_true($dup['duplicate'] === true, 'duplicate post skipped');
assert_true(count(App::tasks()->all()) === 5, 'no extra tasks on duplicate');

$parsed = App::tiktok()->parseUrl('https://www.tiktok.com/@foo/video/1234567890');
assert_true($parsed['video_id'] === '1234567890', 'video id extracted');

$account = App::accounts()->create(['label' => 'N+1 dynamic']);
assert_true(str_starts_with($account['id'], 'account_'), 'dynamic account id');

echo $failed === 0 ? "\nAll PHP tests passed\n" : "\n$failed test(s) failed\n";
exit($failed === 0 ? 0 : 1);
