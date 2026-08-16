<?php

declare(strict_types=1);

final class SimulationPostProvider implements PostProvider
{
    public function getName(): string
    {
        return 'simulation';
    }

    public function getLatestPosts(string $username): array
    {
        $username = normalize_username($username);
        $id = (string) random_int(1000000000, 2147483647);
        return [[
            'username' => $username,
            'url' => 'https://www.tiktok.com/' . $username . '/video/' . $id,
            'video_id' => $id,
            'caption' => 'Simulated post',
            'published_at' => now_utc(),
        ]];
    }
}
