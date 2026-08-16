<?php

declare(strict_types=1);

final class TargetService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read('targets')['items'] ?? [];
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('targets', $id);
    }

    public function forAccount(string $accountId): array
    {
        return array_values(array_filter($this->all(), fn ($t) => ($t['account_id'] ?? '') === $accountId));
    }

    public function forArtist(string $artistId): array
    {
        return array_values(array_filter($this->all(), fn ($t) => ($t['artist_id'] ?? '') === $artistId));
    }

    public function findCouple(string $accountId, string $artistId): ?array
    {
        foreach ($this->all() as $target) {
            if (($target['account_id'] ?? '') === $accountId && ($target['artist_id'] ?? '') === $artistId) {
                return $target;
            }
        }
        return null;
    }

    public function watchedArtistIds(): array
    {
        $ids = [];
        foreach ($this->all() as $target) {
            if (!empty($target['enabled']) && !empty($target['check_new_posts'])) {
                $ids[] = (string) $target['artist_id'];
            }
        }
        return array_values(array_unique($ids));
    }

    public function create(array $input): array
    {
        $accountId = (string) ($input['account_id'] ?? '');
        $artistId = (string) ($input['artist_id'] ?? '');
        if ($accountId === '' || $artistId === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        if ($this->findCouple($accountId, $artistId) !== null) {
            throw new RuntimeException('DUPLICATE_TARGET');
        }
        $now = now_utc();
        return $this->storage->insert('targets', [
            'id' => generate_id('target'),
            'account_id' => $accountId,
            'artist_id' => $artistId,
            'enabled' => (bool) ($input['enabled'] ?? true),
            'check_new_posts' => (bool) ($input['check_new_posts'] ?? true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(string $id, array $changes): ?array
    {
        $patch = [];
        foreach (['enabled', 'check_new_posts'] as $key) {
            if (array_key_exists($key, $changes)) {
                $patch[$key] = (bool) $changes[$key];
            }
        }
        $patch['updated_at'] = now_utc();
        return $this->storage->update('targets', $id, $patch);
    }

    public function delete(string $id): bool
    {
        return $this->storage->delete('targets', $id);
    }

    public function deleteByAccount(string $accountId): void
    {
        foreach ($this->forAccount($accountId) as $target) {
            $this->delete((string) $target['id']);
        }
    }

    public function deleteByArtist(string $artistId): void
    {
        foreach ($this->forArtist($artistId) as $target) {
            $this->delete((string) $target['id']);
        }
    }

    public function orphans(): array
    {
        $orphans = [];
        foreach ($this->all() as $target) {
            $rule = App::rules()->findCouple((string) $target['account_id'], (string) $target['artist_id']);
            if ($rule === null) {
                $orphans[] = $target;
            }
        }
        return $orphans;
    }
}
