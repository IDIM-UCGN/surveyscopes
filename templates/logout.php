<?php

declare(strict_types=1);
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <h1 class="display-6 fw-bold mb-3"><?= t('logout.title') ?></h1>
            <p class="lead"><?= t('logout.confirm') ?></p>
            <form method="post" action="<?= e(url('logout')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-primary" type="submit"><?= t('logout.submit') ?></button>
            </form>
        </div>
    </div>
</main>
