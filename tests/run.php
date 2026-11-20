<?php

declare(strict_types=1);

/*
 * Test runner without dependencies.
 *
 *   php tests/run.php                                   unit tests only (no database needed)
 *   SS_TEST_DB=1 php tests/run.php                      unit tests + database tests
 *   SS_TEST_DB=1 SS_TEST_HTTP=1 php tests/run.php       ... + HTTP tests (tests/http.php)
 *
 * The database tests EMPTY the members, users and login_attempts tables of
 * the configured database. They run only with SS_TEST_DB=1, which must only
 * ever be set for a throw-away test database (as in CI). The HTTP tests start
 * PHP's built-in server on a free port, use the same database, and need an
 * app_key in config/secrets.php; they also create and remove data/setup-token.
 *
 * Exit code 0 if all tests pass, 1 otherwise.
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

// Output is buffered so that session_start() and header() still work in tests.
ob_start();

$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/setup.php';

$GLOBALS['ss_tests'] = ['passed' => 0, 'failed' => [], 'current' => ''];

/** Register and run one test; an uncaught exception fails it. */
function test(string $name, callable $fn): void
{
    $GLOBALS['ss_tests']['current'] = $name;
    try {
        $fn();
    } catch (Throwable $e) {
        fail(get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')');
    }
}

function fail(string $message): void
{
    $GLOBALS['ss_tests']['failed'][] = $GLOBALS['ss_tests']['current'] . ': ' . $message;
}

function ok(bool $condition, string $message = 'expected true'): void
{
    if ($condition) {
        $GLOBALS['ss_tests']['passed']++;
    } else {
        fail($message);
    }
}

function same(mixed $expected, mixed $actual, string $message = ''): void
{
    ok($expected === $actual, ($message !== '' ? $message . ': ' : '')
        . 'expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
}

/** Assert that $fn throws an exception of class $class. */
function throws(string $class, callable $fn, string $message = ''): void
{
    try {
        $fn();
    } catch (Throwable $e) {
        ok($e instanceof $class, ($message !== '' ? $message . ': ' : '')
            . 'expected ' . $class . ', got ' . get_class($e) . ' (' . $e->getMessage() . ')');
        return;
    }
    fail(($message !== '' ? $message . ': ' : '') . 'expected ' . $class . ', nothing thrown');
}

/** Set a config value for the rest of the run, e.g. set_config('auth.window_minutes', 15). */
function set_config(string $key, mixed $value): void
{
    $ref = &$GLOBALS['ss_config'];
    foreach (explode('.', $key) as $part) {
        if (!isset($ref[$part]) || !is_array($ref[$part])) {
            $ref[$part] = $ref[$part] ?? [];
        }
        $ref = &$ref[$part];
    }
    $ref = $value;
}

require __DIR__ . '/unit.php';

$withDb = getenv('SS_TEST_DB') === '1';
if ($withDb) {
    if (!db_configured()) {
        fwrite(STDERR, "SS_TEST_DB=1 but no database is configured.\n");
        exit(1);
    }
    require __DIR__ . '/db.php';
}

$withHttp = getenv('SS_TEST_HTTP') === '1';
if ($withHttp) {
    if (!$withDb) {
        fwrite(STDERR, "SS_TEST_HTTP=1 also needs SS_TEST_DB=1 (it uses and empties the same test database).\n");
        exit(1);
    }
    require __DIR__ . '/http.php';
}

$output = (string) ob_get_clean();
$t = $GLOBALS['ss_tests'];
foreach ($t['failed'] as $line) {
    echo 'FAIL ' . $line . "\n";
}
if ($output !== '') {
    echo "--- unexpected output during tests ---\n" . $output . "\n";
}
$skipped = ($withDb ? '' : ' (database tests skipped; set SS_TEST_DB=1)')
    . ($withHttp ? '' : ($withDb ? ' (HTTP tests skipped; set SS_TEST_HTTP=1)' : ''));
printf("%d assertions passed, %d failed%s\n", $t['passed'], count($t['failed']), $skipped);
exit($t['failed'] === [] && $output === '' ? 0 : 1);
