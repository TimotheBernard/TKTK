# TikTok Multi-Account Manager

Application web privée pour gérer plusieurs comptes TikTok : sessions Chrome isolées, surveillance d’artistes, tâches planifiées à la seconde.

Architecture V1 : voir [ARCHITECTURE.md](./ARCHITECTURE.md).

**TKTKNUEVA V2** — rapport d’écart et plan de migration (à valider avant toute réécriture) : [MIGRATION_PLAN.md](./MIGRATION_PLAN.md).

## Accès

- URL locale : `http://127.0.0.1:8080`
- Utilisateur : `admin`
- Mot de passe initial : `changeme` — à changer depuis **Paramètres → Mot de passe du dashboard**

Les répertoires `data/`, `logs/` et `selenium/profiles/` ne sont pas exposés HTTP (`router.php` + `.htaccess`).

## Démarrage

```bash
php -S 127.0.0.1:8080 router.php
python3 worker/scheduler_worker.py
```

Selenium (détection automatique et ouverture des posts) :

```bash
pip install -r selenium/requirements.txt
```

Ensuite, dans **Paramètres** : **Connecter le watcher** (login TikTok dans Chrome), puis **Tester la session**. Pour chaque compte géré : **Ouvrir TikTok** puis **Tester**.

En `dev_mode`, le dashboard peut simuler un `NEW_POST` sans TikTok. Si `selenium_enabled` est false, le worker marque les tâches comme ouvertes (simulation).

## Décisions V1

- Arborescence étendue
- Warmup Chrome 8 s, `scheduled_at` inchangé
- Détection : poller Selenium (`_watcher`) + ajout manuel
- Tâches `running` orphelines relancées automatiquement au redémarrage du worker
