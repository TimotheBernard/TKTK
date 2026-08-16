<?php

declare(strict_types=1);

final class SchedulerService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function state(): array
    {
        $doc = $this->storage->read('worker_state');
        $heartbeat = $doc['heartbeat_at'] ?? null;
        $alive = false;
        if (is_string($heartbeat) && $heartbeat !== '') {
            $age = parse_utc(now_utc())->format('U.u') - parse_utc($heartbeat)->format('U.u');
            $alive = $age < 5;
        }
        $watcher = $this->storage->read('watcher_state');
        return [
            'worker' => array_merge([
                'pid' => null,
                'status' => 'stopped',
                'heartbeat_at' => null,
                'next_due_at' => null,
                'last_error' => null,
            ], $doc),
            'alive' => $alive,
            'watcher' => $watcher,
        ];
    }
}
