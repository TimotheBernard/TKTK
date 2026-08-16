<?php

declare(strict_types=1);

interface StorageInterface
{
    public function read(string $name): array;

    public function write(string $name, array $document): void;

    public function find(string $name, callable $predicate): array;

    public function findById(string $name, string $id): ?array;

    public function insert(string $name, array $item): array;

    public function update(string $name, string $id, array $changes): ?array;

    public function delete(string $name, string $id): bool;

    /**
     * @param callable(array): array $mutator
     */
    public function mutate(string $name, callable $mutator): array;
}
