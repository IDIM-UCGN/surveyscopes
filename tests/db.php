<?php

declare(strict_types=1);

/*
 * Database tests (MySQL). Included by tests/run.php only with SS_TEST_DB=1.
 * They EMPTY members, users and login_attempts first.
 */

run_migrations(db());
foreach (['login_attempts', 'users', 'members'] as $table) {
    db()->exec('DELETE FROM ' . $table);
}
set_config('app_key', str_repeat('t', 32));

const SS_TEST_PASSWORD = 'correct horse battery';

test('Members: create, read, flags and display name', function (): void {
    $id = Members::create('Creator@Example.org', false, null, '  Cora   Creator ', true);
    $m = Members::findByEmail('creator@example.org');
    same($id, $m['id'] ?? null, 'id');
    same('creator@example.org', $m['email'], 'normalised e-mail');
    same('Cora Creator', $m['display_name'], 'cleaned display name');
    same(false, $m['is_admin'], 'is_admin');
    same(true, $m['create_surveys_allowed'], 'create_surveys_allowed');
    same('active', $m['status'], 'status');
    same(null, $m['claimed_at'], 'no account yet');

    Members::setCreateSurveysAllowed($id, false);
    Members::setDisplayName($id, '   ');
    $m = Members::findByEmail('creator@example.org');
    same(false, $m['create_surveys_allowed'], 'flag revoked');
    same(null, $m['display_name'], 'empty name stored as NULL');

    $plain = Members::create('plain@example.org', false, $id);
    $p = Members::findByEmail('plain@example.org');
    same(false, $p['create_surveys_allowed'], 'flag defaults to off');
    same(null, $p['display_name'], 'name defaults to NULL');
    same($id, $p['created_by'], 'created_by');
    same($plain, $p['id'], 'id of second member');

    throws(PDOException::class, fn () => Members::create('CREATOR@example.org', false, null), 'duplicate e-mail');
    throws(InvalidArgumentException::class, fn () => Members::create('jürgen@example.org', false, null), 'non-ASCII e-mail');
    same(null, Members::findByEmail('nobody@example.org'), 'unknown e-mail');
});

test('provision_admin', function (): void {
    $r = provision_admin('Admin1@Example.org', SS_TEST_PASSWORD, false);
    same('created', $r['result'], 'result');
    same('admin1@example.org', $r['email'], 'e-mail');
    same(null, $r['warning'], 'no warning with app_key');
    $m = Members::findByEmail('admin1@example.org');
    same(true, $m['is_admin'], 'admin flag');
    ok($m['claimed_at'] !== null, 'claimed_at set');

    throws(AdminExistsException::class, fn () => provision_admin('admin1@example.org', SS_TEST_PASSWORD, false), 'existing address without --update');
    throws(InvalidArgumentException::class, fn () => provision_admin('admin9@example.org', 'short', false), 'short password');
    same(null, Members::findByEmail('admin9@example.org'), 'nothing created on invalid password');

    // --update on a member without an account: becomes admin and gets an account
    Members::create('recover@example.org', false, null);
    Members::setStatus(Members::findByEmail('recover@example.org')['id'], 'disabled');
    $r = provision_admin('recover@example.org', SS_TEST_PASSWORD, true);
    same('updated', $r['result'], 'recover result');
    $m = Members::findByEmail('recover@example.org');
    same(true, $m['is_admin'], 'recovered: admin');
    same('active', $m['status'], 'recovered: active again');
    ok(Users::findByEmail('recover@example.org') !== null, 'recovered: account created');
});

test('last active admin with an account is protected', function (): void {
    // Two admins with accounts exist now (admin1, recover). Remove recover's admin flag.
    $admin1 = Members::findByEmail('admin1@example.org')['id'];
    $recover = Members::findByEmail('recover@example.org')['id'];
    Members::setAdmin($recover, false);
    same(1, Members::countActiveAdminsWithAccount(), 'one admin left');

    // A pending admin record (flag set, no account) does not count.
    $pending = Members::create('pending-admin@example.org', true, null);
    same(1, Members::countActiveAdminsWithAccount(), 'pending admin not counted');

    throws(LastAdminException::class, fn () => Members::setAdmin($admin1, false), 'revoke last admin');
    throws(LastAdminException::class, fn () => Members::setStatus($admin1, 'disabled'), 'disable last admin');
    same(true, Members::findByEmail('admin1@example.org')['is_admin'], 'still admin after refused change');
    ok(!db()->inTransaction(), 'no transaction left open');

    Members::setAdmin($pending, false); // no account: allowed
    same(false, Members::findByEmail('pending-admin@example.org')['is_admin'], 'pending admin flag removed');

    provision_admin('admin2@example.org', SS_TEST_PASSWORD, false);
    same(2, Members::countActiveAdminsWithAccount(), 'second admin');
    Members::setAdmin($admin1, false);
    same(false, Members::findByEmail('admin1@example.org')['is_admin'], 'first admin demoted');
    $admin2 = Members::findByEmail('admin2@example.org')['id'];
    throws(LastAdminException::class, fn () => Members::setStatus($admin2, 'disabled'), 'now admin2 is the last one');
    Members::setAdmin($admin1, true);
    same(2, Members::countActiveAdminsWithAccount(), 'restored');
});

test('login, current user and logout', function (): void {
    same('ok', attempt_login('ADMIN1@example.org', SS_TEST_PASSWORD), 'login with upper-case e-mail');
    $user = current_user();
    same('admin1@example.org', $user['email'] ?? null, 'current user');
    same(true, $user['is_admin'], 'admin flag in current user');
    same(true, user_can_create_surveys($user), 'admin may create surveys');
    ok(Users::findByEmail('admin1@example.org')['last_login_at'] !== null, 'last_login_at set');
    logout_user();
    same(null, current_user(), 'logged out');

    same('failed', attempt_login('admin1@example.org', 'wrong password!!'), 'wrong password');
    same('failed', attempt_login('nobody@example.org', SS_TEST_PASSWORD), 'unknown e-mail');
    same('failed', attempt_login(['admin1@example.org'], SS_TEST_PASSWORD), 'array input');
    same('failed', attempt_login('admin1@example.org', ''), 'empty password');
    same(null, current_user(), 'still logged out');
});

test('creator flag reaches the logged-in user', function (): void {
    $id = Members::create('editor@example.org', false, null, 'Eda Editor', true);
    Users::create($id, password_hash(SS_TEST_PASSWORD, PASSWORD_DEFAULT));
    Members::markClaimed($id);
    same('ok', attempt_login('editor@example.org', SS_TEST_PASSWORD), 'login');
    $user = current_user();
    same('Eda Editor', $user['display_name'] ?? null, 'display name');
    same(false, user_is_admin($user), 'not admin');
    same(true, user_can_create_surveys($user), 'may create surveys');

    // Disabled while logged in: the next request counts as logged out.
    Members::setStatus($id, 'disabled');
    reset_current_user();
    same(null, current_user(), 'disabled member is logged out');
    logout_user();
    same('failed', attempt_login('editor@example.org', SS_TEST_PASSWORD), 'disabled member cannot log in');
});

test('throttling', function (): void {
    set_config('auth.max_attempts_pair', 3);
    for ($i = 0; $i < 3; $i++) {
        same('failed', attempt_login('throttle@example.org', 'wrong password ' . $i), 'failure ' . ($i + 1));
    }
    same('throttled', attempt_login('throttle@example.org', 'wrong password x'), 'fourth attempt throttled');

    // A successful login clears the pair and account keys of that address.
    same('failed', attempt_login('admin2@example.org', 'wrong password!!'), 'one failure for admin2');
    same('ok', attempt_login('admin2@example.org', SS_TEST_PASSWORD), 'admin2 login');
    logout_user();
    $keys = throttle_keys('admin2@example.org', client_ip_key());
    same(0, LoginAttempts::countRecent('pair', $keys['pair'], 15), 'pair key cleared');
    same(0, LoginAttempts::countRecent('account', $keys['account'], 15), 'account key cleared');
    ok(LoginAttempts::countRecent('ip', $keys['ip'], 15) > 0, 'IP key kept');
    $GLOBALS['ss_config']['auth'] = [];
});

test('login unavailable without app_key', function (): void {
    set_config('app_key', null);
    same('unavailable', attempt_login('admin1@example.org', SS_TEST_PASSWORD), 'no app_key');
    set_config('app_key', str_repeat('t', 32));
});

test('provision_admin: beforeCommit runs before the commit and can roll everything back', function (): void {
    $ran = false;
    $r = provision_admin('hook1@example.org', SS_TEST_PASSWORD, false, null, function () use (&$ran): void {
        $ran = true;
        ok(db()->inTransaction(), 'transaction still open while the hook runs');
    });
    same('created', $r['result'], 'created');
    ok($ran, 'hook ran');
    ok(Members::findByEmail('hook1@example.org') !== null, 'committed');

    $fail = function (): void {
        throw new SetupTokenException('boom');
    };
    throws(SetupTokenException::class, fn () => provision_admin('hook2@example.org', SS_TEST_PASSWORD, false, null, $fail), 'hook failure is rethrown');
    same(null, Members::findByEmail('hook2@example.org'), 'new member rolled back');
    same(false, db()->inTransaction(), 'no transaction left open');

    // A failing hook during recovery leaves the existing account untouched.
    $id = Members::create('hook3@example.org', false, null);
    Users::create($id, password_hash('old password 123', PASSWORD_DEFAULT));
    throws(SetupTokenException::class, fn () => provision_admin('hook3@example.org', SS_TEST_PASSWORD, true, null, $fail), 'hook failure during recovery');
    same(false, Members::findByEmail('hook3@example.org')['is_admin'], 'recovery rolled back: still not admin');
    ok(password_verify('old password 123', (string) Users::findByEmail('hook3@example.org')['password_hash']), 'recovery rolled back: old password kept');
});

test('setup_run: token, validation, single use, recovery', function (): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'sstok');
    $token = str_repeat('Tk', 20);
    $write = static fn () => file_put_contents($path, $token . "\n");
    $write();
    $pw = SS_TEST_PASSWORD;
    db()->exec('DELETE FROM login_attempts');

    same('failed', setup_run('wrong', 'setup1@example.org', $pw, $pw, $path), 'wrong token');
    same('failed', setup_run(null, 'setup1@example.org', $pw, $pw, $path), 'missing token');
    same('failed', setup_run([$token], 'setup1@example.org', $pw, $pw, $path), 'array instead of a string');
    same('invalid_email', setup_run($token, 'not an address', $pw, $pw, $path), 'invalid e-mail');
    same('invalid_email', setup_run($token, 'änna@example.org', $pw, $pw, $path), 'non-ASCII e-mail');
    same('invalid_email', setup_run($token, null, $pw, $pw, $path), 'missing e-mail');
    same('mismatch', setup_run($token, 'setup1@example.org', $pw, $pw . 'x', $path), 'passwords differ');
    same('too_short', setup_run($token, 'setup1@example.org', 'short', 'short', $path), 'short password');
    same('too_long', setup_run($token, 'setup1@example.org', str_repeat('a', 73), str_repeat('a', 73), $path), '73 bytes');
    same('invalid', setup_run($token, 'setup1@example.org', "valid but \0 nul", "valid but \0 nul", $path), 'NUL byte');
    same(null, Members::findByEmail('setup1@example.org'), 'failed attempts create nothing');
    ok(is_file($path), 'token file still there');

    same('done', setup_run(" $token \n", 'Setup1@Example.org', $pw, $pw, $path), 'correct token (surrounding whitespace ignored)');
    ok(!is_file($path), 'token file deleted');
    $m = Members::findByEmail('setup1@example.org');
    same(true, $m['is_admin'] ?? null, 'admin flag');
    ok(($m['claimed_at'] ?? null) !== null, 'claimed_at set');
    same('failed', setup_run($token, 'setup2@example.org', $pw, $pw, $path), 'the token cannot be used twice');
    same(null, Members::findByEmail('setup2@example.org'), 'no second account');
    same('ok', attempt_login('setup1@example.org', $pw), 'the new admin can log in');
    logout_user();

    // Recovery: an existing, disabled member with an account becomes an active admin with the new password.
    $id = Members::create('setup3@example.org', false, null);
    Users::create($id, password_hash('old password 123', PASSWORD_DEFAULT));
    Members::markClaimed($id);
    Members::setStatus($id, 'disabled');
    $write();
    same('done', setup_run($token, 'setup3@example.org', $pw, $pw, $path), 'recovery of an existing address');
    $m = Members::findByEmail('setup3@example.org');
    same([true, 'active'], [$m['is_admin'], $m['status']], 'recovered: admin and active');
    same('ok', attempt_login('setup3@example.org', $pw), 'new password works');
    logout_user();
    same('failed', attempt_login('setup3@example.org', 'old password 123'), 'old password no longer works');

    if (is_file($path)) {
        unlink($path);
    }
    db()->exec('DELETE FROM login_attempts');
});

test('setup_run: throttled per client address, unavailable without app_key', function (): void {
    $path = (string) tempnam(sys_get_temp_dir(), 'sstok');
    $token = str_repeat('Tk', 20);
    file_put_contents($path, $token);
    $pw = SS_TEST_PASSWORD;
    db()->exec('DELETE FROM login_attempts');

    set_config('auth.max_attempts_setup', 3);
    for ($i = 1; $i <= 3; $i++) {
        same('failed', setup_run('wrong' . $i, 'throttle@example.org', $pw, $pw, $path), 'wrong token ' . $i);
    }
    same('throttled', setup_run('wrong4', 'throttle@example.org', $pw, $pw, $path), 'fourth attempt');
    same('throttled', setup_run($token, 'throttle@example.org', $pw, $pw, $path), 'even the right token is refused while throttled');
    ok(is_file($path), 'token file untouched');
    same(null, Members::findByEmail('throttle@example.org'), 'nothing created');
    same(3, LoginAttempts::countRecent('setup', auth_hmac('setup', client_ip_key()), 15), 'only the three wrong tokens were recorded');
    $GLOBALS['ss_config']['auth'] = [];

    db()->exec('DELETE FROM login_attempts');
    set_config('app_key', null);
    same('unavailable', setup_run($token, 'nokey@example.org', $pw, $pw, $path), 'no app_key');
    set_config('app_key', str_repeat('t', 32));
    same('done', setup_run($token, 'nokey@example.org', $pw, $pw, $path), 'works again with app_key');
    ok(!is_file($path), 'token deleted');
    db()->exec('DELETE FROM login_attempts');
});

test('setup_run: unwritable data directory (not on Windows or as root)', function (): void {
    if (PHP_OS_FAMILY === 'Windows' || (function_exists('posix_geteuid') && posix_geteuid() === 0)) {
        ok(true, 'skipped');
        return;
    }
    $dir = sys_get_temp_dir() . '/ss-unwritable-' . bin2hex(random_bytes(4));
    mkdir($dir);
    $path = $dir . '/setup-token';
    file_put_contents($path, str_repeat('Tk', 20));
    chmod($dir, 0555);
    same('not_writable', setup_run(str_repeat('Tk', 20), 'ro@example.org', SS_TEST_PASSWORD, SS_TEST_PASSWORD, $path), 'directory not writable');
    same(null, Members::findByEmail('ro@example.org'), 'nothing created');
    chmod($dir, 0755);
    unlink($path);
    rmdir($dir);
});
