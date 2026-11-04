<?php

declare(strict_types=1);

/** @var array $surveys */
?>
<main class="container py-5">
    <h1 class="display-6 fw-bold mb-2"><?= t('surveys.title') ?></h1>
    <p class="lead mb-4"><?= t('surveys.lead') ?></p>
<?php require __DIR__ . '/partials/survey-list.php'; ?>
</main>
