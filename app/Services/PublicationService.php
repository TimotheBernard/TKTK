<?php

declare(strict_types=1);

final class PublicationService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function all(): array
    {
        $items = $this->storage->read('publications')['items'] ?? [];
        usort($items, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $items;
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('publications', $id);
    }

    public function forAccount(string $accountId): array
    {
        return array_values(array_filter($this->all(), fn ($p) => ($p['account_id'] ?? '') === $accountId));
    }

    public function create(array $input): array
    {
        $accountId = (string) ($input['account_id'] ?? '');
        if ($accountId === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        if (App::accounts()->get($accountId) === null) {
            throw new RuntimeException('ACCOUNT_NOT_FOUND');
        }
        $mode = (string) ($input['mode'] ?? 'now');
        if (!in_array($mode, ['now', 'scheduled'], true)) {
            $mode = 'now';
        }
        $scheduledAt = $mode === 'now' ? now_utc() : (string) ($input['scheduled_at'] ?? '');
        if ($mode === 'scheduled' && $scheduledAt === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        $now = now_utc();
        $publication = $this->storage->insert('publications', [
            'id' => generate_id('pub'),
            'account_id' => $accountId,
            'caption' => (string) ($input['caption'] ?? ''),
            'mentions' => array_values($input['mentions'] ?? []),
            'media' => array_values($input['media'] ?? []),
            'mode' => $mode,
            'scheduled_at' => $scheduledAt,
            'status' => 'queued',
            'created_at' => $now,
            'updated_at' => $now,
            'sent_at' => null,
            'error_code' => null,
        ]);
        App::history()->append([
            'account_id' => $accountId,
            'publication_id' => $publication['id'],
            'event' => 'PUBLICATION_SCHEDULED',
            'details' => ['mode' => $mode, 'scheduled_at' => $scheduledAt],
        ]);
        $this->queueTask($publication['id']);
        return $this->get($publication['id']) ?? $publication;
    }

    public function queueTask(string $publicationId): ?array
    {
        $publication = $this->get($publicationId);
        if ($publication === null) {
            return null;
        }
        foreach (App::tasks()->all() as $task) {
            if (($task['publication_id'] ?? '') === $publicationId && !in_array($task['status'] ?? '', ['cancelled', 'failed'], true)) {
                return $task;
            }
        }
        return App::tasks()->insert([
            'kind' => 'publication',
            'account_id' => $publication['account_id'],
            'publication_id' => $publicationId,
            'post_id' => null,
            'scenario_id' => null,
            'scheduled_at' => $publication['scheduled_at'] ?? now_utc(),
            'original_scheduled_at' => $publication['scheduled_at'] ?? now_utc(),
            'status' => 'pending',
            'steps' => [['type' => 'PUBLISH_POST']],
        ]);
    }

    public function update(string $id, array $changes): ?array
    {
        $changes['updated_at'] = now_utc();
        return $this->storage->update('publications', $id, $changes);
    }
}

final class TikTokPublishingService
{
    public function publish(array $publication): array
    {
        if (App::tiktok()->officialApiAvailable()) {
            return [
                'ok' => true,
                'channel' => 'api',
                'message' => 'Official API path selected (credentials present). Actual network publish is performed by the worker.',
            ];
        }
        return [
            'ok' => true,
            'channel' => 'capability',
            'message' => 'No official API credentials — worker will resolve Browser vs simulation.',
        ];
    }
}
