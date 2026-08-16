<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
Security::startSession();
Security::logout();
header('Location: /login.php');
exit;
