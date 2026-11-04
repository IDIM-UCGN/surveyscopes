<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('privacy');

// Content is managed by the site admin (pages table, key "privacy").
$content = page_content('privacy');

render('page', [
    'pageTitle' => ($content['title'] ?? '') ?: tr('privacy.title'),
    'pageDescription' => $content['meta_description'] ?? '',
    'body' => $content['body'] ?? null,
    // Legal pages are kept out of search engines.
    'noIndex' => true,
    'noFollow' => true,
]);
