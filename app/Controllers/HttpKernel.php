<?php

declare(strict_types=1);

final class HttpKernel
{
    public static function dispatch(): void
    {
        $path = request_path();
        $method = request_method();

        $aliases = [
            '/login.php' => '/login',
            '/logout.php' => '/logout',
            '/index.php' => '/dashboard',
            '/settings.php' => '/dashboard',
        ];
        $path = $aliases[$path] ?? $path;

        if ($path === '/login' && $method === 'GET') {
            self::loginPage();
            return;
        }
        if ($path === '/login' && $method === 'POST') {
            Security::requireCsrf();
            $username = (string) ($_POST['username'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            if (Security::login($username, $password)) {
                header('Location: /dashboard');
                exit;
            }
            $error = 'Identifiant ou mot de passe incorrect.';
            self::loginPage($error);
            return;
        }
        if ($path === '/logout') {
            Security::logout();
            header('Location: /login');
            exit;
        }

        if (str_starts_with($path, '/api/')) {
            self::api($path, $method);
            return;
        }

        Security::requireLogin();
        if ($path === '/' || $path === '/dashboard') {
            self::view('dashboard', 'Dashboard', 'dashboard');
            return;
        }
        if ($path === '/activity') {
            self::view('activity', 'Activité', 'activity');
            return;
        }
        if ($path === '/system/resources' || $path === '/resources') {
            self::view('resources', 'Ressources', 'resources');
            return;
        }

        http_response_code(404);
        echo 'Not found';
    }

    private static function loginPage(string $error = ''): void
    {
        Security::startSession();
        if (Security::user() !== null) {
            header('Location: /dashboard');
            exit;
        }
        $csrf = Security::csrfToken();
        $errorHtml = $error !== '' ? '<p class="form-error">' . htmlspecialchars($error) . '</p>' : '';
        echo '<!DOCTYPE html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
        echo '<title>Connexion — ' . htmlspecialchars(APP_NAME) . '</title>';
        echo '<link rel="stylesheet" href="/assets/css/app.css"></head><body class="login-body">';
        echo '<form class="login-card" method="post" action="/login">';
        echo '<div class="brand-lockup"><span class="logo-mark">TK</span><div><strong>TKTKNUEVA</strong><small>Gestion multi-comptes</small></div></div>';
        echo $errorHtml;
        echo '<label>Identifiant<input name="username" autocomplete="username" required></label>';
        echo '<label>Mot de passe<input type="password" name="password" autocomplete="current-password" required></label>';
        echo '<input type="hidden" name="_csrf" value="' . htmlspecialchars($csrf) . '">';
        echo '<button type="submit">Se connecter</button></form></body></html>';
    }

    private static function view(string $page, string $title, string $script): void
    {
        $user = Security::user();
        $csrf = Security::csrfToken();
        $dev = App::settings()->isDevMode() ? 'true' : 'false';
        require ROOT_PATH . '/app/Views/shell.php';
    }

    private static function api(string $path, string $method): void
    {
        Security::startSession();
        $GLOBALS['_REQUEST_JSON'] = request_json();
        $body = $GLOBALS['_REQUEST_JSON'];
        Security::requireLogin();
        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            Security::requireCsrf();
        }

        try {
            match (true) {
                $path === '/api/dashboard' && $method === 'GET' => self::dashboard(),
                $path === '/api/accounts' && $method === 'GET' => ApiResponse::items(array_map(fn ($a) => App::accounts()->enrich($a), App::accounts()->all()), ['counts' => App::accounts()->counts()]),
                $path === '/api/accounts' && $method === 'POST' => ApiResponse::success(App::accounts()->create($body)),
                str_starts_with($path, '/api/accounts/') => self::accountItem($path, $method, $body),
                $path === '/api/artists' && $method === 'GET' => ApiResponse::items(App::artists()->all()),
                $path === '/api/artists' && $method === 'POST' => ApiResponse::success(App::artists()->create($body)),
                str_starts_with($path, '/api/artists/') => self::artistItem($path, $method, $body),
                $path === '/api/targets' && $method === 'GET' => ApiResponse::items(App::targets()->all()),
                $path === '/api/targets' && $method === 'POST' => ApiResponse::success(App::targets()->create($body)),
                str_starts_with($path, '/api/targets/') => self::targetItem($path, $method, $body),
                $path === '/api/scenarios' && $method === 'GET' => ApiResponse::items(App::scenarios()->all(), ['actions' => ActionRegistry::catalog()]),
                $path === '/api/scenarios' && $method === 'POST' => ApiResponse::success(App::scenarios()->create($body)),
                str_starts_with($path, '/api/scenarios/') => self::scenarioItem($path, $method, $body),
                $path === '/api/posts' && $method === 'GET' => ApiResponse::items(App::posts()->all()),
                $path === '/api/posts' && $method === 'POST' => ApiResponse::success(App::posts()->ingestManual((string) ($body['url'] ?? ''), $body['artist_id'] ?? null)),
                $path === '/api/publications' && $method === 'GET' => ApiResponse::items(App::publications()->all()),
                $path === '/api/publications' && $method === 'POST' => ApiResponse::success(App::publications()->create($body)),
                $path === '/api/tasks' && $method === 'GET' => ApiResponse::success(App::tasks()->supervision()),
                $path === '/api/history' && $method === 'GET' => ApiResponse::items(App::history()->recent((int) ($_GET['limit'] ?? 100))),
                $path === '/api/activity' && $method === 'GET' => ApiResponse::items(App::history()->recent(80)),
                $path === '/api/events' && $method === 'GET' => ApiResponse::items(App::events()->all()),
                $path === '/api/resources' && $method === 'GET' => ApiResponse::success(App::resources()->snapshot()),
                $path === '/api/settings' && $method === 'GET' => ApiResponse::success(self::publicSettings()),
                $path === '/api/settings' && $method === 'PUT' => ApiResponse::success(App::settings()->update($body)),
                $path === '/api/password' && $method === 'POST' => self::password($body),
                $path === '/api/mentions' && $method === 'GET' => ApiResponse::items(App::artists()->knownUsernames()),
                $path === '/api/recorder' && $method === 'POST' => ApiResponse::success(['steps' => App::recorder()->abstractize($body['events'] ?? [])]),
                $path === '/api/uploads' && $method === 'POST' => self::upload(),
                $path === '/api/simulate' && $method === 'POST' => self::simulate($body),
                $path === '/api/stats' && $method === 'GET' => ApiResponse::success(App::stats()->compute()),
                default => ApiResponse::error('NOT_FOUND', 'Unknown endpoint', 404),
            };
        } catch (InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage(), $e->getMessage(), 422);
        } catch (RuntimeException $e) {
            $code = $e->getMessage();
            $status = $code === 'DEV_MODE_REQUIRED' ? 403 : 400;
            ApiResponse::error($code, $code, $status);
        }
    }

    private static function dashboard(): never
    {
        $accounts = array_map(fn ($a) => App::accounts()->enrich($a), App::accounts()->all());
        ApiResponse::success([
            'accounts' => $accounts,
            'counts' => App::accounts()->counts(),
            'artists' => App::artists()->all(),
            'posts' => array_slice(App::posts()->all(), 0, 20),
            'publications' => array_slice(App::publications()->all(), 0, 20),
            'tasks' => App::tasks()->supervision(),
            'history' => App::history()->recent(40),
            'orphans' => App::targets()->orphans(),
            'scheduler' => App::scheduler()->state(),
            'resources' => App::resources()->snapshot(),
            'actions' => ActionRegistry::catalog(),
            'groups' => account_groups(),
            'dev_mode' => App::settings()->isDevMode(),
            'settings' => self::publicSettings(),
        ]);
    }

    private static function publicSettings(): array
    {
        $settings = App::settings()->get();
        unset($settings['tiktok_client_key']);
        $settings['tiktok_api_configured'] = App::tiktok()->officialApiAvailable();
        return $settings;
    }

    private static function accountItem(string $path, string $method, array $body): never
    {
        $rest = substr($path, strlen('/api/accounts/'));
        $parts = explode('/', $rest);
        $id = $parts[0];
        $action = $parts[1] ?? '';
        if ($method === 'GET') {
            $account = App::accounts()->get($id);
            if ($account === null) {
                ApiResponse::error('ACCOUNT_NOT_FOUND', 'Account not found', 404);
            }
            ApiResponse::success(App::accounts()->enrich($account));
        }
        if ($method === 'PUT') {
            ApiResponse::success(App::accounts()->update($id, $body));
        }
        if ($method === 'DELETE') {
            ApiResponse::success(['deleted' => App::accounts()->delete($id)]);
        }
        if ($method === 'POST' && $action === 'open') {
            $account = App::accounts()->get($id);
            ApiResponse::success(BrowserService::launchAccount($id, (string) ($account['username'] ?? '')));
        }
        if ($method === 'POST' && $action === 'check') {
            ApiResponse::success(BrowserService::checkSession($id));
        }
        if ($method === 'POST' && $action === 'enable') {
            ApiResponse::success(App::accounts()->update($id, ['enabled' => true]));
        }
        if ($method === 'POST' && $action === 'disable') {
            ApiResponse::success(App::accounts()->update($id, ['enabled' => false]));
        }
        ApiResponse::error('NOT_FOUND', 'Unknown account action', 404);
    }

    private static function artistItem(string $path, string $method, array $body): never
    {
        $id = substr($path, strlen('/api/artists/'));
        if ($method === 'PUT') {
            ApiResponse::success(App::artists()->update($id, $body));
        }
        if ($method === 'DELETE') {
            ApiResponse::success(['deleted' => App::artists()->delete($id)]);
        }
        ApiResponse::error('NOT_FOUND', 'Unknown artist action', 404);
    }

    private static function targetItem(string $path, string $method, array $body): never
    {
        $id = substr($path, strlen('/api/targets/'));
        if ($method === 'PUT') {
            ApiResponse::success(App::targets()->update($id, $body));
        }
        if ($method === 'DELETE') {
            ApiResponse::success(['deleted' => App::targets()->delete($id)]);
        }
        ApiResponse::error('NOT_FOUND', 'Unknown target action', 404);
    }

    private static function scenarioItem(string $path, string $method, array $body): never
    {
        $id = substr($path, strlen('/api/scenarios/'));
        if ($method === 'PUT') {
            ApiResponse::success(App::scenarios()->update($id, $body));
        }
        if ($method === 'DELETE') {
            ApiResponse::success(['deleted' => App::scenarios()->delete($id)]);
        }
        ApiResponse::error('NOT_FOUND', 'Unknown scenario action', 404);
    }

    private static function password(array $body): never
    {
        $user = Security::user();
        Security::changePassword(
            (string) ($user['id'] ?? ''),
            (string) ($body['current'] ?? ''),
            (string) ($body['new'] ?? ''),
            (string) ($body['confirm'] ?? '')
        );
        ApiResponse::success(['ok' => true]);
    }

    private static function upload(): never
    {
        if (empty($_FILES['file']) || !is_array($_FILES['file'])) {
            ApiResponse::error('VALIDATION_ERROR', 'Missing file', 422);
        }
        $file = $_FILES['file'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            ApiResponse::error('UPLOAD_FAILED', 'Upload failed', 400);
        }
        $ext = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'mp4', 'mov', 'webm'], true)) {
            ApiResponse::error('VALIDATION_ERROR', 'Unsupported media type', 422);
        }
        $name = generate_id('media') . '.' . $ext;
        $dest = UPLOADS_PATH . '/' . $name;
        if (!move_uploaded_file((string) $file['tmp_name'], $dest)) {
            ApiResponse::error('UPLOAD_FAILED', 'Could not store file', 500);
        }
        ApiResponse::success([
            'id' => pathinfo($name, PATHINFO_FILENAME),
            'filename' => $name,
            'path' => $dest,
            'mime' => (string) ($file['type'] ?? ''),
            'kind' => in_array($ext, ['mp4', 'mov', 'webm'], true) ? 'video' : 'image',
        ]);
    }

    private static function simulate(array $body): never
    {
        $action = (string) ($body['action'] ?? 'NEW_POST');
        $sim = App::simulation();
        $data = match ($action) {
            'NEW_POST', 'new_post' => $sim->newPostFanout($body['delays'] ?? [2, 4, 5, 5, 8], $body['detected_at'] ?? null),
            'SESSION_EXPIRED' => $sim->sessionExpired((string) ($body['account_id'] ?? '')),
            'SCENARIO_COMPLETED' => $sim->completeTask((string) ($body['task_id'] ?? ''), true),
            'SCENARIO_FAILED' => $sim->completeTask((string) ($body['task_id'] ?? ''), false),
            'SCHEDULED_POST' => $sim->emit('SCHEDULED_POST', $body),
            default => $sim->emit($action, $body),
        };
        ApiResponse::success($data);
    }
}
