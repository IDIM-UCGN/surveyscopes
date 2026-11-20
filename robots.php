<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');
echo "User-agent: *\n";
echo "Allow: /\n";
echo "Disallow: /language\n";
// Account pages (also noindex): no reason for crawlers to visit them.
foreach (['login', 'logout', 'setup', 'admin'] as $account) {
    echo "Disallow: /*/$account\n";
}
echo 'Sitemap: ' . url_default('sitemap.xml') . "\n";
