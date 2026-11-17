<?php
/**
 * Favourites (pin) behaviour.
 *
 * Bug: the pin saved to the database, but the "pinned sources" box at the top
 * was rendered from localStorage — which nothing wrote anymore — so a source
 * showed "أُضيف إلى المفضلة" and never appeared in the top box, and the tab
 * counter stayed at 0.
 *
 * Run: php tools/favorites_test.php
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
$ctrl = (string) file_get_contents($root . '/controllers/admin/RssSourcesController.php');
$agg = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
$view = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
$fav = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
$ajax = (string) file_get_contents($root . '/assets/js/admin-ajax.js');
$routes = (string) file_get_contents($root . '/index.php');

echo "--- the server reply carries the id (needed to remove the right card) ---\n";
t('toggle answers with the source id', strpos($ctrl, "'favorite' => (bool) \$now, 'id' => \$cid") !== false);
t('toggle is permission-guarded', strpos($ctrl, "postGuardPermission('sources.manage')") !== false);
t('the route exists', strpos($routes, 'toggle-favorite') !== false);

echo "\n--- the top box is rendered from the database, not localStorage ---\n";
t('favourites are collected from $sources', strpos($view, '$favoriteSources = array();') !== false);
t('it reads is_favorite', strpos($view, "!empty(\$srcRow['is_favorite'])") !== false);
t('chips are printed server-side', strpos($view, 'data-fav-id="<?= (int) $favSrc[\'id\'] ?>"') !== false);
t('chip links to that source', strpos($view, "admin/news-feeds?source_id=' . (int) \$favSrc['id']") !== false);
t('the tab counter starts from the database', strpos($view, 'id="favTabCount"><?= count($favoriteSources) ?>') !== false);

echo "\n--- the old localStorage store is gone ---\n";
t('no localStorage pin key', strpos($view, 'PINNED_STORAGE_KEY') === false);
t('no getPinnedSources', strpos($view, 'getPinnedSources') === false);
t('no renderPinnedSources', strpos($view, 'renderPinnedSources') === false);
t('no dead togglePinSource', strpos($view, 'togglePinSource') === false);

echo "\n--- the top box follows every successful toggle ---\n";
t('a refresh function exists', strpos($view, 'function refreshPinnedUI()') !== false);
t('it is called on load', preg_match("/DOMContentLoaded',\\s*function\\s*\\(\\)\\s*\\{\\s*refreshPinnedUI\\(\\);/s", $view) === 1);
t('it listens to the toggle event', strpos($view, "document.addEventListener('admin:favorite-changed'") !== false);
t('it updates the tab counter', strpos($view, "counter.textContent = String(pinned.length)") !== false);
t('it rebuilds from pinned buttons', strpos($view, ".querySelectorAll('.source-chip-wrapper')") !== false);

echo "\n--- removing from the top chip never reloads the page ---\n";
t('the X uses requestSubmit (fires the AJAX listener)', strpos($view, 'form.requestSubmit()') !== false);
t('the old form.submit() bypass is gone', strpos($view, 'form.submit();') === false);
t('unpin buttons are delegated', strpos($view, "e.target.closest('.js-unpin')") !== false);
t('unpin chips carry the id', strpos($view, 'class="btn btn-link text-danger p-0 ms-1 text-decoration-none js-unpin" data-id="') !== false);

echo "\n--- pin button state is honest ---\n";
t('title/aria are updated with the state', strpos($ajax, "btn.title = on ? 'إزالة من المفضلة' : 'إضافة إلى المفضلة'") !== false);
t('aria-pressed is set', strpos($ajax, "setAttribute('aria-pressed'") !== false);
t('state is reverted on failure', strpos($ajax, 'setPinVisual(wasOn)') !== false);
t('pins start from the database', strpos($view, "!empty(\$src['is_favorite']) ? 'is-pinned'") !== false);

echo "\n--- favourites tab ---\n";
t('the page reads only favourites', strpos($agg, 'WHERE s.is_favorite = 1') !== false);
t('removal targets the toggled source', strpos($fav, "getElementById('pinForm_' + id)") !== false);
t('it no longer relies on focus', strpos($fav, 'document.activeElement') === false);
t('the last removal restores the empty state', strpos($fav, 'window.location.reload()') !== false);

echo "\n--- the publish button publishes (it used to only navigate) ---\n";
t('translate & publish endpoint is wired', strpos($fav, "admin/news-feeds/translate-publish") !== false);
t('fast publish endpoint is wired', strpos($fav, "admin/news-feeds/fast-publish") !== false);
t('draft endpoint is wired', strpos($fav, "admin/news-feeds/draft-article") !== false);
t('the three forms are POST forms', substr_count($fav, 'method="post"') >= 4);
t('item data travels with every form', strpos($fav, '$favPublishFields = function (array $item, string $sourceName)') !== false);
t('all publish fields are included', strpos($fav, "'source_url' => (string) (\$item['link'] ?? '')") !== false
    && strpos($fav, "'source_name' => \$sourceName") !== false
    && strpos($fav, "'featured_image' => (string) (\$item['featured_image'] ?? '')") !== false);
t('the menu offers the three actions', strpos($fav, 'ترجمة ونشر (عربي)') !== false
    && strpos($fav, 'نشر فوري (بدون ترجمة)') !== false
    && strpos($fav, 'تحرير كمسودة') !== false);
t('the publish control is a dropdown button', strpos($fav, 'class="btn btn-sm btn-primary dropdown-toggle"') !== false);
t('the old anchor-only publish button is gone', strpos($fav, 'class="btn btn-sm btn-primary">') === false);
t('favourites now shows publish feedback', strpos($fav, "Session::getFlash('success')") !== false
    && strpos($fav, "Session::getFlash('error')") !== false);
t('both endpoints return to the referring page', substr_count($agg, '$referer = $_SERVER[\'HTTP_REFERER\']') >= 2);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
