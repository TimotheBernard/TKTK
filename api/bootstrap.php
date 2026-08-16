<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/bootstrap.php';

Security::startSession();
header('Content-Type: application/json; charset=utf-8');

$GLOBALS['_REQUEST_JSON'] = request_json();
$method = request_method();
$action = (string) ($_GET['action'] ?? ($GLOBALS['_REQUEST_JSON']['_action'] ?? ''));
$id = (string) ($_GET['id'] ?? ($GLOBALS['_REQUEST_JSON']['id'] ?? ''));

if (!defined('API_PUBLIC')) {
    Security::requireLogin();
    if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
        Security::requireCsrf();
    }
}

function api_handle(callable $fn): never
{
    try {
        $fn();
        ApiResponse::error('UNKNOWN_ERROR', 'No response', 500);
    } catch (InvalidArgumentException $e) {
        $code = $e->getMessage();
        if (!in_array($code, ERROR_CODES, true)) {
            $code = 'VALIDATION_ERROR';
        }
        ApiResponse::error($code, 'Invalid request', 400);
    } catch (RuntimeException $e) {
        $code = $e->getMessage();
        $status = 400;
        $messages = [
            'ACCOUNT_NOT_FOUND' => 'Account not found',
            'ARTIST_NOT_FOUND' => 'Artist not found',
            'POST_NOT_FOUND' => 'Post not found',
            'RULE_NOT_FOUND' => 'Rule not found',
            'TARGET_NOT_FOUND' => 'Target not found',
            'TASK_NOT_FOUND' => 'Task not found',
            'ACCOUNT_BUSY' => 'Account has a running task',
            'DUPLICATE_TARGET' => 'Association already exists',
            'DUPLICATE_RULE' => 'Rule already exists',
            'DEV_MODE_REQUIRED' => 'Development mode is disabled',
            'STORAGE_ERROR' => 'Storage error',
            'UNAUTHORIZED' => 'Authentication required',
        ];
        if ($code === 'ACCOUNT_NOT_FOUND' || $code === 'ARTIST_NOT_FOUND' || $code === 'POST_NOT_FOUND' || $code === 'TASK_NOT_FOUND' || $code === 'RULE_NOT_FOUND' || $code === 'TARGET_NOT_FOUND') {
            $status = 404;
        }
        if ($code === 'DEV_MODE_REQUIRED') {
            $status = 403;
        }
        if ($code === 'STORAGE_ERROR') {
            $status = 500;
        }
        ApiResponse::error(
            in_array($code, ERROR_CODES, true) || isset($messages[$code]) ? $code : 'UNKNOWN_ERROR',
            $messages[$code] ?? $e->getMessage(),
            $status
        );
    } catch (Throwable $e) {
        Logger::error('UNKNOWN_ERROR ' . $e->getMessage());
        ApiResponse::error('UNKNOWN_ERROR', 'Unexpected error', 500);
    }
}

function api_body(): array
{
    return is_array($GLOBALS['_REQUEST_JSON'] ?? null) ? $GLOBALS['_REQUEST_JSON'] : [];
}
