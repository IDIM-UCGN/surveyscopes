<?php

declare(strict_types=1);

/** @var bool $unavailable @var ?string $error @var ?string $notice @var string $email @var ?string $next */
?>
<main class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-5">
            <h1 class="display-6 fw-bold mb-4"><?= t('login.title') ?></h1>
<?php if ($notice !== null): ?>
            <div class="alert alert-success" role="status"><?= t($notice) ?></div>
<?php endif; ?>
<?php if ($unavailable): ?>
            <div class="alert alert-warning" role="alert"><?= t('login.unavailable') ?></div>
<?php else: ?>
<?php if ($error !== null): ?>
            <div class="alert alert-danger" role="alert"><?= t($error) ?></div>
<?php endif; ?>
            <form method="post" action="<?= e(url('login')) ?>">
                <?= csrf_field() ?>
<?php if ($next !== null): ?>
                <input type="hidden" name="next" value="<?= e($next) ?>">
<?php endif; ?>
                <div class="mb-3">
                    <label class="form-label" for="login-email"><?= t('login.email') ?></label>
                    <input class="form-control" type="email" id="login-email" name="email" value="<?= e($email) ?>" maxlength="254" autocomplete="username" required<?php if ($email === ''): ?> autofocus<?php endif; ?>>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="login-password"><?= t('login.password') ?></label>
                    <input class="form-control" type="password" id="login-password" name="password" maxlength="72" autocomplete="current-password" required<?php if ($email !== ''): ?> autofocus<?php endif; ?>>
                </div>
                <button class="btn btn-primary" type="submit"><?= t('login.submit') ?></button>
            </form>
<?php endif; ?>
        </div>
    </div>
</main>
