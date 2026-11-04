<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
page('index');

// Front page content is managed by the site admin (pages table, key "front").
// The first paragraph is the hero intro, everything after it is shown below.
$front = page_content('front');
$parts = preg_split("/\R{2,}/", trim((string) ($front['body'] ?? tr('front.intro'))), 2);

render('front', [
    'pageTitle' => $front['title'] ?? tr('front.headline'),
    'pageDescription' => $front['meta_description'] ?? '' ?: tr('front.intro'),
    'headline' => $front['title'] ?? tr('front.headline'),
    'intro' => $parts[0],
    'more' => $parts[1] ?? '',
    'showSurveyList' => (bool) setting('show_survey_list', true),
    'surveys' => [], // database-backed list follows with the survey module
    'jsonLd' => [
        '@context' => 'https://schema.org',
        '@type' => 'WebSite',
        'name' => site_name(),
        'url' => url('index', lang(), true),
        'inLanguage' => lang(),
        'description' => (string) setting('site_description', tr('front.intro')),
    ],
]);
