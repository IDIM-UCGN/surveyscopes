<?php

declare(strict_types=1);

/** Supported locales of this instance. */
function locales(): array
{
    return array_values(array_filter(
        (array) config('available_locales', ['en']),
        fn ($code): bool => is_string($code) && preg_match('/^[a-z]{2}$/', $code) === 1
            && is_file(SS_ROOT . '/lang/' . $code . '.php')
    )) ?: ['en'];
}

function default_locale(): string
{
    $default = (string) config('default_locale', 'en');
    return in_array($default, locales(), true) ? $default : locales()[0];
}

/** Two-letter prefix from the URL path, supported or not (null if none). */
function path_prefix(): ?string
{
    return preg_match('#^/([a-z]{2})(/|$)#', request_path(), $m) === 1 ? $m[1] : null;
}

/** Best supported locale from Accept-Language, fallback default locale. */
function browser_locale(): string
{
    $candidates = [];
    foreach (explode(',', (string) ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')) as $i => $part) {
        $pieces = explode(';', trim($part));
        $code = strtolower(explode('-', trim($pieces[0]))[0]);
        $q = 1.0;
        foreach (array_slice($pieces, 1) as $param) {
            if (preg_match('/^\s*q\s*=\s*([0-9.]+)\s*$/i', $param, $m) === 1) {
                $q = (float) $m[1];
            }
        }
        if ($code !== '' && $q > 0) {
            $candidates[] = [$code, $q, $i];
        }
    }
    usort($candidates, fn (array $a, array $b): int => [$b[1], $a[2]] <=> [$a[1], $b[2]]);
    foreach ($candidates as [$code]) {
        if (in_array($code, locales(), true)) {
            return $code;
        }
    }
    return default_locale();
}

function set_locale(string $locale): void
{
    $GLOBALS['ss_locale'] = $locale;
    $GLOBALS['ss_strings'] = require SS_ROOT . '/lang/' . $locale . '.php';
    $GLOBALS['ss_fallback'] = $locale === default_locale()
        ? [] : require SS_ROOT . '/lang/' . default_locale() . '.php';
}

function lang(): string
{
    return $GLOBALS['ss_locale'] ?? default_locale();
}

/** Raw UI string (fallback: default locale, then key). */
function tr(string $key, array $replace = []): string
{
    $text = $GLOBALS['ss_strings'][$key] ?? $GLOBALS['ss_fallback'][$key] ?? $key;
    return strtr($text, $replace);
}

/** Escaped UI string for templates. */
function t(string $key, array $replace = []): string
{
    return e(tr($key, $replace));
}

/** Native name of a locale, from its own language file. */
function locale_name(string $code): string
{
    $strings = require SS_ROOT . '/lang/' . $code . '.php';
    return $strings['_name'] ?? $code;
}

/** Per-locale value from an array like ['de' => …, 'en' => …, 'default' => …]. */
function localized(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    return $value[lang()] ?? $value[default_locale()] ?? $value['default'] ?? null;
}
