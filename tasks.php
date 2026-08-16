<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Tâches';
$pageId = 'tasks';
$pageScript = 'tasks.js';
require __DIR__ . '/includes/layout_start.php';
?>
<div id="countdowns" class="grid stats"></div>
<section class="card" style="margin-top:16px">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Heure</th><th>Compte</th><th>Cible</th><th>État</th><th>Countdown</th><th></th></tr></thead>
            <tbody id="tasks-body"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';