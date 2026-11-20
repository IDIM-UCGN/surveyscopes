<?php

declare(strict_types=1);

/*
 * HTTP tests: start PHP's built-in server with tools/dev-router.php and make
 * real requests (cookies, redirects, headers). Included by tests/run.php only
 * with SS_TEST_HTTP=1, which also needs SS_TEST_DB=1.
 *
 * The server reads the repository's own configuration, so config/secrets.php
 * must contain the test database and an app_key of at least 32 characters.
 * These tests EMPTY members, users and login_attempts, and create and remove
 * data/setup-token: only for a throw-away environment (as in CI).
 *
 * Requests use plain sockets (HTTP/1.0), so they need neither curl nor
 * $http_response_header.
 */

const SS_HTTP_PASSWORD = 'correct horse battery';

/** A free TCP port on 127.0.0.1. */
function http_free_port(): int
{
    $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
    if ($server === false) {
        throw new RuntimeException('No free port: ' . $errstr);
    }
    $name = (string) stream_socket_get_name($server, false);
    fclose($server);
    return (int) substr($name, (int) strrpos($name, ':') + 1);
}

/** Start the built-in server; returns the process resource. */
function http_start(int $port): mixed
{
    $null = PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
    $process = proc_open(
        [PHP_BINARY, '-S', '127.0.0.1:' . $port, 'tools/dev-router.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
        $pipes,
        (string) realpath(SS_ROOT)
    );
    if (!is_resource($process)) {
        throw new RuntimeException('Cannot start the built-in server.');
    }
    for ($i = 0; $i < 50; $i++) {
        $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.2);
        if ($socket !== false) {
            fclose($socket);
            return $process;
        }
        usleep(100_000);
    }
    proc_terminate($process);
    throw new RuntimeException('The built-in server did not start.');
}

/**
 * One request. $jar is the cookie jar (name => value), updated from Set-Cookie.
 * Returns ['status' => int, 'headers' => name (lower case) => list of values, 'body' => string].
 */
function http_request(string $method, string $path, array $form = [], array &$jar = [], array $headers = []): array
{
    $port = (int) $GLOBALS['ss_http']['port'];
    $body = $form !== [] ? http_build_query($form) : '';
    $lines = [$method . ' ' . $path . ' HTTP/1.0', 'Host: 127.0.0.1:' . $port, 'Connection: close'];
    if ($jar !== []) {
        $lines[] = 'Cookie: ' . implode('; ', array_map(static fn ($k, $v): string => $k . '=' . $v, array_keys($jar), $jar));
    }
    foreach ($headers as $header) {
        $lines[] = $header;
    }
    if ($body !== '') {
        $lines[] = 'Content-Type: application/x-www-form-urlencoded';
        $lines[] = 'Content-Length: ' . strlen($body);
    }
    $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 5);
    if ($socket === false) {
        throw new RuntimeException('Cannot connect to the test server: ' . $errstr);
    }
    stream_set_timeout($socket, 15);
    fwrite($socket, implode("\r\n", $lines) . "\r\n\r\n" . $body);
    $raw = (string) stream_get_contents($socket);
    fclose($socket);

    [$head, $content] = array_pad(explode("\r\n\r\n", $raw, 2), 2, '');
    $headLines = explode("\r\n", $head);
    preg_match('#^HTTP/\S+ (\d{3})#', $headLines[0], $m);
    $parsed = [];
    foreach (array_slice($headLines, 1) as $line) {
        if (str_contains($line, ':')) {
            [$name, $value] = explode(':', $line, 2);
            $parsed[strtolower(trim($name))][] = trim($value);
        }
    }
    foreach ($parsed['set-cookie'] ?? [] as $cookie) {
        [$name, $value] = array_pad(explode('=', explode(';', $cookie, 2)[0], 2), 2, '');
        if (preg_match('/expires=Thu, 01 Jan 1970|max-age=0/i', $cookie) === 1) {
            unset($jar[$name]);
        } else {
            $jar[$name] = $value;
        }
    }
    return ['status' => (int) ($m[1] ?? 0), 'headers' => $parsed, 'body' => $content];
}

/** The CSRF token of a page. */
function http_csrf(string $html): string
{
    return preg_match('/name="_csrf" value="([0-9a-f]{64})"/', $html, $m) === 1 ? $m[1] : '';
}

/** Text of the first alert box of a page, or null. */
function http_alert(string $html): ?string
{
    if (preg_match('#<div class="alert [^"]*"[^>]*>(.*?)</div>#s', $html, $m) !== 1) {
        return null;
    }
    return trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

/** is_file() for a file the server process may have changed (PHP caches stat results per process). */
function http_file_exists(string $path): bool
{
    clearstatcache(true, $path);
    return is_file($path);
}

function http_location(array $response): ?string
{
    return $response['headers']['location'][0] ?? null;
}

/** A member with an account; returns the member id. */
function http_person(string $email, bool $admin): int
{
    $id = Members::create($email, $admin, null);
    Users::create($id, password_hash(SS_HTTP_PASSWORD, PASSWORD_DEFAULT));
    Members::markClaimed($id);
    return $id;
}

/** Sign in through the form; returns the response of the POST. */
function http_login(array &$jar, string $email, string $password = SS_HTTP_PASSWORD, array $extra = [], string $lang = 'en'): array
{
    $page = http_request('GET', '/' . $lang . '/login', [], $jar);
    return http_request('POST', '/' . $lang . '/login', ['_csrf' => http_csrf($page['body']), 'email' => $email, 'password' => $password] + $extra, $jar);
}

foreach (['login_attempts', 'users', 'members'] as $table) {
    db()->exec('DELETE FROM ' . $table);
}
$GLOBALS['ss_http'] = ['port' => http_free_port(), 'process' => null];
$setupToken = SS_ROOT . '/data/setup-token';

test('HTTP: the built-in server starts and login is configured', function (): void {
    $GLOBALS['ss_http']['process'] = http_start($GLOBALS['ss_http']['port']);
    register_shutdown_function(static function (): void {
        if (is_resource($GLOBALS['ss_http']['process'] ?? null)) {
            proc_terminate($GLOBALS['ss_http']['process']);
        }
    });
    $jar = [];
    $r = http_request('GET', '/en/login', [], $jar);
    same(200, $r['status'], 'login page');
    ok(str_contains($r['body'], 'name="_csrf"'), 'login form shown (config/secrets.php needs an app_key of at least 32 characters)');
});

test('HTTP: public pages set no cookie; robots.txt lists the account pages', function (): void {
    foreach (['/en/', '/de/', '/en/surveys', '/de/imprint', '/en/privacy', '/sitemap.xml', '/robots.txt'] as $path) {
        $jar = [];
        $r = http_request('GET', $path, [], $jar);
        same(200, $r['status'], 'GET ' . $path);
        same(false, isset($r['headers']['set-cookie']), 'no cookie on ' . $path);
    }
    $jar = [];
    $robots = http_request('GET', '/robots.txt', [], $jar)['body'];
    foreach (['login', 'logout', 'setup', 'admin'] as $account) {
        ok(str_contains($robots, 'Disallow: /*/' . $account . "\n"), 'Disallow for ' . $account);
    }
    $home = http_request('GET', '/en/', [], $jar)['body'];
    ok(str_contains($home, 'href="/en/login"'), 'footer link to the login page');
});

test('HTTP: login page', function (): void {
    $jar = [];
    $r = http_request('GET', '/en/login', [], $jar);
    same(200, $r['status'], 'status');
    ok(str_contains($r['body'], '<meta name="robots" content="noindex, nofollow">'), 'noindex');
    ok(in_array('no-store', $r['headers']['cache-control'] ?? [], true), 'Cache-Control: no-store');
    $cookie = implode(' | ', $r['headers']['set-cookie'] ?? []);
    ok(str_contains($cookie, 'ss_session='), 'session cookie');
    ok(str_contains($cookie, 'HttpOnly'), 'HttpOnly');
    ok(str_contains($cookie, 'SameSite=Lax'), 'SameSite=Lax');
    ok(!str_contains($cookie, 'Secure'), 'no Secure flag under the plain-http built-in server');
    ok(http_csrf($r['body']) !== '', 'CSRF field');
    ok(str_contains($r['body'], 'autocomplete="current-password"'), 'password field');
    $de = http_request('GET', '/de/login', [], $jar);
    same(200, $de['status'], 'German page');
    ok(str_contains($de['body'], 'Anmelden'), 'German text');
});

test('HTTP: POST without a valid CSRF token is answered with 400', function (): void {
    http_person('admin@example.org', true);
    $jar = [];
    $page = http_request('GET', '/en/login', [], $jar);
    $token = http_csrf($page['body']);
    $fields = ['email' => 'admin@example.org', 'password' => SS_HTTP_PASSWORD];
    foreach (['missing' => [], 'wrong' => ['_csrf' => 'wrong'], 'zeros' => ['_csrf' => str_repeat('0', 64)], 'array' => ['_csrf' => [$token]]] as $name => $extra) {
        $r = http_request('POST', '/en/login', $fields + $extra, $jar);
        same(400, $r['status'], $name . ': status');
        ok(str_contains($r['body'], 'Request could not be verified'), $name . ': message');
    }
    $r = http_request('GET', '/en/admin', [], $jar);
    same(302, $r['status'], 'still not signed in after the rejected posts');
});

test('HTTP: every kind of login failure gives the same message', function (): void {
    $pw = SS_HTTP_PASSWORD;
    $cases = [
        'wrong password' => ['admin@example.org', 'wrong password 1'],
        'unknown e-mail' => ['nobody@example.org', $pw],
        'invalid e-mail' => ['not-an-address', $pw],
        'non-ASCII e-mail' => ['admín@example.org', $pw],
        'password too long' => ['admin@example.org', str_repeat('a', 73)],
        'empty password' => ['admin@example.org', ''],
    ];
    $messages = [];
    foreach ($cases as $name => [$email, $password]) {
        $jar = [];
        $r = http_login($jar, $email, $password);
        same(303, $r['status'], $name . ': status');
        same('/en/login', http_location($r), $name . ': back to the form');
        $page = http_request('GET', '/en/login', [], $jar);
        $messages[$name] = http_alert($page['body']);
        ok(!str_contains($page['body'], $password) || $password === '', $name . ': password never shown again');
        $again = http_request('GET', '/en/login', [], $jar);
        same(null, http_alert($again['body']), $name . ': message is shown only once');
    }
    same(['E-mail address or password is incorrect.'], array_values(array_unique($messages)), 'one and the same message');

    $jar = [];
    $r = http_login($jar, 'admin@example.org', 'wrong password 1');
    $page = http_request('GET', '/en/login', [], $jar);
    ok(str_contains($page['body'], 'value="admin@example.org"'), 'the e-mail address is kept in the form');
});

test('HTTP: admin login, dashboard, logout', function (): void {
    $jar = [];
    $page = http_request('GET', '/en/login', [], $jar);
    $idBefore = $jar['ss_session'] ?? '';
    $r = http_request('POST', '/en/login', ['_csrf' => http_csrf($page['body']), 'email' => 'ADMIN@example.org', 'password' => SS_HTTP_PASSWORD], $jar);
    same(303, $r['status'], 'login status');
    same('/en/admin', http_location($r), 'login goes to the dashboard');
    ok(($jar['ss_session'] ?? '') !== $idBefore, 'session id changed at login');

    $dash = http_request('GET', '/en/admin', [], $jar);
    same(200, $dash['status'], 'dashboard');
    ok(str_contains($dash['body'], 'Signed in as admin@example.org'), 'e-mail address');
    ok(str_contains($dash['body'], '<meta name="robots" content="noindex, nofollow">'), 'noindex');
    ok(in_array('no-store', $dash['headers']['cache-control'] ?? [], true), 'no-store');
    ok(preg_match('#Administrator</dt>\s*<dd class="col-sm-8">yes</dd>#', $dash['body']) === 1, 'admin: yes');
    ok(preg_match('#May create surveys</dt>\s*<dd class="col-sm-8">yes</dd>#', $dash['body']) === 1, 'may create surveys: yes (admin)');
    $de = http_request('GET', '/de/admin', [], $jar);
    ok(str_contains($de['body'], 'Angemeldet als admin@example.org'), 'German dashboard');

    $login = http_request('GET', '/en/login', [], $jar);
    same(302, $login['status'], 'login page redirects a signed-in admin');
    same('/en/admin', http_location($login), 'to the dashboard');

    $page = http_request('GET', '/en/logout', [], $jar);
    same(200, $page['status'], 'logout confirmation');
    $token = http_csrf($page['body']);
    $old = $jar;
    $r = http_request('POST', '/en/logout', [], $jar);
    same(400, $r['status'], 'logout POST without token');
    same(200, http_request('GET', '/en/admin', [], $jar)['status'], 'still signed in after the rejected logout');
    $r = http_request('POST', '/en/logout', ['_csrf' => $token], $jar);
    same(303, $r['status'], 'logout status');
    same('/en/login?logged_out=1', http_location($r), 'logout redirect');
    $page = http_request('GET', '/en/login?logged_out=1', [], $jar);
    same('You have been signed out.', http_alert($page['body']), 'signed-out message');
    same(302, http_request('GET', '/en/admin', [], $jar)['status'], 'dashboard needs a login again');
    $replay = http_request('GET', '/en/admin', [], $old);
    same(302, $replay['status'], 'the old session cookie no longer works');

    $none = [];
    $r = http_request('GET', '/en/logout', [], $none);
    same([302, '/en/login'], [$r['status'], http_location($r)], 'GET logout while signed out');
    $r = http_request('POST', '/en/logout', ['_csrf' => 'x'], $none);
    same([303, '/en/login'], [$r['status'], http_location($r)], 'POST logout while signed out');
    same(false, isset($r['headers']['set-cookie']), 'no cookie for a signed-out logout');
});

test('HTTP: the next parameter is used only for local admin URLs', function (): void {
    $jar = [];
    $r = http_request('GET', '/en/admin', [], $jar);
    same([302, '/en/login?next=%2Fen%2Fadmin'], [$r['status'], http_location($r)], 'dashboard redirects to login with next');
    same(false, isset($r['headers']['set-cookie']), 'no cookie before the login page');

    $page = http_request('GET', '/en/login?next=%2Fde%2Fadmin', [], $jar);
    ok(str_contains($page['body'], '<input type="hidden" name="next" value="/de/admin">'), 'valid next is kept in the form');
    $page = http_request('GET', '/en/login?next=' . rawurlencode('https://evil.example'), [], $jar);
    ok(!str_contains($page['body'], 'name="next"'), 'invalid next is dropped from the form');

    $r = http_login($jar, 'admin@example.org', SS_HTTP_PASSWORD, ['next' => '/de/admin']);
    same([303, '/de/admin'], [$r['status'], http_location($r)], 'valid next is honoured');

    foreach (['https://evil.example', '//evil.example', '/\\evil.example', "/en/admin\n", '/en/admin%0A', "/en/admin\r\nX: y", '/fr/admin', 'javascript:alert(1)', '/en/login'] as $bad) {
        $fresh = [];
        $r = http_login($fresh, 'admin@example.org', SS_HTTP_PASSWORD, ['next' => $bad]);
        same([303, '/en/admin'], [$r['status'], http_location($r)], 'bad next ' . json_encode($bad));
    }
});

test('HTTP: a member without the admin flag', function (): void {
    $id = http_person('member@example.org', false);
    $jar = [];
    $r = http_login($jar, 'member@example.org');
    same([303, '/en/'], [$r['status'], http_location($r)], 'goes to the front page');
    $home = http_request('GET', '/en/', [], $jar);
    same(200, $home['status'], 'front page');
    ok(str_contains($home['body'], 'You are signed in. Your account has no access to the administration yet.'), 'notice shown');
    $again = http_request('GET', '/en/', [], $jar);
    ok(!str_contains($again['body'], 'no access to the administration'), 'notice shown only once');

    $r = http_request('GET', '/en/admin', [], $jar);
    same(403, $r['status'], 'dashboard is forbidden');
    ok(str_contains($r['body'], 'Access denied'), '403 page');
    ok(in_array('no-store', $r['headers']['cache-control'] ?? [], true), '403 is not cached');
    ok(!str_contains($r['body'], 'member@example.org'), 'no account details on the 403 page');

    $r = http_request('GET', '/en/login', [], $jar);
    same(302, $r['status'], 'login page redirects a signed-in member');

    Members::setStatus($id, 'disabled');
    $r = http_request('GET', '/en/admin', [], $jar);
    same([302, '/en/login?next=%2Fen%2Fadmin'], [$r['status'], http_location($r)], 'a disabled member counts as signed out');
    $fresh = [];
    $r = http_login($fresh, 'member@example.org');
    same('/en/login', http_location($r), 'a disabled member cannot sign in');
});

test('HTTP: setup page exists only while a valid token file exists', function () use ($setupToken): void {
    @mkdir(dirname($setupToken), 0777, true);
    @unlink($setupToken);

    $jar = [];
    $none = [];
    $setup = http_request('GET', '/en/setup', [], $jar);
    $unknown = http_request('GET', '/en/nonexistent', [], $none);
    same(404, $setup['status'], 'no token file: 404');
    same($unknown['body'], $setup['body'], 'identical to an unknown page');
    same(false, isset($setup['headers']['set-cookie']), 'no cookie');
    same(404, http_request('POST', '/en/setup', ['token' => 'x'], $jar)['status'], 'POST without a token file: 404');

    $token = bin2hex(random_bytes(20));
    file_put_contents($setupToken, $token . "\n");
    $page = http_request('GET', '/en/setup', [], $jar);
    same(200, $page['status'], 'token file present: page shown');
    ok(preg_match('#type="password" id="setup-token" name="token" autocomplete="off"#', $page['body']) === 1, 'token field is a password field without autocomplete');
    ok(str_contains($page['body'], '<meta name="robots" content="noindex, nofollow">'), 'noindex');
    $csrf = http_csrf($page['body']);
    $pw = SS_HTTP_PASSWORD;
    $fields = ['_csrf' => $csrf, 'token' => $token, 'email' => 'first@example.org', 'password' => $pw, 'password_repeat' => $pw];

    same(400, http_request('POST', '/en/setup', array_diff_key($fields, ['_csrf' => 1]), $jar)['status'], 'POST without CSRF token');
    ok(http_file_exists($setupToken), 'token file untouched by the rejected post');

    $r = http_request('POST', '/en/setup', ['token' => 'wrong'] + $fields, $jar);
    same([303, '/en/setup'], [$r['status'], http_location($r)], 'wrong token: back to the form');
    same('The setup token is not correct.', http_alert(http_request('GET', '/en/setup', [], $jar)['body']), 'wrong token message');
    $r = http_request('POST', '/en/setup', ['password_repeat' => 'different password'] + $fields, $jar);
    same('The two passwords do not match.', http_alert(http_request('GET', '/en/setup', [], $jar)['body']), 'mismatch message');
    $r = http_request('POST', '/en/setup', ['email' => 'not an address'] + $fields, $jar);
    ok(str_contains((string) http_alert(http_request('GET', '/en/setup', [], $jar)['body']), 'valid e-mail address'), 'invalid e-mail message');
    ok(http_file_exists($setupToken), 'token file still there after the failures');
    same(null, Members::findByEmail('first@example.org'), 'nothing created by the failures');

    $page = http_request('GET', '/en/setup', [], $jar);
    $fields['_csrf'] = http_csrf($page['body']);
    $r = http_request('POST', '/en/setup', $fields, $jar);
    same([303, '/en/login'], [$r['status'], http_location($r)], 'success: redirect to login');
    ok(!http_file_exists($setupToken), 'token file deleted');
    same('The administrator account is ready. You can sign in now.', http_alert(http_request('GET', '/en/login', [], $jar)['body']), 'success message');
    same(404, http_request('GET', '/en/setup', [], $jar)['status'], 'the page is gone again');
    $r = http_login($jar, 'first@example.org');
    same([303, '/en/admin'], [$r['status'], http_location($r)], 'the new admin can sign in');
    same(200, http_request('GET', '/en/admin', [], $jar)['status'], 'and reach the dashboard');
    @unlink($setupToken);
});

test('HTTP: routing of the admin pages', function (): void {
    $jar = [];
    $r = http_request('GET', '/admin', [], $jar);
    same([302, '/en/admin'], [$r['status'], http_location($r)], '/admin without language');
    $r = http_request('GET', '/admin', [], $jar, ['Accept-Language: de']);
    same([302, '/de/admin'], [$r['status'], http_location($r)], '/admin with Accept-Language: de');
    $r = http_request('GET', '/en/admin/', [], $jar);
    same([301, '/en/admin'], [$r['status'], http_location($r)], 'trailing slash');
    $r = http_request('GET', '/de/admin', [], $jar);
    same([302, '/de/login?next=%2Fde%2Fadmin'], [$r['status'], http_location($r)], '/de/admin signed out');
    same(404, http_request('GET', '/en/admin/nope', [], $jar)['status'], 'unknown admin page');
    same(404, http_request('GET', '/fr/admin', [], $jar)['status'], 'unsupported language');
});

foreach (['login_attempts', 'users', 'members'] as $table) {
    db()->exec('DELETE FROM ' . $table);
}
@unlink($setupToken);
if (is_resource($GLOBALS['ss_http']['process'])) {
    proc_terminate($GLOBALS['ss_http']['process']);
    $GLOBALS['ss_http']['process'] = null;
}
