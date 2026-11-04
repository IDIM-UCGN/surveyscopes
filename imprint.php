<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('imprint');

// Content is managed by the site admin (pages table, key "imprint").
$content = page_content('imprint');

render('page', [
    'pageTitle' => ($content['title'] ?? '') ?: tr('imprint.title'),
    'pageDescription' => $content['meta_description'] ?? '',
    'body' => $content['body'] ?? null,
    'noIndex' => $content === null,
]);
