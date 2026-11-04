<?php

declare(strict_types=1);
?>
<main class="container py-5">
    <h1 class="display-6 fw-bold mb-3"><?= t('404.title') ?></h1>
    <p class="lead"><?= t('404.text') ?></p>
    <a class="btn btn-primary" href="<?= e(url()) ?>"><?= t('404.back') ?></a>
</main>
