<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
require __DIR__ . '/src/setup.php';
page('setup');

// The page exists only while a valid data/setup-token file does. Otherwise it
// answers exactly like any unknown page (404, no session, no cookie).
if (setup_token() === null) {
    not_found();
}

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$page = ['pageTitle' => tr('setup.title'), 'noIndex' => true, 'noFollow' => true];

if (!auth_available()) {
    render('setup', $page + ['unavailable' => true, 'error' => null, 'email' => '']);
    exit;
}

session_begin(); // before any output: the form needs the CSRF token

if ($isPost) {
    csrf_require();
    $result = setup_run(
        $_POST['token'] ?? null,
        $_POST['email'] ?? null,
        $_POST['password'] ?? null,
        $_POST['password_repeat'] ?? null
    );
    if ($result === 'done') {
        session_regenerate_id(true);
        flash('notice', 'setup.done');
        header('Location: ' . url('login'), true, 303);
        exit;
    }
    flash('error', 'setup.' . $result);
    $email = $_POST['email'] ?? null;
    if (is_string($email) && strlen($email) <= SS_EMAIL_MAX_BYTES) {
        flash('email', $email); // never the token or a password
    }
    header('Location: ' . url('setup'), true, 303);
    exit;
}

$error = take_flash('error');
$email = take_flash('email');

render('setup', $page + [
    'unavailable' => false,
    'error' => is_string($error) ? $error : null,
    'email' => is_string($email) ? $email : '',
]);
