<?php

declare(strict_types=1);

/** @var string $pageTitle @var ?string $body */
?>
<main class="container py-5">
    <div class="content-page">
        <h1 class="display-6 fw-bold mb-4"><?= e($pageTitle) ?></h1>
<?php if ($body !== null && $body !== ''): ?>
        <?= markup($body) ?>
<?php else: ?>
        <p class="empty-state"><?= t('legal.missing') ?></p>
<?php endif; ?>
    </div>
</main>
