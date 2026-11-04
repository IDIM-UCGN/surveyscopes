<?php

declare(strict_types=1);

/** Layered configuration: defaults → instance → secrets → local. */
function load_config(string $configDir): array
{
    $config = [];
    foreach (['defaults', 'instance', 'secrets', 'local'] as $layer) {
        $file = $configDir . '/' . $layer . '.php';
        if (is_file($file)) {
            $values = require $file;
            if (is_array($values)) {
                $config = array_replace_recursive($config, $values);
            }
        }
    }
    return $config;
}

/** Read a config value by dotted key, e.g. config('front.show_survey_list'). */
function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['ss_config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
