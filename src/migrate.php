<?php

declare(strict_types=1);

/*
 * Automatic, forward-only schema migrations and instance seed sync.
 * See docs/architecture.md §7.
 */

/** Fingerprint of the code-side state (migration files + seed version). */
function schema_fingerprint(): string
{
    $files = glob(SS_ROOT . '/src/migrations/*.sql') ?: [];
    sort($files);
    return sha1(implode('|', array_map('basename', $files)) . '#seed' . (int) config('seed.version', 0));
}

/** Run pending migrations and seed sync if the code changed since the last run. */
function ensure_schema(): void
{
    if (!db_configured()) {
        return;
    }
    $stateFile = SS_ROOT . '/data/.schema-state';
    $fingerprint = schema_fingerprint();
    if (is_file($stateFile) && trim((string) file_get_contents($stateFile)) === $fingerprint) {
        return;
    }

    run_migrations(db());

    if (!is_dir(SS_ROOT . '/data')) {
        @mkdir(SS_ROOT . '/data', 0755, true);
    }
    @file_put_contents($stateFile, $fingerprint . "\n", LOCK_EX);
}

function run_migrations(PDO $pdo): void
{
    $locked = (int) $pdo->query("SELECT GET_LOCK('surveyscopes_migrate', 30)")->fetchColumn();
    if ($locked !== 1) {
        throw new RuntimeException('Could not acquire migration lock.');
    }

    try {
        $pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
            version VARCHAR(100) NOT NULL,
            applied_at DATETIME NOT NULL,
            PRIMARY KEY (version)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');

        $applied = $pdo->query('SELECT version FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN);
        $files = glob(SS_ROOT . '/src/migrations/*.sql') ?: [];
        sort($files);

        foreach ($files as $file) {
            $version = basename($file, '.sql');
            if (in_array($version, $applied, true)) {
                continue;
            }
            $sql = preg_replace('/^\s*--.*$/m', '', (string) file_get_contents($file));
            foreach (preg_split('/;\s*$/m', (string) $sql) as $statement) {
                if (trim($statement) !== '') {
                    $pdo->exec($statement);
                }
            }
            $stmt = $pdo->prepare('INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)');
            $stmt->execute([$version, db_now()]);
        }

        sync_seed($pdo);
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('surveyscopes_migrate')");
    }
}

/**
 * Copy the instance seed (config 'seed') into the database.
 * Rows that the site admin has edited (source = 'admin') are never touched;
 * rows from an older seed are updated when the seed version increases.
 */
function sync_seed(PDO $pdo): void
{
    $seed = (array) config('seed', []);
    $version = (int) ($seed['version'] ?? 0);
    if ($version === 0) {
        return;
    }

    $stored = $pdo->query("SELECT value FROM settings WHERE setting_key = '_seed_version'")->fetchColumn();
    if ($stored !== false && (int) json_decode((string) $stored) >= $version) {
        return;
    }

    $pdo->beginTransaction();
    try {
        foreach ((array) ($seed['settings'] ?? []) as $key => $value) {
            seed_setting($pdo, (string) $key, $value);
        }

        foreach ((array) ($seed['pages'] ?? []) as $pageKey => $translations) {
            $pdo->prepare('INSERT IGNORE INTO pages (page_key, created_at) VALUES (?, ?)')
                ->execute([$pageKey, db_now()]);
            foreach ((array) $translations as $locale => $t) {
                $source = $pdo->prepare('SELECT source FROM page_translations WHERE page_key = ? AND locale = ?');
                $source->execute([$pageKey, $locale]);
                $current = $source->fetchColumn();
                $row = [
                    (string) ($t['title'] ?? ''),
                    (string) ($t['body'] ?? ''),
                    (string) ($t['meta_description'] ?? ''),
                    db_now(),
                    $pageKey,
                    $locale,
                ];
                if ($current === false) {
                    $pdo->prepare('INSERT INTO page_translations (title, body, meta_description, updated_at, page_key, locale, source)
                        VALUES (?, ?, ?, ?, ?, ?, \'seed\')')->execute($row);
                } elseif ($current === 'seed') {
                    $pdo->prepare('UPDATE page_translations SET title = ?, body = ?, meta_description = ?, updated_at = ?
                        WHERE page_key = ? AND locale = ?')->execute($row);
                }
            }
        }

        seed_setting($pdo, '_seed_version', $version, true);
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function seed_setting(PDO $pdo, string $key, mixed $value, bool $force = false): void
{
    $stmt = $pdo->prepare('SELECT source FROM settings WHERE setting_key = ?');
    $stmt->execute([$key]);
    $current = $stmt->fetchColumn();
    $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    if ($current === false) {
        $pdo->prepare("INSERT INTO settings (setting_key, value, source, updated_at) VALUES (?, ?, 'seed', ?)")
            ->execute([$key, $json, db_now()]);
    } elseif ($current === 'seed' || $force) {
        $pdo->prepare('UPDATE settings SET value = ?, updated_at = ? WHERE setting_key = ?')
            ->execute([$json, db_now(), $key]);
    }
}
