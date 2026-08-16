<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Paramètres';
$pageId = 'settings';
$pageScript = 'settings.js';
require __DIR__ . '/includes/layout_start.php';
?>
<div class="split">
    <section class="card">
        <h2>Système</h2>
        <form id="settings-form">
            <label class="field"><span><input type="checkbox" name="scheduler_enabled"> Scheduler activé</span></label>
            <label class="field"><span><input type="checkbox" name="selenium_enabled"> Selenium activé</span></label>
            <label class="field"><span><input type="checkbox" name="watch_enabled"> Surveillance artistes</span></label>
            <label class="field"><span><input type="checkbox" name="dev_mode"> Mode développement</span></label>
            <label class="field"><span><input type="checkbox" name="verbose_logs"> Logs détaillés</span></label>
            <label class="field">Chemin Python<input name="python_path"></label>
            <label class="field">Chemin Chrome<input name="chrome_path" placeholder="laissé vide = détection auto"></label>
            <label class="field">Fréquence de surveillance (s)<input type="number" min="1" name="watch_interval_seconds"></label>
            <label class="field">Timeout navigateur (s)<input type="number" min="1" name="browser_timeout_seconds"></label>
            <label class="field">Warmup navigateur (s)<input type="number" min="0" name="browser_warmup_seconds"></label>
            <button class="btn" type="submit">Enregistrer</button>
        </form>
    </section>
    <div>
        <section class="card">
            <h2>Mot de passe du dashboard</h2>
            <p>Change le mot de passe de connexion <strong>admin</strong> (pas un mot de passe TikTok).</p>
            <form id="password-form">
                <label class="field">Mot de passe actuel<input type="password" name="current_password" required autocomplete="current-password"></label>
                <label class="field">Nouveau mot de passe<input type="password" name="new_password" required minlength="8" autocomplete="new-password"></label>
                <label class="field">Confirmation<input type="password" name="confirm_password" required minlength="8" autocomplete="new-password"></label>
                <button class="btn" type="submit">Changer le mot de passe</button>
            </form>
        </section>
        <section class="card" style="margin-top:16px">
            <h2>Session watcher</h2>
            <p>Profil Chrome dédié à la détection automatique. Connecte-toi à TikTok dans la fenêtre qui s’ouvre, puis teste la session.</p>
            <p>État : <span id="watcher-status">—</span></p>
            <div class="row">
                <button class="btn" id="open-watcher" type="button">Connecter le watcher</button>
                <button class="btn secondary" id="test-watcher" type="button">Tester la session</button>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/layout_end.php';