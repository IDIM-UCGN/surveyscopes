<?php

declare(strict_types=1);

// Language switch without JavaScript: /language?to=/de/surveys -> 302 to that local URL.
$to = (string) ($_GET['to'] ?? '/');
if (preg_match('#^/[a-z]{2}(/[a-z0-9/-]*)?$#', $to) !== 1 || str_contains($to, '//')) {
    $to = '/';
}
header('Location: ' . $to, true, 302);
