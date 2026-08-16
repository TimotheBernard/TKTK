<?php

declare(strict_types=1);

final class SeleniumWatchPostProvider implements PostProvider
{
    public function getName(): string
    {
        return 'selenium_watch';
    }

    public function getLatestPosts(string $username): array
    {
        return [];
    }
}
