<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('logout');

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';

// Nobody to sign out (never signed in, or the session has expired).
if (current_user() === null) {
    header('Location: ' . url('login'), true, $isPost ? 303 : 302);
    exit;
}

// Signing out is POST only, with a CSRF token; GET just shows the button.
if ($isPost) {
    csrf_require();
    logout_user();
    header('Location: ' . url('login') . '?logged_out=1', true, 303);
    exit;
}

render('logout', ['pageTitle' => tr('logout.title'), 'noIndex' => true, 'noFollow' => true]);
