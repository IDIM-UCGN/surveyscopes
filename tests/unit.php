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

test('uncaught exception after bootstrap: CLI exits non-zero without HTML', function (): void {
    $script = tempnam(sys_get_temp_dir(), 'ss');
    file_put_contents($script, "<?php\n\$_SERVER['REQUEST_URI'] = '/';\nrequire "
        . var_export(dirname(__DIR__) . '/src/bootstrap.php', true)
        . ";\nthrow new RuntimeException('simulated startup failure');\n");
    $proc = proc_open([PHP_BINARY, $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    $code = proc_close($proc);
    unlink($script);
    same(1, $code, 'exit code');
    same('', $stdout, 'no HTML on stdout');
    ok(str_contains($stderr, 'startup failed: RuntimeException'), 'one clear line on stderr');
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

/* ------------------------------------------------------------------- mail */

/** Mail config for one test: log transport, a valid sender, a temporary log directory. Returns the directory. */
function mail_test_setup(): string
{
    $dir = sys_get_temp_dir() . '/ss-mail-' . bin2hex(random_bytes(6));
    mkdir($dir, 0700);
    $GLOBALS['ss_mail_test'] = ['config' => $GLOBALS['ss_config']['mail'] ?? null, 'error_log' => (string) ini_get('error_log')];
    set_config('mail.transport', 'log');
    set_config('mail.from', 'noreply@example.org');
    set_config('mail.envelope_from', null);
    set_config('mail.log_dir', $dir);
    ini_set('error_log', $dir . '/php-errors.log'); // keeps the test output free of the "development only" warnings
    return $dir;
}

function mail_test_cleanup(string $dir): void
{
    ini_set('error_log', $GLOBALS['ss_mail_test']['error_log']);
    $GLOBALS['ss_config']['mail'] = $GLOBALS['ss_mail_test']['config'];
    foreach (array_merge(glob($dir . '/*') ?: [], glob($dir . '/.*') ?: []) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
    rmdir($dir);
}

/** The .eml files in a log directory, sorted. */
function mail_test_files(string $dir): array
{
    $files = glob($dir . '/*.eml') ?: [];
    sort($files);
    return $files;
}

/** Split a message into [headers (name => unfolded value), decoded body, raw body]. */
function mail_test_parse(string $raw): array
{
    [$head, $rawBody] = explode("\r\n\r\n", $raw, 2);
    $headers = [];
    foreach (explode("\r\n", (string) preg_replace('/\r\n[ \t]+/', ' ', $head)) as $line) {
        [$name, $value] = explode(': ', $line, 2);
        $headers[$name] = $value;
    }
    return [$headers, quoted_printable_decode($rawBody), $rawBody];
}

test('send_mail: the log transport writes a complete message', function (): void {
    $dir = mail_test_setup();
    $link = 'https://www.surveyscopes.example/en/register/' . str_repeat('a1b2c3d4', 8);
    $body = "Bitte bestätigen Sie Ihre E-Mail-Adresse:\n\n" . $link . "\n\nGrüße";

    same(true, send_mail('Anna@Example.org', 'Bitte bestätigen – SurveyScopes', $body), 'sent');
    $files = mail_test_files($dir);
    same(1, count($files), 'one .eml file');
    $raw = (string) file_get_contents($files[0]);
    [$h, $text, $rawBody] = mail_test_parse($raw);
    same('noreply@example.org', $h['From'] ?? null, 'From');
    same('anna@example.org', $h['To'] ?? null, 'To is normalised');
    same('Bitte bestätigen – SurveyScopes', mb_decode_mimeheader($h['Subject'] ?? ''), 'subject round trip');
    same('1.0', $h['MIME-Version'] ?? null, 'MIME-Version');
    same('text/plain; charset=UTF-8', $h['Content-Type'] ?? null, 'Content-Type');
    same('quoted-printable', $h['Content-Transfer-Encoding'] ?? null, 'Content-Transfer-Encoding');
    ok(preg_match('/^[A-Z][a-z]{2}, \d{2} [A-Z][a-z]{2} \d{4} \d{2}:\d{2}:\d{2} \+0000$/', $h['Date'] ?? '') === 1, 'Date header');
    same(['From', 'To', 'Subject', 'Date', 'MIME-Version', 'Content-Type', 'Content-Transfer-Encoding'], array_keys($h), 'only the fixed headers');
    same(str_replace("\n", "\r\n", $body), $text, 'body round trip');
    same(1, substr_count($text, $link), 'the link appears exactly once, unbroken');
    ok(max(array_map('strlen', explode("\r\n", $rawBody))) <= 76, 'quoted-printable lines are at most 76 characters');

    ok(preg_match('/^\d{8}-\d{6}-[0-9a-f]{12}\.eml$/', basename($files[0])) === 1, 'file name pattern');
    same("Require all denied\n", file_get_contents($dir . '/.htaccess'), 'log directory is closed to the web');
    same([], glob($dir . '/.*.tmp') ?: [], 'no temporary files left');
    if (PHP_OS_FAMILY !== 'Windows') {
        same(0600, fileperms($files[0]) & 0777, 'file mode');
    }

    same(true, send_mail('anna@example.org', 'Second', 'Hello'), 'second message');
    same(2, count(mail_test_files($dir)), 'every message gets its own file');

    $log = (string) file_get_contents($dir . '/php-errors.log');
    same(2, substr_count($log, 'development only'), 'one warning per send');
    ok(!str_contains($log, 'anna@') && !str_contains($log, 'a1b2c3d4'), 'no address or link in the log');
    mail_test_cleanup($dir);
});

test('send_mail: unusable recipients send nothing', function (): void {
    $dir = mail_test_setup();
    $bad = [
        'empty' => '',
        'no @' => 'no-at-sign',
        'non-ASCII' => 'änna@example.org',
        'header injection' => "anna@example.org\r\nBcc: evil@example.org",
        'two addresses' => 'a@example.org, b@example.org',
        'angle brackets' => '<anna@example.org>',
        'too long' => str_repeat('a', 250) . '@example.org',
    ];
    foreach ($bad as $name => $to) {
        same(false, send_mail($to, 'Subject', 'Body'), $name);
    }
    same([], mail_test_files($dir), 'no message written');
    mail_test_cleanup($dir);
});

test('send_mail: needs a valid sender and a known transport (fails closed)', function (): void {
    $dir = mail_test_setup();
    foreach ([null, '', 'not-an-address', 'änna@example.org', ['noreply@example.org']] as $i => $from) {
        set_config('mail.from', $from);
        same(false, send_mail('anna@example.org', 'Subject', 'Body'), 'mail.from case ' . $i);
    }
    set_config('mail.from', 'noreply@example.org');
    foreach (['logg', 'MAIL', '', null, true] as $i => $transport) {
        set_config('mail.transport', $transport);
        same(null, mail_transport(), 'invalid transport case ' . $i);
        same(false, send_mail('anna@example.org', 'Subject', 'Body'), 'transport case ' . $i);
    }
    same([], mail_test_files($dir), 'nothing written in any case');
    set_config('mail.transport', 'log');
    same(true, send_mail('anna@example.org', 'Subject', 'Body'), 'works again with a valid configuration');
    mail_test_cleanup($dir);
});

test('send_mail: the subject is cleaned and encoded, header injection is impossible', function (): void {
    $dir = mail_test_setup();
    $subjectOf = function (string $subject) use ($dir): ?array {
        foreach (mail_test_files($dir) as $file) {
            unlink($file);
        }
        if (!send_mail('anna@example.org', $subject, 'Body')) {
            return null;
        }
        $raw = (string) file_get_contents(mail_test_files($dir)[0]);
        [$h] = mail_test_parse($raw);
        return [$h, explode("\r\n\r\n", $raw, 2)[0]];
    };

    [$h, $head] = $subjectOf("Hi\r\nBcc: evil@example.org");
    ok(!isset($h['Bcc']) && !str_contains($head, "\r\nBcc:"), 'CRLF in the subject cannot start a header');
    same('Hi Bcc: evil@example.org', mb_decode_mimeheader($h['Subject']), 'the text stays in the subject');

    [$h] = $subjectOf("A\u{200B}B\u{202E}C\u{0007}D");
    same('A B C D', mb_decode_mimeheader($h['Subject']), 'invisible and control characters become spaces');
    [$h] = $subjectOf('Confirm your address');
    same('Confirm your address', $h['Subject'], 'plain ASCII is left as it is');
    $long = str_repeat('ä', 150);
    [$h, $head] = $subjectOf($long);
    same(str_repeat('ä', 100), mb_decode_mimeheader($h['Subject']), 'cut to 100 characters');
    ok(max(array_map('strlen', explode("\r\n", $head))) <= 78, 'a long non-ASCII subject is folded into lines of at most 78 characters');

    same(null, $subjectOf(''), 'empty subject');
    same(null, $subjectOf(" \r\n\t "), 'only whitespace');
    same(null, $subjectOf("invalid \xff UTF-8"), 'invalid UTF-8');
    mail_test_cleanup($dir);
});

test('send_mail: line endings and encoding of the body', function (): void {
    $dir = mail_test_setup();
    $bodyOf = function (string $body) use ($dir): ?string {
        foreach (mail_test_files($dir) as $file) {
            unlink($file);
        }
        if (!send_mail('anna@example.org', 'Subject', $body)) {
            return null;
        }
        return mail_test_parse((string) file_get_contents(mail_test_files($dir)[0]))[1];
    };
    $expected = "one\r\ntwo\r\n\r\nthree";
    same($expected, $bodyOf("one\ntwo\n\nthree"), 'LF');
    same($expected, $bodyOf("one\r\ntwo\r\n\r\nthree"), 'CRLF');
    same($expected, $bodyOf("one\rtwo\r\rthree"), 'CR');
    same("a\r\n.\r\nb", $bodyOf("a\n.\nb"), 'a line with only a dot survives');
    same("Größe: ä ö ü ß €", $bodyOf("Größe: ä ö ü ß €"), 'non-ASCII text');
    $url = 'https://example.org/en/register/' . str_repeat('x', 300);
    same($url, $bodyOf($url), 'a very long line');
    same(null, $bodyOf("invalid \xff UTF-8"), 'invalid UTF-8 body');
    mail_test_cleanup($dir);
});

test('mail_compose: fixed headers, envelope address, subject folding', function (): void {
    $dir = mail_test_setup();
    $m = mail_compose('Anna@Example.org', 'Subject', 'Body', 1_800_000_000);
    same('anna@example.org', $m['to'] ?? null, 'normalised recipient');
    same(gmdate('r', 1_800_000_000), $m['headers']['Date'] ?? null, 'Date from the given time');
    foreach ($m['headers'] as $name => $value) {
        if ($name !== 'Subject') {
            same(false, str_contains($value, "\r") || str_contains($value, "\n"), 'no line break in ' . $name);
        }
    }
    same(null, mail_compose('not-an-address', 'Subject', 'Body'), 'invalid recipient');

    $folded = mail_compose('anna@example.org', str_repeat('Überschrift ', 8), 'Body')['headers']['Subject'];
    ok(str_contains($folded, "\r\n "), 'a long non-ASCII subject is folded');
    same(0, preg_match('/\r\n(?![ \t])/', $folded), 'every fold is followed by whitespace');

    same(null, $m['envelope'], 'no envelope address by default');
    foreach (['bounce@example.org' => 'bounce@example.org', 'Bounce+x@Example.org' => 'bounce+x@example.org', " x@example.org\n" => 'x@example.org'] as $in => $out) {
        set_config('mail.envelope_from', $in);
        same($out, mail_compose('anna@example.org', 'S', 'B')['envelope'], 'envelope ' . json_encode($in) . ' (normalised, surrounding whitespace trimmed)');
    }
    foreach (["o'brien@example.org", 'änna@example.org', "x@example.org\nBcc: y@example.org", 'a b@example.org', '-oQ/tmp@example.org', ['x@example.org']] as $i => $bad) {
        set_config('mail.envelope_from', $bad);
        same(null, mail_compose('anna@example.org', 'S', 'B')['envelope'], 'ignored envelope case ' . $i);
    }
    mail_test_cleanup($dir);
});

test('mail_deliver_mail with a fake sendmail (Linux and CI only)', function (): void {
    if (PHP_OS_FAMILY === 'Windows') {
        ok(true, 'skipped: on Windows mail() talks SMTP and ignores sendmail_path');
        return;
    }
    $out = (string) tempnam(sys_get_temp_dir(), 'ssmail');
    $sendmail = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(SS_ROOT . '/tests/fake-sendmail.php') . ' ' . escapeshellarg($out);

    // A child PHP process sends through the real mail(); sendmail_path can only be set that way (it is not settable with ini_set).
    $send = function (?string $envelope) use ($sendmail, $out): array {
        file_put_contents($out, '');
        $mail = ['transport' => 'mail', 'from' => 'noreply@example.org', 'envelope_from' => $envelope, 'log_dir' => null];
        $code = '$_SERVER["REQUEST_URI"] = "/"; require ' . var_export(SS_ROOT . '/src/bootstrap.php', true)
            . '; $GLOBALS["ss_config"]["mail"] = ' . var_export($mail, true)
            . '; exit(send_mail("Anna@Example.org", "Bitte bestätigen – SurveyScopes", "Hallo\nhttps://example.org/en/register/abc") ? 0 : 1);';
        $process = proc_open([PHP_BINARY, '-d', 'sendmail_path=' . $sendmail, '-r', $code], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        $status = proc_close($process);
        $recorded = json_decode((string) file_get_contents($out), true);
        return [$status, $stdout . $stderr, is_array($recorded) ? $recorded : ['args' => [], 'message' => '']];
    };

    [$status, $noise, $got] = $send('bounce@example.org');
    same(0, $status, 'mail() reported success');
    same('', trim($noise), 'no warnings or output from the child');
    same(['-fbounce@example.org'], $got['args'], 'envelope address passed with -f');
    [$head, $body] = preg_split('/\r?\n\r?\n/', $got['message'], 2);
    $lines = preg_split('/\r?\n/', $head);
    ok(in_array('To: anna@example.org', $lines, true), 'To header');
    ok(in_array('From: noreply@example.org', $lines, true), 'From header');
    ok(in_array('Content-Type: text/plain; charset=UTF-8', $lines, true), 'Content-Type header');
    ok(count(array_filter($lines, static fn (string $l): bool => str_starts_with($l, 'Subject: =?UTF-8?B?'))) === 1, 'encoded Subject header');
    same("Hallo\nhttps://example.org/en/register/abc", rtrim(str_replace("\r\n", "\n", quoted_printable_decode($body))), 'body');

    [$status, , $got] = $send(null);
    same(0, $status, 'success without an envelope address');
    same([], $got['args'], 'no -f without envelope_from');
    unlink($out);
});

test('test_db_allowed: the database tests only run on test databases', function (): void {
    foreach (['surveyscopes_test', 'test', 'app_test', 'Surveyscopes_TEST'] as $name) {
        same(true, test_db_allowed($name, null), 'named like a test database: ' . $name);
    }
    foreach (['surveyscopes', 'surveyscopes_dev', 'surveyscopes_prod', 'testing', 'test_surveyscopes', 'mytest', 'surveyscopes_test_old', ''] as $name) {
        same(false, test_db_allowed($name, null), 'refused without confirmation: ' . json_encode($name));
    }
    same(true, test_db_allowed('surveyscopes', 'surveyscopes'), 'confirmed by exact name');
    same(true, test_db_allowed('surveyscopes_dev', 'surveyscopes_dev'), 'confirmed by exact name (dev)');
    same(false, test_db_allowed('surveyscopes', 'Surveyscopes'), 'confirmation is case-sensitive');
    same(false, test_db_allowed('surveyscopes', 'surveyscopes_dev'), 'confirmation for another database');
    same(false, test_db_allowed('surveyscopes', ''), 'empty confirmation');
    same(false, test_db_allowed('surveyscopes', '1'), 'a bare "1" is not a confirmation');
    same(false, test_db_allowed('surveyscopes', null), 'no confirmation');
});
