<?php

declare(strict_types=1);

/**
 * SEO head + site header.
 * Page variables: $pageTitle, $pageDescription, $noIndex (bool), $jsonLd (array|null)
 */
$siteName = site_name();
$fullTitle = ($pageTitle ?? '') !== '' && $pageTitle !== $siteName ? $pageTitle . ' – ' . $siteName : $siteName;
$description = trim((string) ($pageDescription ?? '')) ?: (string) setting('site_description', '');
$noIndex = $noIndex ?? false;
$canonical = url(current_page(), lang(), true);
$ogImage = (string) setting('og_image', config('seo.og_image', '/assets/img/og-image.png'));
if (str_starts_with($ogImage, '/')) {
    $ogImage = url_default(ltrim($ogImage, '/'));
}
?>
<!doctype html>
<html lang="<?= e(lang()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($fullTitle) ?></title>
<?php if ($description !== ''): ?>
    <meta name="description" content="<?= e($description) ?>">
<?php endif; ?>
<?php if ($noIndex): ?>
    <meta name="robots" content="noindex, follow">
<?php else: ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
<?php foreach (locales() as $code): ?>
    <link rel="alternate" hreflang="<?= e($code) ?>" href="<?= e(url(current_page(), $code, true)) ?>">
<?php endforeach; ?>
    <link rel="alternate" hreflang="x-default" href="<?= e(url_default(current_page())) ?>">
<?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($fullTitle) ?>">
<?php if ($description !== ''): ?>
    <meta property="og:description" content="<?= e($description) ?>">
<?php endif; ?>
    <meta property="og:url" content="<?= e($canonical) ?>">
    <meta property="og:locale" content="<?= t('_og_locale') ?>">
    <meta property="og:image" content="<?= e($ogImage) ?>">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="theme-color" content="#0f8a5f">
    <link rel="icon" href="/assets/img/logo.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/vendor/bootstrap-5.3.8/css/bootstrap.min.css">
    <link rel="stylesheet" href="/assets/css/app.css?v=<?= e(app_version()) ?>">
<?php if (is_string(config('custom_css')) && config('custom_css') !== ''): ?>
    <link rel="stylesheet" href="<?= e((string) config('custom_css')) ?>?v=<?= e(app_version()) ?>">
<?php endif; ?>
<?php if (!empty($jsonLd)): ?>
    <script type="application/ld+json"><?= json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<?php endif; ?>
    <script src="/assets/js/app.js?v=<?= e(app_version()) ?>" defer></script>
</head>
<body class="d-flex flex-column min-vh-100">
<header class="site-header">
    <nav class="navbar navbar-expand" aria-label="<?= e($siteName) ?>">
        <div class="container flex-wrap gap-2">
            <a class="navbar-brand site-brand me-auto" href="<?= e(url()) ?>">
                <img src="/assets/img/logo.svg" alt="" width="32" height="32">
                <span><?= e($siteName) ?></span>
            </a>
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link<?= current_page() === 'surveys' ? ' active' : '' ?>" href="<?= e(url('surveys')) ?>"<?= current_page() === 'surveys' ? ' aria-current="page"' : '' ?>><?= t('surveys.title') ?></a>
                </li>
            </ul>
        </div>
    </nav>
</header>
