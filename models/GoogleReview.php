<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleReviews\Models;

use Osmium\Core\Library\OsmiumPDO;
use Osmium\Core\Models\Model;

/**
 * Reviews copied by hand from the site's Google Business Profile - not
 * scraped (violates Google's ToS) and not pulled via the Places API (needs a
 * billing-enabled Google Cloud project). Power a homepage social-proof widget.
 */
class GoogleReview extends Model
{
    protected ?string $sqlDir = __DIR__ . '/sql';

    public function __construct(OsmiumPDO $database)
    {
        parent::__construct(database: $database, tableName: 'google_reviews');
    }

    /**
     * List every review for admin, featured first then by sort order (excludes soft-deleted)
     */
    public function listAll(): array
    {
        $sql = $this->loadSqlFile('google-review-list-all.sql');
        $this->database->query($sql);

        return $this->database->resultset();
    }

    /**
     * List featured reviews for the homepage widget, in display order
     */
    public function listFeatured(int $limit): array
    {
        $sql = $this->loadSqlFile('google-review-list-featured.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':limit', value: $limit);

        return $this->database->resultset();
    }

    public function getById(int $id): ?array
    {
        $sql = $this->loadSqlFile('google-review-get-by-id.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $result = $this->database->single();

        return $result ?: null;
    }

    public function create(
        string $authorName,
        int $rating,
        ?string $reviewText,
        string $reviewDate,
        bool $isFeatured,
        int $sortOrder,
    ): int {
        $sql = $this->loadSqlFile('google-review-create.sql');
        $this->database->query($sql);
        $this->bindDetails(
            authorName: $authorName,
            rating: $rating,
            reviewText: $reviewText,
            reviewDate: $reviewDate,
            isFeatured: $isFeatured,
            sortOrder: $sortOrder,
        );
        $this->database->execute();

        return (int) $this->database->lastInsertId();
    }

    public function update(
        int $id,
        string $authorName,
        int $rating,
        ?string $reviewText,
        string $reviewDate,
        bool $isFeatured,
        int $sortOrder,
    ): void {
        $sql = $this->loadSqlFile('google-review-update.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->bindDetails(
            authorName: $authorName,
            rating: $rating,
            reviewText: $reviewText,
            reviewDate: $reviewDate,
            isFeatured: $isFeatured,
            sortOrder: $sortOrder,
        );
        $this->database->execute();
    }

    /**
     * Flip featured status on its own, so the list view's pill toggle never
     * rewrites any of the review's other fields.
     */
    public function toggleFeatured(int $id, bool $isFeatured): void
    {
        $sql = $this->loadSqlFile('google-review-toggle-featured.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->database->bind(param: ':is_featured', value: $isFeatured ? 1 : 0);
        $this->database->execute();
    }

    public function softDelete(int $id): void
    {
        $sql = $this->loadSqlFile('google-review-soft-delete.sql');
        $this->database->query($sql);
        $this->database->bind(param: ':id', value: $id);
        $this->database->execute();
    }

    private function bindDetails(
        string $authorName,
        int $rating,
        ?string $reviewText,
        string $reviewDate,
        bool $isFeatured,
        int $sortOrder,
    ): void {
        $this->database->bind(param: ':author_name', value: $authorName);
        $this->database->bind(param: ':rating', value: $rating);
        $this->database->bind(param: ':review_text', value: $reviewText);
        $this->database->bind(param: ':review_date', value: $reviewDate);
        $this->database->bind(param: ':is_featured', value: $isFeatured ? 1 : 0);
        $this->database->bind(param: ':sort_order', value: $sortOrder);
    }
}
