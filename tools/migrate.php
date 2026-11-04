<?php

declare(strict_types=1);

/*
 * CLI: apply pending migrations and seed sync, then print the state.
 *   php tools/migrate.php
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$_SERVER['REQUEST_URI'] = '/';
require __DIR__ . '/../src/bootstrap.php';

if (!db_configured()) {
    fwrite(STDERR, "No database configured.\n");
    exit(1);
}

run_migrations(db());

foreach (db()->query('SELECT version, applied_at FROM schema_migrations ORDER BY version') as $row) {
    echo "migration {$row['version']} applied {$row['applied_at']}\n";
}
foreach (db()->query('SELECT page_key, locale, source FROM page_translations ORDER BY page_key, locale') as $row) {
    echo "page {$row['page_key']}/{$row['locale']} ({$row['source']})\n";
}
echo 'seed version ' . (db()->query("SELECT value FROM settings WHERE setting_key = '_seed_version'")->fetchColumn() ?: '-') . "\n";
