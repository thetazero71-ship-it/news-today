<?php
/**
 * فحص مؤقت: هل يعمل جلب الأخبار تلقائياً على الخادم؟ (يُحذف بعد الفحص)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');
$db = new Database();

echo "== هل توجد سجلات جلب تلقائي؟ (cron_logs) ==\n";
try {
 $rows = $db->fetchAll("SELECT * FROM cron_logs ORDER BY id DESC LIMIT 8");
 if (!$rows) { echo "  (لا توجد سجلات)\n"; }
 foreach ($rows as $r) {
  $line = json_encode($r, JSON_UNESCAPED_UNICODE);
  echo '  ' . substr($line, 0, 260) . "\n";
 }
} catch (Throwable $e) { echo '  error: ' . $e->getMessage() . "\n"; }

echo "\n== آخر جلب لكل مصدر (rss_sources) ==\n";
try {
 $rows = $db->fetchAll("SELECT COUNT(*) c, SUM(last_fetched_at IS NOT NULL) fetched, MAX(last_fetched_at) newest FROM rss_sources");
 print_r($rows[0]);
 $rows = $db->fetchAll("SELECT name, last_fetched_at, last_status FROM rss_sources ORDER BY last_fetched_at DESC LIMIT 6");
 foreach ($rows as $r) { echo "  {$r['name']} | " . ($r['last_fetched_at'] ?? 'null') . " | " . ($r['last_status'] ?? '-') . "\n"; }
} catch (Throwable $e) { echo '  error: ' . $e->getMessage() . "\n"; }

echo "\n== أحدث المقالات (created_at) ==\n";
try {
 $rows = $db->fetchAll("SELECT id, title, created_at, published_at FROM articles ORDER BY id DESC LIMIT 6");
 foreach ($rows as $r) { echo "  #{$r['id']} | " . substr((string) $r['created_at'], 0, 19) . " | " . mb_substr((string) $r['title'], 0, 50) . "\n"; }
 echo '  total articles: ' . $db->fetch("SELECT COUNT(*) c FROM articles")['c'] . "\n";
} catch (Throwable $e) { echo '  error: ' . $e->getMessage() . "\n"; }

echo "\n== حالة المجدول المحفوظة ==\n";
$file = __DIR__ . '/storage/cron_status.json';
if (is_file($file)) {
 $st = json_decode((string) @file_get_contents($file), true);
 echo '  ' . json_encode($st, JSON_UNESCAPED_UNICODE) . "\n";
} else { echo "  لا يوجد ملف حالة\n"; }

echo "\n== إعدادات الجدولة ==\n";
try {
 foreach ($db->fetchAll("SELECT `key`, LEFT(`value`,90) v FROM settings WHERE `key` LIKE '%cron%' OR `key` LIKE '%auto_publish%' OR `key` LIKE '%fetch%'") as $r) {
  echo "  {$r['key']} = {$r['v']}\n";
 }
} catch (Throwable $e) { echo '  error: ' . $e->getMessage() . "\n"; }