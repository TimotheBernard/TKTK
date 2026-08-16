<?php

declare(strict_types=1);

final class BackupService
{
    public function __construct(private JsonStorage $storage)
    {
    }

    public function snapshot(string $name): ?string
    {
        $file = $this->storage->path($name);
        if (!is_file($file)) {
            return null;
        }
        $dir = DATA_PATH . '/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $stamp = (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Ymd-His-v');
        $dest = $dir . '/' . $name . '-' . $stamp . '.json';
        copy($file, $dest);
        $this->rotate();
        return $dest;
    }

    public function snapshotAll(): array
    {
        $created = [];
        foreach (['accounts', 'artists', 'targets', 'posts', 'rules', 'tasks', 'history', 'events', 'settings', 'users'] as $name) {
            $path = $this->snapshot($name);
            if ($path !== null) {
                $created[] = $path;
            }
        }
        return $created;
    }

    private function rotate(): void
    {
        $keep = (int) (App::settings()->get()['backup_keep'] ?? 50);
        $dir = DATA_PATH . '/backups';
        $files = glob($dir . '/*.json') ?: [];
        rsort($files, SORT_STRING);
        foreach (array_slice($files, $keep) as $old) {
            @unlink($old);
        }
    }
}
