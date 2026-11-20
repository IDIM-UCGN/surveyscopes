<?php

declare(strict_types=1);

/* Unit tests: pure functions and validation, no database. Included by tests/run.php. */

test('session_expiry_reason', function (): void {
    $idle = 120 * 60;
    $abs = 12 * 3600;
    $now = 1_800_000_000;
    same(null, session_expiry_reason(['login_at' => $now - 60, 'last_seen' => $now - 30], $now, $idle, $abs), 'fresh session');
    same('idle', session_expiry_reason(['login_at' => $now - 3 * 3600, 'last_seen' => $now - $idle], $now, $idle, $abs), 'idle limit reached');
    same('absolute', session_expiry_reason(['login_at' => $now - $abs, 'last_seen' => $now - 10], $now, $idle, $abs), 'absolute limit reached');
    same('absolute', session_expiry_reason(['login_at' => $now - $abs, 'last_seen' => $now - $idle], $now, $idle, $abs), 'absolute wins over idle');
    same('absolute', session_expiry_reason([], $now, $idle, $abs), 'missing timestamps');
    same('absolute', session_expiry_reason(['login_at' => (string) $now, 'last_seen' => $now], $now, $idle, $abs), 'non-integer timestamp');
});

test('validate_new_password', function (): void {
    same('too_short', validate_new_password('elevenchars'), '11 characters');
    same(null, validate_new_password('twelve chars'), '12 characters');
    same(null, validate_new_password(str_repeat('ä', 12)), '12 multibyte characters (24 bytes)');
    same(null, validate_new_password(str_repeat('a', 72)), '72 bytes');
    same('too_long', validate_new_password(str_repeat('a', 73)), '73 bytes');
    same('too_long', validate_new_password(str_repeat('ä', 37)), '37 multibyte characters = 74 bytes');
    same('invalid', validate_new_password("valid but \0 nul"), 'NUL byte');
    same('invalid', validate_new_password("\xff\xfe invalid utf-8 bytes"), 'invalid UTF-8');
});

test('client_ip_key', function (): void {
    same('192.0.2.1', client_ip_key('192.0.2.1'), 'IPv4');
    same('192.0.2.1', client_ip_key('::ffff:192.0.2.1'), 'IPv4-mapped IPv6');
    same('20010db800010002/64', client_ip_key('2001:db8:1:2:aaaa:bbbb:cccc:dddd'), 'IPv6 reduced to /64');
    same(client_ip_key('2001:db8:1:2::1'), client_ip_key('2001:db8:1:2::ffff'), 'same /64, same key');
    same('unknown', client_ip_key('not an ip'), 'garbage');
    same('unknown', client_ip_key(''), 'empty');
});

test('Members::validEmail', function (): void {
    same('anna@example.org', Members::validEmail('  Anna@Example.ORG '), 'trimmed and lower-cased');
    same(null, Members::validEmail('änna@example.org'), 'non-ASCII local part');
    same(null, Members::validEmail('anna@exämple.org'), 'non-ASCII domain');
    same(null, Members::validEmail('no-at-sign'), 'no @');
    same(null, Members::validEmail("anna@example.org\r\nBcc: x@example.org"), 'header injection');
    same(null, Members::validEmail(str_repeat('a', 64) . '@' . str_repeat('b', 186) . '.org'), '255 characters');
});

test('Members::cleanDisplayName', function (): void {
    same(null, Members::cleanDisplayName(null), 'null');
    same(null, Members::cleanDisplayName("  \t "), 'only whitespace');
    same('Anna Müller', Members::cleanDisplayName('  Anna   Müller '), 'trim and collapse');
    same('Anna Müller', Members::cleanDisplayName("Anna\nMüller"), 'newline');
    same('Anna Müller', Members::cleanDisplayName("Anna\u{200B}\u{202E}Müller"), 'zero-width space and direction override');
    same('Anna', Members::cleanDisplayName("Anna\x07"), 'control character');
    same(str_repeat('ß', 255), Members::cleanDisplayName(str_repeat('ß', 255)), '255 multibyte characters');
    throws(InvalidArgumentException::class, fn () => Members::cleanDisplayName(str_repeat('a', 256)), '256 characters');
    throws(InvalidArgumentException::class, fn () => Members::cleanDisplayName("Anna \xff"), 'invalid UTF-8');
});

test('user_is_admin and user_can_create_surveys', function (): void {
    $member = ['is_admin' => false, 'create_surveys_allowed' => false];
    $creator = ['is_admin' => false, 'create_surveys_allowed' => true];
    $admin = ['is_admin' => true, 'create_surveys_allowed' => false];
    same(false, user_is_admin(null), 'nobody: admin');
    same(false, user_can_create_surveys(null), 'nobody: create');
    same(false, user_is_admin($member), 'member: admin');
    same(false, user_can_create_surveys($member), 'member: create');
    same(false, user_is_admin($creator), 'creator: admin');
    same(true, user_can_create_surveys($creator), 'creator: create');
    same(true, user_is_admin($admin), 'admin: admin');
    same(true, user_can_create_surveys($admin), 'admin: create (implicit)');
    same(false, user_can_create_surveys(['is_admin' => 1, 'create_surveys_allowed' => 1]), 'only real booleans count');
});

test('auth_setting falls back on invalid values', function (): void {
    $before = $GLOBALS['ss_config']['auth'] ?? [];
    set_config('auth.window_minutes', 30);
    same(30, auth_setting('window_minutes'), 'valid value');
    set_config('auth.window_minutes', 0);
    same(15, auth_setting('window_minutes'), 'zero');
    set_config('auth.window_minutes', '20');
    same(15, auth_setting('window_minutes'), 'string');
    throws(InvalidArgumentException::class, fn () => auth_setting('no_such_key'), 'unknown key');
    $GLOBALS['ss_config']['auth'] = $before;
});

test('throttle_keys', function (): void {
    $before = $GLOBALS['ss_config']['app_key'] ?? null;
    set_config('app_key', str_repeat('k', 32));
    same(['ip'], array_keys(throttle_keys(null, '192.0.2.1')), 'invalid e-mail: IP key only');
    $keys = throttle_keys('anna@example.org', '192.0.2.1');
    same(['ip', 'pair', 'account'], array_keys($keys), 'valid e-mail: all three keys');
    same($keys, throttle_keys('anna@example.org', '192.0.2.1'), 'deterministic');
    ok($keys['account'] === throttle_keys('anna@example.org', '198.51.100.7')['account'], 'account key independent of IP');
    ok($keys['pair'] !== throttle_keys('anna@example.org', '198.51.100.7')['pair'], 'pair key depends on IP');
    ok(!str_contains(implode('', $keys), 'anna'), 'no plain e-mail in keys');
    set_config('app_key', 'too short');
    same(null, auth_key(), 'short app_key rejected');
    $GLOBALS['ss_config']['app_key'] = $before;
});

test('session cookie is Secure outside the built-in server', function (): void {
    same(true, session_cookie_secure(), 'CLI (tests) counts as not the dev server');
});

test('CSRF token', function (): void {
    $token = csrf_token();
    same(64, strlen($token), 'token length');
    same($token, csrf_token(), 'stable within a session');
    ok(str_contains(csrf_field(), 'value="' . $token . '"'), 'hidden field contains the token');
    $_POST = ['_csrf' => $token];
    same(true, csrf_verify(), 'matching token');
    $_POST = ['_csrf' => 'x' . substr($token, 1)];
    same(false, csrf_verify(), 'wrong token');
    $_POST = ['_csrf' => [$token]];
    same(false, csrf_verify(), 'array instead of string');
    $_POST = [];
    same(false, csrf_verify(), 'missing token');
    logout_user();
});

test('safe_next_path accepts only local admin URLs', function (): void {
    same('/en/admin', safe_next_path('/en/admin'), 'dashboard');
    same('/de/admin/users', safe_next_path('/de/admin/users'), 'admin sub page');
    same('/en/admin/survey-edit', safe_next_path('/en/admin/survey-edit'), 'hyphen in page name');
    same(null, safe_next_path(null), 'null');
    same(null, safe_next_path(['/en/admin']), 'array');
    same(null, safe_next_path(''), 'empty');
    same(null, safe_next_path("/en/admin\n"), 'trailing newline');
    same(null, safe_next_path('/en/admin%0A'), 'encoded newline');
    same(null, safe_next_path("/en/admin\r\nSet-Cookie: x=y"), 'CRLF');
    same(null, safe_next_path('https://evil.example'), 'absolute URL');
    same(null, safe_next_path('//evil.example'), 'protocol-relative URL');
    same(null, safe_next_path('/\evil.example'), 'backslash');
    same(null, safe_next_path('/en/admin/../login'), 'dot segments');
    same(null, safe_next_path('/en/admin/'), 'trailing slash');
    same(null, safe_next_path('/en/login'), 'not an admin page');
    same(null, safe_next_path('/fr/admin'), 'unsupported language');
    same(null, safe_next_path('/en/admin?x=1'), 'query string');
    same(null, safe_next_path('en/admin'), 'no leading slash');
    same(null, safe_next_path('/en/admin/' . str_repeat('a', 300)), 'too long');
});

test('setup_token_age: the later of mtime and ctime counts', function (): void {
    same(0, setup_token_age(100, 100, 100), 'brand new');
    same(50, setup_token_age(100, 100, 150), 'equal times');
    same(10, setup_token_age(100, 140, 150), 'ctime is later');
    same(10, setup_token_age(140, 100, 150), 'mtime is later');
    same(0, setup_token_age(200, 200, 100), 'clock skew is never negative');
});

test('setup_token: age, length and trimming', function (): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'sstok');
    $token = str_repeat('a1', 20); // 40 characters
    $limit = auth_setting('setup_token_max_age_hours') * 3600;

    file_put_contents($path, $token . "\n");
    same($token, setup_token($path), 'valid, trailing newline trimmed');
    same(null, setup_token($path . '-missing'), 'no file');
    same($token, setup_token($path, time() + $limit - 120), 'just inside the age limit');
    same(null, setup_token($path, time() + $limit + 120), 'older than the limit');

    touch($path, time() - 5 * $limit); // old mtime, but the file was just created or changed
    same($token, setup_token($path), 'an old mtime alone does not expire the token');

    file_put_contents($path, str_repeat('a', 31));
    same(null, setup_token($path), '31 characters');
    file_put_contents($path, str_repeat('a', 32) . "\r\n");
    same(str_repeat('a', 32), setup_token($path), '32 characters');
    file_put_contents($path, "   \n");
    same(null, setup_token($path), 'only whitespace');
    unlink($path);
});

test('pending_notice starts no session without a session cookie', function (): void {
    unset($_COOKIE[SS_SESSION_NAME]);
    same(PHP_SESSION_NONE, session_status(), 'no session before');
    same(null, pending_notice(), 'no cookie, no notice');
    same(PHP_SESSION_NONE, session_status(), 'still no session');

    $_COOKIE[SS_SESSION_NAME] = 'test';
    flash('notice', 'login.expired');
    same('login.expired', pending_notice(), 'notice is delivered once');
    same(null, pending_notice(), 'and then gone');
    logout_user();
    unset($_COOKIE[SS_SESSION_NAME]);
});

test('language files have the same keys, none empty', function (): void {
    $en = require SS_ROOT . '/lang/en.php';
    $de = require SS_ROOT . '/lang/de.php';
    same([], array_keys(array_diff_key($en, $de)), 'keys only in en');
    same([], array_keys(array_diff_key($de, $en)), 'keys only in de');
    foreach ([$en, $de] as $strings) {
        same([], array_keys(array_filter($strings, static fn ($text): bool => trim((string) $text) === '')), 'empty texts');
    }
    foreach (['login', 'logout', 'setup', 'admin', '400', '403', 'footer.login'] as $prefix) {
        ok(array_filter(array_keys($en), static fn (string $k): bool => str_starts_with($k, $prefix)) !== [], 'strings for ' . $prefix);
    }
});
