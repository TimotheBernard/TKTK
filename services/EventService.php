<?php

declare(strict_types=1);

final class EventService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function all(): array
    {
        $items = $this->storage->read('events')['items'] ?? [];
        usort($items, fn ($a, $b) => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
        return $items;
    }

    public function emit(string $type, array $payload): array
    {
        $event = [
            'id' => generate_id('event'),
            'type' => $type,
            'payload' => $payload,
            'created_at' => now_utc(),
            'processed' => false,
            'processed_at' => null,
        ];
        $event = $this->storage->insert('events', $event);
        Logger::app($type . ' ' . ($payload['post_id'] ?? $event['id']));

        if ($type === 'NEW_POST') {
            App::history()->append([
                'event' => 'new_post',
                'post_id' => $payload['post_id'] ?? null,
                'status' => 'success',
                'details' => $payload,
            ]);
            $tasks = App::tasks()->generateFromNewPost($payload);
            $this->storage->update('events', $event['id'], [
                'processed' => true,
                'processed_at' => now_utc(),
            ]);
            $event['processed'] = true;
            $event['processed_at'] = now_utc();
            $event['tasks'] = $tasks;
            Logger::app('TASKS_CREATED count=' . count($tasks) . ' post=' . ($payload['post_id'] ?? ''));
        }

        return $event;
    }
}
