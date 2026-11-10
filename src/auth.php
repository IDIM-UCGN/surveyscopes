<?php

declare(strict_types=1);

/*
 * Admin-area authentication: lazy session, login, roles, throttling.
 * Public pages never call anything here, so participants never get a cookie.
 * See docs/architecture.md §10.
 */

const SS_SESSION_NAME = 'ss_session';

/** Fallbacks for invalid auth.* config values (<= 0 or not an integer). */
const SS_AUTH_DEFAULTS = [
    'idle_minutes' => 120,
    'absolute_hours' => 12,
    'window_minutes' => 15,
    'max_attempts_pair' => 5,
    'max_attempts_account' => 50,
    'max_attempts_ip' => 30,
    'max_attempts_setup' => 10,
    'setup_token_max_age_hours' => 24,
];

const SS_EMAIL_MAX_BYTES = 254;
const SS_PASSWORD_MIN_CHARS = 12;
const SS_PASSWORD_MAX_BYTES = 72; // bcrypt ignores everything after 72 bytes

/** Positive integer auth setting; invalid values are logged and replaced by the default. */
function auth_setting(string $key): int
{
    if (!array_key_exists($key, SS_AUTH_DEFAULTS)) {
        throw new InvalidArgumentException('Unknown auth setting: ' . $key);
    }
    static $logged = [];
    $value = config('auth.' . $key, SS_AUTH_DEFAULTS[$key]);
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (!isset($logged[$key])) {
        $logged[$key] = true;
        error_log(sprintf('[surveyscopes] invalid config auth.%s, using default %d', $key, SS_AUTH_DEFAULTS[$key]));
    }
    return SS_AUTH_DEFAULTS[$key];
}

/** The HMAC key for throttling hashes, or null if app_key is missing or too short. */
function auth_key(): ?string
{
    $key = config('app_key');
    return is_string($key) && strlen($key) >= 32 ? $key : null;
}

/** Login needs a database and an app_key; a missing key is logged. */
function auth_available(): bool
{
    if (!db_configured()) {
        return false;
    }
    if (auth_key() === null) {
        error_log('[surveyscopes] login disabled: app_key missing or shorter than 32 characters');
        return false;
    }
    return true;
}

/* ---------------------------------------------------------------- session */

/** Start the session (idempotent). Only login and admin pages call this. */
function session_begin(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    if (headers_sent()) {
        throw new RuntimeException('Session must start before any output.');
    }

    $dir = SS_ROOT . '/data/sessions';
    if (!is_dir($dir) && !@mkdir($dir, 0700, true) && !is_dir($dir)) {
        throw new RuntimeException('Cannot create session directory data/sessions.');
    }
    if (!is_file($dir . '/.htaccess')) {
        @file_put_contents($dir . '/.htaccess', "Require all denied\n");
    }

    $idle = auth_setting('idle_minutes') * 60;
    session_name(SS_SESSION_NAME);
    session_save_path($dir);
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start([
        'use_strict_mode' => 1,
        'use_only_cookies' => 1,
        'use_trans_sid' => 0,
        'cache_limiter' => '',
        'gc_probability' => 1,
        'gc_divisor' => 100,
        'gc_maxlifetime' => $idle,
    ]);
    header('Cache-Control: no-store');
    header('X-Robots-Tag: noindex, nofollow');

    if (isset($_SESSION['user_id'])) {
        $reason = session_expiry_reason($_SESSION, time(), $idle, auth_setting('absolute_hours') * 3600);
        if ($reason !== null) {
            end_user_session();
            flash('notice', 'login.expired');
        } else {
            $_SESSION['last_seen'] = time();
        }
    }
}

/**
 * Why a logged-in session has expired: 'absolute' (too long since login),
 * 'idle' (too long since the last request) or null (still valid).
 * Pure function, so the lifetime rules are testable with injected times.
 */
function session_expiry_reason(array $session, int $now, int $idleSeconds, int $absoluteSeconds): ?string
{
    $loginAt = $session['login_at'] ?? null;
    $lastSeen = $session['last_seen'] ?? null;
    if (!is_int($loginAt) || !is_int($lastSeen)) {
        return 'absolute';
    }
    if ($now - $loginAt >= $absoluteSeconds) {
        return 'absolute';
    }
    if ($now - $lastSeen >= $idleSeconds) {
        return 'idle';
    }
    return null;
}

/** Drop the login from the session but keep the session (e.g. for a flash message). */
function end_user_session(): void
{
    unset($_SESSION['user_id'], $_SESSION['login_at'], $_SESSION['last_seen'], $_SESSION['_csrf']);
    session_regenerate_id(true);
    reset_current_user();
}

/** One-time message for the next request (PRG). */
function flash(string $key, mixed $value): void
{
    session_begin();
    $_SESSION['_flash'][$key] = $value;
}

function take_flash(string $key): mixed
{
    session_begin();
    $value = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $value;
}

/* ------------------------------------------------------------ users/roles */

/** The logged-in, active user (re-read from the database once per request), or null. */
function current_user(): ?array
{
    $cache = &current_user_cache();
    if ($cache['loaded']) {
        return $cache['user'];
    }
    if (session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[SS_SESSION_NAME])) {
        // No session cookie: nobody is logged in. Don't create a session
        // (no file in data/sessions/, no Set-Cookie) just to find that out.
        $cache = ['loaded' => true, 'user' => null];
        return null;
    }
    session_begin();
    $id = $_SESSION['user_id'] ?? null;
    $user = null;
    if (is_int($id) && db_configured()) {
        $user = Users::findActiveById($id);
        if ($user === null) {
            end_user_session(); // disabled or deleted meanwhile
        }
    }
    $cache = ['loaded' => true, 'user' => $user];
    return $user;
}

/** Per-request cache for current_user() (static, by reference). */
function &current_user_cache(): array
{
    static $cache = ['loaded' => false, 'user' => null];
    return $cache;
}

/** Forget the cached user, e.g. after login or logout. */
function reset_current_user(): void
{
    $cache = &current_user_cache();
    $cache = ['loaded' => false, 'user' => null];
}

/** admin includes all editor rights. */
function role_satisfies(string $have, string $need): bool
{
    $rank = ['editor' => 1, 'admin' => 2];
    return isset($rank[$have], $rank[$need]) && $rank[$have] >= $rank[$need];
}

/**
 * Guard for admin pages: not logged in → 302 to the login page (with next),
 * insufficient role → 403. Returns the current user.
 */
function require_role(string $role): array
{
    if (!in_array($role, Users::ROLES, true)) {
        throw new InvalidArgumentException('Unknown role: ' . $role);
    }
    $user = current_user();
    if ($user === null) {
        header('Cache-Control: no-store'); // also when no session was started
        header('Location: ' . url('login') . '?next=' . rawurlencode(request_path()), true, 302);
        exit;
    }
    if (!role_satisfies((string) $user['role'], $role)) {
        header('Cache-Control: no-store');
        if (!is_file(SS_ROOT . '/templates/403.php')) {
            // Fallback until templates/403.php exists (added with the admin pages).
            http_response_code(403);
            header('Content-Type: text/plain; charset=utf-8');
            echo "403 Forbidden\n";
            exit;
        }
        render('403', ['pageTitle' => tr('403.title'), 'noIndex' => true, 'noFollow' => true], 403);
        exit;
    }
    return $user;
}

/* ----------------------------------------------------------- throttling */

/**
 * Throttling identity of the client: REMOTE_ADDR only (forwarding headers
 * are ignored). IPv4 in full, IPv6 reduced to its /64 prefix.
 */
function client_ip_key(?string $remoteAddr = null): string
{
    $ip = $remoteAddr ?? (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $bin = @inet_pton($ip);
    if ($bin === false) {
        return 'unknown';
    }
    if (strlen($bin) === 16) {
        if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
            return (string) inet_ntop(substr($bin, 12)); // IPv4-mapped
        }
        return bin2hex(substr($bin, 0, 8)) . '/64';
    }
    return (string) inet_ntop($bin);
}

function auth_hmac(string $purpose, string $value): string
{
    $key = auth_key();
    if ($key === null) {
        throw new RuntimeException('app_key is required for login throttling.');
    }
    return hash_hmac('sha256', $purpose . "\0" . $value, $key);
}

/**
 * Throttling keys (type => HMAC). Without a valid e-mail only the IP key.
 * @return array<string, string>
 */
function throttle_keys(?string $email, string $ipKey): array
{
    $keys = ['ip' => auth_hmac('ip', $ipKey)];
    if ($email !== null) {
        $keys['pair'] = auth_hmac('pair', $email . "\n" . $ipKey);
        $keys['account'] = auth_hmac('account', $email);
    }
    return $keys;
}

/** True if any of the keys has reached its limit within the window. */
function is_throttled(array $keys): bool
{
    $window = auth_setting('window_minutes');
    foreach ($keys as $type => $hash) {
        if (LoginAttempts::countRecent($type, $hash, $window) >= auth_setting('max_attempts_' . $type)) {
            return true;
        }
    }
    return false;
}

/**
 * Record a failure for each key. Throttled requests never get here, so a key
 * reaches its limit exactly once per lockout: that moment is logged (no e-mail, no IP).
 */
function record_failure(array $keys): void
{
    LoginAttempts::record($keys);
    $window = auth_setting('window_minutes');
    foreach ($keys as $type => $hash) {
        if (LoginAttempts::countRecent($type, $hash, $window) === auth_setting('max_attempts_' . $type)) {
            error_log(sprintf('[surveyscopes] login throttled: key_type=%s key=%s window=%dmin', $type, substr($hash, 0, 12), $window));
        }
    }
}

/** Delete attempts older than 24 h on about 1 in 100 calls. */
function maybe_purge_attempts(): void
{
    if (random_int(1, 100) === 1) {
        LoginAttempts::purgeOlderThan(24);
    }
}

/* ------------------------------------------------------------- passwords */

/** null if acceptable, else 'too_short', 'too_long' or 'invalid'. */
function validate_new_password(string $password): ?string
{
    if (!mb_check_encoding($password, 'UTF-8') || str_contains($password, "\0")) {
        return 'invalid';
    }
    if (strlen($password) > SS_PASSWORD_MAX_BYTES) {
        return 'too_long';
    }
    if (mb_strlen($password, 'UTF-8') < SS_PASSWORD_MIN_CHARS) {
        return 'too_short';
    }
    return null;
}

/**
 * Hash used for password_verify() when there is no real hash to check, so
 * unknown and known accounts take about the same time. Generated at runtime
 * with the current PASSWORD_DEFAULT cost and cached in data/, so failure
 * paths cost one verify, not an extra hash.
 */
function dummy_password_hash(): string
{
    static $hash = null;
    if (is_string($hash)) {
        return $hash;
    }
    $valid = static fn (string $h): bool => !empty(password_get_info($h)['algo'])
        && !password_needs_rehash($h, PASSWORD_DEFAULT);

    $file = SS_ROOT . '/data/.dummy-hash';
    $stored = is_file($file) ? trim((string) @file_get_contents($file)) : '';
    if ($stored !== '' && $valid($stored)) {
        return $hash = $stored;
    }

    $created = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
    if (!$valid($created)) {
        throw new RuntimeException('Could not create a valid dummy password hash.');
    }
    // Not cached: still correct, only slower on failure paths. Logging is
    // sampled (about 1 in 100 calls) because there is nowhere to keep a counter.
    if (!write_file_atomic($file, $created . "\n", 0600) && random_int(1, 100) === 1) {
        error_log('[surveyscopes] cannot cache dummy password hash in data/ (directory not writable?)');
    }
    return $hash = $created;
}

/**
 * Write a file atomically: temp file in the same directory with $mode,
 * then rename over the target. Returns false on any failure.
 */
function write_file_atomic(string $file, string $content, int $mode): bool
{
    $dir = dirname($file);
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        return false;
    }
    $tmp = $dir . '/.' . basename($file) . '.' . bin2hex(random_bytes(6)) . '.tmp';
    $fh = @fopen($tmp, 'xb');
    if ($fh === false) {
        return false;
    }
    @chmod($tmp, $mode); // before any content is written
    $ok = fwrite($fh, $content) === strlen($content);
    $ok = fclose($fh) && $ok;
    if (!$ok || !@rename($tmp, $file)) {
        @unlink($tmp);
        return false;
    }
    return true;
}

/* ----------------------------------------------------------------- login */

/**
 * Try to log in. Returns 'ok', 'failed', 'throttled' or 'unavailable'.
 * Unknown e-mail, wrong password, disabled account and invalid or overlong
 * input all return 'failed' and take about the same time.
 */
function attempt_login(mixed $emailInput, mixed $passwordInput): string
{
    if (!auth_available()) {
        return 'unavailable';
    }
    session_begin();
    maybe_purge_attempts();

    $email = is_string($emailInput) && strlen($emailInput) <= SS_EMAIL_MAX_BYTES
        ? Users::validEmail($emailInput) : null; // ASCII only; anything else counts as invalid
    $password = is_string($passwordInput) && $passwordInput !== ''
        && strlen($passwordInput) <= SS_PASSWORD_MAX_BYTES ? $passwordInput : null;

    $keys = throttle_keys($email, client_ip_key());
    if (is_throttled($keys)) {
        return 'throttled';
    }

    $user = $email !== null && $password !== null ? Users::findByEmail($email) : null;
    if ($user !== null && $user['email'] !== $email) {
        $user = null; // defence in depth: only an exact match counts, else treat as unknown
    }
    if ($user !== null) {
        $ok = password_verify($password, (string) $user['password_hash']) && $user['status'] === 'active';
    } else {
        password_verify($password ?? '', dummy_password_hash());
        $ok = false;
    }

    if (!$ok) {
        record_failure($keys); // only the IP key if the e-mail was invalid
        return 'failed';
    }

    if (password_needs_rehash((string) $user['password_hash'], PASSWORD_DEFAULT)) {
        Users::updatePasswordHash($user['id'], password_hash($password, PASSWORD_DEFAULT));
    }
    LoginAttempts::clear(['pair' => $keys['pair'], 'account' => $keys['account']]);
    Users::touchLogin($user['id']);

    session_regenerate_id(true);
    $now = time();
    $_SESSION = ['user_id' => $user['id'], 'login_at' => $now, 'last_seen' => $now];
    reset_current_user();
    return 'ok';
}

/** Log out: empty and destroy the session, expire the cookie. */
function logout_user(): void
{
    session_begin();
    $_SESSION = [];
    session_destroy();
    setcookie(SS_SESSION_NAME, '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    reset_current_user();
}
