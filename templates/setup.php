<?php

declare(strict_types=1);

/** @var bool $unavailable @var ?string $error @var string $email */
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6">
            <h1 class="display-6 fw-bold mb-3"><?= t('setup.title') ?></h1>
<?php if ($unavailable): ?>
            <div class="alert alert-warning" role="alert"><?= t('setup.unavailable') ?></div>
<?php else: ?>
            <p class="mb-4"><?= t('setup.intro') ?></p>
<?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert"><?= t($error) ?></div>
<?php endif; ?>
            <form method="post" action="<?= e(url('setup')) ?>">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="setup-token"><?= t('setup.token') ?></label>
                    <input class="form-control" type="password" id="setup-token" name="token" autocomplete="off" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="setup-email"><?= t('setup.email') ?></label>
                    <input class="form-control" type="email" id="setup-email" name="email" value="<?= e($email) ?>" maxlength="254" autocomplete="email" required>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="setup-password"><?= t('setup.password') ?></label>
                    <input class="form-control" type="password" id="setup-password" name="password" maxlength="72" autocomplete="new-password" required>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="setup-password-repeat"><?= t('setup.password_repeat') ?></label>
                    <input class="form-control" type="password" id="setup-password-repeat" name="password_repeat" maxlength="72" autocomplete="new-password" required>
                </div>
                <button class="btn btn-primary" type="submit"><?= t('setup.submit') ?></button>
            </form>
<?php endif; ?>
        </div>
    </div>
</main>
