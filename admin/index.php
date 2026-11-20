<?php

declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
page('admin');

// Not signed in: redirect to the login page. Signed in without the admin flag: 403.
$user = require_admin();

render('admin/index', [
    'pageTitle' => tr('admin.title'),
    'noIndex' => true,
    'noFollow' => true,
    'user' => $user,
]);
