<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function () use ($method, $action, $id): void {
    $rules = App::rules();
    if ($method === 'GET') {
        $accountId = (string) ($_GET['account_id'] ?? '');
        $items = $accountId !== '' ? $rules->forAccount($accountId) : $rules->all();
        ApiResponse::items($items);
    }
    if ($method === 'POST') {
        ApiResponse::success(['rule' => $rules->create(api_body())], 201);
    }
    if ($method === 'PUT' && $action === 'bulk_delays') {
        $items = api_body()['items'] ?? [];
        ApiResponse::success(['items' => $rules->bulkDelays(is_array($items) ? $items : [])]);
    }
    if ($method === 'PUT' && $id !== '') {
        $rule = $rules->update($id, api_body());
        if ($rule === null) {
            throw new RuntimeException('RULE_NOT_FOUND');
        }
        ApiResponse::success(['rule' => $rule]);
    }
    if ($method === 'DELETE' && $id !== '') {
        if (!$rules->delete($id)) {
            throw new RuntimeException('RULE_NOT_FOUND');
        }
        ApiResponse::success(['deleted' => true]);
    }
    ApiResponse::error('VALIDATION_ERROR', 'Unsupported method', 405);
});
