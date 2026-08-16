<?php

declare(strict_types=1);

final class JsonStorage implements StorageInterface
{
    private string $basePath;
    private int $lockTimeoutUs = 5_000_000;

    public function __construct(string $basePath)
    {
        $this->basePath = rtrim($basePath, '/');
    }

    public function path(string $name): string
    {
        $safe = preg_replace('/[^a-z0-9_\-]/i', '', $name) ?? '';
        if ($safe === '') {
            throw new RuntimeException('STORAGE_ERROR');
        }
        return $this->basePath . '/' . $safe . '.json';
    }

    public function read(string $name): array
    {
        return $this->withLock($name, function (string $file): array {
            return $this->decodeFile($file);
        }, false);
    }

    public function write(string $name, array $document): void
    {
        $this->withLock($name, function (string $file) use ($document): array {
            $document['version'] = $document['version'] ?? STORAGE_VERSION;
            $document['updated_at'] = now_utc();
            $this->atomicWrite($file, $document);
            return $document;
        }, true);
    }

    public function find(string $name, callable $predicate): array
    {
        $doc = $this->read($name);
        $items = $doc['items'] ?? [];
        return array_values(array_filter(is_array($items) ? $items : [], $predicate));
    }

    public function findById(string $name, string $id): ?array
    {
        foreach ($this->read($name)['items'] ?? [] as $item) {
            if (is_array($item) && ($item['id'] ?? '') === $id) {
                return $item;
            }
        }
        return null;
    }

    public function insert(string $name, array $item): array
    {
        return $this->withLock($name, function (string $file) use ($item): array {
            $doc = $this->decodeFile($file);
            $items = $doc['items'] ?? [];
            if (!is_array($items)) {
                $items = [];
            }
            if (!isset($item['id']) || $item['id'] === '') {
                $item['id'] = generate_id($name);
            }
            foreach ($items as $existing) {
                if (($existing['id'] ?? null) === $item['id']) {
                    throw new RuntimeException('DUPLICATE_ID');
                }
            }
            $items[] = $item;
            $doc['items'] = array_values($items);
            $doc['version'] = STORAGE_VERSION;
            $doc['updated_at'] = now_utc();
            $this->atomicWrite($file, $doc);
            return $item;
        }, true);
    }

    public function update(string $name, string $id, array $changes): ?array
    {
        return $this->withLock($name, function (string $file) use ($id, $changes): ?array {
            $doc = $this->decodeFile($file);
            $items = $doc['items'] ?? [];
            $found = null;
            foreach ($items as $i => $item) {
                if (($item['id'] ?? '') !== $id) {
                    continue;
                }
                unset($changes['id']);
                $items[$i] = array_merge($item, $changes);
                $found = $items[$i];
                break;
            }
            if ($found === null) {
                return null;
            }
            $doc['items'] = array_values($items);
            $doc['updated_at'] = now_utc();
            $this->atomicWrite($file, $doc);
            return $found;
        }, true);
    }

    public function delete(string $name, string $id): bool
    {
        return $this->withLock($name, function (string $file) use ($id): bool {
            $doc = $this->decodeFile($file);
            $items = $doc['items'] ?? [];
            $next = [];
            $deleted = false;
            foreach ($items as $item) {
                if (($item['id'] ?? '') === $id) {
                    $deleted = true;
                    continue;
                }
                $next[] = $item;
            }
            if (!$deleted) {
                return false;
            }
            $doc['items'] = array_values($next);
            $doc['updated_at'] = now_utc();
            $this->atomicWrite($file, $doc);
            return true;
        }, true);
    }

    /**
     * Mutate a document (settings, worker_state, stats) under lock.
     *
     * @param callable(array): array $mutator
     */
    public function mutate(string $name, callable $mutator): array
    {
        return $this->withLock($name, function (string $file) use ($mutator): array {
            $doc = $this->decodeFile($file);
            $next = $mutator($doc);
            if (!is_array($next)) {
                throw new RuntimeException('STORAGE_ERROR');
            }
            $next['version'] = $next['version'] ?? STORAGE_VERSION;
            $next['updated_at'] = now_utc();
            $this->atomicWrite($file, $next);
            return $next;
        }, true);
    }

    /**
     * @template T
     * @param callable(string): T $callback
     * @return T
     */
    private function withLock(string $name, callable $callback, bool $exclusive)
    {
        $file = $this->path($name);
        $lockFile = $file . '.lock';
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }

        $handle = fopen($lockFile, 'c+');
        if ($handle === false) {
            Logger::error('STORAGE_ERROR lock_open ' . $name);
            throw new RuntimeException('STORAGE_ERROR');
        }

        $start = microtime(true);
        $locked = false;
        while ((microtime(true) - $start) * 1_000_000 < $this->lockTimeoutUs) {
            $locked = flock($handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB);
            if ($locked) {
                break;
            }
            usleep(20_000);
        }

        if (!$locked) {
            fclose($handle);
            Logger::error('STORAGE_ERROR lock_timeout ' . $name);
            throw new RuntimeException('STORAGE_ERROR');
        }

        try {
            return $callback($file);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function decodeFile(string $file): array
    {
        if (!is_file($file)) {
            return ['version' => STORAGE_VERSION, 'updated_at' => now_utc(), 'items' => []];
        }
        $raw = file_get_contents($file);
        if ($raw === false || $raw === '') {
            return ['version' => STORAGE_VERSION, 'updated_at' => now_utc(), 'items' => []];
        }
        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            Logger::error('STORAGE_ERROR invalid_json ' . $file);
            throw new RuntimeException('STORAGE_ERROR');
        }
        return $decoded;
    }

    private function atomicWrite(string $file, array $document): void
    {
        $json = json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException('STORAGE_ERROR');
        }
        $tmp = $file . '.' . getmypid() . '.' . bin2hex(random_bytes(3)) . '.tmp';
        if (file_put_contents($tmp, $json . PHP_EOL, LOCK_EX) === false) {
            throw new RuntimeException('STORAGE_ERROR');
        }
        if (!rename($tmp, $file)) {
            @unlink($tmp);
            throw new RuntimeException('STORAGE_ERROR');
        }
    }
}
