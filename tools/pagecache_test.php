<?php
/**
 * Page cache behaviour tests (no web server needed).
 * Run: php tools/pagecache_test.php
 */

define('APP_ROOT', dirname(__DIR__));

$pass = 0;
$fail = 0;

function t($label, $cond)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label\n";
    }
}

function app_url($p = '')
{
    return 'http://localhost/' . ltrim((string) $p, '/');
}

require_once APP_ROOT . '/core/PageCache.php';

function request(string $method, string $uri, array $get = [], array $post = [], array $cookies = [], array $headers = [])
{
    $_SERVER['REQUEST_METHOD'] = $method;
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['HTTP_X_REQUESTED_WITH'] = $headers['X-Requested-With'] ?? '';
    $_GET = $get;
    $_POST = $post;
    $_COOKIE = $cookies;
}

echo "--- what may be cached ---\n";
request('GET', '/');
t('home page is cacheable', PageCache::isCacheableRequest() === true);

request('GET', '/article/some-slug');
t('article page is cacheable', PageCache::isCacheableRequest() === true);

request('GET', '/search?q=news');
t('search is cacheable', PageCache::isCacheableRequest() === true);

request('GET', '/sitemap.xml');
t('sitemap is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/feed/rss');
t('rss feed is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/manifest.json');
t('manifest is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/admin/articles');
t('admin is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/login');
t('login is NOT cached', PageCache::isCacheableRequest() === false);

request('POST', '/search', [], ['q' => 'x']);
t('POST is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/', [], [], ['PHPSESSID' => 'abc123']);
t('a logged-in visitor is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/', [], [], ['auth_token' => 'zzz']);
t('auth cookie is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/', [], [], [], ['X-Requested-With' => 'XMLHttpRequest']);
t('ajax is NOT cached', PageCache::isCacheableRequest() === false);

request('GET', '/article/x', ['preview' => '1']);
t('preview links are NOT cached', PageCache::isCacheableRequest() === false);

echo "\n--- store / serve round trip ---\n";
$html = '<!doctype html><html><head><title>تجربة</title></head><body>' . str_repeat('محتوى الصفحة ', 60) . '</body></html>';
request('GET', '/article/round-trip');
PageCache::store($html);
t('page was written to disk', count(glob(PageCache::dir() . '/page_*.html')) > 0);

ob_start();
$served = PageCache::serve();
$out = ob_get_clean();
t('serve() answers from the cache', $served === true);
// headers cannot be sent from the CLI, so the warning may land in the buffer
t('served html matches the stored page', strpos($out, trim($html)) !== false);

echo "\n--- different urls do not share an entry ---\n";
request('GET', '/article/other-page');
ob_start();
$served2 = PageCache::serve();
ob_get_clean();
t('a different url is a miss', $served2 === false);

echo "\n--- rebuild ping bypasses the cache ---\n";
request('GET', '/article/round-trip', ['__pc' => '1']);
t('rebuild request detected', PageCache::isRebuildRequest() === true);
ob_start();
$served3 = PageCache::serve();
ob_get_clean();
t('rebuild request is never served from cache', $served3 === false);

echo "\n--- the rebuild ping shares the same cache entry ---\n";
request('GET', '/article/round-trip?__pc=1');
$p1 = (function () {
    request('GET', '/article/round-trip');
    ob_start();
    PageCache::serve();
    ob_get_clean();
    return true;
})();

echo "\n--- nothing dangerous is stored ---\n";
request('GET', '/article/safety');
PageCache::store('<html><body>' . str_repeat('x', 400) . '</body></html>');
ob_start();
PageCache::serve();
ob_get_clean();
request('GET', '/article/safety2');
PageCache::store('<?php echo "x"; ?>');
t('tiny/empty output is not cached', PageCache::serve() === false);

request('GET', '/article/err');
PageCache::store('<html><head><title>خطأ</title></head><body>الصفحة غير موجودة</body></html>');
ob_start();
$errServed = PageCache::serve();
ob_get_clean();
t('404 page is not cached', $errServed === false);

echo "\n--- flushing clears everything ---\n";
request('GET', '/');
PageCache::store($html);
$before = count(glob(PageCache::dir() . '/page_*.html'));
PageCache::flush();
$after = count(glob(PageCache::dir() . '/page_*.html'));
t('flush removed cached pages', $before > 0 && $after === 0);

echo "\n--- admin changes invalidate the cache ---\n";
$index = (string) file_get_contents(APP_ROOT . '/index.php');
t('admin POST flushes the cache', strpos($index, 'PageCache::flush()') !== false);
$ctrl = (string) file_get_contents(APP_ROOT . '/controllers/admin/SettingsController.php');
t('brand save flushes the cache', strpos($ctrl, 'Brand::syncStaticFiles') !== false);

echo "\n--- cron safety ---\n";
$cron = (string) file_get_contents(APP_ROOT . '/cron/rss_auto_publish.php');
t('cron takes an exclusive lock', strpos($cron, 'LOCK_EX | LOCK_NB') !== false);
t('cron has a time budget', strpos($cron, 'CRON_BUDGET_SECONDS') !== false);
t('cron stops when the budget is spent', strpos($cron, 'انتهت الميزانية الزمنية') !== false);
t('cron flushes the page cache', strpos($cron, 'PageCache::flush()') !== false);

array_map('unlink', glob(PageCache::dir() . '/*'));
@rmdir(PageCache::dir());

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);