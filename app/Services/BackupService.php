<?php

declare(strict_types=1);

final class BackupService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function snapshot(string $name): string
    {
        $src = $this->storage instanceof JsonStorage ? $this->storage->path($name) : (DATA_PATH . '/' . $name . '.json');
        $dir = DATA_PATH . '/backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        $dest = $dir . '/' . $name . '-' . gmdate('YmdHis') . '.json';
        if (is_file($src)) {
            copy($src, $dest);
        }
        $this->rotate();
        return $dest;
    }

    public function snapshotAll(): array
    {
        $out = [];
        foreach (['accounts', 'artists', 'targets', 'scenarios', 'posts', 'tasks', 'publications', 'events', 'history', 'settings'] as $name) {
            $out[] = $this->snapshot($name);
        }
        return $out;
    }

    private function rotate(): void
    {
        $keep = (int) (App::settings()->get()['backup_keep'] ?? 50);
        $files = glob(DATA_PATH . '/backups/*.json') ?: [];
        rsort($files);
        foreach (array_slice($files, max(0, $keep)) as $file) {
            @unlink($file);
        }
    }
}
