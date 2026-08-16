<?php

declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only\n");
}

$artists = [];
foreach (App::targets()->watchedArtistIds() as $artistId) {
    $artist = App::artists()->get($artistId);
    if ($artist === null || empty($artist['enabled'])) {
        continue;
    }
    $artists[] = [
        'id' => $artist['id'],
        'username' => $artist['username'],
        'tiktok_url' => $artist['tiktok_url'] ?? ('https://www.tiktok.com/' . $artist['username']),
    ];
}

echo json_encode([
    'success' => true,
    'data' => [
        'artists' => $artists,
        'settings' => App::settings()->get(),
    ],
    'error' => null,
], JSON_UNESCAPED_SLASHES) . PHP_EOL;
