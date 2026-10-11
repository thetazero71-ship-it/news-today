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
t('there are two tabs', strpos($view, 'data-tab="all"') !== false && strpos($view, 'data-tab="fav"') !== false);
t('a switch function exists', strpos($view, 'function switchSourceTab') !== false);
t('it filters the chips', strpos($view, "chip.style.display = show ? '' : 'none'") !== false);
t('the counter is refreshed', strpos($view, "favTabCount") !== false);
t('pinning re-applies the tab', strpos($view, 'applySourceTab();') !== false);
t('it runs on load', preg_match('/renderPinnedSources\(\);\s*\n\s*applySourceTab\(\);/', $view) === 1);
t('an empty favourites tab explains itself', strpos($view, 'لا يوجد مصدر في المفضلة') !== false);
t('the existing favourites list still works', strpos($view, 'pinnedSourcesList') !== false && strpos($view, 'togglePinSource') !== false);

echo "\n--- the schedule is as fast as GitHub allows ---\n";
t('runs every 5 minutes', strpos($workflow, "*/5 * * * *") !== false);
t('only one run at a time', strpos($workflow, 'concurrency:') !== false);
t('it runs on GitHub, not the host', strpos($workflow, 'SITE_URL') !== false && strpos($workflow, 'curl') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);