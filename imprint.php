<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('imprint');

// Interim: text from config/instance.php ('pages' => ['imprint' => ['de' => '…']]);
// will be edited by the site admin (page_translations table).
render('page', [
    'pageTitle' => tr('imprint.title'),
    'body' => localized(config('pages.imprint')),
]);
