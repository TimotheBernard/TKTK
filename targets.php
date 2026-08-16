<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Associations';
$pageId = 'targets';
$pageScript = 'targets.js';
require __DIR__ . '/includes/layout_start.php';
?>
<p id="orphan-warning" class="error"></p>
<section class="card">
    <h2>Nouvelle association</h2>
    <form id="target-form" class="row">
        <label class="field">Compte<select id="account_id" name="account_id" required></select></label>
        <label class="field">Artiste<select id="artist_id" name="artist_id" required></select></label>
        <label class="field"><span><input type="checkbox" name="check_new_posts" checked> Surveiller les posts</span></label>
        <button class="btn" type="submit">Associer</button>
    </form>
</section>
<section class="card" style="margin-top:16px">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Compte</th><th>Artiste</th><th>Actif</th><th>Watch</th><th></th></tr></thead>
            <tbody id="targets-body"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';