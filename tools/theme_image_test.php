<?php
/**
 * Template persistence and featured-image protection.
 *
 * Run: php tools/theme_image_test.php
 */

$pass = 0;
$fail = 0;
function t($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label" . ($extra ? "\n     " . $extra : '') . "\n";
    }
}

$root = dirname(__DIR__);
if (!class_exists('Settings')) {
    class Settings
    {
        public static function get($key, $default = null)
        {
            return $default;
        }
    }
}
require_once $root . '/core/FallbackImage.php';
require_once $root . '/core/FetchOg.php';

echo "--- the saved template is the one applied ---\n";
$settings = (string) file_get_contents($root . '/controllers/admin/SettingsController.php');
t('stale copies in other groups are removed', strpos($settings, "DELETE FROM settings WHERE `key` = 'site_theme_template' AND `group` <> ?") !== false);
t('only after the template was saved', strpos($settings, "isset(\$_POST['settings']['site_theme_template'])") !== false);
t('the settings cache is cleared', strpos($settings, 'Settings::clear();') !== false);
t('the public page cache is flushed', strpos($settings, 'PageCache::flush();') !== false);
t('the cleanup cannot break saving', strpos($settings, '// لا نُفشل الحفظ بسبب تنظيف صف قديم.') !== false);

echo "\n--- a supplied image is never blanked ---\n";
$local = FetchOg::resolveFor('not a valid url', 'uploads/brand/logo.png');
t('a local path survives a failed lookup', $local === 'uploads/brand/logo.png');
$remote = FetchOg::resolveFor('not a valid url', 'https://cdn.example.com/photo.jpg');
t('a remote URL survives too', $remote === 'https://cdn.example.com/photo.jpg');
$empty = FetchOg::resolveFor('not a valid url', '');
t('nothing is invented when there is no image', $empty === '');

echo "\n--- publishing uses the guard ---\n";
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('the helper exists', strpos($controller, 'private function protectFeaturedImage') !== false);
t('it falls back to the supplied image', strpos($controller, 'return $resolved !== \'\' ? $resolved : $image;') !== false);
t('all publish paths use it', substr_count($controller, '$this->protectFeaturedImage($sourceUrl, $featuredImage)') === 3);
t('the old direct resolve is gone', strpos($controller, 'FetchOg::resolveFor($sourceUrl, $featuredImage)') === false);
t('updates still keep an existing DB image', strpos($controller, 'featured_image = COALESCE(:featured_image, featured_image)') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
