<?php
/** فحص مؤقت: تشخيص جلب خلاصة العالم من الخادم (يُحذف بعده) */
require_once __DIR__ . '/core/FeedFetcher.php';
header('Content-Type: text/plain; charset=utf-8');

foreach (array(
    'https://www.alalam.ir/rss/latest',
    'https://www.alalam.ir/rss/latest.xml',
) as $u) {
    echo "== $u ==\n";
    $t = microtime(true);
    // نفس المسار الذي يستخدمه فحص الصحة (الوضع الافتراضي الكامل)
    $f = FeedFetcher::fetchRaw($u);
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