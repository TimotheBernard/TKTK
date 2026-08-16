<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
Security::startSession();

$error = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $user = (string) ($_POST['username'] ?? '');
    $pass = (string) ($_POST['password'] ?? '');
    if (Security::login($user, $pass)) {
        header('Location: /index.php');
        exit;
    }
    $error = 'Identifiants invalides';
}
if (Security::user() !== null) {
    header('Location: /index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion — <?= APP_NAME ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="login-wrap">
    <form class="login-card" method="post">
        <h1>TikTok Manager</h1>
        <p>Accès privé au dashboard multi-comptes.</p>
        <?php if ($error !== ''): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <label class="field">Utilisateur<input name="username" autocomplete="username" required></label>
        <label class="field">Mot de passe<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn" type="submit">Entrer</button>
    </form>
</div>
</body>
</html>
