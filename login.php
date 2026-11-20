<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('login');

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
// Where to go after login: only a local admin URL, see safe_next_path().
$next = safe_next_path($isPost ? ($_POST['next'] ?? null) : ($_GET['next'] ?? null));

/**
 * Target after a login, or for a visitor who is already signed in.
 * Stopgap until survey roles exist: a member without the admin flag has no
 * page of their own yet, so they get the front page with a notice.
 */
$landing = static function (array $user) use ($next): string {
    if (user_is_admin($user)) {
        return $next ?? url('admin');
    }
    flash('notice', 'login.no_admin_access');
    return url();
};

$page = ['pageTitle' => tr('login.title'), 'noIndex' => true, 'noFollow' => true];

// Without a database or app_key there is nothing to sign in to: no form, no session.
if (!auth_available()) {
    render('login', $page + ['unavailable' => true, 'error' => null, 'notice' => null, 'email' => '', 'next' => null]);
    exit;
}

$user = current_user(); // starts no session without a session cookie
if ($user !== null && !$isPost) {
    header('Location: ' . $landing($user), true, 302);
    exit;
}

session_begin(); // before any output: the form needs the CSRF token

if ($isPost) {
    csrf_require();
    $result = attempt_login($_POST['email'] ?? null, $_POST['password'] ?? null);
    if ($result === 'ok') {
        header('Location: ' . $landing((array) current_user()), true, 303);
        exit;
    }
    // One message for every kind of failure; only "throttled" and "unavailable" differ.
    flash('error', ['throttled' => 'login.throttled', 'unavailable' => 'login.unavailable'][$result] ?? 'login.failed');
    $email = $_POST['email'] ?? null;
    if (is_string($email) && strlen($email) <= SS_EMAIL_MAX_BYTES) {
        flash('email', $email); // never the password
    }
    header('Location: ' . url('login') . ($next !== null ? '?next=' . rawurlencode($next) : ''), true, 303);
    exit;
}

$error = take_flash('error');
$notice = take_flash('notice');
$email = take_flash('email');
if ($notice === null && isset($_GET['logged_out'])) {
    $notice = 'logout.done';
}

render('login', $page + [
    'unavailable' => false,
    'error' => is_string($error) ? $error : null,
    'notice' => is_string($notice) ? $notice : null,
    'email' => is_string($email) ? $email : '',
    'next' => $next,
]);
