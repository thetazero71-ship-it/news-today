<?php
/**
 * SEO/social links: absolute image URLs must not be prefixed twice.
 *
 * Run: php tools/seo_links_test.php
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
require_once $root . '/core/AppUrl.php';

$base = 'https://news.example.com';

echo "--- relative paths are joined once ---\n";
t('the bare base is returned', AppUrl::make('', $base) === $base);
t('a relative path is joined', AppUrl::make('article/hello', $base) === $base . '/article/hello');
t('a leading slash is normalised', AppUrl::make('/uploads/a.jpg', $base) === $base . '/uploads/a.jpg');
t('nested paths survive', AppUrl::make('assets/css/site.css', $base) === $base . '/assets/css/site.css');

echo "\n--- absolute images pass through untouched ---\n";
t('https images are untouched', AppUrl::make('https://cdn.example.com/a.jpg', $base) === 'https://cdn.example.com/a.jpg');
t('http images are untouched', AppUrl::make('http://cdn.example.com/a.jpg', $base) === 'http://cdn.example.com/a.jpg');
t('protocol-relative URLs are untouched', AppUrl::make('//cdn.example.com/a.jpg', $base) === '//cdn.example.com/a.jpg');
t('absolute detection agrees', AppUrl::isAbsolute('https://x/y') && !AppUrl::isAbsolute('uploads/a.jpg'));

echo "\n--- the platform uses it everywhere ---\n";
$index = (string) file_get_contents($root . '/index.php');
$header = (string) file_get_contents($root . '/views/partials/header.php');
$controller = (string) file_get_contents($root . '/controllers/ArticleController.php');
t('app_url delegates to AppUrl', strpos($index, 'return AppUrl::make($path, $base);') !== false);
t('canonical ignores the query string', strpos($header, "parse_url(\$_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH)") !== false);
t('og:image falls back to a real file', strpos($header, "app_url('assets/images/icon-512.png')") !== false);
t('og:image is only prefixed when relative', strpos($header, "preg_match('#^https?://#i', \$ogImage)") !== false);
t('the shortlink still 301s to the slug', strpos($controller, "header('Location: ' . app_url('article/' . rawurlencode(\$article['slug'])), true, 301)") !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
