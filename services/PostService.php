<?php

declare(strict_types=1);

final class PostService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function all(): array
    {
        $items = $this->storage->read('posts')['items'] ?? [];
        usort($items, fn ($a, $b) => strcmp((string) ($b['detected_at'] ?? ''), (string) ($a['detected_at'] ?? '')));
        return $items;
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('posts', $id);
    }

    public function update(string $id, array $changes): ?array
    {
        return $this->storage->update('posts', $id, $changes);
    }

    public function findDuplicate(?string $videoId, string $url): ?array
    {
        foreach ($this->all() as $post) {
            if ($videoId && ($post['video_id'] ?? '') === $videoId) {
                return $post;
            }
            if (($post['url'] ?? '') === $url) {
                return $post;
            }
        }
        return null;
    }

    public function ingestManual(string $url, ?string $artistId = null): array
    {
        $parsed = App::tiktok()->parseUrl($url);
        if ($parsed === null || $parsed['video_id'] === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        $artist = $artistId ? App::artists()->get($artistId) : App::artists()->findByUsername($parsed['username']);
        if ($artist === null) {
            throw new RuntimeException('ARTIST_NOT_FOUND');
        }
        return $this->ingest([
            'artist_id' => $artist['id'],
            'username' => $parsed['username'] ?: $artist['username'],
            'url' => $parsed['url'],
            'video_id' => $parsed['video_id'],
            'caption' => '',
            'published_at' => now_utc(),
            'source' => 'manual',
            'provider' => 'manual',
        ]);
    }

    /**
     * @param array<string, mixed> $input
     * @return array{post: array, duplicate: bool, event?: array, tasks?: array}
     */
    public function ingest(array $input): array
    {
        $url = (string) ($input['url'] ?? '');
        $videoId = (string) ($input['video_id'] ?? '');
        $artistId = (string) ($input['artist_id'] ?? '');
        if ($url === '' || $artistId === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        $duplicate = $this->findDuplicate($videoId !== '' ? $videoId : null, $url);
        if ($duplicate !== null) {
            return ['post' => $duplicate, 'duplicate' => true];
        }
        $detectedAt = (string) ($input['detected_at'] ?? now_utc());
        $post = $this->storage->insert('posts', [
            'id' => generate_id('post'),
            'artist_id' => $artistId,
            'username' => normalize_username((string) ($input['username'] ?? '')),
            'url' => $url,
            'video_id' => $videoId,
            'caption' => (string) ($input['caption'] ?? ''),
            'published_at' => (string) ($input['published_at'] ?? $detectedAt),
            'detected_at' => $detectedAt,
            'status' => 'new',
            'source' => (string) ($input['source'] ?? 'manual'),
            'provider' => (string) ($input['provider'] ?? 'manual'),
        ]);
        $event = App::events()->emit('NEW_POST', [
            'post_id' => $post['id'],
            'artist_id' => $artistId,
            'published_at' => $post['published_at'],
            'detected_at' => $detectedAt,
        ]);
        return [
            'post' => App::posts()->get($post['id']),
            'duplicate' => false,
            'event' => $event,
            'tasks' => $event['tasks'] ?? [],
        ];
    }
}
