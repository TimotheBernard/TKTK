<?php

declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
Security::requireLogin();
$pageTitle = 'Statistiques';
$pageId = 'stats';
$pageScript = '';
$stats = App::stats()->compute();
require __DIR__ . '/includes/layout_start.php';
$t = $stats['totals'];
?>
<section class="grid stats">
    <article class="card"><div class="metric"><?= (int) $t['accounts'] ?></div><small>Comptes</small></article>
    <article class="card"><div class="metric"><?= (int) $t['accounts_enabled'] ?></div><small>Actifs</small></article>
    <article class="card"><div class="metric"><?= (int) $t['artists'] ?></div><small>Artistes</small></article>
    <article class="card"><div class="metric"><?= (int) $t['posts_detected'] ?></div><small>Publications</small></article>
    <article class="card"><div class="metric"><?= (int) $t['tasks_generated'] ?></div><small>Tâches</small></article>
    <article class="card"><div class="metric"><?= (int) $t['tasks_completed'] ?></div><small>Terminées</small></article>
    <article class="card"><div class="metric"><?= (int) $t['tasks_failed'] ?></div><small>Échecs</small></article>
    <article class="card"><div class="metric"><?= (int) $t['avg_execution_ms'] ?> ms</div><small>Temps moyen</small></article>
</section>
<section class="card" style="margin-top:16px">
    <h2>Par compte</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Compte</th><th class="num">Opérations</th><th class="num">OK</th><th class="num">Échecs</th><th class="num">Moy. ms</th></tr></thead>
            <tbody>
            <?php foreach ($stats['per_account'] as $row): ?>
                <tr>
                    <td><?= htmlspecialchars((string) $row['label']) ?></td>
                    <td class="num"><?= (int) $row['operations'] ?></td>
                    <td class="num"><?= (int) $row['completed'] ?></td>
                    <td class="num"><?= (int) $row['failed'] ?></td>
                    <td class="num"><?= (int) $row['avg_execution_ms'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require __DIR__ . '/includes/layout_end.php';