# TikTok Multi-Account Manager

Application web privée pour gérer plusieurs comptes TikTok : sessions Chrome isolées, surveillance d’artistes, tâches planifiées à la seconde.

Architecture : voir [ARCHITECTURE.md](./ARCHITECTURE.md).

## Accès

- URL locale : `http://127.0.0.1:8080`
- Utilisateur : `admin`
- Mot de passe initial : `changeme` (à changer après installation)

Les répertoires `data/`, `logs/` et `selenium/profiles/` ne sont pas exposés HTTP (`router.php` + `.htaccess`).

## Démarrage

```bash
php -S 127.0.0.1:8080 router.php
python3 worker/scheduler_worker.py
```

Selenium (optionnel, détection automatique et ouverture des posts) :

```bash
pip install -r selenium/requirements.txt
```

En `dev_mode`, le dashboard peut simuler un `NEW_POST` sans TikTok. Si `selenium_enabled` est false, le worker marque les tâches comme ouvertes (simulation).

## Décisions V1

- Arborescence étendue
- Warmup Chrome 8 s, `scheduled_at` inchangé
- Détection : poller Selenium (`_watcher`) + ajout manuel
- Tâches `running` orphelines relancées automatiquement au redémarrage du worker
