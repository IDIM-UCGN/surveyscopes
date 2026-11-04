<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /language\n";
echo 'Sitemap: ' . url_default('sitemap.xml') . "\n";
