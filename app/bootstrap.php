<?php

declare(strict_types=1);

$root = dirname(__DIR__);

require_once $root . '/app/helpers.php';
load_dotenv($root . '/.env');

define('ROOT_PATH', $root);
define('DATA_PATH', env_value('TKTK_DATA_PATH') ?: ($root . '/data'));
define('LOG_PATH', env_value('TKTK_LOG_PATH') ?: ($root . '/logs'));
define('PROFILES_PATH', env_value('TKTK_PROFILES_PATH') ?: ($root . '/profiles'));
define('UPLOADS_PATH', env_value('TKTK_UPLOADS_PATH') ?: ($root . '/uploads'));
define('BROWSER_PATH', $root . '/browser');
define('PUBLIC_PATH', $root . '/public');
define('APP_NAME', env_value('APP_NAME', 'TKTKNUEVA') ?? 'TKTKNUEVA');
define('STORAGE_VERSION', 2);
define('DEFAULT_TIMEZONE', 'UTC');
define('SELENIUM_PATH', BROWSER_PATH);
define('SELENIUM_PROFILES_PATH', PROFILES_PATH);

require_once $root . '/app/logger.php';
require_once $root . '/app/Storage/StorageInterface.php';
require_once $root . '/app/Storage/JsonStorage.php';
require_once $root . '/app/response.php';
require_once $root . '/app/security.php';
require_once $root . '/app/Services/SettingsService.php';
require_once $root . '/app/Services/HistoryService.php';
require_once $root . '/app/Services/BackupService.php';
require_once $root . '/app/Services/TikTokService.php';
require_once $root . '/app/Services/AccountService.php';
require_once $root . '/app/Services/ArtistService.php';
require_once $root . '/app/Services/TargetService.php';
require_once $root . '/app/Services/ScenarioService.php';
require_once $root . '/app/Services/EventDispatcher.php';
require_once $root . '/app/Services/TaskService.php';
require_once $root . '/app/Services/PostService.php';
require_once $root . '/app/Services/PublicationService.php';
require_once $root . '/app/Services/SimulationService.php';
require_once $root . '/app/Services/SupportServices.php';

final class App
{
    private static ?StorageInterface $storage = null;
    private static array $services = [];

    public static function boot(): void
    {
        if (self::$storage instanceof StorageInterface) {
            return;
        }
        foreach ([DATA_PATH, LOG_PATH, PROFILES_PATH, UPLOADS_PATH, DATA_PATH . '/backups'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0770, true);
            }
        }
        self::$storage = new JsonStorage(DATA_PATH);
        date_default_timezone_set(DEFAULT_TIMEZONE);
        self::seedDevUser();
    }

    public static function storage(): StorageInterface
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

    public static function scenarios(): ScenarioService
    {
        return self::once(ScenarioService::class, fn () => new ScenarioService(self::storage()));
    }

    public static function posts(): PostService
    {
        return self::once(PostService::class, fn () => new PostService(self::storage()));
    }

    public static function tasks(): TaskService
    {
        return self::once(TaskService::class, fn () => new TaskService(self::storage()));
    }

    public static function events(): EventDispatcher
    {
        return self::once(EventDispatcher::class, fn () => new EventDispatcher(self::storage()));
    }

    public static function history(): HistoryService
    {
        return self::once(HistoryService::class, fn () => new HistoryService(self::storage()));
    }

    public static function publications(): PublicationService
    {
        return self::once(PublicationService::class, fn () => new PublicationService(self::storage()));
    }

    public static function publishing(): TikTokPublishingService
    {
        return self::once(TikTokPublishingService::class, fn () => new TikTokPublishingService());
    }

    public static function scheduler(): SchedulerService
    {
        return self::once(SchedulerService::class, fn () => new SchedulerService(self::storage()));
    }

    public static function resources(): ResourceManager
    {
        return self::once(ResourceManager::class, fn () => new ResourceManager());
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

    public static function stats(): StatsService
    {
        return self::once(StatsService::class, fn () => new StatsService(self::storage()));
    }

    public static function recorder(): RecorderService
    {
        return self::once(RecorderService::class, fn () => new RecorderService());
    }

    private static function seedDevUser(): void
    {
        if (!env_bool('DEV_MODE', true)) {
            return;
        }
        $users = self::$storage->read('users')['items'] ?? [];
        if ($users !== []) {
            return;
        }
        $username = env_value('TKTK_ADMIN_USER', 'admin') ?? 'admin';
        $password = env_value('TKTK_ADMIN_PASSWORD', 'changeme') ?? 'changeme';
        self::$storage->insert('users', [
            'id' => 'user_001',
            'username' => $username,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'created_at' => now_utc(),
            'last_login_at' => null,
        ]);
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
