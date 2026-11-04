<?php

declare(strict_types=1);

$legalLink = static function (string $page): string {
    $external = localized(config("legal.{$page}_url"));
    return is_string($external) && $external !== '' ? $external : url($page);
};
?>
<footer class="site-footer mt-auto">
    <div class="container d-flex flex-column flex-md-row justify-content-between gap-3 py-4">
        <div>
            <ul class="list-inline mb-1">
                <li class="list-inline-item"><a href="<?= e($legalLink('imprint')) ?>"><?= t('imprint.title') ?></a></li>
                <li class="list-inline-item"><a href="<?= e($legalLink('privacy')) ?>"><?= t('privacy.title') ?></a></li>
            </ul>
            <p class="mb-0 small footer-muted">
                &copy; <?= date('Y') ?> <?= e((string) config('instance_name', 'SurveyScopes')) ?> &middot;
                <a href="https://www.surveyscopes.org/"><?= t('footer.powered') ?></a>
            </p>
        </div>
<?php if (count(locales()) > 1): ?>
        <nav aria-label="<?= t('footer.language') ?>">
            <ul class="list-inline mb-0 small">
                <li class="list-inline-item footer-muted"><?= t('footer.language') ?>:</li>
<?php foreach (locales() as $code): ?>
                <li class="list-inline-item">
<?php if ($code === lang()): ?>
                    <span class="fw-semibold" aria-current="true"><?= e(locale_name($code)) ?></span>
<?php else: ?>
                    <a href="<?= e(url(current_page(), $code)) ?>" hreflang="<?= e($code) ?>" lang="<?= e($code) ?>"><?= e(locale_name($code)) ?></a>
<?php endif; ?>
                </li>
<?php endforeach; ?>
            </ul>
        </nav>
<?php endif; ?>
    </div>
</footer>
</body>
</html>
