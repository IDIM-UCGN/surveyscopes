<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('privacy');

// Interim: text from config/instance.php ('pages' => ['privacy' => ['de' => '…']]);
// will be edited by the site admin (page_translations table).
render('page', [
    'pageTitle' => tr('privacy.title'),
    'body' => localized(config('pages.privacy')),
]);
