<?php

declare(strict_types=1);

namespace Osmium\Services\GoogleReviews\Controllers;

use Osmium\Modules\Admin\Core\AdminController;
use Osmium\Services\GoogleReviews\Models\GoogleReviewsConfig;

/**
 * Google Reviews settings: a full-page form POST/redirect flow.
 *
 * Routes:
 *   - index() → /admin/settings/google-reviews/  (GET shows the form, POST saves it)
 */
class GoogleReviewsSettingsController extends AdminController
{
    private const CONFIG_FILE_PATH = 'app/config/services/google-reviews.json.php';

    public function index(): void
    {
        $isPost = $_SERVER['REQUEST_METHOD'] === 'POST';
        if ($isPost) $this->handleSubmit();

        $config = GoogleReviewsConfig::get();
        $this->data['admin']['writeReviewUrl'] = $config['writeReviewUrl'];
        $this->data['admin']['settingsSaved'] = $_SESSION['google_reviews_saved'] ?? false;
        $this->data['admin']['settingsError'] = $_SESSION['google_reviews_error'] ?? false;
        unset($_SESSION['google_reviews_saved'], $_SESSION['google_reviews_error']);

        $this->setView('google-reviews/settings.phtml');
    }

    private function handleSubmit(): void
    {
        $csrfValid = $this->admin->auth->validateCsrf();
        if (!$csrfValid) {
            $_SESSION['google_reviews_error'] = 'Invalid form submission. Please try again.';
            $this->redirect('settings/google-reviews/');
        }

        $current = GoogleReviewsConfig::get()['writeReviewUrl'];
        $value = \trim((string) ($_POST['write_review_url'] ?? ''));
        $isEmpty = $value === '';
        $isHttp = (bool) \preg_match('#^https://[^\s]+$#i', $value);

        if (!$isEmpty && !$isHttp) {
            $_SESSION['google_reviews_error'] = 'The review link must be a full link starting with https:// - kept the previous value.';
            $this->redirect('settings/google-reviews/');
        }

        $this->saveConfig($isEmpty ? '' : $value);
        $this->admin->model->changelog->log(description: 'Updated Google Reviews settings', recordType: 'settings');
        GoogleReviewsConfig::clearCache();

        $_SESSION['google_reviews_saved'] = true;
        $this->redirect('settings/google-reviews/');
    }

    private function saveConfig(string $writeReviewUrl): void
    {
        $dir = \dirname(self::CONFIG_FILE_PATH);
        $dirExists = \is_dir($dir);
        if (!$dirExists) \mkdir(directory: $dir, permissions: 0755, recursive: true);

        $json = \json_encode(
            value: ['googleReviews' => ['writeReviewUrl' => $writeReviewUrl]],
            flags: JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE,
        );

        \file_put_contents(self::CONFIG_FILE_PATH, "<?php exit(); ?>\n" . $json . "\n");
    }
}
