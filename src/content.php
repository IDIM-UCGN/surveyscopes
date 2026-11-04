<?php

declare(strict_types=1);

/*
 * Admin-managed content: settings and pages.
 * Source of truth is the database; without a database (local development)
 * the instance seed from config is used read-only.
 */

/** All settings as key => decoded value. */
function settings_all(): array
{
    static $cache = null;
    if (is_array($cache)) {
        return $cache;
    }
    $cache = (array) config('seed.settings', []);
    if (db_configured()) {
        foreach (db()->query('SELECT setting_key, value FROM settings') as $row) {
            $cache[$row['setting_key']] = json_decode((string) $row['value'], true);
        }
    }
    return $cache;
}

/** One setting; per-locale arrays are resolved with localized(). */
function setting(string $key, mixed $default = null): mixed
{
    $all = settings_all();
    if (!array_key_exists($key, $all)) {
        return $default;
    }
    $value = $all[$key];
    if (is_array($value) && array_intersect(array_keys($value), array_merge(locales(), ['default'])) !== []) {
        return localized($value) ?? $default;
    }
    return $value;
}

/** Site name: admin setting, falling back to config. */
function site_name(): string
{
    return (string) setting('site_name', config('instance_name', 'SurveyScopes'));
}

/**
 * Page content in the current locale (fallback: default locale).
 * Returns ['title' => …, 'body' => …, 'meta_description' => …] or null.
 */
function page_content(string $pageKey): ?array
{
    $wanted = array_unique([lang(), default_locale()]);

    if (db_configured()) {
        $stmt = db()->prepare('SELECT locale, title, body, meta_description FROM page_translations
            WHERE page_key = ? AND locale IN (?, ?)');
        $stmt->execute([$pageKey, $wanted[0], $wanted[1] ?? $wanted[0]]);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[$row['locale']] = $row;
        }
    } else {
        $rows = (array) config("seed.pages.$pageKey", []);
    }

    foreach ($wanted as $locale) {
        if (isset($rows[$locale]) && trim((string) ($rows[$locale]['body'] ?? '')) !== '') {
            return [
                'title' => (string) ($rows[$locale]['title'] ?? ''),
                'body' => (string) $rows[$locale]['body'],
                'meta_description' => (string) ($rows[$locale]['meta_description'] ?? ''),
            ];
        }
    }
    return null;
}
