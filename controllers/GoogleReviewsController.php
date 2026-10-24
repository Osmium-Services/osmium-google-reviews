<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleReviews\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\GoogleReviews\Models\GoogleReview;

/**
 * Google Reviews controller - manages the reviews behind a homepage
 * social-proof widget.
 *
 * Reviews are copied by hand from the site's Google Business Profile
 * dashboard, not scraped or pulled via the Places API - see GoogleReview.php.
 *
 * Routes:
 *   - index()  → /admin/google-reviews/
 *   - action() → /admin/google-reviews/action/   (AJAX CRUD)
 */
class GoogleReviewsController extends AdminController
{
    private const RECORD_TYPE = 'google_review';

    // =========================================================================
    // Public Actions
    // =========================================================================

    public function index(): void
    {
        $model = new GoogleReview(database: $this->osmium->dataSource);

        $this->data['admin']['isAdmin'] = $this->isAdmin();
        $this->data['admin']['lists'] = require __DIR__ . '/../lists/google-reviews.php';
        $this->data['admin']['reviews'] = \array_map($this->formatReview(...), $model->listAll());
        $this->data['admin']['recentReviews'] = \array_map($this->formatRecentReview(...), $model->listRecent());
        $this->data['admin']['deletedReviews'] = \array_map($this->formatDeletedReview(...), $model->listDeleted());

        $this->setView('google-reviews/index.phtml');
    }

    public function action()
    {
        \header('Content-Type: application/json');

        $isPost = $this->isPost();
        if (!$isPost) $this->admin->jsonError('Method not allowed');

        $input = $this->admin->auth->getJsonInput();
        $action = $input['action'] ?? '';
        $id = (int) ($input['id'] ?? 0);

        $csrfValid = $this->admin->auth->validateCsrfJson($input);
        if (!$csrfValid) $this->admin->jsonError('Invalid request token. Please refresh and try again.');

        $needsId = !\in_array($action, ['create', 'purge_deleted'], true);
        $missingParams = !$action || ($needsId && !$id);
        if ($missingParams) $this->admin->jsonError('Missing parameters');

        try {
            $result = match ($action) {
                'create' => $this->createReview($input),
                'update' => $this->updateReview(id: $id, input: $input),
                'feature' => $this->setFeatured(id: $id, isFeatured: true),
                'unfeature' => $this->setFeatured(id: $id, isFeatured: false),
                'delete' => $this->deleteReview($id),
                'restore' => $this->restoreReview($id),
                'permanently_delete' => $this->permanentlyDelete($id),
                'purge_deleted' => $this->purgeDeleted(),
                default => throw new \InvalidArgumentException('Unknown action'),
            };

            $this->admin->jsonSuccess($result);
        } catch (\Exception $e) {
            $this->admin->jsonError($e->getMessage());
        }
    }

    // =========================================================================
    // Private Helpers
    // =========================================================================

    private function createReview(array $input): array
    {
        $details = $this->validateDetails($input);
        $model = new GoogleReview(database: $this->osmium->dataSource);

        $newId = $model->create(
            authorName: $details['authorName'],
            rating: $details['rating'],
            reviewText: $details['reviewText'],
            reviewDate: $details['reviewDate'],
            isFeatured: $details['isFeatured'],
            sortOrder: $details['sortOrder'],
        );

        $this->logChange(action: 'Created', id: $newId, name: $details['authorName']);

        return ['success' => true, 'id' => $newId];
    }

    private function updateReview(int $id, array $input): array
    {
        $details = $this->validateDetails($input);
        $model = new GoogleReview(database: $this->osmium->dataSource);

        $existing = $model->getById($id);
        if (!$existing) throw new \InvalidArgumentException('Review not found');

        $model->update(
            id: $id,
            authorName: $details['authorName'],
            rating: $details['rating'],
            reviewText: $details['reviewText'],
            reviewDate: $details['reviewDate'],
            isFeatured: $details['isFeatured'],
            sortOrder: $details['sortOrder'],
        );

        $this->logChange(action: 'Updated', id: $id, name: $details['authorName']);

        return ['success' => true, 'id' => $id];
    }

    private function setFeatured(int $id, bool $isFeatured): array
    {
        $model = new GoogleReview(database: $this->osmium->dataSource);
        $review = $model->getById($id);
        if (!$review) throw new \InvalidArgumentException('Review not found');

        $model->toggleFeatured(id: $id, isFeatured: $isFeatured);

        $this->logChange(action: $isFeatured ? 'Featured' : 'Unfeatured', id: $id, name: $review['author_name']);

        return ['success' => true, 'id' => $id];
    }

    private function deleteReview(int $id): array
    {
        $model = new GoogleReview(database: $this->osmium->dataSource);
        $review = $model->getById($id);
        if (!$review) throw new \InvalidArgumentException('Review not found');

        $model->softDelete($id);

        $this->logChange(action: 'Deleted', id: $id, name: $review['author_name']);

        return ['success' => true, 'id' => $id];
    }

    private function restoreReview(int $id): array
    {
        $model = new GoogleReview(database: $this->osmium->dataSource);
        $review = $model->getAnyById($id);
        if (!$review) throw new \InvalidArgumentException('Review not found');

        $model->restore($id);

        $this->logChange(action: 'Restored', id: $id, name: $review['author_name']);

        return ['success' => true, 'id' => $id];
    }

    private function permanentlyDelete(int $id): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $model = new GoogleReview(database: $this->osmium->dataSource);
        $review = $model->getAnyById($id);
        if (!$review) throw new \InvalidArgumentException('Review not found');

        $this->logChange(action: 'Permanently deleted', id: $id, name: $review['author_name']);
        $model->hardDelete($id);

        return ['success' => true, 'id' => $id];
    }

    private function purgeDeleted(): array
    {
        $notAdmin = !$this->isAdmin();
        if ($notAdmin) throw new \Exception('Unauthorized');

        $model = new GoogleReview(database: $this->osmium->dataSource);
        $count = $model->purgeDeleted();
        $this->admin->model->changelog->log(description: "Purged {$count} deleted review(s)", recordType: self::RECORD_TYPE, recordId: 0);

        return ['success' => true, 'count' => $count];
    }

    /**
     * @return array{authorName: string, rating: int, reviewText: ?string, reviewDate: string, isFeatured: bool, sortOrder: int}
     */
    private function validateDetails(array $input): array
    {
        $authorName = \trim($input['author_name'] ?? '');
        $reviewDate = \trim($input['review_date'] ?? '');
        $rating = (int) ($input['rating'] ?? 0);

        $requiredFieldsMissing = $authorName === '' || $reviewDate === '';
        if ($requiredFieldsMissing) throw new \InvalidArgumentException('Author name and review date are required');

        $ratingOutOfRange = $rating < 1 || $rating > 5;
        if ($ratingOutOfRange) throw new \InvalidArgumentException('Rating must be between 1 and 5');

        $reviewText = \trim($input['review_text'] ?? '');
        $sortOrder = (int) ($input['sort_order'] ?? 0);

        return [
            'authorName' => $authorName,
            'rating' => $rating,
            'reviewText' => $reviewText !== '' ? $reviewText : null,
            'reviewDate' => $reviewDate,
            'isFeatured' => !empty($input['is_featured']),
            'sortOrder' => $sortOrder,
        ];
    }

    private function formatReview(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'authorName' => $row['author_name'],
            'rating' => (int) $row['rating'],
            'reviewText' => $row['review_text'] ?? '',
            'reviewDate' => $row['review_date'],
            'isFeatured' => (bool) $row['is_featured'],
            'sortOrder' => (int) $row['sort_order'],
            'reviewPreview' => $this->preview((string) ($row['review_text'] ?? '')),
            'ratingStars' => $this->stars((int) $row['rating']),
            'featuredBadge' => (bool) $row['is_featured']
                ? '<span class="badge bg-label-success">Featured</span>'
                : '<span class="badge bg-label-secondary">Hidden</span>',
        ];
    }

    private function formatRecentReview(array $row): array
    {
        $changed = \max((string) ($row['updated_at'] ?? ''), (string) ($row['deleted_at'] ?? ''), (string) $row['created_at']);

        return [
            'id' => (int) $row['id'],
            'deleted_at' => $row['deleted_at'] ?? null,
            'authorName' => $row['author_name'],
            'ratingStars' => $this->stars((int) $row['rating']),
            'updatedDate' => \date('j M Y, g:ia', \strtotime($changed)),
            'changed_by' => $row['changed_by'] ?? null,
            'changed_by_avatar' => $row['changed_by_avatar'] ?? null,
        ];
    }

    private function formatDeletedReview(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'authorName' => $row['author_name'],
            'ratingStars' => $this->stars((int) $row['rating']),
            'deletedDate' => \date('d M Y', \strtotime((string) $row['deleted_at'])),
            'changed_by' => $row['changed_by'] ?? null,
            'changed_by_avatar' => $row['changed_by_avatar'] ?? null,
        ];
    }

    /**
     * Plain-text preview: the review text editor (Summernote) wraps saved content in <p> tags and can carry a product link
     */
    private function preview(string $reviewText): string
    {
        $plain = \strip_tags($reviewText);

        return \mb_strlen($plain) > 100 ? \mb_substr($plain, 0, 100) . '…' : $plain;
    }

    private function stars(int $rating): string
    {
        $stars = '';
        for ($i = 1; $i <= 5; $i++) {
            $color = $i <= $rating ? '#ffc107' : '#e0e0e0';
            $stars .= '<i class="bx bxs-star" style="color: ' . $color . '; font-size: 0.9rem;"></i>';
        }

        return $stars;
    }

    private function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    private function logChange(string $action, int $id, string $name): void
    {
        $this->admin->model->changelog->log(
            description: $action . ': ' . $name,
            recordType: self::RECORD_TYPE,
            recordId: $id,
        );
    }
}
