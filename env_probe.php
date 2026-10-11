<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
header('Content-Type: text/plain; charset=utf-8');
$db = new Database();

echo "== حالة المجدول ==\n";
$f = __DIR__ . '/storage/cron_status.json';
echo is_file($f) ? substr((string) file_get_contents($f), 0, 700) . "\n" : "  لا يوجد\n";

echo "\n== مصادر تم جلبها ==\n";
$r = $db->fetchAll("SELECT name, last_fetched_at, last_status, last_item_count FROM rss_sources WHERE last_fetched_at IS NOT NULL ORDER BY last_fetched_at DESC LIMIT 6");
foreach ($r as $x) { echo "  {$x['name']} | {$x['last_fetched_at']} | {$x['last_status']} | {$x['last_item_count']}\n"; }

echo "\n== آخر 5 مقالات ==\n";
foreach ($db->fetchAll("SELECT id, title, source_name, created_at FROM articles ORDER BY id DESC LIMIT 5") as $a) {
  echo "  #{$a['id']} | " . substr((string) $a['created_at'], 0, 19) . " | " . ($a['source_name'] ?? '-') . " | " . mb_substr((string) $a['title'], 0, 45) . "\n";
}
echo '  total: ' . $db->fetch("SELECT COUNT(*) c FROM articles")['c'] . "\n";

echo "\n== سجل الكرون ==\n";
foreach ($db->fetchAll("SELECT * FROM cron_logs ORDER BY id DESC LIMIT 4") as $l) { echo '  ' . substr(json_encode($l, JSON_UNESCAPED_UNICODE), 0, 300) . "\n"; }

echo "\n== إعدادات الذكاء الاصطناعي ==\n";
foreach ($db->fetchAll("SELECT `key`, LEFT(`value`, 60) v FROM settings WHERE `key` LIKE '%ai_%' OR `key` LIKE '%provider%' OR `key` LIKE '%api_key%' OR `key` LIKE '%translate%' ORDER BY `key`") as $r) {
  echo "  {$r['key']} = " . ($r['v'] === '' ? '(فارغ)' : $r['v']) . "\n";
}

echo "\n== آخر سجلات الأخطاء ==\n";
$logDir = __DIR__ . '/storage/logs';
foreach ((array) glob($logDir . '/*.log') as $lf) {
  echo '  ' . basename($lf) . ' (' . date('Y-m-d H:i', filemtime($lf)) . ")\n";
  $tail = array_slice(file($lf, FILE_IGNORE_NEW_LINES) ?: [], -6);
  foreach ($tail as $line) { echo "    " . mb_substr($line, 0, 190) . "\n"; }
}