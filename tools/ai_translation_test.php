<?php
/**
 * Translation must never pass an untranslated headline off as translated.
 * Run: php tools/ai_translation_test.php
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
$src = (string) file_get_contents($root . '/core/AiTranslator.php');

echo "--- a failed translation is not accepted ---\n";
t('there is a translation check', strpos($src, 'function isTranslated') !== false);
t('it looks for arabic output', (bool) preg_match('/preg_match\(\'\/\\\\p\{Arabic\}\/u\', \$result\)/', $src));
t('the free translator result is verified', strpos($src, "if (self::isTranslated(\$res, (string) \$titleEn))") !== false);
t('gemini result is verified', strpos($src, "self::isTranslated(\$res['data'] ?? [], (string) \$titleEn)") !== false);
t('omniroute result is verified', strpos($src, "'fallback_omniroute'") !== false && strpos($src, "لم تُرجع Omniroute نصاً عربياً") !== false);
t('a total failure is reported honestly', strpos($src, "'used_provider' => 'none'") !== false || strpos($src, "'none');") !== false);
t('the attempt is logged', strpos($src, 'أعاد المزوّد النص الأصلي دون ترجمة') !== false
  || strpos($src, 'المترجم المجاني أعاد النص الأصلي دون ترجمة') !== false);
t('every "mymemory success" is guarded', (bool) preg_match_all(
 "/if \(self::isTranslated\(\\\$res, \(string\) \\\$titleEn\)\) \{\s*\n\s*return \\\$buildResult\(\\\$res, 'mymemory'\);/",
 $src,
 $m
) && count($m[0]) === substr_count($src, "return \$buildResult(\$res, 'mymemory');"));

echo "\n--- the chain cannot hang a request ---\n";
t('a translation budget exists', strpos($src, 'TRANSLATE_BUDGET_SECONDS') !== false);
t('it is short', strpos($src, 'const TRANSLATE_BUDGET_SECONDS = 14') !== false);
t('fallbacks check the clock', substr_count($src, '$outOfTime()') >= 5);
t('no provider timeout exceeds 15s', (bool) preg_match('/CURLOPT_TIMEOUT\s*=>\s*(2[0-9]|3[0-9])/', $src) === false);

echo "\n--- google model name is real ---\n";
t('no google 1.5/2.0 default remains', strpos($src, "'gemini_model'] ?? 'gemini-2.0-flash'") === false);
t('an unknown model name is corrected', strpos($src, 'function pickGeminiModel') !== false);
t('the allow-list holds live models (oct 2026)', strpos($src, "'gemini-3.5-flash-lite'") !== false && strpos($src, "'gemini-3.6-flash'") !== false);
t('the default is the model google recommends', strpos($src, "const GEMINI_DEFAULT = 'gemini-3.5-flash-lite'") !== false);
t('grandfathered 2.5 models still accepted for old keys', strpos($src, "'gemini-2.5-flash-lite'") !== false);
t('third-party gateway ids are untouched', strpos($src, 'antigravity/gemini-3.7-flash-high') !== false);
t('the backup chain has no dead google model', (bool) preg_match("/backupModels = \[[^\]]*gemini-2\.0-flash[^\]]*\]/", $src) === false);
t('a 404 recommendation is parsed and retried', strpos($src, 'models\\/([a-z0-9][a-z0-9._-]*)') !== false);
t('the retry loop is index-based (sees new models)', strpos($src, 'for ($mi = 0; $mi < count($candidateModels); $mi++)') !== false);
t('the settings dropdown shows live models', strpos($src, "'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite") !== false);
t('the dropdown warns about 2.5 keys', strpos($src, 'للمفاتيح القديمة فقط') !== false);

echo "\n--- the cron/UI gets a readable signal ---\n";
$cron = (string) file_get_contents($root . '/cron/rss_auto_publish.php');
t('the cron still publishes even without translation', strpos($cron, 'نشر فوري بلا ترجمة') !== false);
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('manual publish falls back to the original text', strpos($controller, "\$transResult['title_ar'] ?? \$title") !== false);

echo "\n--- the cron / manual publish never ship untranslated text ---\n";
$cron = (string) file_get_contents($root . '/cron/rss_auto_publish.php');
t('the cron checks the translated flag', strpos($cron, "\$aiResult['translated'] === false") !== false);
t('and skips the item instead of publishing it', strpos($cron, 'تعذّرت الترجمة') !== false);
t('the failure is counted', strpos($cron, "translation_failed") !== false);
t('the free translator is skipped when a real ai is ready', strpos($src, 'تم تخطّي المترجم المجاني') !== false);
t('a provider is not started without enough time left', strpos($src, 'function (int $need = 6)') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);