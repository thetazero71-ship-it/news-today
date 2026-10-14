<?php
/** فحص مؤقت: تشخيص جلب خلاصة العالم من الخادم (يُحذف بعده) - v4 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

echo "== إصلاح روابط العالم (apex -> www) ==\n";
try {
    $db = new Database();
    $rows = $db->fetchAll("SELECT id, url FROM rss_sources WHERE url LIKE '%alalam.ir/%' AND url NOT LIKE '%www.alalam.ir%'");
    foreach ($rows as $r) {
        $fixed = str_replace('://alalam.ir/', '://www.alalam.ir/', (string) $r['url']);
        $db->query('UPDATE rss_sources SET url = :u, last_status = NULL, last_error = NULL, fail_count = 0 WHERE id = :id', [':u' => $fixed, ':id' => (int) $r['id']]);
        echo "  #{$r['id']}: {$r['url']}  =>  $fixed\n";
    }
    if (!$rows) {
        echo "  لا يوجد ما يُصلح (كلها www مسبقاً)\n";
    }

    echo "\n== فحص صحة بنفس منطق زر الفحص ==\n";
    require_once __DIR__ . '/core/FeedFetcher.php';
    foreach ($db->fetchAll("SELECT id, name, url FROM rss_sources WHERE url LIKE '%alalam%'") as $s) {
        $f = FeedFetcher::fetchRaw($s['url'], true, 0, 5, 10);
        $items = 0;
        if (!empty($f['success'])) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($f['body'], 'SimpleXMLElement', LIBXML_NOCDATA);
            if ($xml) {
                $items = isset($xml->channel->item) ? count($xml->channel->item) : (isset($xml->entry) ? count($xml->entry) : 0);
            }
        }
        printf("  #%d %s => success=%s http=%s items=%d %s\n",
            $s['id'], mb_substr($s['name'], 0, 30), var_export($f['success'], true),
            $f['http_code'] ?? '?', $items, $f['success'] ? '' : (' err=' . mb_substr($f['error'] ?? '', 0, 100)));
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}
echo "== نهاية ==\n";
exit;
require_once __DIR__ . '/core/FeedFetcher.php';
header('Content-Type: text/plain; charset=utf-8');

foreach (array(
    'https://www.alalam.ir/rss/latest',
    'https://www.alalam.ir/rss/latest.xml',
) as $u) {
    echo "== $u ==\n";
    $t = microtime(true);
    // مهلات قصيرة صريحة حتى لا تُقتل العملية قبل الإجابة
    $f = FeedFetcher::fetchRaw($u, true, 0, 5, 10);
    printf("  success=%s http=%s bytes=%d time=%.1fs\n",
        var_export($f['success'], true),
        $f['http_code'] ?? '?',
        strlen($f['body'] ?? ''),
        microtime(true) - $t);
    if (!$f['success']) {
        echo '  error: ' . mb_substr($f['error'] ?? '', 0, 200) . "\n";
    } else {
        libxml_use_internal_errors(true);
        $xml = @simplexml_load_string($f['body'], 'SimpleXMLElement', LIBXML_NOCDATA);
        echo $xml
            ? '  parses OK, items=' . (isset($xml->channel->item) ? count($xml->channel->item) : 0) . "\n"
            : "  body is NOT xml, head=" . mb_substr($f['body'], 0, 120) . "\n";
    }
}
echo "done\n";