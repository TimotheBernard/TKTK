<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

api_handle(function (): void {
    ApiResponse::success(App::stats()->compute());
});
