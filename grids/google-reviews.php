<?php

declare(strict_types=1);

/**
 * Grid (card) layout for the Google Reviews list, rendered by Osmium\Modules\Admin\Core\ListViewGrid. Columns use the
 * same vocabulary as lists/google-reviews.php but nothing is shared with it. The actions are a copy of the list's for now.
 */
return [
    'columns' => 3,
    'title' => [
        'key' => 'authorName',
        'type' => 'text',
        'strong' => true,
        'sub' => ['key' => 'reviewDate', 'style' => 'muted'],
    ],
    'meta' => [
        ['key' => 'ratingStars', 'type' => 'raw'],
        ['key' => 'featuredBadge', 'type' => 'raw'],
        ['key' => 'reviewPreview', 'type' => 'text', 'small' => true],
    ],
    'actionsAt' => 'bottom-end',
    'actions' => [
        ['label' => 'Edit', 'icon' => 'bx-edit-alt', 'event' => 'edit'],
        [
            'label' => 'Feature on homepage',
            'icon' => 'bx-show',
            'action' => 'feature',
            'visible' => fn(array $row, array $context): bool => !$row['isFeatured'],
        ],
        [
            'label' => 'Hide from homepage',
            'icon' => 'bx-hide',
            'action' => 'unfeature',
            'visible' => fn(array $row, array $context): bool => (bool) $row['isFeatured'],
        ],
        [
            'label' => 'Delete',
            'icon' => 'bx-trash',
            'action' => 'delete',
            'confirm' => 'Delete this review? It can be restored from Deleted Reviews.',
            'danger' => true,
        ],
    ],
];
