<?php

declare(strict_types=1);
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title><?= t('maintenance.title') ?></title>
    <link rel="stylesheet" href="/assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="d-flex align-items-center min-vh-100">
<main class="container text-center">
    <img src="/assets/img/logo.svg" alt="" width="56" height="56" class="mb-3">
    <h1 class="h3"><?= t('maintenance.title') ?></h1>
    <p class="lead"><?= t('maintenance.text') ?></p>
</main>
</body>
</html>
