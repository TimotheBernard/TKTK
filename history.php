<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Historique';
$pageId = 'history';
$pageScript = 'history.js';
require __DIR__ . '/includes/layout_start.php';
?>
<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Heure</th><th>Événement</th><th>Compte</th><th>Post</th><th>Statut</th><th>Durée</th></tr></thead>
            <tbody id="history-body"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';