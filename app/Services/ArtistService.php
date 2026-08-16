<?php

declare(strict_types=1);

final class ArtistService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read('artists')['items'] ?? [];
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('artists', $id);
    }

    public function findByUsername(string $username): ?array
    {
        $username = normalize_username($username);
        foreach ($this->all() as $artist) {
            if (normalize_username((string) ($artist['username'] ?? '')) === $username) {
                return $artist;
            }
        }
        return null;
    }

    public function knownUsernames(): array
    {
        $names = [];
        foreach ($this->all() as $artist) {
            if (!empty($artist['username'])) {
                $names[] = normalize_username((string) $artist['username']);
            }
        }
        foreach (App::accounts()->all() as $account) {
            if (!empty($account['username'])) {
                $names[] = normalize_username((string) $account['username']);
            }
        }
        return array_values(array_unique($names));
    }

    public function create(array $input): array
    {
        $name = trim((string) ($input['name'] ?? ''));
        $username = normalize_username((string) ($input['username'] ?? ''));
        if ($name === '' || $username === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        $now = now_utc();
        return $this->storage->insert('artists', [
            'id' => generate_id('artist'),
            'name' => $name,
            'username' => $username,
            'tiktok_url' => (string) ($input['tiktok_url'] ?? ('https://www.tiktok.com/' . $username)),
            'category' => (string) ($input['category'] ?? ''),
            'country' => (string) ($input['country'] ?? ''),
            'priority' => (int) ($input['priority'] ?? 1),
            'enabled' => (bool) ($input['enabled'] ?? true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(string $id, array $changes): ?array
    {
        $allowed = ['name', 'username', 'tiktok_url', 'category', 'country', 'priority', 'enabled'];
        $patch = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $changes)) {
                $patch[$key] = $key === 'username' ? normalize_username((string) $changes[$key]) : $changes[$key];
            }
        }
        $patch['updated_at'] = now_utc();
        return $this->storage->update('artists', $id, $patch);
    }

    public function delete(string $id): bool
    {
        App::targets()->deleteByArtist($id);
        App::scenarios()->deleteByArtist($id);
        return $this->storage->delete('artists', $id);
    }
}
