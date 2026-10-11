<?php
/**
 * Admin interactions must never navigate away or reload the page.
 * Run: php tools/admin_ajax_test.php
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
$ajax = (string) file_get_contents($root . '/assets/js/admin-ajax.js');
$layout = (string) file_get_contents($root . '/views/layouts/admin.php');
$sources = (string) file_get_contents($root . '/controllers/admin/RssSourcesController.php');
$cats = (string) file_get_contents($root . '/controllers/admin/CategoriesController.php');

echo "--- the library is loaded everywhere ---\n";
t('the admin layout loads it', strpos($layout, 'admin-ajax.js') !== false);
t('it is loaded after the toast helper', strpos($layout, 'admin-toast.js') < strpos($layout, 'admin-ajax.js'));
t('the shared poster exists', strpos($ajax, 'window.postForm') !== false);
t('it sends the ajax marker', strpos($ajax, "X-Requested-With': 'XMLHttpRequest'") !== false);
t('it never navigates the browser', strpos($ajax, 'location.href =') === false
  && strpos($ajax, 'location.assign(') === false
  && strpos($ajax, 'location.replace(') === false);
t('it only reads the current url', (bool) preg_match('/form\.action\s*\|\|\s*window\.location\.href/', $ajax));
t('it never reloads', strpos($ajax, 'location.reload') === false);

echo "\n--- pin button: no navigation ---\n";
t('pin forms are bound', strpos($ajax, 'form[id^="pinForm_"]') !== false);
t('the default submit is cancelled', strpos($ajax, 'e.preventDefault()') !== false);
t('the icon flips immediately', strpos($ajax, 'bi-pin-angle-fill text-warning') !== false);
t('a failure is rolled back', strpos($ajax, "btn.classList.toggle('is-pinned', wasOn)") !== false);
t('the pin button appears in the feeds page', strpos((string) file_get_contents($root . '/views/admin/aggregator/index.php'), 'pinForm_') !== false);
t('and in the sources table', strpos((string) file_get_contents($root . '/views/admin/rss_sources/index.php'), 'pinForm_src_') !== false);

echo "\n--- sources table: every button stays in place ---\n";
$sourcesView = (string) file_get_contents($root . '/views/admin/rss_sources/index.php');
t('auto-publish switch is bound', strpos($ajax, '.js-auto-toggle') !== false);
t('delete is ajax', strpos($sourcesView, 'data-ajax-delete="1"') !== false);
t('delete confirms in-app', strpos($ajax, "form[data-ajax-delete]") !== false && strpos($ajax, 'adminConfirm') !== false);
t('a deleted row disappears', strpos($ajax, "row = form.closest('tr')") !== false);
t('bulk bar is ajax', strpos($ajax, "document.getElementById('bulkSourcesForm')") !== false);
t('bulk delete removes rows', strpos($ajax, "data.action === 'delete'") !== false);
t('bulk auto toggle updates buttons', strpos($ajax, "data.action === 'auto-on'") !== false);
t('bulk clears the selection', strpos($ajax, "checked.forEach((c) => { c.checked = false; })") !== false);
t('no leftover onsubmit=confirm on delete', strpos($sourcesView, 'onsubmit="return confirm(') === false);

echo "\n--- the server answers with json ---\n";
t('it detects an ajax call', strpos($sources, 'function wantsJson') !== false);
t('favourite toggle answers json', strpos($sources, "'favorite' => (bool) \$now") !== false);
t('auto toggle answers json', strpos($sources, "'auto_publish' => (bool) \$now") !== false);
t('bulk answers json with the ids', strpos($sources, 'function bulkJson') !== false);
t('categories delete answers json', strpos($cats, 'function jsonOrFlash') !== false);
t('categories delete still works without ajax', strpos($cats, "Session::flash('success', \$message)") !== false);

echo "\n--- categories page too ---\n";
$catsView = (string) file_get_contents($root . '/views/admin/categories/index.php');
t('category delete is ajax', strpos($catsView, 'data-ajax-delete="1"') !== false);
t('category delete has a friendly confirm', strpos($catsView, 'data-confirm=') !== false);

echo "\n--- favourites tab thumbnails ---\n";
$fav = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('each item shows a thumbnail', strpos($fav, 'fav-thumb') !== false);
t('it uses the feed image', strpos($fav, "(\$item['featured_image'] ?? '')") !== false);
t('a broken image falls back', strpos($fav, 'onerror=') !== false);
t('lazy loading is on', strpos($fav, 'loading="lazy"') !== false);
t('only http(s) images are shown', strpos($fav, "preg_match('#^https?://#i', \$thumb)") !== false);
t('removing a favourite drops the card', strpos($fav, 'admin:favorite-changed') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);