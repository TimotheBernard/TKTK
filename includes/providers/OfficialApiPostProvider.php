<?php

declare(strict_types=1);

final class OfficialApiPostProvider implements PostProvider
{
    public function getName(): string
    {
        return 'official_api';
    }

    public function getLatestPosts(string $username): array
    {
        return [];
    }
}
