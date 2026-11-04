<?php

declare(strict_types=1);

/** @var string $pageTitle */
$siteName = (string) config('instance_name', 'SurveyScopes');
$fullTitle = ($pageTitle ?? '') !== '' ? $pageTitle . ' – ' . $siteName : $siteName;
$noIndex = $noIndex ?? false;
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
<?php if (isset($pageDescription)): ?>
    <meta name="description" content="<?= e($pageDescription) ?>">
<?php endif; ?>
<?php if ($noIndex): ?>
    <meta name="robots" content="noindex">
<?php else: ?>
<?php foreach (locales() as $code): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(url(current_page(), $code, true)) ?>">
<?php endforeach; ?>
<?php endif; ?>
    <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e(app_version()) ?>">
<?php if (is_string(config('custom_css')) && config('custom_css') !== ''): ?>
    <link rel="stylesheet" href="<?= e((string) config('custom_css')) ?>?v=<?= e(app_version()) ?>">
<?php endif; ?>
</head>
<body class="d-flex flex-column min-vh-100">
<header class="site-header">
    <nav class="navbar navbar-expand container">
        <a class="navbar-brand site-brand" href="<?= e(url()) ?>">
            <img src="/assets/img/logo.svg" alt="" width="32" height="32">
            <span><?= e($siteName) ?></span>
        </a>
        <ul class="navbar-nav ms-auto">
            <li class="nav-item">
                <a class="nav-link<?= current_page() === 'surveys' ? ' active' : '' ?>" href="<?= e(url('surveys')) ?>"<?= current_page() === 'surveys' ? ' aria-current="page"' : '' ?>><?= t('surveys.title') ?></a>
            </li>
        </ul>
    </nav>
</header>
