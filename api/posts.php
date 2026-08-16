<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $action, $id): void {
    $posts = App::posts();
    if ($method === 'GET' && $id === '') {
        $items = $posts->all();
        $status = (string) ($_GET['status'] ?? '');
        $artistId = (string) ($_GET['artist_id'] ?? '');
        if ($status !== '') {
            $items = array_values(array_filter($items, fn ($p) => ($p['status'] ?? '') === $status));
        }
        if ($artistId !== '') {
            $items = array_values(array_filter($items, fn ($p) => ($p['artist_id'] ?? '') === $artistId));
        }
        ApiResponse::items($items);
    }
    if ($method === 'GET' && $id !== '') {
        $post = $posts->get($id);
        if ($post === null) {
            throw new RuntimeException('POST_NOT_FOUND');
        }
        ApiResponse::success(['post' => $post]);
    }
    if ($method === 'POST' && $action === 'ingest') {
        $body = api_body();
        ApiResponse::success($posts->ingest($body));
    }
    if ($method === 'POST' && $action === '') {
        $body = api_body();
        $url = (string) ($body['url'] ?? '');
        $artistId = isset($body['artist_id']) ? (string) $body['artist_id'] : null;
        ApiResponse::success($posts->ingestManual($url, $artistId !== '' ? $artistId : null));
    }
    if ($method === 'PUT' && $id !== '') {
        $post = $posts->update($id, api_body());
        if ($post === null) {
            throw new RuntimeException('POST_NOT_FOUND');
        }
        ApiResponse::success(['post' => $post]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
