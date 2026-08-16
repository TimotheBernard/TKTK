<?php

declare(strict_types=1);

final class EventDispatcher
{
    /** @var array<string, list<callable>> */
    private array $listeners = [];

    public function __construct(private StorageInterface $storage)
    {
        $this->listeners['NEW_POST'] = [
            fn (array $payload) => App::history()->append([
                'event' => 'NEW_POST',
                'post_id' => $payload['post_id'] ?? null,
                'artist_id' => $payload['artist_id'] ?? null,
                'status' => 'success',
                'details' => $payload,
            ]),
            fn (array $payload) => App::scenarios()->matching((string) ($payload['artist_id'] ?? ''), 'NEW_POST')
                ? App::tasks()->generateFromNewPost($payload)
                : App::tasks()->generateFromNewPost($payload),
        ];
        $this->listeners['SCHEDULED_POST'] = [
            fn (array $payload) => App::publications()->queueTask((string) ($payload['publication_id'] ?? '')),
        ];
        $this->listeners['SESSION_EXPIRED'] = [
            fn (array $payload) => App::history()->append([
                'event' => 'SESSION_EXPIRED',
                'account_id' => $payload['account_id'] ?? null,
                'status' => 'error',
                'details' => $payload,
            ]),
        ];
    }

    public function listen(string $type, callable $listener): void
    {
        $this->listeners[$type][] = $listener;
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
            'event' => $type,
            'payload' => $payload,
            'created_at' => now_utc(),
            'processed' => false,
            'processed_at' => null,
        ];
        $event = $this->storage->insert('events', $event);
        Logger::app($type . ' ' . ($payload['post_id'] ?? $payload['publication_id'] ?? $event['id']));

        $results = [];
        foreach ($this->listeners[$type] ?? [] as $listener) {
            try {
                $results[] = $listener($payload);
            } catch (Throwable $e) {
                Logger::error('LISTENER_FAILED ' . $type . ' ' . $e->getMessage());
            }
        }

        $tasks = [];
        foreach ($results as $result) {
            if (is_array($result) && array_is_list($result)) {
                $tasks = array_merge($tasks, $result);
            }
        }

        $this->storage->update('events', $event['id'], [
            'processed' => true,
            'processed_at' => now_utc(),
        ]);
        $event['processed'] = true;
        $event['processed_at'] = now_utc();
        $event['tasks'] = $tasks;
        if ($tasks !== []) {
            Logger::app('TASKS_CREATED count=' . count($tasks) . ' event=' . $type);
        }
        return $event;
    }
}
