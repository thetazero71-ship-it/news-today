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
require_once $root . '/core/NewsDeduplicator.php';

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
t('a fresh copy is returned without network', is_array($cache->getNewer('https://example.com/feed', 300)));

$oldFreshUrl = 'https://example.com/old-fresh';
$cache->put($oldFreshUrl, $items);
$oldPath = $cache->path($oldFreshUrl);
$tmpData = json_decode((string) file_get_contents($oldPath), true);
$tmpData['fetched_at'] = time() - 400;
file_put_contents($oldPath, json_encode($tmpData));
t('an older copy is not "fresh"', $cache->getNewer($oldFreshUrl, 300) === null && $cache->get($oldFreshUrl) !== null);
t('the dedupe cache can be reset', method_exists('NewsDeduplicator', 'clearCache'));

echo "\n--- the cache accumulates instead of replacing ---\n";
$accUrl = 'https://example.com/acc';
$cache->accumulate($accUrl, [
    ['title' => 'older', 'link' => 'https://example.com/a', 'pubDate' => '2026-11-20 08:00'],
    ['title' => 'newer', 'link' => 'https://example.com/b', 'pubDate' => '2026-11-21 08:00'],
]);
$acc = $cache->accumulate($accUrl, [
    ['title' => 'brand new', 'link' => 'https://example.com/c', 'pubDate' => '2026-11-22 08:00'],
    ['title' => 'newer again', 'link' => 'https://example.com/b', 'pubDate' => '2026-11-21 09:00'],
]);
t('items from both fetches are kept', count($acc) === 3);
t('the newest item is first', $acc[0]['link'] === 'https://example.com/c');
$hitAcc = $cache->get($accUrl);
t('first_seen_at is stamped', !empty($hitAcc['items'][0]['first_seen_at']));
t('a disappeared item inside 48h survives', count($hitAcc['items']) === 3);

$oldAcc = $cache->path('https://example.com/acc-old');
file_put_contents($oldAcc, json_encode([
    'url' => 'x',
    'fetched_at' => time(),
    'items' => [
        ['title' => 'very old', 'link' => 'https://example.com/old', 'pubDate' => '2026-11-01 08:00', 'first_seen_at' => time() - FeedCache::TTL - 60],
    ],
]));
$afterOld = $cache->accumulate('https://example.com/acc-old', [
    ['title' => 'fresh', 'link' => 'https://example.com/fresh', 'pubDate' => '2026-11-22 08:00'],
]);
t('an expired disappeared item is dropped', count($afterOld) === 1 && $afterOld[0]['link'] === 'https://example.com/fresh');

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
t('a successful fetch is accumulated', strpos($controller, '$this->feedCache()->accumulate($url, $items);') !== false);
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
t('it accepts an explicit offset', strpos($home, '$_GET[\'offset\']') !== false
    && strpos($home, 'max(0, (int) $_GET[\'offset\'])') !== false);
t('it asks for one extra row to detect more', strpos($home, 'getLatest($perPage + 1, $offset)') !== false);
t('the query supports offsets', strpos($model, 'public function getLatest($limit = 10, $offset = 0)') !== false
    && strpos($model, '" OFFSET " . $offset') !== false);
t('the card markup is shared', strpos($homeView, "require APP_ROOT . '/views/partials/home-news-cards.php'") !== false
    && $partial !== '');
t('the classic grid has a stable id', strpos($homeView, 'id="homeNewsGrid"') !== false);
t('the bento grid has a stable id', strpos($homeView, 'id="homeBentoGrid"') !== false);
t('the default grid has a stable id', strpos($homeView, 'id="homeDefaultGrid"') !== false);
t('the button carries the next offset', strpos($homeView, 'data-next-offset=') !== false);
t('the button carries the active template', strpos($homeView, 'data-template=') !== false);
t('the script fetches the endpoint', strpos($js, "'/home/load-more?offset='") !== false);
t('the endpoint renders the bento partial', strpos($home, "'home-news-cards-bento'") !== false);
t('the endpoint renders the default partial', strpos($home, "'home-news-cards-default'") !== false);
t('new cards are appended', strpos($js, "grid.insertAdjacentHTML('beforeend', data.html)") !== false);
t('the button disappears at the end', strpos($js, "document.getElementById('homeLoadMoreWrap')") !== false);

// cleanup
foreach (glob($tmp . '/*') ?: [] as $f) { @unlink($f); }
@rmdir($tmp);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
