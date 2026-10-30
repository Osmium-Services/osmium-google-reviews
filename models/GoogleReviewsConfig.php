<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleReviews\Models;

/**
 * Loads this service's own settings file (app/config/services/google-reviews.json.php).
 *
 * Core passes get() to every theme block as $googleReviews (see ServiceLoader::serviceConfigContext),
 * so a theme can print the "leave a review" link without depending on this service's PHP.
 */
class GoogleReviewsConfig
{
    private static ?array $config = null;
    private static string $configPath = 'app/config/services/google-reviews.json.php';

    public static function get(): array
    {
        $configLoaded = self::$config !== null;
        if ($configLoaded) return self::$config;

        self::$config = self::defaults();

        $configExists = \file_exists(self::$configPath);
        if (!$configExists) return self::$config;

        $content = \file_get_contents(self::$configPath);
        $jsonStart = \strpos(haystack: $content, needle: '{');
        $noJsonFound = $jsonStart === false;
        if ($noJsonFound) return self::$config;

        $decoded = \json_decode(\substr(string: $content, offset: $jsonStart), associative: true);
        $saved = \is_array($decoded) ? ($decoded['googleReviews'] ?? []) : [];
        self::$config['writeReviewUrl'] = \trim((string) ($saved['writeReviewUrl'] ?? ''));

        return self::$config;
    }

    public static function clearCache(): void
    {
        self::$config = null;
    }

    public static function defaults(): array
    {
        return ['writeReviewUrl' => ''];
    }
}
