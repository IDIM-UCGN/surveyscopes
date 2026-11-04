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

    'seo' => [
        'og_image' => '/assets/img/og-image.png',
    ],

    /*
     * Instance seed: initial settings and page contents, copied into the
     * database on first run (and when 'version' increases). Afterwards the
     * database is the source of truth; rows edited by the site admin are
     * never overwritten. Without a database the seed is used read-only.
     *
     * 'seed' => [
     *     'version' => 1,
     *     'settings' => [
     *         'site_name' => 'SurveyScopes',
     *         'site_description' => ['de' => '…', 'en' => '…'],
     *         'show_survey_list' => true,
     *     ],
     *     'pages' => [
     *         'front'   => ['de' => ['title' => '…', 'body' => '…', 'meta_description' => '…'], 'en' => [/* … *\/]],
     *         'imprint' => [/* … *\/],
     *         'privacy' => [/* … *\/],
     *     ],
     * ],
     */
    'seed' => [],

    'app_key' => null,
    'database' => null,
];
