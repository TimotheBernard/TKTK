<?php

declare(strict_types=1);

final class HistoryService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function all(): array
    {
        $items = $this->storage->read('history')['items'] ?? [];
        usort($items, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $items;
    }

    public function recent(int $limit = 50): array
    {
        return array_slice($this->all(), 0, $limit);
    }

    public function append(array $entry): array
    {
        $item = array_merge([
            'id' => generate_id('history'),
            'task_id' => null,
            'account_id' => null,
            'artist_id' => null,
            'post_id' => null,
            'publication_id' => null,
            'scenario_id' => null,
            'event' => 'unknown',
            'status' => 'success',
            'created_at' => now_utc(),
            'duration_ms' => 0,
            'details' => [],
        ], $entry);
        return $this->storage->insert('history', $item);
    }

    public function forAccount(string $accountId): array
    {
        return array_values(array_filter($this->all(), fn ($h) => ($h['account_id'] ?? '') === $accountId));
    }
}
