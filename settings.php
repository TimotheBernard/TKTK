<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Paramètres';
$pageId = 'settings';
$pageScript = 'settings.js';
require __DIR__ . '/includes/layout_start.php';
?>
<section class="card">
    <form id="settings-form">
        <label class="field"><span><input type="checkbox" name="scheduler_enabled"> Scheduler activé</span></label>
        <label class="field"><span><input type="checkbox" name="selenium_enabled"> Selenium activé</span></label>
        <label class="field"><span><input type="checkbox" name="watch_enabled"> Surveillance artistes</span></label>
        <label class="field"><span><input type="checkbox" name="dev_mode"> Mode développement</span></label>
        <label class="field"><span><input type="checkbox" name="verbose_logs"> Logs détaillés</span></label>
        <label class="field">Chemin Python<input name="python_path"></label>
        <label class="field">Chemin Chrome<input name="chrome_path"></label>
        <label class="field">Fréquence de surveillance (s)<input type="number" min="1" name="watch_interval_seconds"></label>
        <label class="field">Timeout navigateur (s)<input type="number" min="1" name="browser_timeout_seconds"></label>
        <label class="field">Warmup navigateur (s)<input type="number" min="0" name="browser_warmup_seconds"></label>
        <button class="btn" type="submit">Enregistrer</button>
    </form>
</section>
<?php require __DIR__ . '/includes/layout_end.php';