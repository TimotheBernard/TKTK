<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Logs';
$pageId = 'logs';
$pageScript = '';
$name = (string) ($_GET['name'] ?? 'application');
$allowed = [
    'application' => LOG_PATH . '/application.log',
    'scheduler' => LOG_PATH . '/scheduler.log',
    'errors' => LOG_PATH . '/errors.log',
];
if (!isset($allowed[$name])) {
    $name = 'application';
}
$lines = is_file($allowed[$name]) ? array_slice(file($allowed[$name]) ?: [], -200) : [];
require __DIR__ . '/includes/layout_start.php';
?>
<div class="toolbar">
    <div class="row">
        <a class="btn secondary" href="/logs.php?name=application">application</a>
        <a class="btn secondary" href="/logs.php?name=scheduler">scheduler</a>
        <a class="btn secondary" href="/logs.php?name=errors">errors</a>
    </div>
</div>
<section class="card">
    <pre style="white-space:pre-wrap;font-size:12px;line-height:1.45"><?= htmlspecialchars(implode('', $lines) ?: 'Journal vide') ?></pre>
</section>
<?php require __DIR__ . '/includes/layout_end.php';