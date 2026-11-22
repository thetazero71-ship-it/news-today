<?php
/**
 * Favorites rapid publishing deck (reference-aligned).
 *
 * Run: php tools/favorites_deck_test.php
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
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
$view = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
$adminCss = (string) file_get_contents($root . '/assets/css/admin-bootstrap.css');

echo "--- fetching and sorting ---\n";
t('up to 30 items are examined', strpos($controller, 'array_slice($feed[\'items\'] ?? array(), 0, 30)') !== false);
t('unpublished and published are split', strpos($controller, '$unpubItems = array();') !== false
    && strpos($controller, '$pubItems = array();') !== false);
t('unpublished comes first', strpos($controller, '$sorted = array_merge($unpubItems, $pubItems);') !== false);
t('all unpublished are kept even beyond the limit', strpos($controller, 'max($perSource, count($unpubItems))') !== false);
t('the default is 8 per source', strpos($controller, "\$_GET['per'] ?? 8") !== false);
t('totals are computed', strpos($controller, '$totalNew++') !== false
    && strpos($controller, '$totalPublished++') !== false
    && strpos($controller, '$totalAll++') !== false);
t('the view receives all three totals', strpos($controller, "'totalNew' => \$totalNew") !== false
    && strpos($controller, "'totalPublished' => \$totalPublished") !== false
    && strpos($controller, "'totalAll' => \$totalAll") !== false);
t('each source counts its own new/published', strpos($controller, "'new_count' => \$sourceNew") !== false
    && strpos($controller, "'published_count' => \$sourcePub") !== false);

echo "\n--- the deck is scrollable ---\n";
t('the card body is the scroll container', strpos($view, 'fav-items-scrollable') !== false);
t('it is capped at 480px', strpos($view, 'max-height: 480px') !== false);
t('the scrollbar shape comes from the admin stylesheet', strpos($adminCss, '::-webkit-scrollbar-thumb') !== false);

echo "\n--- explicit publishing buttons ---\n";
t('translate & publish button', strpos($view, 'btn-fav-translate') !== false
    && strpos($view, 'ترجمة ونشر') !== false);
t('fast publish button', strpos($view, 'نشر فوري') !== false);
t('source link button', strpos($view, 'المصدر') !== false);
t('three publish forms', substr_count($view, 'fav-publish-form') === 3);
t('the AJAX endpoint header is used', strpos($view, "'X-Requested-With': 'XMLHttpRequest'") !== false);
t('published items swap to edit + source', strpos($view, 'fav-actions-col') !== false
    && strpos($view, "data.edit_url") !== false);

echo "\n--- live counters and sorting after publish ---\n";
t('global live counters exist', strpos($view, 'favLiveNewCount') !== false
    && strpos($view, 'favLivePubCount') !== false
    && strpos($view, 'favLiveTotalCount') !== false);
t('per-source counters exist', strpos($view, 'source-badge-new') !== false
    && strpos($view, 'source-badge-pub') !== false);
t('published rows move to the bottom', strpos($view, 'parentList.appendChild(itemLi)') !== false);
t('a green flash marks the published row', strpos($view, 'just-published-flash') !== false);
t('the publisher and exact time are injected', strpos($view, 'publisher-info-badge') !== false
    && strpos($view, 'publish-time-badge') !== false);

echo "\n--- filtering and search ---\n";
t('status filter buttons exist', strpos($view, 'filterFavByStatus') !== false
    && substr_count($view, 'data-filter=') >= 3);
t('items expose data-status', strpos($view, "data-status=\"<?= \$published ? 'published' : 'new' ?>\"") !== false);
t('live search exists', strpos($view, 'filterFavNewsLive') !== false
    && strpos($view, 'favSearchInput') !== false);

echo "\n--- the admin scrollbar is the capsule-on-rail style ---\n";
$adminCss = (string) file_get_contents($root . '/assets/css/admin-bootstrap.css');
t('the admin rail is centred and 3px', strpos($adminCss, 'calc(50% - 1.5px)') !== false
    && strpos($adminCss, 'calc(50% + 1.5px)') !== false);
t('the admin rail is deep black', strpos($adminCss, '#0a0a0a calc(50% - 1.5px)') !== false);
t('the admin thumb is a capsule', strpos($adminCss, 'border-radius: 9999px;') !== false
    && strpos($adminCss, 'min-height: 72px;') !== false);
t('the admin scrollbar is 12px wide', strpos($adminCss, '::-webkit-scrollbar { width: 12px; height: 12px; }') !== false);
t('inline favorites styles no longer override it', strpos($view, '.fav-items-scrollable::-webkit-scrollbar') === false);
$adminLayout = (string) file_get_contents($root . '/views/layouts/admin.php');
t('the admin stylesheet is cache-busted', strpos($adminLayout, "admin-bootstrap.css') . '?v='") !== false);
t('the admin sidebar uses the same capsule rail', strpos($adminLayout, '.sidebar-nav::-webkit-scrollbar-track') !== false
    && strpos($adminLayout, 'calc(50% - 1.5px)') !== false);

echo "\n--- pinned box sits below all sources ---\n";
$aggView = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
$chipsPos = strpos($aggView, 'id="sourcesChipsGrid"');
$pinnedPos = strpos($aggView, 'id="pinnedSourcesSection"');
t('the chips grid exists', $chipsPos !== false);
t('the pinned box exists', $pinnedPos !== false);
t('pinned comes after the chips', $chipsPos !== false && $pinnedPos !== false && $pinnedPos > $chipsPos);
t('the old top placement marker is gone', strpos($aggView, 'Pinned Sources Top Section') === false);
t('the favorites tab counter exists', strpos($aggView, 'id="favTabCount"') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
