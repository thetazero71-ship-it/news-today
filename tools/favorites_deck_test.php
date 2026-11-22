<?php
/**
 * Favorites rapid publishing deck: sort, scroll, counters, AJAX, quick filter.
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
$css = (string) file_get_contents($root . '/assets/css/site.css');

echo "--- new stories float, published ones sink ---\n";
t('the deck is sorted', strpos($controller, '// Rapid deck order: new items first, published ones sink to the bottom.') !== false);
t('published is the sort key', strpos($controller, "\$aPub = ((\$a['import_status'] ?? '') === 'published') ? 1 : 0;") !== false
    && strpos($controller, "return \$aPub <=> \$bPub;") !== false);

echo "\n--- the stream scrolls on its own ---\n";
t('the list is a scroll container', strpos($view, 'fav-list-scroll') !== false);
t('the CSS caps its height', strpos($css, '.fav-list-scroll { max-height: 560px; overflow-y: auto;') !== false);

echo "\n--- live counters per source ---\n";
t('a new-items counter exists', strpos($view, 'fav-new-count') !== false);
t('the counter is keyed by source', strpos($view, "data-source-id=\"<?= (int) \$src['id'] ?>\"") !== false);
t('it counts unpublished items', strpos($view, "if ((\$gi['import_status'] ?? '') !== 'published') \$newCount++;") !== false);
t('it is decremented after publishing', strpos($view, 'function refreshCounter(sourceId)') !== false);

echo "\n--- publishing happens in place ---\n";
t('all three publish forms are marked', substr_count($view, 'fav-publish-form') === 4); // 3 forms + selector in JS
t('the action is carried for draft handling', strpos($view, 'data-action="draft"') !== false);
t('the submit is intercepted', strpos($view, "form.addEventListener('submit', async function (event)") !== false);
t('it posts as AJAX', strpos($view, "'X-Requested-With': 'XMLHttpRequest'") !== false);
t('success swaps the action bar', strpos($view, 'function markItem(item, state, editUrl, label)') !== false);
t('an edit link is kept', strpos($view, "btn-outline-primary\" href=\"' + String(editUrl)") !== false);
t('drafts are labelled differently', strpos($view, "markItem(item, 'draft'") !== false);
t('errors are shown as toasts', strpos($view, "deckToast(err.message || 'تعذر تنفيذ العملية.', 'error')") !== false);

echo "\n--- quick filtering ---\n";
t('filter buttons exist', substr_count($view, 'data-fav-filter=') >= 3);
t('items expose their status', strpos($view, "data-fav-status=\"<?= \$published ? 'published' : 'new' ?>\"") !== false);
t('new means anything not published', strpos($view, "status !== 'published' : status === currentFilter") !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
