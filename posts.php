<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Publications';
$pageId = 'posts';
$pageScript = 'posts.js';
require __DIR__ . '/includes/layout_start.php';
?>
<section class="card">
    <h2>Ajouter une URL TikTok</h2>
    <form id="post-form" class="row">
        <label class="field" style="flex:1">URL<input name="url" placeholder="https://www.tiktok.com/@user/video/…" required></label>
        <label class="field">Artiste<select id="artist_id" name="artist_id"></select></label>
        <button class="btn" type="submit">Enregistrer</button>
    </form>
</section>
<section class="card" style="margin-top:16px">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Détectée</th><th>Username</th><th>Vidéo</th><th>Statut</th><th>Source</th></tr></thead>
            <tbody id="posts-body"></tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';