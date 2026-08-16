<?php

declare(strict_types=1);

final class Logger
{
    public static function app(string $message): void
    {
        self::write(LOG_PATH . '/application.log', $message);
    }

    public static function scheduler(string $message): void
    {
        self::write(LOG_PATH . '/scheduler.log', $message);
    }

    public static function error(string $message): void
    {
        self::write(LOG_PATH . '/errors.log', $message);
        self::write(LOG_PATH . '/application.log', 'ERROR ' . $message);
    }

    private static function write(string $file, string $message): void
    {
        $line = now_utc() . ' ' . $message . PHP_EOL;
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true);
        }
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}
