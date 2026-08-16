<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Règles';
$pageId = 'rules';
$pageScript = 'rules.js';
require __DIR__ . '/includes/layout_start.php';
?>
<section class="card">
    <h2>Nouvelle règle</h2>
    <form id="rule-form" class="row">
        <label class="field">Compte<select id="rule-account" name="account_id" required></select></label>
        <label class="field">Artiste<select id="rule-artist" name="artist_id" required></select></label>
        <label class="field">Délai (s)<input type="number" min="0" step="1" name="delay_seconds" value="2" required></label>
        <button class="btn" type="submit">Créer</button>
    </form>
</section>
<section class="card" style="margin-top:16px">
    <div class="toolbar">
        <h2>Configuration rapide des délais</h2>
        <button class="btn" id="save-delays" type="button">Enregistrer les délais</button>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Compte</th><th>Artiste</th><th>Actif</th><th class="num">Délai</th></tr></thead>
            <tbody id="rules-body"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';