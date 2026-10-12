<?php
/** فحص مؤقت: قياس زمن كل جزء من عملية النشر على الخادم (يُحذف بعده) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Settings.php';
require_once __DIR__ . '/core/AiTranslator.php';
require_once __DIR__ . '/core/NewsDeduplicator.php';
require_once __DIR__ . '/core/FeedFetcher.php';
require_once __DIR__ . '/core/FetchOg.php';
require_once __DIR__ . '/core/CategoryClassifier.php';
require_once __DIR__ . '/core/CategorySeeder.php';

header('Content-Type: text/plain; charset=utf-8');

function stamp(string $label, callable $fn)
{
    $t = microtime(true);
    try {
        $out = $fn();
    } catch (Throwable $e) {
        $out = 'ERROR: ' . $e->getMessage();
    }
    printf("%-34s %7.0f ms   %s\n", $label, (microtime(true) - $t) * 1000, mb_substr(is_string($out) ? $out : json_encode($out, JSON_UNESCAPED_UNICODE), 0, 70));
}

$db = new Database();

echo "== القاعدة ==\n";
stamp('عدد المقالات', function () use ($db) {
    return (string) $db->fetch('SELECT COUNT(*) c FROM articles')['c'];
});
stamp('جلب 200 صف عنوان', function () use ($db) {
    return count($db->fetchAll('SELECT id,title,title_ar FROM articles ORDER BY id DESC LIMIT 200'));
});

echo "\n== فحص التكرار ==\n";
stamp('NewsDeduplicator (خبر جديد)', function () use ($db) {
    $m = NewsDeduplicator::findMatch($db, 'A completely unrelated headline about the economy ' . mt_rand(100, 999));
    return $m ? ('MATCH ' . $m['id']) : 'no match';
});
stamp('NewsDeduplicator (مرة ثانية)', function () use ($db) {
    $m = NewsDeduplicator::findMatch($db, 'Another unrelated headline ' . mt_rand(100, 999));
    return $m ? 'MATCH' : 'no match';
});

echo "\n== جلب كل الروابط (المطابقة في النشر اليدوي) ==\n";
stamp('كل روابط المقالات', function () use ($db) {
    return count($db->fetchAll('SELECT id, source_url FROM articles WHERE source_url IS NOT NULL'));
});

echo "\n== الشبكة ==\n";
$src = $db->fetch('SELECT url FROM rss_sources WHERE last_status = "ok" LIMIT 1');
$url = $src['url'] ?? null;
if ($url) {
    stamp('جلب خلاصة RSS', function () use ($url) {
        $f = FeedFetcher::fetchRaw($url);
        return 'items=' . count($f['items'] ?? []);
    });
    stamp('استخراج صورة og:image', function () use ($url) {
        return FetchOg::resolveFor($url, '');
    });
}

echo "\n== الذكاء الاصطناعي (الترجمة) ==\n";
stamp('ترجمة قصيرة', function () {
    $r = AiTranslator::translateArticle('Short test headline about markets', 'The central bank cut interest rates today, analysts said.');
    return 'title: ' . mb_substr($r['title_ar'] ?? '?', 0, 40);
});
stamp('ترجمة ثانية (نفس النداء)', function () {
    $r = AiTranslator::translateArticle('Another short headline about weather', 'Heavy rain is expected in the north during the weekend, forecasters warned.');
    return 'title: ' . mb_substr($r['title_ar'] ?? '?', 0, 40);
});

echo "\n== تفاصيل المزوّدين ==\n";
$keys = array('ai_provider', 'ai_fallback_enabled', 'opencode_fallback_enabled', 'gemini_api_key', 'gemini_model', 'omniroute_api_key', 'omniroute_endpoint', 'custom_api_key', 'custom_api_endpoint', 'openai_api_key', 'groq_api_key', 'deepseek_api_key');
foreach ($keys as $k) {
    $v = (string) Settings::get($k, '');
    $shown = $v === '' ? '(فارغ)' : (strpos($k, 'key') !== false ? 'موجود (' . strlen($v) . ' حرف)' : $v);
    echo "  $k = $shown\n";
}

echo "\n== نتيجة الترجمة الكاملة ==\n";
try {
    $r = AiTranslator::translateArticle('Diagnostics headline', 'Body text for the diagnostics run to see which provider answers.');
    echo '  title_ar: ' . mb_substr((string) ($r['title_ar'] ?? ''), 0, 60) . "\n";
    echo '  diagnostics: ' . json_encode($r['diagnostics'] ?? [], JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    echo '  ERROR: ' . $e->getMessage() . "\n";
}

echo "\n== التصنيف ==\n";
stamp('CategoryClassifier::classify', function () {
    return (string) CategoryClassifier::classify('Some English headline here', 'Some content body for classification', 'عنوان', null, [], 'https://x.com/a', null);
});

if (isset($_GET['fixmodel'])) {
 echo "\n== تصحيح نموذج Gemini ==\n";
 $db->query("UPDATE settings SET `value` = :v WHERE `key` = 'gemini_model'", [':v' => 'gemini-2.0-flash']);
 Settings::clear();
 echo "  gemini_model = " . Settings::get('gemini_model') . "\n";
}