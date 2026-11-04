<?php

declare(strict_types=1);

require __DIR__ . '/src/bootstrap.php';
set_locale(default_locale());

$pages = ['index', 'surveys'];
foreach (['imprint', 'privacy'] as $legal) {
    if (page_content($legal) !== null) {
        $pages[] = $legal;
    }
}

header('Content-Type: application/xml; charset=utf-8');
echo '<?xml version="1.0" encoding="UTF-8"?>', "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">
<?php foreach ($pages as $p): foreach (locales() as $code): ?>
    <url>
        <loc><?= e(url($p, $code, true)) ?></loc>
<?php foreach (locales() as $alt): ?>
        <xhtml:link rel="alternate" hreflang="<?= e($alt) ?>" href="<?= e(url($p, $alt, true)) ?>"/>
<?php endforeach; ?>
        <xhtml:link rel="alternate" hreflang="x-default" href="<?= e(url_default($p)) ?>"/>
    </url>
<?php endforeach; endforeach; ?>
</urlset>
