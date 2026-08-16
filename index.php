<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Dashboard';
$pageId = 'dashboard';
$pageScript = 'dashboard.js';
require __DIR__ . '/includes/layout_start.php';
?>
<div class="toolbar">
    <p>Supervision temps réel des comptes, publications et tâches.</p>
    <button class="btn secondary" id="sim-fanout" type="button">Simuler NEW_POST (DEV)</button>
</div>
<section class="grid stats">
    <article class="card"><div class="metric" id="m-accounts">0</div><small>Comptes</small></article>
    <article class="card"><div class="metric" id="m-active">0</div><small>Actifs</small></article>
    <article class="card"><div class="metric" id="m-busy">0</div><small>Utilisés</small></article>
    <article class="card"><div class="metric" id="m-valid">0</div><small>Sessions valides</small></article>
    <article class="card"><div class="metric" id="m-expired">0</div><small>Sessions expirées</small></article>
    <article class="card"><div class="metric" id="m-disabled">0</div><small>Désactivés</small></article>
    <article class="card"><div class="metric" id="m-error">0</div><small>En erreur</small></article>
</section>
<section class="grid stats" style="margin-top:16px">
    <article class="card"><div class="metric" id="m-artists">0</div><small>Artistes</small></article>
    <article class="card"><div class="metric" id="m-posts">0</div><small>Publications</small></article>
    <article class="card"><div class="metric" id="m-scheduled">0</div><small>Tâches prévues</small></article>
    <article class="card"><div class="metric" id="m-imminent">0</div><small>Imminentes</small></article>
    <article class="card"><div class="metric" id="m-running">0</div><small>En cours</small></article>
    <article class="card"><div class="metric" id="m-done">0</div><small>Terminées</small></article>
    <article class="card"><div class="metric" id="m-failed">0</div><small>Échouées</small></article>
</section>
<div class="split" style="margin-top:16px">
    <section class="card">
        <h2>Comptes</h2>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Compte</th><th>État</th><th>Session</th></tr></thead>
                <tbody id="account-status-body"></tbody>
            </table>
        </div>
    </section>
    <section class="card">
        <h2>Activité récente</h2>
        <ul class="feed" id="activity-feed"></ul>
    </section>
</div>
<section class="card" style="margin-top:16px">
    <h2>Dernières publications</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Détectée</th><th>Compte</th><th>Statut</th></tr></thead>
            <tbody id="latest-posts"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';