<?php

declare(strict_types=1);

final class TikTokService
{
    public function parseUrl(string $url): ?array
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        if (!preg_match('#^https?://#i', $url)) {
            $url = 'https://' . $url;
        }
        $parts = parse_url($url);
        if (!is_array($parts) || empty($parts['host'])) {
            return null;
        }
        $host = strtolower((string) $parts['host']);
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        if (!in_array($host, ['tiktok.com', 'vm.tiktok.com', 'vt.tiktok.com'], true)) {
            return null;
        }
        $path = $parts['path'] ?? '';
        $username = '';
        $videoId = '';
        if (preg_match('#/@([^/]+)/video/(\d+)#', $path, $m)) {
            $username = normalize_username($m[1]);
            $videoId = $m[2];
        } elseif (preg_match('#/@([^/]+)/?$#', $path, $m)) {
            $username = normalize_username($m[1]);
        }
        $canonical = $username !== '' && $videoId !== ''
            ? 'https://www.tiktok.com/' . $username . '/video/' . $videoId
            : 'https://www.tiktok.com' . $path;
        return [
            'url' => $canonical,
            'username' => $username,
            'video_id' => $videoId,
            'profile_url' => $username !== '' ? 'https://www.tiktok.com/' . $username : '',
        ];
    }

    public function isValidPostUrl(string $url): bool
    {
        $parsed = $this->parseUrl($url);
        return $parsed !== null && $parsed['video_id'] !== '';
    }

    public function officialApiAvailable(): bool
    {
        $settings = App::settings()->get();
        return !empty($settings['tiktok_api_enabled']) && trim((string) ($settings['tiktok_client_key'] ?? '')) !== '';
    }
}
