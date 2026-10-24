<?php

declare(strict_types=1);

use Osmium\Modules\Admin\Core\ListView;

/**
 * Google Reviews lists, rendered by Osmium\Modules\Admin\Core\ListView. Row fields (ratingStars, reviewPreview,
 * featuredBadge...) are prepared by GoogleReviewsController::formatReview() and formatRecentReview()/formatDeletedReview().
 */
require_once 'app/core/admin/services/ChangedByCell.php';

$changedByColumn = ['key' => 'changed_by', 'label' => '', 'type' => 'html', 'sortable' => false, 'render' => fn(array $row, array $context): string => \Osmium\Modules\Admin\Services\ChangedByCell::html($row, (string)($context['avatarUrl'] ?? ''))];

return [
    'active' => [
        'id' => 'google-reviews',
        'title' => 'Google Reviews',
        'noun' => ['review', 'reviews'],
        'endpoint' => 'google-reviews/action/',
        'note' => 'Reviews are copied by hand from the Google Business Profile dashboard, not scraped. Featured reviews show on the homepage widget.',
        'emptyText' => 'No reviews yet - use Add Review to create one.',
        'datatable' => false,
        'order' => null, // Server order: featured first, then display order
        'columns' => [
            ['key' => 'ratingStars', 'label' => 'Rating', 'type' => 'raw', 'nowrap' => true],
            ['key' => 'authorName', 'label' => 'Author', 'type' => 'text', 'strong' => true],
            ['key' => 'reviewPreview', 'label' => 'Review', 'type' => 'text', 'small' => true, 'fill' => true],
            ['key' => 'reviewDate', 'label' => 'Date', 'type' => 'text', 'nowrap' => true],
            ['key' => 'featuredBadge', 'label' => 'Featured', 'type' => 'raw'],
        ],
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
    ],

    'recent' => [
        'id' => 'google-reviews-recent',
        'title' => 'Recent Changes',
        'noun' => ['review', 'reviews'],
        'icon' => 'bx-time-five',
        'rowClass' => static fn(array $row): string => empty($row['deleted_at']) ? '' : 'table-danger',
        'endpoint' => 'google-reviews/action/',
        'datatable' => false,
        'collapsible' => ['label' => 'Recent Changes ({count})'],
        'hideWhenEmpty' => false,
        'columns' => [
            $changedByColumn,
            ['key' => 'authorName', 'label' => 'Author', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'ratingStars', 'label' => 'Rating', 'type' => 'raw', 'nowrap' => true],
            ['key' => 'updatedDate', 'label' => 'Updated', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ],

    'deleted' => ListView::deletedPreset([
        'id' => 'google-reviews-deleted',
        'title' => 'Deleted Reviews',
        'noun' => ['item', 'items'],
        'endpoint' => 'google-reviews/action/',
        'hideWhenEmpty' => false,
        'headerButton' => [
            'label' => 'Purge All',
            'icon' => 'bx-trash',
            'class' => 'btn-outline-danger',
            'action' => 'purge_deleted',
            'confirm' => 'Permanently delete all deleted reviews? This cannot be undone!',
            'visible' => fn(array $rows, array $context): bool => (bool) $context['isAdmin'],
        ],
        'columns' => [
            $changedByColumn,
            ['key' => 'authorName', 'label' => 'Author', 'type' => 'text', 'strong' => true, 'fill' => true],
            ['key' => 'ratingStars', 'label' => 'Rating', 'type' => 'raw', 'nowrap' => true],
            ['key' => 'deletedDate', 'label' => 'Deleted', 'type' => 'text', 'small' => true, 'nowrap' => true],
        ],
    ]),
];
