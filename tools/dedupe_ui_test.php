<?php
/**
 * Duplicate tuning and duplicate badges in the aggregator.
 *
 * Run: php tools/dedupe_ui_test.php
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
require_once $root . '/core/NewsDeduplicator.php';

echo "--- exact duplicates keep the first as the original ---\n";
$items = NewsDeduplicator::annotateDuplicates([
    ['title' => 'Apple unveils new iPhone with AI features'],
    ['title' => 'Apple unveils the new iPhone with AI features'],
    ['title' => 'Samsung launches Galaxy S26 with a new camera'],
]);
t('the first item stays original', $items[0]['duplicate_of'] === null);
t('the second is marked duplicate', $items[1]['duplicate_of'] === 0);
t('the score is recorded', $items[1]['duplicate_score'] >= 80);
t('the original title travels with the mark', $items[1]['duplicate_title'] === 'Apple unveils new iPhone with AI features');
t('a different story is not marked', $items[2]['duplicate_of'] === null);

echo "\n--- Arabic headlines with stopwords ---\n";
$ar = NewsDeduplicator::annotateDuplicates([
    ['title' => 'أبل تكشف عن هاتف آيفون جديد بالذكاء الاصطناعي'],
    ['title' => 'أبل تكشف هاتف آيفون بالذكاء الاصطناعي'],
    ['title' => 'مايكروسوفت تعلن نتائجها المالية الربعية'],
]);
t('the Arabic duplicate is caught', $ar[1]['duplicate_of'] === 0);
t('the unrelated Arabic story stays', $ar[2]['duplicate_of'] === null);

echo "\n--- containment still works ---\n";
$c = NewsDeduplicator::annotateDuplicates([
    ['title' => 'OpenAI releases GPT-5 model for developers'],
    ['title' => 'عاجل: OpenAI releases GPT-5 model for developers'],
]);
t('a prefixed copy is caught', $c[1]['duplicate_of'] === 0);
t('containment scores 95', $c[1]['duplicate_score'] === 95);

echo "\n--- tuning constants ---\n";
t('the threshold is 80%', NewsDeduplicator::SIMILARITY === 0.80);
t('Arabic stopwords exist', in_array('من', NewsDeduplicator::STOPWORDS, true));
t('English stopwords exist', in_array('the', NewsDeduplicator::STOPWORDS, true));

echo "\n--- the controller annotates both tabs ---\n";
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('the aggregator annotates items', strpos($controller, '$items = NewsDeduplicator::annotateDuplicates($items);') !== false);
t('favorites annotates them too', substr_count($controller, 'NewsDeduplicator::annotateDuplicates($items)') === 2);
t('duplicates are excluded from the new counter', strpos($controller, "if (!empty(\$it['duplicate_of'])) continue;") !== false);
t('the duplicate count reaches the view', strpos($controller, "'duplicateCount' => \$duplicateCount ?? 0") !== false);

echo "\n--- the card exposes the mark ---\n";
$view = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
$fav = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('cards carry a duplicate flag', strpos($view, 'data-news-duplicate="<?= !empty($item[\'duplicate_of\']) ? \'1\' : \'0\' ?>"') !== false);
t('a duplicate badge is rendered', strpos($view, 'خبر مكرر — الأصل:') !== false);
t('the badge shows the original title', strpos($view, "\$item['duplicate_title']") !== false);
t('the similarity percent is shown', strpos($view, "\$item['duplicate_score']") !== false);
t('a quick hide button exists', strpos($view, 'id="duplicateFilterBtn"') !== false
    && strpos($view, 'toggleDuplicateFilter(this)') !== false);
t('the live filter respects the toggle', strpos($view, 'hideDuplicates && isDuplicate') !== false);
t('the AJAX switch refreshes the count', strpos($view, "getElementById('duplicateCount')") !== false);
t('favorites marks duplicates', strpos($fav, 'data-fav-duplicate=') !== false
    && strpos($fav, 'خبر مكرر') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
