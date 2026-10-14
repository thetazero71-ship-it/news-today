<?php
/**
 * Feeds page: published detection + favourites tab.
 * Run: php tools/feeds_page_test.php
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
$agg = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
$view = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
$workflow = (string) file_get_contents($root . '/.github/workflows/auto-fetch-news.yml');

echo "--- an auto-published item shows up as published ---\n";
t('feed urls are normalised before matching', strpos($agg, 'normaliseItemUrl') !== false);
t('the map is keyed by the normalised url', strpos($agg, "\$key = \$this->normaliseItemUrl((string) \$row['source_url'])") !== false);
t('items look up the normalised key', strpos($agg, "\$urlStatusMap[\$key] ?? null") !== false);
t('the article id travels with the status', strpos($agg, "\$urlStatusMap[\$key . '#id']") !== false);
t('tracking parameters are stripped', strpos($agg, 'utm_') !== false && strpos($agg, 'fbclid') !== false);
t('scheme/www/slash are normalised', strpos($agg, "#^www\\.#i") !== false && strpos($agg, "rtrim(\$url, '/')") !== false);
t('manual publishing also finds a known article', strpos($agg, 'findArticleByNormalisedUrl') !== false);

echo "\n--- the edit button has something to point at ---\n";
t('the card renders an edit link', strpos($view, "admin/articles/' . (int) \$item['article_id'] . '/edit'") !== false);
t('and after an ajax publish', strpos($view, "edit = ' <a class=\"btn btn-sm btn-outline-primary") !== false);
t('the published badge is shown', strpos($view, 'منشور للعامة') !== false);

echo "\n--- favourites tab ---\n";
t('the two tabs are real links', strpos($view, 'admin/news-feeds?tab=favorites') !== false);
t('the all-sources tab links back', strpos($view, "app_url('admin/news-feeds')") !== false);
t('pinning posts to the server', strpos($view, 'pinForm_') !== false && strpos($view, 'toggle-favorite') !== false);
t('the counter is refreshed from the saved list', strpos($view, 'favTabCount') !== false);
t('the dead client filter is gone', strpos($view, 'switchSourceTab') === false && strpos($view, "chip.style.display = show") === false);
t('the pinned list still highlights chips', strpos($view, 'renderPinnedSources') !== false);

$favView = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('a full favourites page exists', strlen($favView) > 800);
t('it groups sources with their items', strpos($favView, 'foreach ($groups as $group)') !== false);
t('it shows the published state', strpos($favView, 'منشور') !== false);
t('it offers editing a published item', strpos($favView, "/edit'") !== false);
t('it explains an empty tab', strpos($favView, 'لا يوجد مصدر في المفضلة') !== false);
t('it reports a dead feed', strpos($favView, 'تعذّر جلب الخلاصة') !== false);
t('items per source is adjustable', strpos($favView, 'per=') !== false);
t('a favourite can be removed from there', strpos($favView, 'toggle-favorite') !== false);

$aggCtrl = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('the controller builds it', strpos($aggCtrl, 'function favoritesView') !== false);
t('it reads only favourite sources', strpos($aggCtrl, 'WHERE s.is_favorite = 1') !== false);
t('the tab is routed through index', strpos($aggCtrl, "'favorites'") !== false);

echo "\n--- the schedule is as fast as GitHub allows ---\n";
t('runs every 5 minutes', strpos($workflow, "*/5 * * * *") !== false);
t('only one run at a time', strpos($workflow, 'concurrency:') !== false);
t('it runs on GitHub, not the host', strpos($workflow, 'SITE_URL') !== false && strpos($workflow, 'curl') !== false);

echo "\n--- broken-host suggestions ---\n";
require_once $root . '/core/FeedFetcher.php';
$alts = FeedFetcher::suggestAlternatives('https://alalam.ir/rss/latest');
t('the www variant of the same path comes first', ($alts[0] ?? '') === 'https://www.alalam.ir/rss/latest');
t('the apex variant is also offered', in_array('https://alalam.ir/rss/latest', $alts, true));
$altsWww = FeedFetcher::suggestAlternatives('https://www.alalam.ir/rss/latest');
t('no duplicate hosts collapse', count($altsWww) === count(array_unique($altsWww)));
$altsRoot = FeedFetcher::suggestAlternatives('https://example.com');
t('a bare host still gets generic guesses', in_array('https://example.com/feed/', $altsRoot, true));
t('empty host yields nothing', FeedFetcher::suggestAlternatives('not-a-url') === []);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);