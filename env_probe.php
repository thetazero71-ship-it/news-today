<?php
/**
 * فحص مؤقت: مفاتيح الإعدادات في الخادم (يُحذف بعد الاستخدام).
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = new Database();

    echo "=== groups ===\n";
    foreach ($db->fetchAll('SELECT `group`, COUNT(*) AS n FROM settings GROUP BY `group` ORDER BY n DESC') as $r) {
        echo '  ' . $r['group'] . ' => ' . $r['n'] . "\n";
    }

    echo "\n=== site/brand keys ===\n";
    $rows = $db->fetchAll("SELECT `key`, `value`, `group` FROM settings
                           WHERE `key` LIKE 'site%' OR `key` LIKE '%name%' OR `key` LIKE '%brand%'
                           ORDER BY `group`, `key`");
    if (!$rows) {
        echo "  (none)\n";
    }
    foreach ($rows as $r) {
        echo '  [' . $r['group'] . '] ' . $r['key'] . ' = ' . substr((string) $r['value'], 0, 60) . "\n";
    }

    echo "\n=== general group keys ===\n";
    foreach ($db->fetchAll("SELECT `key`, LEFT(`value`, 40) AS v FROM settings WHERE `group` = 'general' ORDER BY `key`") as $r) {
        echo '  ' . $r['key'] . ' = ' . $r['v'] . "\n";
    }
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage() . "\n";
}