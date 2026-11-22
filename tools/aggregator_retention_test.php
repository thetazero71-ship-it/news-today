<?php
/**
 * Aggregator retention (48h cache) + home load-more + in-place feed switching.
 *
 * Run: php tools/aggregator_retention_test.php
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
require_once $root . '/core/FeedCache.php';

echo "--- the cache keeps quiet sources alive for 48h ---\n";
$tmp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'tnp_feedcache_' . bin2hex(random_bytes(4));
$cache = new FeedCache($tmp);
$items = [
    ['title' => 'خبر تجريبي', 'link' => 'https://example.com/a', 'pubDate' => '2026-11-20 10:00'],
    ['title' => 'خبر ثانٍ', 'link' => 'https://example.com/b', 'pubDate' => '2026-11-20 11:00'],
];
t('an empty cache returns null', $cache->get('https://example.com/feed') === null);
t('saving succeeds', $cache->put('https://example.com/feed', $items) === true);
$hit = $cache->get('https://example.com/feed');
t('the saved items come back', is_array($hit) && count($hit['items']) === 2);
t('the fetch time is stamped', is_array($hit) && $hit['fetched_at'] > time() - 5);
t('empty item lists are not cached', $cache->put('https://example.com/empty', []) === false);
t('the TTL is 48 hours', FeedCache::TTL === 172800);

echo "\n--- normalisation keeps one file per feed ---\n";
$cache->put('http://www.Example.com/feed/', $items);
t('scheme, www and trailing slash collapse', $cache->path('https://example.com/feed') === $cache->path('http://www.Example.com/feed/'));
t('the path is a sha1 json file', (bool) preg_match('/[0-9a-f]{40}\.json$/', $cache->path('https://example.com/feed')));

echo "\n--- expired copies are removed, not served ---\n";
$staleUrl = 'https://example.com/stale-feed';
$stalePath = $cache->path($staleUrl);
file_put_contents($stalePath, json_encode(['url' => $staleUrl, 'fetched_at' => time() - FeedCache::TTL - 30, 'items' => $items]));
t('an expired copy is refused', $cache->get($staleUrl) === null);
t('and its file is gone', !is_file($stalePath));

$corrupt = $cache->path('https://example.com/corrupt');
file_put_contents($corrupt, '{not json');
t('corrupt json is refused', $cache->get('https://example.com/corrupt') === null);

$old = $cache->path('https://example.com/prune-old');
file_put_contents($old, json_encode(['url' => 'x', 'fetched_at' => time() - FeedCache::TTL - 60, 'items' => $items]));
@touch($old, time() - FeedCache::TTL - 60);
$fresh = $cache->path('https://example.com/prune-fresh');
$cache->put('https://example.com/prune-fresh', $items);
t('prune deletes only expired files', $cache->prune() >= 1 && is_file($fresh) && !is_file($old));

echo "\n--- the controller falls back instead of failing ---\n";
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
$aggView = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
$favView = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('the controller owns a cache', strpos($controller, 'private function feedCache(): FeedCache') !== false);
t('a failed fetch reads the cache', strpos($controller, '$cached = $this->feedCache()->get($url);') !== false);
t('the cached response is marked', strpos($controller, "'from_cache' => true") !== false);
t('a successful fetch is stored', strpos($controller, '$this->feedCache()->put($url, $items);') !== false);
t('the aggregator warns when serving cache', strpos($aggView, 'آخر نسخة محفوظة') !== false);
t('the warning shows the cache time', strpos($aggView, "fmt_date(\$cachedAt, 'Y-m-d H:i')") !== false);
t('favorites marks cached sources', strpos($favView, "!empty(\$group['from_cache'])") !== false);
t('favorites sets the flag per group', strpos($controller, "'from_cache' => !empty(\$feed['from_cache'])") !== false);

echo "\n--- feed switching happens in place ---\n";
t('the grid has a stable id', strpos($aggView, 'id="newsFeedGrid"') !== false);
t('the active source has a stable id', strpos($aggView, 'id="activeSourceBadge"') !== false);
t('clicks are intercepted', strpos($aggView, ".closest('.source-chip-link, .pinned-source-chip a')") !== false);
t('the response is parsed without a reload', strpos($aggView, "new DOMParser().parseFromString(html, 'text/html')") !== false);
t('the URL is pushed', strpos($aggView, 'window.history.pushState({ feed: url }') !== false);
t('back/forward is handled', strpos($aggView, "window.addEventListener('popstate'") !== false);
t('pending requests are aborted', strpos($aggView, 'currentRequest.abort()') !== false);
t('it degrades to a normal link', strpos($aggView, 'window.location.href = url;') !== false);
t('the active chip is repainted', strpos($aggView, 'function setActiveChip') !== false);

echo "\n--- home load-more endpoint ---\n";
$index = (string) file_get_contents($root . '/index.php');
$home = (string) file_get_contents($root . '/controllers/HomeController.php');
$model = (string) file_get_contents($root . '/models/Article.php');
$homeView = (string) file_get_contents($root . '/views/home.php');
$partial = (string) file_get_contents($root . '/views/partials/home-news-cards.php');
$js = (string) file_get_contents($root . '/assets/js/tech-platform.js');
t('the route exists', strpos($index, "'/home/load-more', 'HomeController@loadMore'") !== false);
t('the action exists', strpos($home, 'public function loadMore()') !== false);
t('it answers with json', strpos($home, "'Content-Type: application/json; charset=utf-8'") !== false);
t('it compensates for the hidden featured card', strpos($home, '$offset = max(0, ($page - 1) * $perPage - 1);') !== false);
t('it asks for one extra row to detect more', strpos($home, 'getLatest($perPage + 1, $offset)') !== false);
t('the query supports offsets', strpos($model, 'public function getLatest($limit = 10, $offset = 0)') !== false
    && strpos($model, '" OFFSET " . $offset') !== false);
t('the card markup is shared', strpos($homeView, "require APP_ROOT . '/views/partials/home-news-cards.php'") !== false
    && $partial !== '');
t('the grid has a stable id', strpos($homeView, 'id="homeNewsGrid"') !== false);
t('the button carries the next page', strpos($homeView, 'id="homeLoadMoreBtn" data-next-page="2"') !== false);
t('the script fetches the endpoint', strpos($js, "'/home/load-more?page='") !== false);
t('new cards are appended', strpos($js, "grid.insertAdjacentHTML('beforeend', data.html)") !== false);
t('the button disappears at the end', strpos($js, "document.getElementById('homeLoadMoreWrap')") !== false);

// cleanup
foreach (glob($tmp . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($tmp);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
