<?php

declare(strict_types=1);
?>
<footer class="site-footer mt-auto">
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 py-4">
        <div>
            <ul class="list-inline mb-1">
                <li class="list-inline-item"><a href="<?= e(url('imprint')) ?>"><?= t('imprint.title') ?></a></li>
                <li class="list-inline-item"><a href="<?= e(url('privacy')) ?>"><?= t('privacy.title') ?></a></li>
            </ul>
            <p class="mb-0 small footer-muted">
                &copy; <?= date('Y') ?> <?= e(site_name()) ?> &middot;
                <a href="https://www.surveyscopes.org/"><?= t('footer.powered') ?></a>
            </p>
        </div>
<?php if (count(locales()) > 1): ?>
        <form class="lang-switch d-flex gap-2" action="/language" method="get">
            <select name="to" class="form-select form-select-sm" aria-label="<?= t('footer.language') ?>" data-nav>
<?php foreach (locales() as $code): ?>
                <option value="<?= e(url(current_page(), $code)) ?>" lang="<?= e($code) ?>"<?= $code === lang() ? ' selected' : '' ?>><?= e(locale_name($code)) ?></option>
<?php endforeach; ?>
            </select>
            <button type="submit" class="btn btn-sm btn-outline-secondary" data-nav-submit><?= t('footer.language_go') ?></button>
        </form>
<?php endif; ?>
    </div>
</footer>
</body>
</html>
