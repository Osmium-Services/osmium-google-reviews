<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleReviews\Models;

/**
 * What the Google reviews service tells the front-end staff bar: that the page showed the review widget
 * (adminbar.context) and the admin link that goes with it (adminbar.links). Themes fetch the featured reviews through
 * GoogleReview::listFeatured(), which notes it; core asks after the page is built, so the answer is current. The
 * widget is curated as one list, so the link opens the list. A supporting link (order 80): after the page's own and
 * any main service link.
 */
class ReviewsAdminBar
{
    /**
     * @param array<string, mixed> $payload the finished page's view data (unused: listFeatured() notes itself)
     * @return string|null "google-reviews:featured" when the page showed the widget, null otherwise
     */
    public static function context(array $payload): ?string
    {
        return GoogleReview::featuredWereShown() ? 'google-reviews:featured' : null;
    }

    /**
     * @param array{context?: string, role?: string} $payload
     * @return array<int, array<string, mixed>>|null
     */
    public static function links(array $payload): ?array
    {
        $isContext = ($payload['context'] ?? '') === 'google-reviews:featured';
        if (!$isContext) return null;

        return [['label' => 'Edit Google reviews', 'icon' => 'bxs-star', 'path' => 'google-reviews/', 'primary' => true, 'order' => 80]];
    }
}
