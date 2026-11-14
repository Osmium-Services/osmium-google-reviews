<?php

declare(strict_types=1);

namespace Tests\Services\GoogleReviews;

use Osmium\Services\GoogleReviews\Models\GoogleReview;
use Osmium\Services\GoogleReviews\Models\ReviewsAdminBar;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../models/GoogleReview.php';
require_once __DIR__ . '/../models/ReviewsAdminBar.php';

/**
 * The staff bar's Google reviews answers: a page that showed the widget announces it and gets a "Google reviews" link.
 */
final class ReviewsAdminBarTest extends TestCase
{
    protected function setUp(): void
    {
        $this->shown(false);
    }

    public function test_a_page_without_the_widget_announces_nothing(): void
    {
        $this->assertNull(ReviewsAdminBar::context(['data' => []]));
    }

    public function test_a_page_with_the_widget_announces_it(): void
    {
        $this->shown(true);

        $this->assertSame('google-reviews:featured', ReviewsAdminBar::context(['data' => []]));
    }

    public function test_the_link_opens_the_reviews_list_with_the_services_own_icon(): void
    {
        $links = ReviewsAdminBar::links(['context' => 'google-reviews:featured']);

        $this->assertSame('google-reviews/', $links[0]['path']);
        $this->assertSame('Edit Google reviews', $links[0]['label']);
        $this->assertSame('bxs-star', $links[0]['icon']);
        $this->assertSame(80, $links[0]['order']);
    }

    public function test_other_contexts_get_no_links(): void
    {
        $this->assertNull(ReviewsAdminBar::links(['context' => 'product:5']));
        $this->assertNull(ReviewsAdminBar::links([]));
    }

    /** Sets the flag directly, as listFeatured() would, so no database is needed. */
    private function shown(bool $value): void
    {
        $property = new \ReflectionProperty(GoogleReview::class, 'featuredShown');
        $property->setValue(null, $value);
    }
}
