# TKTKNUEVA — Rapport d’architecture V1 et plan de migration V2

**Statut :** plan à valider — aucune réécriture applicative dans ce commit.

**Date :** 2026-08-16  
**Dépôt inspecté :** Git local, branche `main` (README seul) + V1 sur `origin/cursor/architecture-tiktok-manager-2bfb`  
**Branche de ce document :** `cursor/tktknueva-v2-plan-5eb2`  
**Branche d’implémentation proposée :** `cursor/tktknueva-v2-5eb2` (équivalent CDC : `feature/tktknueva-v2`)

---

## Vérifications Git (avant toute modification)

| Contrôle | Résultat |
| --- | --- |
| `git status` (sur `main` au démarrage) | Propre, uniquement `README.md` (`# TKTK`) |
| Branche de base | `main` — commit `95bfbb6 Initial commit` |
| `git log --oneline -10` | Un seul commit sur `main` |
| Remote | Présent (`origin` → GitHub `TimotheBernard/TKTK`) mais **non requis par le produit** |
| V1 existante | Branche `cursor/architecture-tiktok-manager-2bfb` (PR brouillon #1, ~6740 lignes, 121 fichiers) |
| Travail direct sur `main` / `master` | Interdit — ce plan est hors `main` |

Le cahier des charges indique qu’une première version existe déjà. Elle n’est **pas** sur `main`. Elle vit sur la branche V1 ci-dessus. Toute migration part de **cette** base, pas d’un greenfield.

---

## Synthèse exécutive

La V1 est un gestionnaire multi-comptes **opérationnel mais incomplet** par rapport au CDC TKTKNUEVA V2.

Elle sait déjà :

- authentifier un opérateur (PHP session + bcrypt) ;
- enregistrer un nombre illimité de comptes (`account_id`) ;
- isoler les profils Chrome ;
- détecter des publications (poller `_watcher`) ;
- générer des tâches à délai fixe après `NEW_POST` ;
- exécuter un worker Python à ~100 ms ;
- lancer Chromium **à la demande** puis le quitter.

Elle ne sait **pas** encore :

- présenter un dashboard à cartes dépliables (recherche, filtres, groupes, favoris) ;
- exprimer des **scénarios** (séquences d’actions abstraites) ;
- publier du contenu TikTok (immédiat / programmé) ;
- enregistrer un parcours (Scenario Recorder) ;
- choisir API vs navigateur (`CapabilityResolver`) ;
- superviser CPU / RAM / navigateurs actifs (`ResourceManager`) ;
- vivre dans l’arborescence CDC (`/app`, `/public`, `/browser`, données hors code).

**Décision de migration proposée :** évolution structurée de la V1 (réorganisation + nouveaux moteurs), **pas** une réécriture from scratch. Les invariants V1 (multi-comptes, isolation, JSON + `StorageInterface`, worker persistant, Selenium hors métier) sont conservés.

**Aucune implémentation V2 n’est lancée tant que ce plan n’est pas validé.**

---

# Rapport d’analyse (27 livrables)

## 1. Fonctionnalités existantes

| Domaine | État V1 |
| --- | --- |
| Auth dashboard `/login` | Sessions PHP `tktk_session`, cookie httponly/samesite, `session_regenerate_id`, mot de passe bcrypt dans `users.json` |
| CSRF | Jeton session, exigé sur les API mutantes |
| Multi-comptes | CRUD dynamique, **aucun** `MAX_ACCOUNTS` |
| Isolation navigateur | `selenium/profiles/{account_id}/` + profil `_watcher` séparé |
| Associations compte ↔ artiste | `targets.json` (N-N, paramètres par relation) |
| Règles de délai | `rules.json` : `account_id` + `artist_id` + `delay_seconds` |
| Ingest de posts | Manuel + watcher Selenium + simulation DEV |
| Événement `NEW_POST` | `EventService::emit` → génération synchrone de tâches |
| File de tâches globale | `tasks.json`, horodatage ISO-8601 UTC ms |
| Worker Python persistant | Boucle 100 ms, dispatch parallèle des tâches dues au même instant |
| Ouverture de post | Action Selenium `open_post` uniquement |
| Simulation DEV | `settings.dev_mode` + `/api/simulate.php` |
| Historique / stats / logs | Pages + JSON |
| Tests | `tests/php/run.php`, `tests/python/test_scheduler.py` |
| Chemins données/logs | Surchargeables via `TKTK_DATA_PATH` / `TKTK_LOG_PATH` |

## 2. Fonctionnalités partielles

| Élément CDC | Écart |
| --- | --- |
| Dashboard unique | Existe, mais **tableaux + métriques**, pas cartes dépliables |
| États de compte | `idle/busy/offline/disabled/session_expired/error` — manquent `QUEUED`, `STARTING`, `COOLDOWN` |
| EventDispatcher | `EventService` = journal + handler synchrone **uniquement** pour `NEW_POST` |
| PublicationMonitor | `watch_loop.py` (poll 15 s, profil `_watcher`) — pas un service nommé, pas API-first |
| TaskDispatcher | `scheduler_worker.execute_one` + `task_runner` — pas de résolution de capacité |
| BrowserManager / SessionManager | Présents en Python, cycle start/quit par tâche |
| Scheduler à la seconde | Timestamps à la ms, poll 100 ms, **pas** de sleep jusqu’à la prochaine échéance, warmup Chrome non utilisé |
| DEV_MODE | Booléen `settings.json`, pas de `.env` / `DEV_MODE=true` |
| Historique | Global + par compte via filtre, événements métier incomplets vs CDC |
| StorageInterface | Existe, mais `App::storage()` est typé `JsonStorage` |
| Providers de posts | Interface + stubs vides, jamais résolus |
| Activité temps réel | Feed dashboard (poll 1 s), **pas** de page `/activity` |

## 3. Fonctionnalités absentes

- Cartes comptes dépliables, recherche, filtres, tri, favoris, groupes, pagination / lazy loading
- ScenarioEngine, ActionRegistry, scénarios JSON multi-étapes
- Éditeur visuel de scénarios
- Scenario Recorder / zoning abstrait
- CapabilityResolver (API d’abord, navigateur ensuite)
- TikTokPublishingService, upload drag & drop, mentions `@`, publier maintenant / programmer
- ResourceManager et écran `SYSTÈME > RESSOURCES`
- Catalogue d’actions métier (`WATCH`, `WAIT_RANDOM`, `USER_CONFIRMATION`, `PUBLISH_POST`, …)
- Temporisations fenêtre / aléatoire dans une fenêtre / minutes / heures (seul le délai **fixe en secondes** existe)
- `.env.example`, séparation code / données VPS (`/var/lib/tktknueva`)
- Arborescence CDC (`/app`, `/public`, `/browser`)
- Tags Git `v0.1.0` …
- Page `/activity` dédiée
- Groupes de comptes (`PRINCIPAUX`, `PROMOTION`, …)

## 4. Composants inutiles (à retirer ou fusionner)

| Composant V1 | Décision proposée |
| --- | --- |
| Pages éclatées (`artists.php`, `targets.php`, `rules.php`, …) comme **écran principal** | Conservées en API ; l’UI migre vers le dashboard dépliable. Pages secondaires optionnelles. |
| `RuleService` / `rules.json` | **Remplacés** par `scenarios.json` (un scénario = trigger + timing + steps). Migration des délais V1 → scénario `NEW_POST` + étape implicite `OPEN_POST`. |
| Providers PHP stubs jamais instanciés | Remplacés par `PublicationMonitor` + `CapabilityResolver` |
| `BackupService` jamais appelé | Conservé, branché sur les écritures critiques + écran système |
| Marque « TikTok Manager » / `APP_NAME` | Renommer **TKTKNUEVA** |
| `data/*.json` versionnés (runtime) | Sortir du Git ; ne versionner que `data/examples/` |

## 5. Composants à modifier

| Composant | Modification |
| --- | --- |
| Auth | Routes `/login` `/dashboard` (rewrite nginx / front controller), conserver bcrypt + sessions |
| Dashboard | Cartes, expand in-place, toolbar recherche/filtres/groupes |
| `EventService` | Devenir un vrai `EventDispatcher` (registre de listeners, types extensibles) |
| `TaskService` | Tâches génériques (`kind`: scenario_step / publish / session_check), plus seulement `open_post` |
| `AccountService` | Champs CDC (`connection_status`, `runtime_status`, `group`, `favorite`, `current_task`, `next_task`) |
| Worker | Sleep jusqu’à `next_due`, warmup, pid-lock, ResourceManager, activation événementielle |
| `BrowserManager` | Libération garantie, cooldown, jamais N Chromium = N comptes |
| `.gitignore` | Exclure tout runtime (`/data/*`, `/profiles/*`, `/uploads/*`, `/logs/*`, `.env`) |
| README | Aligné sur l’arborescence réelle V2 |
| Tests | Étendre par phase (auth, dashboard, scénarios, scheduler, capability, resources) |

## 6. Nouvelle arborescence

Cible CDC, avec les ajouts V1 justifiés (bootstrap API, storage, tests, exemples).

```text
/tktknueva
├── app/
│   ├── Controllers/          # Login, Dashboard, Accounts, Activity, System, Api
│   ├── Services/             # métier PHP (Account, History, Settings, Auth…)
│   ├── Models/               # DTO / schémas (pas d’ORM V1)
│   └── Storage/
│       ├── StorageInterface.php
│       └── JsonStorage.php
├── public/
│   ├── index.php             # front controller
│   └── assets/{css,js,img}
├── api/                      # endpoints JSON (ou app/Controllers/Api)
├── worker/
│   ├── scheduler.py
│   ├── event_dispatcher.py
│   ├── task_dispatcher.py
│   ├── publication_monitor.py
│   ├── scenario_engine.py
│   ├── capability_resolver.py
│   ├── resource_manager.py
│   └── json_store.py
├── browser/
│   ├── browser_manager.py
│   ├── session_manager.py
│   ├── actions.py            # implémentations Selenium des actions du registry
│   ├── action_registry.py
│   └── recorder.py
├── config/
│   ├── app.php
│   └── nginx.example.conf
├── data/                     # runtime — NON versionné
├── data/examples/            # fichiers d’exemple versionnés
├── profiles/                 # runtime Chrome — NON versionné
├── uploads/                  # médias publications — NON versionné
├── logs/                     # NON versionné
├── tests/{php,python}
├── .env.example
├── .gitignore
├── README.md
├── MIGRATION_PLAN.md
└── ARCHITECTURE.md           # mis à jour en phase 1, puis synchronisé
```

Sur VPS : code dans `/var/www/tktknueva`, données dans `/var/lib/tktknueva/{data,profiles,uploads,logs}` via variables d’environnement.

## 7. Nouveau modèle de données

Envelope commune (inchangée) :

```json
{ "version": 2, "updated_at": "ISO-8601", "items": [] }
```

Collections V2 :

| Fichier | Rôle |
| --- | --- |
| `users.json` | Auth TKTKNUEVA (hash uniquement) |
| `accounts.json` | Comptes contrôlés |
| `artists.json` | Profils TikTok connus (cibles) |
| `targets.json` | Relation N-N account↔artist + flags indépendants |
| `scenarios.json` | Scénarios abstraits (remplace `rules.json`) |
| `posts.json` | Publications détectées |
| `tasks.json` | File scheduler (scénarios, publications, checks) |
| `publications.json` | File de publication (immédiat / programmé) — **nouveau** |
| `events.json` | Bus / journal d’événements internes |
| `history.json` | Audit opérateur |
| `settings.json` | Config runtime |
| `worker_state.json` | Heartbeat worker |
| `resource_state.json` | Snapshot ResourceManager — **nouveau** |

### Compte

```json
{
  "id": "account_a84f92",
  "label": "Account 01",
  "username": "@account01",
  "display_name": "",
  "profile_picture": "",
  "group": "PRINCIPAUX",
  "favorite": false,
  "enabled": true,
  "connection_status": "connected",
  "runtime_status": "idle",
  "browser_profile": "account_a84f92",
  "current_task_id": null,
  "next_task_id": null,
  "created_at": "",
  "last_activity": ""
}
```

`runtime_status` ∈ `idle | queued | starting | busy | cooldown | session_expired | disconnected | error | disabled`

Aucun champ mot de passe / cookie / token TikTok.

### Scénario (remplace la règle)

```json
{
  "id": "scenario_001",
  "account_id": "account_a84f92",
  "artist_id": "artist_001",
  "trigger": "NEW_POST",
  "timing": { "type": "fixed", "delay_seconds": 5 },
  "steps": [
    { "type": "OPEN_POST" },
    { "type": "WATCH", "value": "100%" },
    { "type": "WAIT", "seconds": 5 },
    { "type": "USER_CONFIRMATION" }
  ],
  "enabled": true
}
```

`timing.type` ∈ `fixed | minutes | hours | window | random_window`

### Publication programmée

```json
{
  "id": "pub_001",
  "account_id": "account_a84f92",
  "caption": "",
  "mentions": [],
  "media": [],
  "mode": "scheduled",
  "scheduled_at": "",
  "status": "draft | queued | sending | sent | failed",
  "created_at": ""
}
```

### Migration V1 → V2 (données)

- `rules` → scénario `NEW_POST` à une étape `OPEN_POST` + `timing.fixed.delay_seconds`
- `accounts.status` / `session_status` → `runtime_status` / `connection_status`
- `tasks` existantes : `kind = "legacy_open_post"` jusqu’à épuisement
- `version` envelope : 1 → 2 via script `bin/migrate-storage.php` (idempotent)

## 8. Architecture de `EventDispatcher`

**Rôle :** unique point d’entrée des événements internes. Une source **n’appelle jamais** Selenium.

```text
SOURCE → EVENT → EventDispatcher → listeners enregistrés
```

Sources : `PublicationMonitor`, `ScheduledPublication`, `ScheduledScenario`, `Dashboard`, `SessionMonitor`, `SimulationService`.

Types initiaux (extensibles par registre, pas par `switch` figé) :

`NEW_POST`, `SCHEDULED_POST`, `SCHEDULED_SCENARIO`, `USER_ACTION`, `SESSION_CHECK`, plus les événements d’historique CDC.

Implémentation :

- PHP : `app/Services/EventDispatcher.php` (émission depuis l’API / ingest)
- Python : `worker/event_dispatcher.py` (émission depuis monitor / scheduler)
- Contrat : append `events.json` + invocation des listeners
- Isolation : l’échec d’un listener **ne bloque pas** les autres ; l’échec d’un `account_id` n’affecte pas les autres comptes

`NEW_POST` ne lance pas le navigateur : il crée des **tâches** via `ScenarioEngine`.

## 9. Architecture de `ScenarioEngine`

Un scénario = `account_id` + `artist_id` + `trigger` + `timing` + `steps[]`.

Interdit dans un scénario : Selenium, xpath, css, coordonnées.

Flux :

```text
EVENT (ex. NEW_POST)
  → ScenarioEngine.match(account, artist, trigger)
  → calcule scheduled_at (fixe / fenêtre / random)
  → crée 1 tâche (ou 1 tâche par scénario) dans tasks.json
  → Scheduler
```

L’exécution des `steps` a lieu **à l’échéance**, pas à la détection. Entre les deux, le compte reste `IDLE` (navigateur dormant).

## 10. Architecture de `ActionRegistry`

Catalogue initial (extensible) :

`OPEN_PROFILE`, `OPEN_POST`, `OPEN_URL`, `WATCH`, `SCROLL`, `WAIT`, `WAIT_RANDOM`, `CHECK_PAGE`, `CHECK_SESSION`, `USER_CONFIRMATION`, `PUBLISH_POST`.

Chaque action déclare :

- `id`, paramètres JSON schema
- `requires_browser: bool`
- `requires_session: bool`
- `requires_user: bool` (ex. `USER_CONFIRMATION`)

Le registry vit côté worker (`browser/action_registry.py`) avec un miroir PHP pour l’éditeur UI (liste des blocs). **Une seule implémentation DOM** par action (`OPEN_POST` dans `browser/actions.py`). Si TikTok change, on corrige cette implémentation, pas les scénarios.

## 11. Architecture de `CapabilityResolver`

Principe obligatoire : **API FIRST, BROWSER SECOND**.

```text
ACTION
  → CapabilityResolver
      → API officielle adaptée disponible ? → TikTokService / TikTokPublishingService
      → sinon action navigateur compatible → BrowserManager
```

V2 :

- Publication : Content Posting API / Login Kit **si** credentials officiels configurés
- Détection : API officielle si disponible, sinon monitor autorisé (interfaces/sources autorisées), **jamais** d’appel Selenium depuis la source d’événement
- Scénarios de visionnage / scroll : navigateur (pas d’API officielle équivalente)

Le scénario ne connaît pas la technologie d’exécution.

## 12. Architecture de `TaskDispatcher`

À l’échéance :

```text
Task → account_id → Scenario | Publication
    → Action (courante)
    → CapabilityResolver
    → exécution
    → history + runtime_status
```

Règles :

- une erreur sur un compte n’annule pas les autres tâches dues au même instant
- plusieurs comptes peuvent partager le même `scheduled_at`
- le dispatcher **ne recalcule jamais** `scheduled_at`
- si l’action n’a pas besoin du navigateur : pas de Chromium
- si besoin navigateur : `IDLE → STARTING → BUSY → (COOLDOWN) → IDLE`

## 13. Architecture de `PublicationMonitor`

Service Python `worker/publication_monitor.py`.

- Lit les cibles `check_new_posts=true`
- Utilise la source **autorisée** la plus adaptée (CapabilityResolver)
- Dédoublonne `video_id` / `post_id`
- Émet **uniquement** `NEW_POST` vers `EventDispatcher`
- **N’appelle jamais Selenium directement** depuis le code de détection métier : s’il faut un navigateur, c’est via une capability `LIST_PROFILE_POSTS` du resolver

DEV_MODE : injection d’événements sans TikTok.

## 14. Architecture de `TikTokPublishingService`

Service distinct des scénarios.

```text
PUBLICATIONS UI → publications.json
  → SCHEDULED_POST | immédiat
  → Scheduler
  → TaskDispatcher
  → CapabilityResolver
      → API officielle → TikTokPublishingService
      → sinon BrowserAction PUBLISH_POST (si compatible)
  → history PUBLICATION_SENT | PUBLICATION_FAILED
```

UI : compte, caption, mentions autocomplétées depuis artistes/comptes connus, médias (drag & drop, preview, suppression), `PUBLIER MAINTENANT` / `PROGRAMMER`.

Aucun mot de passe TikTok en JSON. Auth publication = token OAuth officiel **ou** session Chrome du profil `account_id`.

## 15. Architecture de `BrowserManager`

Python `browser/browser_manager.py` (évolution du module V1).

- Démarrer Chromium avec `--user-data-dir` du profil
- Ouvrir une URL
- Cycle de vie start / crash / timeout / close
- Libération **toujours** en `finally`
- Pas de pool permanent : 100 comptes ≠ 100 Chrome
- Warmup configurable **avant** l’échéance seulement si l’action le requiert (et sans décaler `scheduled_at`)

## 16. Architecture de `SessionManager`

- Map `account_id` → profil exclusif
- Vérifie l’état (`CHECK_SESSION`)
- `SESSION_EXPIRED` → `runtime_status=session_expired`, demande de reconnexion UI, **pas** de mot de passe stocké
- Isolation stricte : cookies, localStorage, tokens, user-data-dir jamais partagés

## 17. Architecture du Scenario Recorder

CTA `ENREGISTRER UN SCÉNARIO` → session observée (compte choisi).

Le recorder observe : navigation, ouverture de page/publication, scroll, attente, durée de visionnage.

**Sortie interdite :** `click x,y` ou xpath brut comme unique représentation.

**Sortie obligatoire :** actions abstraites `OPEN_PROFILE → OPEN_POST → WATCH → WAIT`.

L’éditeur visuel permet ensuite d’ajouter / supprimer / réordonner / configurer les blocs.

## 18. Architecture du `ResourceManager`

Superviseur, **pas** un plafond métier de comptes.

Métriques : CPU, RAM, navigateurs actifs, workers, tâches pending/running, files.

Écran `SYSTÈME > RESSOURCES` :

```text
Comptes enregistrés     N
Comptes connectés       n
Navigateurs actifs      k
Tâches en attente / en cours
CPU / RAM
```

Il peut **retarder** (queue) une activation navigateur si la machine est saturée, jamais refuser d’enregistrer un compte.

## 19. Stratégie d’activation événementielle

État normal d’un compte enregistré et connecté : **IDLE, navigateur dormant**.

```text
NEW_POST
  → ScenarioEngine (tâche à T+delay)
  → Scheduler (précision seconde)
  → TaskDispatcher
  → si compte IDLE et action navigateur : STARTING → charge profil → BUSY
  → sinon API sans Chrome
  → résultat → libération → IDLE
```

Le watcher de détection, s’il a besoin d’un navigateur, utilise le profil `_watcher` (ou une API), **pas** les N profils de comptes.

## 20. Stratégie de conservation des sessions (navigateurs dormants)

- Persistance = répertoire profil Chrome sur disque (`profiles/{account_id}/`)
- Chrome n’est pas laissé ouvert « pour garder la session »
- Login TikTok : lancement **détaché** ponctuel (déjà en V1 : `action=launch`) puis fermeture ; la session reste dans le profil
- `SESSION_CHECK` périodique ou à la demande, léger, peut réutiliser un Chromium court puis quit

## 21. Modifications nécessaires pour un VPS

- PHP-FPM + Nginx (`config/nginx.example.conf`), HTTPS
- Worker systemd : `tktknueva-worker.service` (Restart=always)
- Variables : `TKTK_DATA_PATH=/var/lib/tktknueva/data`, idem profiles/uploads/logs
- Chrome/Chromium + chromedriver headless sur le serveur
- Séparation code `/var/www/tktknueva` vs données `/var/lib/tktknueva`
- Git **local** sur le VPS ; déploiement par rsync / archive / repo bare privé — **aucune hypothèse GitHub**
- `.htaccess` / Nginx : deny `data`, `profiles`, `uploads`, `logs`, `config`, `.env`

## 22. Stratégie Git locale

- Le dépôt Git **local** est la source de vérité du **code**
- Pas de GitHub Actions, pas de dépendance produit à GitHub/GitLab/Bitbucket
- Remote facultatif ; le workflow métier est `modifier → tester → git add → git commit`
- Données de production **hors** Git : un rollback de code ne rollback pas `accounts.json` / profils / uploads

*Note d’environnement Cursor :* ce workspace est déjà lié à un `origin` GitHub (héritage du dépôt `TKTK`). Cela n’introduit pas GitHub dans l’architecture applicative. Aucune GitHub Action ne sera créée. Le produit doit pouvoir être cloné/copié et développé **sans** remote.

## 23. Branche proposée

| Usage | Branche |
| --- | --- |
| Ce plan (document seul) | `cursor/tktknueva-v2-plan-5eb2` |
| Implémentation V2 (après validation) | `cursor/tktknueva-v2-5eb2` |
| Nom CDC équivalent | `feature/tktknueva-v2` |
| Interdit | commits directs sur `main` / `master` |

Base d’implémentation : `cursor/architecture-tiktok-manager-2bfb` (V1), pas `main` vide.

## 24. Fichiers à exclure (`.gitignore`)

Ne jamais versionner :

```text
.env
/data/*
/profiles/*
/uploads/*
/logs/*
cookies, sessions, tokens, credentials
.venv/ venv/ __pycache__/ *.pyc cache/ tmp/
```

Versionner :

```text
.env.example
/data/examples/*.example.json
.gitignore, README, code, tests, config d’exemple
```

Corriger la V1 qui versionne aujourd’hui `data/users.json`, `data/settings.json` et les JSON runtime vides.

## 25. Stratégie de commits

Un commit par phase CDC, après tests locaux. Exemples :

```text
chore: prepare TKTKNUEVA v2 architecture
feat: implement TKTKNUEVA authentication
feat: add expandable multi-account dashboard
feat: implement account session management
feat: add followed profiles and targets
feat: add publication monitor
feat: implement event dispatcher
feat: implement scenario engine
feat: add realtime scheduler
feat: implement task dispatcher and capability resolver
feat: implement browser manager
feat: add scenario recorder
feat: implement scheduled publishing
feat: add history and activity feed
feat: implement resource manager
feat: harden security and env configuration
feat: add VPS deployment docs and unit files
```

Pas un unique commit final. Pas de `git push` **dans le workflow produit**. (La livraison Cursor de ce plan peut utiliser le remote déjà présent ; ce n’est pas une dépendance de TKTKNUEVA.)

## 26. Stratégie de rollback

- Tag local après chaque phase stable : `v0.1.0` (auth+archi), `v0.2.0` (dashboard), … `v1.0.0`
- Avant une évolution : `git status` propre
- Rollback code : `git switch --detach <tag>` ou revert de commit de phase
- Rollback données : backups JSON (`BackupService`) dans `/var/lib/tktknueva/data/backups`, **indépendants** du Git
- Worker : une tâche `failed` d’un compte ne stoppe pas le processus

## 27. Plan de migration (phasé)

Voir section suivante. Principe : **aucune réécriture globale d’un coup**. Chaque phase est testable et commitable.

---

# Plan de migration (à valider)

## Principe

```text
V1 (branche architecture-tiktok-manager)
        ↓
Phase 1  squelette V2 + Git + .env + README   → tag v0.1.0-arch
        ↓
Phases 2–18  incréments CDC
        ↓
V2 TKTKNUEVA
```

On **déplace** le code V1 vers la nouvelle arborescence au lieu de le jeter. On **remplace** les règles par des scénarios via un migrateur.

## Phase 1 — Git et architecture

- Branche `cursor/tktknueva-v2-5eb2` depuis la V1
- `.gitignore` CDC, `.env.example`, `data/examples/`
- README TKTKNUEVA (archi, install PHP/Python/worker, DEV_MODE, VPS, Git local)
- Déplacer vers `/app` `/public` `/browser` (aliases / front controller pour ne pas casser les tests)
- `APP_NAME=TKTKNUEVA`
- Script de migration storage v1→v2 (no-op si vide)

**Commit :** `chore: prepare TKTKNUEVA v2 architecture`  
**Tests :** PHP bootstrap, chemins data/examples, gitignore (aucun secret)

## Phase 2 — Authentification

- `/login` → `/dashboard`
- Sessions sécurisées déjà en place : durcir (`secure` cookie, rotation CSRF)
- Plus de hash `changeme` dans le dépôt : bootstrap initial via `.env` / commande CLI `php bin/create-user.php`

**Commit :** `feat: implement TKTKNUEVA authentication`

## Phase 3 — Dashboard

- Grille de cartes (photo, username, nom, statuts, suivis, scénarios, tâche courante / suivante, OUVRIR, CONFIGURER)
- Clic → expand in-place (cibles, scénarios, publications, historique, paramètres)
- Toolbar : recherche, filtres, tri, favoris, groupes, pagination/lazy, filtre statut
- Majorité des actions sans quitter le dashboard

**Commit :** `feat: add expandable multi-account dashboard`

## Phase 4 — Gestion des comptes

- Modal `+ AJOUTER UN COMPTE` (mécanismes officiels, jamais mot de passe TikTok en clair)
- Groupes, favoris, enable/disable
- Compte N+1 sans modification de code (déjà vrai — conserver les tests)

## Phase 5 — SessionManager

- Mapping, check, expiration, reconnexion UI, isolation
- États `connection_status` / `runtime_status`

## Phase 6 — Comptes suivis

- Cibles N-N, 30+ profils, paramètres par relation
- UI dans la carte dépliée : `+ AJOUTER UNE CIBLE`

## Phase 7 — PublicationMonitor

- Service dédié, événement interne uniquement
- DEV_MODE : simuler `NEW_POST`

## Phase 8 — EventDispatcher

- Registre de listeners, types extensibles
- Plus de couplage `EventService` → `TaskService` en dur : listener `ScenarioEngine`

## Phase 9 — ScenarioEngine + ActionRegistry + éditeur

- JSON scénarios, timing fixe/fenêtre/aléatoire
- UI blocs + CTA `+ AJOUTER UNE ÉTAPE`
- Migration `rules` → scénarios

## Phase 10 — Scheduler

- Worker persistant, précision seconde, sleep jusqu’à next due
- Pid-lock, warmup, mêmes timestamps en parallèle
- Pas de cron minute

## Phase 11 — TaskDispatcher + CapabilityResolver

- Chaîne Task → Action → API ou Browser
- Isolation d’erreur par compte

## Phase 12 — BrowserManager

- Cycle de vie, libération, profils `profiles/{account_id}/`
- 100 comptes / k Chrome actifs

## Phase 13 — Scenario Recorder

- Session d’enregistrement → actions abstraites
- Relecture dans l’éditeur

## Phase 14 — Publications

- Upload, mentions, immédiat / programmé
- `TikTokPublishingService` + `publications.json`

## Phase 15 — Historique + `/activity`

- Événements CDC, global et par compte
- AJAX/fetch sans reload

## Phase 16 — ResourceManager

- Écran ressources, métriques, file d’attente navigateur (pas de MAX comptes)

## Phase 17 — Sécurité

- Audit `.env`, headers, deny dirs, pas de secrets Git, sessions, CSRF, permissions fichiers

## Phase 18 — Déploiement VPS

- Nginx example, systemd worker, chemins `/var/lib/tktknueva`, procédure rsync/archive/bare Git
- Tag `v1.0.0`

---

# Ce qui n’est **pas** fait dans ce livrable

- Aucune réécriture de l’application
- Aucun déplacement de fichiers V1
- Aucune GitHub Action
- Aucun tag (les tags viendront après validation + phases)

# Validation attendue

Merci de valider (ou amender) :

1. Migration **incrémentale** depuis la V1 (vs greenfield)
2. Branche d’implémentation `cursor/tktknueva-v2-5eb2`
3. Remplacement `rules` → `scenarios` avec migrateur
4. Dashboard cartes dépliables comme écran principal
5. API officielle prioritaire ; Selenium uniquement comme couche navigateur
6. Données runtime hors Git ; exemples seulement
7. Démarrage Phase 1 dès validation

Dès validation, le workflow sera uniquement :

```text
MODIFICATION → TEST → git diff → git add → git commit
```
