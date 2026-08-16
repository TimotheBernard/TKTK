<?php
/** @var string $page */
/** @var string $title */
/** @var string $script */
/** @var array $user */
/** @var string $csrf */
/** @var string $dev */
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
    <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars(APP_NAME) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body data-page="<?= htmlspecialchars($page) ?>" data-dev="<?= htmlspecialchars($dev) ?>">
<div class="app-shell">
    <aside class="sidebar">
        <div class="brand-lockup">
            <span class="logo-mark">TK</span>
            <div>
                <strong>TKTKNUEVA</strong>
                <small>Comptes persistants · activation événementielle</small>
            </div>
        </div>
        <nav>
            <p class="nav-group">Vue</p>
            <a class="<?= $page === 'dashboard' ? 'active' : '' ?>" href="/dashboard">Dashboard</a>
            <a class="<?= $page === 'activity' ? 'active' : '' ?>" href="/activity">Activité</a>
            <p class="nav-group">Système</p>
            <a class="<?= $page === 'resources' ? 'active' : '' ?>" href="/system/resources">Ressources</a>
        </nav>
        <div class="sidebar-foot">
            <span><?= htmlspecialchars((string) ($user['username'] ?? '')) ?></span>
            <a href="/logout">Déconnexion</a>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button class="nav-toggle" type="button" data-toggle-nav aria-label="Menu">☰</button>
            <h1><?= htmlspecialchars($title) ?></h1>
            <div class="topbar-meta" id="worker-pill">Worker —</div>
        </header>
        <main class="content" id="page-root">
            <?php require __DIR__ . '/' . $page . '.php'; ?>
        </main>
    </div>
</div>
<div class="toast-stack" id="toasts"></div>
<script src="/assets/js/app.js"></script>
<script src="/assets/js/<?= htmlspecialchars($script) ?>.js"></script>
</body>
</html>
