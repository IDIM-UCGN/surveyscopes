<?php

declare(strict_types=1);

/** @var string $headline @var string $intro @var array $surveys */
?>
<main>
    <section class="hero">
        <div class="container py-5">
            <div class="row">
                <div class="col-lg-8 py-lg-4">
                    <h1 class="display-5 fw-bold mb-4"><?= e($headline) ?></h1>
                    <div class="lead mb-4"><?= markup($intro) ?></div>
                    <a class="btn btn-primary btn-lg" href="<?= e(url('surveys')) ?>"><?= t('front.cta') ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="container py-5">
        <div class="row g-4">
<?php foreach (['open', 'privacy', 'languages'] as $feature): ?>
            <div class="col-md-4">
                <div class="feature-card h-100">
                    <h2 class="h5"><?= t("feature.$feature.title") ?></h2>
                    <p class="mb-0"><?= t("feature.$feature.text") ?></p>
                </div>
            </div>
<?php endforeach; ?>
        </div>
    </section>

<?php if ($showSurveyList): ?>
    <section class="container pb-5">
        <h2 class="h3 mb-3"><?= t('surveys.title') ?></h2>
<?php require __DIR__ . '/partials/survey-list.php'; ?>
    </section>
<?php endif; ?>
</main>
