<?php
/**
 * Search engine test: Arabic normalization equivalence, LIKE safety, ranking
 * weights and the WHERE builder.
 * Run: php tools/search_test.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
require_once $root . '/core/SearchQuery.php';

$pass = 0;
$fail = 0;
function t($label, $actual, $expected = null)
{
    global $pass, $fail;
    $ok = func_num_args() >= 3 ? ($actual === $expected) : ((bool) $actual);
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$ok && func_num_args() >= 3) {
        echo "     got: " . var_export($actual, true) . "\n";
    }
    $ok ? $pass++ : $fail++;
}

$n = array('SearchQuery', 'normalize');

// ── letter-shape folding: these MUST collapse to one canonical form ──────
$groups = array(
    array('أحمد', 'احمد', 'آحمد', 'إحمد'),  // alef family
    array('مدرسة', 'مدرسه'),               // ta marbuta
    array('مصطفى', 'مصطفي'),               // alef maqsura
    array('مسؤول', 'مسوول'),               // waw with hamza
    array('سؤال', 'سوال'),                 // bare hamza
    array('قيمة', 'قيمه'),                 // ta marbuta at word end
);
foreach ($groups as $group) {
    $forms = array();
    foreach ($group as $form) {
        $forms[] = $n($form);
    }
    t('equivalent forms fold together: ' . $group[0] . ' / ' . $group[1],
        count(array_unique($forms)) === 1, true);
}

// tatweel and harakat are removed
t('tatweel removed', $n("الـجـيـش"), $n("الجيش"));
t('harakat removed', $n("الجيشُ"), $n("الجيش"));
t('fatha removed', $n("مُحَمَّد"), $n("محمد"));
t('sukun removed', $n("الجيشْ"), $n("الجيش"));

// digits: Arabic-Indic and Extended -> ASCII (note: ة folds to ه as well)
t('arabic-indic digits folded', $n('سنة ٢٠٢٦'), $n('سنه 2026'));
t('extended arabic digits folded', $n('سنة ۲۰۲۵'), $n('سنه 2025'));
t('mixed digits folded', $n('M8 و M7'), 'm8 و m7');

// case + punctuation + whitespace
t('lowercases latin', $n('Breaking Defense'), 'breaking defense');
t('punctuation becomes space', $n('الجيش، الأمريكي!'), $n('الجيش الأمريكي'));
t('collapses spaces', $n('  الجيش    الأمريكي  '), $n('الجيش الأمريكي'));
t('handles hyphen', $n('نشر-فوري'), 'نشر فوري');

// ── tokens ─────────────────────────────────────────────────────────────
t('single word token', SearchQuery::tokens('الجيش'), array('الجيش'));
t('multi word tokens', SearchQuery::tokens('الجيش الأمريكي'),
    array('الجيش', 'الامريكي'));
t('drops 1-char tokens', SearchQuery::tokens('ا الجيش ب'), array('الجيش'));
t('dedupes tokens', count(SearchQuery::tokens('الجيش الجيش')), 1);
t('empty query no tokens', SearchQuery::tokens('   '), array());
t('caps at 6 tokens', count(SearchQuery::tokens('الجيش Plans Aircraft News Today Weather here')), 6);

// ── LIKE escaping: user typed % and _ must stay literal ────────────────
t('percent escaped', SearchQuery::likeEscape('100%'), '100\\%');
t('underscore escaped', SearchQuery::likeEscape('a_b'), 'a\\_b');
t('backslash escaped', SearchQuery::likeEscape('a\\b'), 'a\\\\b');
t('normal text untouched', SearchQuery::likeEscape('الجيش'), 'الجيش');

// ── WHERE builder ──────────────────────────────────────────────────────
list($sql, $params, $relevance) = SearchQuery::buildWhere(array('الجيش'));
// matching is done against the FOLDED column (both sides folded), so the SQL
// contains a REPLACE chain per field rather than a raw `col LIKE`
t('builds a folded WHERE clause', strpos($sql, 'REPLACE') !== false, true);
t('covers title (folded)', strpos($sql, "REPLACE(a.title,") !== false, true);
t('covers title_ar (folded)', strpos($sql, "REPLACE(a.title_ar,") !== false, true);
t('covers content_ar (folded)', strpos($sql, "REPLACE(a.content_ar,") !== false, true);
t('covers excerpt (folded)', strpos($sql, "REPLACE(a.excerpt,") !== false, true);
t('covers source_name (folded)', strpos($sql, "REPLACE(a.source_name,") !== false, true);
t('has ESCAPE clause', strpos($sql, "ESCAPE") !== false, true);
t('binds params', count($params) > 0, true);
t('relevance expression built', strpos($relevance, 'IF(') !== false, true);
t('exact-match bonus present', strpos($relevance, '= :eq') !== false, true);
t('where stays reasonably small', strlen($sql) < 5000, true);

list($sql2, $params2) = SearchQuery::buildWhere(array('الجيش', 'أمريكا'));
t('two tokens => AND', substr_count($sql2, ') AND (') === 1, true);
t('two tokens => more params', count($params2) > count($params), true);

// four tokens max in SQL even when the query has more words
list($sql3) = SearchQuery::buildWhere(array('واحد', 'اثنان', 'ثلاثة', 'رباع', 'خمس', 'ستة', 'سبعة'));
t('caps SQL tokens at 4', substr_count($sql3, ' AND '), 3);

// foldExpr folds the same shapes as PHP
$expr = SearchQuery::foldExpr('a.title');
t('foldExpr handles alef', strpos($expr, "'أ', 'ا'") !== false, true);
t('foldExpr handles ya', strpos($expr, "'ى', 'ي'") !== false, true);
t('foldExpr handles ta marbuta', strpos($expr, "'ة', 'ه'") !== false, true);
t('foldExpr removes tatweel', strpos($expr, "'ـ', ''") !== false, true);
t('foldExpr lowercases', strpos($expr, 'LOWER(') === 0, true);

// ── fields and weights ─────────────────────────────────────────────────
$fields = SearchQuery::fields();
t('title has top weight', $fields['a.title'], 100);
t('title_ar has top weight', $fields['a.title_ar'], 100);
t('excerpt outranks content', $fields['a.excerpt'] > $fields['a.content'], true);
t('nine searchable fields', count($fields), 9);

// ── highlighting ───────────────────────────────────────────────────────
$html = SearchQuery::highlight('الجيش', 'خبر عن الجيش الأمريكي في المنطقة');
t('highlight wraps match', strpos($html, '<mark>') !== false, true);
t('highlight escapes html', strpos(SearchQuery::highlight('x', '<b>hi</b>'), '<b>') === false, true);
t('highlight handles empty text', SearchQuery::highlight('x', ''), '');

// ── constants ──────────────────────────────────────────────────────────
t('min length', SearchQuery::MIN_LENGTH, 2);
t('max length', SearchQuery::MAX_LENGTH, 120);

// every :placeholder in the SQL must have a bound param, and vice versa
$all = $sql . ' ' . $relevance;
preg_match_all('/:([a-zA-Z0-9_]+)/', $all, $m);
$placeholders = array_unique($m[1]);
$bound = array_map(function ($k) {
    return ltrim($k, ':');
}, array_keys($params));
$missing = array_diff($placeholders, $bound);
$extra = array_diff($bound, $placeholders);
t('no placeholder without a bound param', array_values($missing), array());
t('no bound param without a placeholder', array_values($extra), array());

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);