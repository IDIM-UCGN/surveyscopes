<?php

declare(strict_types=1);

const SS_ROOT = __DIR__ . '/..';

require __DIR__ . '/config.php';
require __DIR__ . '/view.php';
require __DIR__ . '/i18n.php';
require __DIR__ . '/db.php';
require __DIR__ . '/migrate.php';
require __DIR__ . '/content.php';
require __DIR__ . '/repositories/Users.php';
require __DIR__ . '/repositories/LoginAttempts.php';
require __DIR__ . '/auth.php'; // no session here: only login/admin pages start one
require __DIR__ . '/csrf.php';

$GLOBALS['ss_config'] = load_config(SS_ROOT . '/config');
date_default_timezone_set((string) config('timezone', 'UTC'));

// Uncaught errors: log details, show a neutral page (no internals to visitors).
set_exception_handler(static function (Throwable $e): void {
    error_log('[surveyscopes] ' . $e::class . ': ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(503);
        header('Retry-After: 120');
        header('Content-Type: text/html; charset=utf-8');
    }
    if (!isset($GLOBALS['ss_strings'])) {
        set_locale(browser_locale());
    }
    $GLOBALS['ss_status_page'] = 'error';
    require SS_ROOT . '/templates/status.php';
});

// Maintenance mode during deployments (see docs/instances.md)
if (is_file(SS_ROOT . '/.maintenance')) {
    set_locale(browser_locale());
    http_response_code(503);
    header('Retry-After: 60');
    header('Content-Type: text/html; charset=utf-8');
    $GLOBALS['ss_status_page'] = 'maintenance';
    require SS_ROOT . '/templates/status.php';
    exit;
}

ensure_schema();

/**
 * Start a page: take the locale from the URL prefix, or redirect
 * prefix-less URLs to the browser language. Unknown prefixes are 404.
 */
function page(string $name): void
{
    $GLOBALS['ss_page'] = $name;
    $prefix = path_prefix();

    if ($prefix === null) {
        $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
        header('Vary: Accept-Language');
        header('Cache-Control: no-cache');
        header('Location: ' . url($name, browser_locale()) . ($query !== '' ? '?' . $query : ''), true, 302);
        exit;
    }

    if (!in_array($prefix, locales(), true)) {
        set_locale(browser_locale());
        not_found();
    }

    set_locale($prefix);
}

/** Version string of the running code. */
function app_version(): string
{
    return trim((string) @file_get_contents(SS_ROOT . '/VERSION')) ?: 'dev';
}
