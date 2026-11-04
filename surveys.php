<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('surveys');

render('surveys', [
    'pageTitle' => tr('surveys.title'),
    'surveys' => [], // database-backed list follows with the survey module
]);
