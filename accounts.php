<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Comptes TikTok';
$pageId = 'accounts';
$pageScript = 'accounts.js';
require __DIR__ . '/includes/layout_start.php';
?>
<div class="toolbar">
    <p>Nombre de comptes dynamique — aucun plafond dans le code.</p>
    <button class="btn" id="add-account" type="button">Ajouter</button>
</div>
<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Label</th><th>Username</th><th>Statut</th><th>Session</th><th>Actif</th><th>Actions</th></tr></thead>
            <tbody id="accounts-body"></tbody>
        </table>
    </div>
</section>
<div class="modal-bg" id="account-modal">
    <form class="modal" id="account-form">
        <h3>Compte</h3>
        <input type="hidden" name="id">
        <label class="field">Label<input name="label" required></label>
        <label class="field">Username<input name="username" placeholder="@username"></label>
        <label class="field">Notes<textarea name="notes" rows="3"></textarea></label>
        <label class="field"><span><input type="checkbox" name="enabled" checked> Actif</span></label>
        <div class="row">
            <button class="btn" type="submit">Enregistrer</button>
            <button class="btn secondary" type="button" data-close>Annuler</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/layout_end.php';