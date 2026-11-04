<?php

declare(strict_types=1);

/*
 * Shipped defaults. Instances override them in config/instance.php,
 * config/secrets.php and config/local.php (see docs/architecture.md §8).
 */

return [
    'instance_name' => 'SurveyScopes',
    'base_url' => '',
    'default_locale' => 'en',
    'available_locales' => ['en', 'de'],
    'timezone' => 'UTC',
    'custom_css' => null,

    // Interim front page content until it is managed in the admin area.
    // Per locale: ['de' => ['headline' => '…', 'intro' => '…']]
    'front' => [
        'show_survey_list' => true,
    ],

    // Interim imprint/privacy: external URLs per locale (or 'default').
    // Replaced by admin-managed pages once the admin area exists.
    'legal' => [
        'imprint_url' => null,
        'privacy_url' => null,
    ],

    'app_key' => null,
    'database' => null,
];
