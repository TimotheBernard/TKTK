<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/logger.php';
require_once __DIR__ . '/StorageInterface.php';
require_once __DIR__ . '/JsonStorage.php';
require_once __DIR__ . '/response.php';
require_once __DIR__ . '/security.php';

require_once __DIR__ . '/providers/PostProvider.php';
require_once __DIR__ . '/providers/ManualPostProvider.php';
require_once __DIR__ . '/providers/OfficialApiPostProvider.php';
require_once __DIR__ . '/providers/ImportPostProvider.php';
require_once __DIR__ . '/providers/SeleniumWatchPostProvider.php';
require_once __DIR__ . '/providers/SimulationPostProvider.php';

require_once __DIR__ . '/../services/BackupService.php';
require_once __DIR__ . '/../services/SettingsService.php';
require_once __DIR__ . '/../services/TikTokService.php';
require_once __DIR__ . '/../services/AccountService.php';
require_once __DIR__ . '/../services/ArtistService.php';
require_once __DIR__ . '/../services/TargetService.php';
require_once __DIR__ . '/../services/HistoryService.php';
require_once __DIR__ . '/../services/StatsService.php';
require_once __DIR__ . '/../services/EventService.php';
require_once __DIR__ . '/../services/RuleService.php';
require_once __DIR__ . '/../services/TaskService.php';
require_once __DIR__ . '/../services/PostService.php';
require_once __DIR__ . '/../services/SchedulerService.php';
require_once __DIR__ . '/../services/SimulationService.php';

final class App
{
    private static ?JsonStorage $storage = null;
    private static array $services = [];

    public static function boot(): void
    {
        if (self::$storage instanceof JsonStorage) {
            return;
        }
        self::$storage = new JsonStorage(DATA_PATH);
        date_default_timezone_set(DEFAULT_TIMEZONE);
    }

    public static function storage(): JsonStorage
    {
        self::boot();
        return self::$storage;
    }

    public static function settings(): SettingsService
    {
        return self::once(SettingsService::class, fn () => new SettingsService(self::storage()));
    }

    public static function accounts(): AccountService
    {
        return self::once(AccountService::class, fn () => new AccountService(self::storage()));
    }

    public static function artists(): ArtistService
    {
        return self::once(ArtistService::class, fn () => new ArtistService(self::storage()));
    }

    public static function targets(): TargetService
    {
        return self::once(TargetService::class, fn () => new TargetService(self::storage()));
    }

    public static function rules(): RuleService
    {
        return self::once(RuleService::class, fn () => new RuleService(self::storage()));
    }

    public static function posts(): PostService
    {
        return self::once(PostService::class, fn () => new PostService(self::storage()));
    }

    public static function tasks(): TaskService
    {
        return self::once(TaskService::class, fn () => new TaskService(self::storage()));
    }

    public static function events(): EventService
    {
        return self::once(EventService::class, fn () => new EventService(self::storage()));
    }

    public static function history(): HistoryService
    {
        return self::once(HistoryService::class, fn () => new HistoryService(self::storage()));
    }

    public static function stats(): StatsService
    {
        return self::once(StatsService::class, fn () => new StatsService(self::storage()));
    }

    public static function scheduler(): SchedulerService
    {
        return self::once(SchedulerService::class, fn () => new SchedulerService(self::storage()));
    }

    public static function simulation(): SimulationService
    {
        return self::once(SimulationService::class, fn () => new SimulationService());
    }

    public static function backups(): BackupService
    {
        return self::once(BackupService::class, fn () => new BackupService(self::storage()));
    }

    public static function tiktok(): TikTokService
    {
        return self::once(TikTokService::class, fn () => new TikTokService());
    }

    private static function once(string $key, callable $factory): mixed
    {
        self::boot();
        if (!isset(self::$services[$key])) {
            self::$services[$key] = $factory();
        }
        return self::$services[$key];
    }
}

App::boot();
