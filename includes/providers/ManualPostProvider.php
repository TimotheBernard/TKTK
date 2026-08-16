<?php

declare(strict_types=1);

final class ManualPostProvider implements PostProvider
{
    public function getName(): string
    {
        return 'manual';
    }

    public function getLatestPosts(string $username): array
    {
        return [];
    }
}
