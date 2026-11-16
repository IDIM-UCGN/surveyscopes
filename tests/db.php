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
