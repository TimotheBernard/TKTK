<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$ids = App::targets()->watchedArtistIds();
$artists = [];
foreach ($ids as $id) {
    $artist = App::artists()->get($id);
    if ($artist !== null && !empty($artist['enabled'])) {
        $artists[] = $artist;
    }
}
fwrite(STDOUT, json_encode(['success' => true, 'data' => ['artists' => $artists]]) . PHP_EOL);
