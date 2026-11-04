<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('index');

// Interim: content from config/instance.php; will come from the admin area (pages table).
$front = (array) config('front', []);
$custom = (array) (localized($front['content'] ?? []) ?? []);

render('front', [
    'pageTitle' => tr('front.title'),
    'pageDescription' => $custom['intro'] ?? tr('front.intro'),
    'headline' => $custom['headline'] ?? tr('front.headline'),
    'intro' => $custom['intro'] ?? tr('front.intro'),
    'showSurveyList' => (bool) ($front['show_survey_list'] ?? true),
    'surveys' => [], // database-backed list follows with the survey module
]);
