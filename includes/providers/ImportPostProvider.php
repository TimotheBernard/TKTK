<?php

declare(strict_types=1);

final class ImportPostProvider implements PostProvider
{
    public function getName(): string
    {
        return 'import';
    }

    public function getLatestPosts(string $username): array
    {
        return [];
    }
}
