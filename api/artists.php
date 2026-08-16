<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $id): void {
    $artists = App::artists();
    if ($method === 'GET' && $id === '') {
        ApiResponse::items($artists->all());
    }
    if ($method === 'GET' && $id !== '') {
        $artist = $artists->get($id);
        if ($artist === null) {
            throw new RuntimeException('ARTIST_NOT_FOUND');
        }
        ApiResponse::success([
            'artist' => $artist,
            'targets' => App::targets()->forArtist($id),
        ]);
    }
    if ($method === 'POST') {
        ApiResponse::success(['artist' => $artists->create(api_body())], 201);
    }
    if ($method === 'PUT' && $id !== '') {
        $artist = $artists->update($id, api_body());
        if ($artist === null) {
            throw new RuntimeException('ARTIST_NOT_FOUND');
        }
        ApiResponse::success(['artist' => $artist]);
    }
    if ($method === 'DELETE' && $id !== '') {
        if (!$artists->delete($id)) {
            throw new RuntimeException('ARTIST_NOT_FOUND');
        }
        ApiResponse::success(['deleted' => true]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
