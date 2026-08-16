<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Artistes';
$pageId = 'artists';
$pageScript = 'artists.js';
require __DIR__ . '/includes/layout_start.php';
?>
<div class="toolbar">
    <p>Bibliothèque des comptes TikTok à surveiller.</p>
    <button class="btn" id="add-artist" type="button">Ajouter</button>
</div>
<section class="card">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Nom</th><th>Username</th><th>Catégorie</th><th>Actif</th><th></th></tr></thead>
            <tbody id="artists-body"></tbody>
        </table>
    </div>
</section>
<div class="modal-bg" id="artist-modal">
    <form class="modal" id="artist-form">
        <h3>Artiste</h3>
        <input type="hidden" name="id">
        <label class="field">Nom<input name="name" required></label>
        <label class="field">Username<input name="username" placeholder="@username" required></label>
        <label class="field">Catégorie<input name="category"></label>
        <label class="field">Pays<input name="country"></label>
        <label class="field"><span><input type="checkbox" name="enabled" checked> Actif</span></label>
        <div class="row">
            <button class="btn" type="submit">Enregistrer</button>
            <button class="btn secondary" type="button" data-close>Annuler</button>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/layout_end.php';