<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function (): void {
    $limit = (int) ($_GET['limit'] ?? 100);
    $accountId = (string) ($_GET['account_id'] ?? '');
    $items = $accountId !== '' ? App::history()->forAccount($accountId) : App::history()->recent($limit);
    ApiResponse::items($items);
});
