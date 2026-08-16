# TKTKNUEVA

Application web privée de gestion multi-comptes TikTok : comptes persistants, activation événementielle, ressources navigateur chargées à la demande.

Le dépôt Git **local** est la source de vérité du code. Aucun remote Git n’est obligatoire. GitHub n’est pas une dépendance du produit.

## Architecture

```text
LOGIN → DASHBOARD → événement → EventDispatcher → ScenarioEngine / PublishingService
     → Scheduler (seconde) → TaskDispatcher → CapabilityResolver
         → API officielle  ou  Browser (SessionManager → Chromium → Action)
     → Historique → compte IDLE
```

- PHP 8+ : interface, API, services métier, `StorageInterface` / `JsonStorage`
- Python 3 : worker temps réel (`worker/scheduler.py`)
- Selenium : couche technique uniquement (`browser/`), jamais dans un scénario
- Stockage V1 JSON, migrable plus tard vers SQLite / MySQL / PostgreSQL

Principes : aucun `MAX_ACCOUNTS` ; un `account_id` = une session = un profil `profiles/{id}/` ; 100 comptes ≠ 100 Chrome.

## Installation

```bash
cp .env.example .env
# éditer DEV_MODE, chemins, identifiants initiaux
php bin/create-user.php admin 'votre-mot-de-passe'
pip install -r browser/requirements.txt
```

En `DEV_MODE=true`, un utilisateur `TKTK_ADMIN_USER` / `TKTK_ADMIN_PASSWORD` est créé si `users.json` est vide.

## Lancement

PHP (développement) :

```bash
php -S 127.0.0.1:8080 router.php
```

Worker Python (précision à la seconde, pas de cron) :

```bash
python3 worker/scheduler.py
```

Puis ouvrir `http://127.0.0.1:8080/login` → `/dashboard`.

## Variables d’environnement

Voir `.env.example`.

| Variable | Rôle |
| --- | --- |
| `DEV_MODE` | Simulation `NEW_POST`, `SESSION_EXPIRED`, scénarios, sans TikTok |
| `TKTK_DATA_PATH` | JSON runtime (défaut `./data`) |
| `TKTK_LOG_PATH` | Journaux |
| `TKTK_PROFILES_PATH` | Profils Chrome isolés |
| `TKTK_UPLOADS_PATH` | Médias de publication |
| `TIKTOK_API_ENABLED` / `TIKTOK_CLIENT_KEY` | API officielle si disponible |

Sur VPS : données dans `/var/lib/tktknueva/{data,profiles,uploads,logs}`, code dans `/var/www/tktknueva`.

## DEV_MODE

`POST /api/simulate` avec `action` :

`NEW_POST` · `SCHEDULED_POST` · `SESSION_EXPIRED` · `SCENARIO_COMPLETED` · `SCENARIO_FAILED`

Le dashboard expose le bouton **Simuler NEW_POST** lorsque `DEV_MODE` est actif.

## Structure

```text
app/          Controllers, Services, Storage, Views
public/       Front controller + assets
worker/       Scheduler, dispatchers, monitor, ResourceManager
browser/      BrowserManager, SessionManager, actions, recorder
data/examples Fichiers d’exemple versionnés
config/       Nginx + systemd d’exemple
```

Runtime **non versionné** : `data/*.json`, `profiles/`, `uploads/`, `logs/`, `.env`.

## Tests

```bash
php tests/php/run.php
python3 tests/python/test_scheduler.py
```

## Déploiement VPS

1. Copier le code (rsync, archive, ou dépôt Git bare privé — pas besoin de GitHub).
2. `cp config/nginx.example.conf` vers Nginx ; HTTPS.
3. `cp config/tktknueva-worker.service /etc/systemd/system/` puis `systemctl enable --now tktknueva-worker`.
4. PHP-FPM pointe sur `public/`.
5. Chromium + chromedriver sur le serveur.

Mise à jour : déployer le nouveau code, `php bin/migrate-storage.php`, redémarrer le worker. Les données de production ne vivent pas dans Git.

## Workflow Git

```text
modification → tests → git diff → git add → git commit
```

Pas de `git push` obligatoire. Tags locaux : `git tag v0.1.0`. Rollback code : `git switch --detach <tag>`. Rollback données : `data/backups/` (indépendant du code).

Voir [MIGRATION_PLAN.md](./MIGRATION_PLAN.md) pour l’écart V1 → V2.
