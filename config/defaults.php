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

    /*
     * Admin login. Values must be positive integers; invalid values are
     * logged and replaced by these defaults. Throttling counts failures
     * per e-mail+IP ("pair"), per e-mail ("account") and per IP within
     * window_minutes; IPs are taken from REMOTE_ADDR only and stored as
     * HMAC hashes (requires app_key, at least 32 characters).
     */
    'auth' => [
        'idle_minutes' => 120,
        'absolute_hours' => 12,
        'window_minutes' => 15,
        'max_attempts_pair' => 5,
        'max_attempts_account' => 50,
        'max_attempts_ip' => 30,
        'max_attempts_setup' => 10,
        'setup_token_max_age_hours' => 24,
    ],

    'app_key' => null,
    'database' => null,
];
