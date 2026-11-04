<?php

declare(strict_types=1);

/** @var array<int, array{slug: string, title: string, description: string}> $surveys */
?>
<?php if ($surveys === []): ?>
        <div class="empty-state"><?= t('surveys.empty') ?></div>
<?php else: ?>
        <div class="list-group survey-list">
<?php foreach ($surveys as $survey): ?>
            <a class="list-group-item list-group-item-action" href="<?= e(url('survey/' . $survey['slug'])) ?>">
                <span class="fw-semibold"><?= e($survey['title']) ?></span>
                <span class="d-block small text-body-secondary"><?= e($survey['description']) ?></span>
            </a>
<?php endforeach; ?>
        </div>
<?php endif; ?>
