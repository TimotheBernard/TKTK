<?php

declare(strict_types=1);

final class RuleService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function all(): array
    {
        return $this->storage->read('rules')['items'] ?? [];
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('rules', $id);
    }

    public function findCouple(string $accountId, string $artistId): ?array
    {
        foreach ($this->all() as $rule) {
            if (($rule['account_id'] ?? '') === $accountId && ($rule['artist_id'] ?? '') === $artistId) {
                return $rule;
            }
        }
        return null;
    }

    public function forAccount(string $accountId): array
    {
        return array_values(array_filter($this->all(), fn ($r) => ($r['account_id'] ?? '') === $accountId));
    }

    public function applicableForArtist(string $artistId): array
    {
        $artist = App::artists()->get($artistId);
        if ($artist === null || empty($artist['enabled'])) {
            return [];
        }
        $out = [];
        foreach ($this->all() as $rule) {
            if (($rule['artist_id'] ?? '') !== $artistId || empty($rule['enabled'])) {
                continue;
            }
            $account = App::accounts()->get((string) $rule['account_id']);
            if ($account === null || empty($account['enabled']) || ($account['status'] ?? '') === 'disabled') {
                continue;
            }
            $target = App::targets()->findCouple((string) $rule['account_id'], $artistId);
            if ($target === null || empty($target['enabled']) || empty($target['check_new_posts'])) {
                continue;
            }
            $out[] = $rule;
        }
        return $out;
    }

    public function create(array $input): array
    {
        $accountId = (string) ($input['account_id'] ?? '');
        $artistId = (string) ($input['artist_id'] ?? '');
        $delay = (int) ($input['delay_seconds'] ?? 0);
        if ($accountId === '' || $artistId === '' || $delay < 0) {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        if ($this->findCouple($accountId, $artistId) !== null) {
            throw new RuntimeException('DUPLICATE_RULE');
        }
        $now = now_utc();
        return $this->storage->insert('rules', [
            'id' => generate_id('rule'),
            'account_id' => $accountId,
            'artist_id' => $artistId,
            'enabled' => (bool) ($input['enabled'] ?? true),
            'delay_seconds' => $delay,
            'priority' => (int) ($input['priority'] ?? 10),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function update(string $id, array $changes): ?array
    {
        $patch = [];
        if (array_key_exists('enabled', $changes)) {
            $patch['enabled'] = (bool) $changes['enabled'];
        }
        if (array_key_exists('delay_seconds', $changes)) {
            $delay = (int) $changes['delay_seconds'];
            if ($delay < 0) {
                throw new InvalidArgumentException('VALIDATION_ERROR');
            }
            $patch['delay_seconds'] = $delay;
        }
        if (array_key_exists('priority', $changes)) {
            $patch['priority'] = (int) $changes['priority'];
        }
        $patch['updated_at'] = now_utc();
        return $this->storage->update('rules', $id, $patch);
    }

    public function bulkDelays(array $items): array
    {
        $updated = [];
        foreach ($items as $item) {
            $id = (string) ($item['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $patch = [];
            if (array_key_exists('delay_seconds', $item)) {
                $patch['delay_seconds'] = (int) $item['delay_seconds'];
            }
            if (array_key_exists('enabled', $item)) {
                $patch['enabled'] = (bool) $item['enabled'];
            }
            $row = $this->update($id, $patch);
            if ($row !== null) {
                $updated[] = $row;
            }
        }
        return $updated;
    }

    public function delete(string $id): bool
    {
        return $this->storage->delete('rules', $id);
    }

    public function deleteByAccount(string $accountId): void
    {
        foreach ($this->forAccount($accountId) as $rule) {
            $this->delete((string) $rule['id']);
        }
    }

    public function deleteByArtist(string $artistId): void
    {
        foreach ($this->all() as $rule) {
            if (($rule['artist_id'] ?? '') === $artistId) {
                $this->delete((string) $rule['id']);
            }
        }
    }
}
