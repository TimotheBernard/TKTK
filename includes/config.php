<?php

declare(strict_types=1);

define('ROOT_PATH', dirname(__DIR__));
define('DATA_PATH', getenv('TKTK_DATA_PATH') ?: (ROOT_PATH . '/data'));
define('LOG_PATH', getenv('TKTK_LOG_PATH') ?: (ROOT_PATH . '/logs'));
define('SELENIUM_PATH', ROOT_PATH . '/selenium');
define('SELENIUM_PROFILES_PATH', SELENIUM_PATH . '/profiles');

const APP_NAME = 'TikTok Manager';
const STORAGE_VERSION = 1;
const DEFAULT_TIMEZONE = 'UTC';

const ACCOUNT_STATUSES = ['idle', 'busy', 'offline', 'disabled', 'session_expired', 'error'];
const SESSION_STATUSES = ['connected', 'expired', 'reauthentication_required', 'unknown'];
const POST_STATUSES = ['new', 'queued', 'processed', 'ignored'];
const TASK_STATUSES = ['pending', 'ready', 'running', 'completed', 'failed', 'cancelled'];

const ERROR_CODES = [
    'ACCOUNT_NOT_FOUND',
    'ACCOUNT_DISABLED',
    'PROFILE_NOT_FOUND',
    'SESSION_EXPIRED',
    'POST_NOT_FOUND',
    'BROWSER_START_FAILED',
    'BROWSER_CRASHED',
    'ACTION_TIMEOUT',
    'STORAGE_ERROR',
    'UNKNOWN_ERROR',
    'VALIDATION_ERROR',
    'DUPLICATE_POST',
    'UNAUTHORIZED',
    'CSRF_FAILED',
    'DEV_MODE_REQUIRED',
    'WORKER_STALE_TASK',
    'WORKER_RESTART_RETRY',
    'ARTIST_NOT_FOUND',
    'RULE_NOT_FOUND',
    'TARGET_NOT_FOUND',
    'TASK_NOT_FOUND',
];
