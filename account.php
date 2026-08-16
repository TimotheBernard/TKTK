<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$id = (string) ($_GET['id'] ?? '');
$account = $id !== '' ? App::accounts()->get($id) : null;
if ($account === null) {
    http_response_code(404);
    $pageTitle = 'Compte introuvable';
    $pageId = 'accounts';
    $pageScript = '';
    require __DIR__ . '/includes/layout_start.php';
    echo '<p>Compte introuvable.</p>';
    require __DIR__ . '/includes/layout_end.php';
    exit;
}
$pageTitle = (string) $account['label'];
$pageId = 'accounts';
$pageScript = '';
require __DIR__ . '/includes/layout_start.php';
$targets = App::targets()->forAccount($id);
$rules = App::rules()->forAccount($id);
$tasks = App::tasks()->forAccount($id);
$history = App::history()->forAccount($id);
$artists = [];
foreach (App::artists()->all() as $artist) {
    $artists[$artist['id']] = $artist;
}
?>
<div class="split">
    <section class="card">
        <h2>Identité</h2>
        <p><strong><?= htmlspecialchars((string) $account['label']) ?></strong></p>
        <p><?= htmlspecialchars((string) $account['username']) ?></p>
        <p>Statut <?= htmlspecialchars((string) $account['status']) ?> — session <?= htmlspecialchars((string) $account['session_status']) ?></p>
        <p>Profil navigateur : <code><?= htmlspecialchars((string) $account['browser_profile']) ?></code></p>
        <p>Dernière vérif. session : <?= htmlspecialchars((string) ($account['session_checked_at'] ?? '—')) ?></p>
    </section>
    <section class="card">
        <h2>Cibles</h2>
        <ul>
            <?php foreach ($targets as $target): ?>
                <li><?= htmlspecialchars((string) ($artists[$target['artist_id']]['name'] ?? $target['artist_id'])) ?></li>
            <?php endforeach; ?>
            <?php if ($targets === []): ?><li>Aucune</li><?php endif; ?>
        </ul>
        <h2>Règles</h2>
        <ul>
            <?php foreach ($rules as $rule): ?>
                <li><?= htmlspecialchars((string) ($artists[$rule['artist_id']]['name'] ?? $rule['artist_id'])) ?> — délai <?= (int) $rule['delay_seconds'] ?> s</li>
            <?php endforeach; ?>
            <?php if ($rules === []): ?><li>Aucune</li><?php endif; ?>
        </ul>
    </section>
</div>
<section class="card" style="margin-top:16px">
    <h2>Tâches</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Horaire</th><th>État</th><th>Post</th></tr></thead>
            <tbody>
            <?php foreach ($tasks as $task): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $task['scheduled_at']) ?></td>
                    <td><?= htmlspecialchars((string) $task['status']) ?></td>
                    <td><?= htmlspecialchars((string) $task['post_id']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<section class="card" style="margin-top:16px">
    <h2>Historique</h2>
    <ul class="feed">
        <?php foreach (array_slice($history, 0, 40) as $h): ?>
            <li><time><?= htmlspecialchars((string) $h['created_at']) ?></time><span><?= htmlspecialchars((string) $h['event']) ?></span></li>
        <?php endforeach; ?>
    </ul>
</section>
<?php require __DIR__ . '/includes/layout_end.php';