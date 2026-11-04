<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

$prefix = path_prefix();
set_locale($prefix !== null && in_array($prefix, locales(), true) ? $prefix : browser_locale());
not_found();
