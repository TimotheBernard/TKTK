<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

$username = $argv[1] ?? '';
$password = $argv[2] ?? '';
if ($username === '' || $password === '') {
    fwrite(STDERR, "Usage: php bin/create-user.php <username> <password>\n");
    exit(1);
}
try {
    $user = Security::createUser($username, $password);
    echo 'Created ' . $user['id'] . ' ' . $user['username'] . PHP_EOL;
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}
