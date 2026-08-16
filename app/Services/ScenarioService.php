<?php

declare(strict_types=1);

final class ActionRegistry
{
    public static function catalog(): array
    {
        return [
            ['type' => 'OPEN_PROFILE', 'label' => 'Ouvrir le profil', 'requires_browser' => true, 'requires_session' => true, 'requires_user' => false],
            ['type' => 'OPEN_POST', 'label' => 'Ouvrir la publication', 'requires_browser' => true, 'requires_session' => true, 'requires_user' => false],
            ['type' => 'OPEN_URL', 'label' => 'Ouvrir une URL', 'requires_browser' => true, 'requires_session' => false, 'requires_user' => false],
            ['type' => 'WATCH', 'label' => 'Regarder', 'requires_browser' => true, 'requires_session' => true, 'requires_user' => false],
            ['type' => 'SCROLL', 'label' => 'Défiler', 'requires_browser' => true, 'requires_session' => false, 'requires_user' => false],
            ['type' => 'WAIT', 'label' => 'Attendre', 'requires_browser' => false, 'requires_session' => false, 'requires_user' => false],
            ['type' => 'WAIT_RANDOM', 'label' => 'Attente aléatoire', 'requires_browser' => false, 'requires_session' => false, 'requires_user' => false],
            ['type' => 'CHECK_PAGE', 'label' => 'Vérifier la page', 'requires_browser' => true, 'requires_session' => false, 'requires_user' => false],
            ['type' => 'CHECK_SESSION', 'label' => 'Vérifier la session', 'requires_browser' => true, 'requires_session' => true, 'requires_user' => false],
            ['type' => 'USER_CONFIRMATION', 'label' => 'Validation opérateur', 'requires_browser' => false, 'requires_session' => false, 'requires_user' => true],
            ['type' => 'PUBLISH_POST', 'label' => 'Publier', 'requires_browser' => false, 'requires_session' => true, 'requires_user' => false],
        ];
    }

    public static function get(string $type): ?array
    {
        foreach (self::catalog() as $action) {
            if ($action['type'] === $type) {
                return $action;
            }
        }
        return null;
    }

    public static function requiresBrowser(string $type): bool
    {
        $action = self::get($type);
        return $action ? (bool) $action['requires_browser'] : true;
    }
}

final class ScenarioService
{
    public function __construct(private StorageInterface $storage)
    {
    }

    public function all(): array
    {
        $items = $this->storage->read('scenarios')['items'] ?? [];
        if ($items === []) {
            $items = $this->migrateRulesIfNeeded();
        }
        return $items;
    }

    public function get(string $id): ?array
    {
        return $this->storage->findById('scenarios', $id);
    }

    public function forAccount(string $accountId): array
    {
        return array_values(array_filter($this->all(), fn ($s) => ($s['account_id'] ?? '') === $accountId));
    }

    public function findCouple(string $accountId, string $artistId, string $trigger = 'NEW_POST'): ?array
    {
        foreach ($this->all() as $scenario) {
            if (($scenario['account_id'] ?? '') === $accountId
                && ($scenario['artist_id'] ?? '') === $artistId
                && ($scenario['trigger'] ?? 'NEW_POST') === $trigger) {
                return $scenario;
            }
        }
        return null;
    }

    public function matching(string $artistId, string $trigger): array
    {
        $artist = App::artists()->get($artistId);
        if ($artist === null || empty($artist['enabled'])) {
            return [];
        }
        $out = [];
        foreach ($this->all() as $scenario) {
            if (($scenario['artist_id'] ?? '') !== $artistId) {
                continue;
            }
            if (($scenario['trigger'] ?? '') !== $trigger || empty($scenario['enabled'])) {
                continue;
            }
            $account = App::accounts()->get((string) $scenario['account_id']);
            if ($account === null || empty($account['enabled']) || ($account['runtime_status'] ?? '') === 'disabled') {
                continue;
            }
            $target = App::targets()->findCouple((string) $scenario['account_id'], $artistId);
            if ($target === null || empty($target['enabled']) || empty($target['check_new_posts'])) {
                continue;
            }
            $out[] = $scenario;
        }
        return $out;
    }

    public function create(array $input): array
    {
        $accountId = (string) ($input['account_id'] ?? '');
        $artistId = (string) ($input['artist_id'] ?? '');
        $trigger = (string) ($input['trigger'] ?? 'NEW_POST');
        if ($accountId === '' || $artistId === '') {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        $steps = $input['steps'] ?? [['type' => 'OPEN_POST']];
        if (!is_array($steps) || $steps === []) {
            throw new InvalidArgumentException('VALIDATION_ERROR');
        }
        foreach ($steps as $step) {
            $type = (string) ($step['type'] ?? '');
            if (ActionRegistry::get($type) === null) {
                throw new InvalidArgumentException('UNKNOWN_ACTION');
            }
        }
        $now = now_utc();
        $scenario = $this->storage->insert('scenarios', [
            'id' => generate_id('scenario'),
            'account_id' => $accountId,
            'artist_id' => $artistId,
            'label' => trim((string) ($input['label'] ?? 'Scenario')),
            'trigger' => $trigger,
            'timing' => is_array($input['timing'] ?? null) ? $input['timing'] : ['type' => 'fixed', 'delay_seconds' => (int) ($input['delay_seconds'] ?? 0)],
            'steps' => array_values($steps),
            'enabled' => (bool) ($input['enabled'] ?? true),
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        App::history()->append([
            'account_id' => $accountId,
            'artist_id' => $artistId,
            'scenario_id' => $scenario['id'],
            'event' => 'SCENARIO_CREATED',
        ]);
        return $scenario;
    }

    public function update(string $id, array $changes): ?array
    {
        $patch = [];
        foreach (['label', 'trigger', 'timing', 'steps', 'enabled'] as $key) {
            if (array_key_exists($key, $changes)) {
                $patch[$key] = $changes[$key];
            }
        }
        if (isset($patch['steps']) && is_array($patch['steps'])) {
            foreach ($patch['steps'] as $step) {
                if (ActionRegistry::get((string) ($step['type'] ?? '')) === null) {
                    throw new InvalidArgumentException('UNKNOWN_ACTION');
                }
            }
        }
        $patch['updated_at'] = now_utc();
        return $this->storage->update('scenarios', $id, $patch);
    }

    public function delete(string $id): bool
    {
        return $this->storage->delete('scenarios', $id);
    }

    public function deleteByAccount(string $accountId): void
    {
        foreach ($this->forAccount($accountId) as $scenario) {
            $this->delete((string) $scenario['id']);
        }
    }

    public function deleteByArtist(string $artistId): void
    {
        foreach ($this->all() as $scenario) {
            if (($scenario['artist_id'] ?? '') === $artistId) {
                $this->delete((string) $scenario['id']);
            }
        }
    }

    private function migrateRulesIfNeeded(): array
    {
        $rules = $this->storage->read('rules')['items'] ?? [];
        if ($rules === []) {
            return [];
        }
        $created = [];
        foreach ($rules as $rule) {
            $existing = $this->storage->findById('scenarios', 'scenario_from_' . ($rule['id'] ?? ''));
            if ($existing !== null) {
                $created[] = $existing;
                continue;
            }
            $item = [
                'id' => generate_id('scenario'),
                'account_id' => $rule['account_id'] ?? '',
                'artist_id' => $rule['artist_id'] ?? '',
                'label' => 'Legacy delay',
                'trigger' => 'NEW_POST',
                'timing' => ['type' => 'fixed', 'delay_seconds' => (int) ($rule['delay_seconds'] ?? 0)],
                'steps' => [['type' => 'OPEN_POST']],
                'enabled' => (bool) ($rule['enabled'] ?? true),
                'created_at' => $rule['created_at'] ?? now_utc(),
                'updated_at' => now_utc(),
                'migrated_from_rule' => $rule['id'] ?? null,
            ];
            $created[] = $this->storage->insert('scenarios', $item);
        }
        return $created;
    }
}
