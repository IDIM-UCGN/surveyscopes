<?php

declare(strict_types=1);

/** @var array $user The signed-in user, see current_user() */
$yesNo = static fn (bool $value): string => t($value ? 'admin.yes' : 'admin.no');
?>
<main class="container py-5">
    <h1 class="display-6 fw-bold mb-2"><?= t('admin.title') ?></h1>
    <p class="lead mb-4"><?= t('admin.signed_in_as', ['{email}' => (string) $user['email']]) ?></p>

    <h2 class="h5"><?= t('admin.account') ?></h2>
    <dl class="row mb-4">
        <dt class="col-sm-4"><?= t('login.email') ?></dt>
        <dd class="col-sm-8"><?= e((string) $user['email']) ?></dd>
        <dt class="col-sm-4"><?= t('admin.status') ?></dt>
        <dd class="col-sm-8"><?= t('admin.status.' . (string) $user['status']) ?></dd>
        <dt class="col-sm-4"><?= t('admin.is_admin') ?></dt>
        <dd class="col-sm-8"><?= $yesNo(user_is_admin($user)) ?></dd>
        <dt class="col-sm-4"><?= t('admin.can_create_surveys') ?></dt>
        <dd class="col-sm-8"><?= $yesNo(user_can_create_surveys($user)) ?></dd>
        <dt class="col-sm-4"><?= t('admin.survey_roles') ?></dt>
        <dd class="col-sm-8"><?= t('admin.survey_roles_none') ?></dd>
    </dl>

    <form method="post" action="<?= e(url('logout')) ?>">
        <?= csrf_field() ?>
        <button class="btn btn-primary" type="submit"><?= t('logout.submit') ?></button>
    </form>
</main>
