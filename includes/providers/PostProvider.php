<?php

declare(strict_types=1);

interface PostProvider
{
    public function getName(): string;

    /** @return list<array<string, mixed>> */
    public function getLatestPosts(string $username): array;
}
