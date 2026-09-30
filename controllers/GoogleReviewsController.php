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
        $reviews = $model->listAll();

        $this->data['admin']['reviews'] = \array_map($this->formatReview(...), $reviews);

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

        $needsId = $action !== 'create';
        $missingParams = !$action || ($needsId && !$id);
        if ($missingParams) $this->admin->jsonError('Missing parameters');

        try {
            $result = match ($action) {
                'create' => $this->createReview($input),
                'update' => $this->updateReview(id: $id, input: $input),
                'toggle_featured' => $this->toggleFeatured(id: $id, input: $input),
                'delete' => $this->deleteReview($id),
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

    private function toggleFeatured(int $id, array $input): array
    {
        $model = new GoogleReview(database: $this->osmium->dataSource);
        $review = $model->getById($id);
        if (!$review) throw new \InvalidArgumentException('Review not found');

        $isFeatured = !empty($input['is_featured']);
        $model->toggleFeatured(id: $id, isFeatured: $isFeatured);

        $action = $isFeatured ? 'Featured' : 'Unfeatured';
        $this->logChange(action: $action, id: $id, name: $review['author_name']);

        return ['success' => true, 'id' => $id, 'is_featured' => $isFeatured];
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
        ];
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
