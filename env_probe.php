<?php
/**
 * فحص مؤقت: تفعيل النشر التلقائي لمصدر واحد للتحقق (يُحذف بعد الفحص).
 * GET /env_probe.php?on=<id>&tr=0|1
 * GET /env_probe.php?off
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');
$db = new Database();

try {
    $db->query('ALTER TABLE rss_sources ADD COLUMN auto_publish TINYINT(1) NOT NULL DEFAULT 0');
    echo "column auto_publish added\n";
} catch (Throwable $e) {
}
try {
    $db->query('ALTER TABLE rss_sources ADD COLUMN auto_publish_translate TINYINT(1) NOT NULL DEFAULT 0');
    echo "column auto_publish_translate added\n";
} catch (Throwable $e) {
}

if (isset($_GET['off'])) {
    $db->query('UPDATE rss_sources SET auto_publish = 0');
    echo "all sources disabled\n";
    exit;
}

if (isset($_GET['on'])) {
    $id = (int) $_GET['on'];
    $tr = isset($_GET['tr']) ? (int) $_GET['tr'] : 0;
    $db->query('UPDATE rss_sources SET auto_publish = 1, auto_publish_translate = :tr WHERE id = :id', [
        ':tr' => $tr,
        ':id' => $id,
    ]);
    $row = $db->fetch('SELECT name FROM rss_sources WHERE id = :id', [':id' => $id]);
    echo "enabled: " . ($row['name'] ?? '?') . " (translate={$tr})\n";
    exit;
}

foreach ($db->fetchAll("SELECT id, name, auto_publish, auto_publish_translate, last_fetched_at FROM rss_sources WHERE auto_publish = 1") as $r) {
    echo "  #{$r['id']} {$r['name']} translate={$r['auto_publish_translate']} last={$r['last_fetched_at']}\n";
}
echo "enabled count: " . $db->fetch("SELECT COUNT(*) c FROM rss_sources WHERE auto_publish = 1")['c'] . "\n";