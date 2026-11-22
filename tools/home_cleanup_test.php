<?php
/**
 * Home cleanup: the duplicated Bento "quick pulse" row is gone.
 *
 * Run: php tools/home_cleanup_test.php
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
$home = (string) file_get_contents($root . '/views/home.php');

echo "--- the duplicate row is gone ---\n";
t('the pulse row class is gone', strpos($home, 'bento-pulse-row') === false);
t('the AI radar pulse card is gone', strpos($home, 'Pulse 1: AI Radar') === false);
t('the breaking flash card is gone', strpos($home, 'عاجل ومهم') === false);
t('the tutorials pulse card is gone', strpos($home, 'جديد الشروحات') === false);
t('the removal is documented in place', strpos($home, 'quick pulse') !== false);

echo "\n--- the rest of the template is intact ---\n";
t('the Bento hero remains', strpos($home, 'bento-matrix-grid') !== false);
t('the main bento cell remains', strpos($home, 'bento-cell-master') !== false);
t('the multi-stream section remains', strpos($home, 'شبكة الأخبار والتحليلات المتخصصة') !== false);
t('the default stream remains', strpos($home, 'homeNewsGrid') !== false);
t('the load-more button remains', strpos($home, 'homeLoadMoreBtn') !== false);
t('the broadsheet template remains', strpos($home, 'broadsheet') !== false);
t('closing tags are balanced', substr_count($home, '<section') === substr_count($home, '</section>'));

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
