<?php

declare(strict_types=1);

/** True if this instance has a database configured. */
function db_configured(): bool
{
    $db = config('database');
    return is_array($db) && ($db['name'] ?? '') !== '';
}

/** Shared PDO connection (lazy). */
function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    if (!db_configured()) {
        throw new RuntimeException('No database configured.');
    }

    $c = (array) config('database');
    $dsn = sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        $c['host'] ?? 'localhost',
        (int) ($c['port'] ?? 3306),
        $c['name'],
        $c['charset'] ?? 'utf8mb4'
    );
    $pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['password'] ?? ''), [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
    $pdo->exec("SET time_zone = '+00:00', sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
    return $pdo;
}

/** Current UTC time in MySQL DATETIME format. */
function db_now(): string
{
    return gmdate('Y-m-d H:i:s');
}
