<?php

declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Dashboard';
$pageId = $pageId ?? 'dashboard';
$pageScript = $pageScript ?? '';
$nav = [
    'dashboard' => ['href' => '/index.php', 'label' => 'Dashboard', 'group' => 'Vue'],
    'accounts' => ['href' => '/accounts.php', 'label' => 'Comptes TikTok', 'group' => 'Comptes'],
    'artists' => ['href' => '/artists.php', 'label' => 'Artistes', 'group' => 'Comptes'],
    'targets' => ['href' => '/targets.php', 'label' => 'Associations', 'group' => 'Comptes'],
    'posts' => ['href' => '/posts.php', 'label' => 'Publications', 'group' => 'Contenu'],
    'rules' => ['href' => '/rules.php', 'label' => 'Règles', 'group' => 'Automatisation'],
    'tasks' => ['href' => '/tasks.php', 'label' => 'Tâches', 'group' => 'Automatisation'],
    'history' => ['href' => '/history.php', 'label' => 'Historique', 'group' => 'Automatisation'],
    'stats' => ['href' => '/stats.php', 'label' => 'Statistiques', 'group' => 'Système'],
    'logs' => ['href' => '/logs.php', 'label' => 'Logs', 'group' => 'Système'],
    'settings' => ['href' => '/settings.php', 'label' => 'Paramètres', 'group' => 'Système'],
];
$user = Security::user();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars(Security::csrfToken(), ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($pageTitle) ?> — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="stylesheet" href="/assets/css/dashboard.css">
</head>
<body data-page="<?= htmlspecialchars($pageId) ?>">
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand">
            <span class="brand-mark">TM</span>
            <div>
                <strong>TikTok Manager</strong>
                <small>Multi-comptes</small>
            </div>
        </div>
        <nav>
            <?php
            $currentGroup = '';
            foreach ($nav as $id => $item):
                if ($item['group'] !== $currentGroup):
                    $currentGroup = $item['group'];
                    echo '<p class="nav-group">' . htmlspecialchars($currentGroup) . '</p>';
                endif;
            ?>
                <a class="<?= $pageId === $id ? 'active' : '' ?>" href="<?= htmlspecialchars($item['href']) ?>"><?= htmlspecialchars($item['label']) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="sidebar-foot">
            <span><?= htmlspecialchars((string) ($user['username'] ?? '')) ?></span>
            <a href="/logout.php">Déconnexion</a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="nav-toggle" type="button" data-toggle-nav aria-label="Menu">☰</button>
            <h1><?= htmlspecialchars($pageTitle) ?></h1>
            <div class="topbar-meta" id="worker-pill">Worker —</div>
        </header>
        <main class="content">
