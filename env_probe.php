<?php
/**
 * فحص مؤقت: تصدير مخطط قاعدة البيانات الحي (إنشاء فقط، بدون ALTER).
 * يُرجع النص بترميز base64 حتى لا يتلف أثناء النقل.
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');

$db = new Database();

$tables = $db->fetchAll(
    "SELECT TABLE_NAME, TABLE_TYPE, ENGINE, TABLE_COLLATION, TABLE_ROWS
     FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE()
     ORDER BY TABLE_NAME"
);

$creates = [];
$views = [];

foreach ($tables as $t) {
    $name = (string) $t['TABLE_NAME'];
    if (strtoupper((string) $t['TABLE_TYPE']) === 'VIEW') {
        $row = $db->fetchAll('SHOW CREATE VIEW `' . $name . '`');
        if (isset($row[0]['Create View'])) {
            $views[$name] = $row[0]['Create View'];
        }
        continue;
    }
    try {
        $row = $db->fetchAll('SHOW CREATE TABLE `' . $name . '`');
        if (isset($row[0]['Create Table'])) {
            $creates[$name] = $row[0]['Create Table'];
        }
    } catch (Throwable $e) {
        $creates[$name] = '-- !! could not read: ' . $e->getMessage();
    }
}

// ترتيب يحترم المفاتيح الأجنبية: الجداول المُشار إليها أولاً
$refs = [];
foreach ($creates as $name => $sql) {
    $deps = [];
    if (preg_match_all('/CONSTRAINT\s+`?[^`\s]+`?\s+FOREIGN KEY\s*\([^)]*\)\s*REFERENCES\s+`?([A-Za-z0-9_]+)`?/i', $sql, $m)) {
        foreach ($m[1] as $dep) {
            $dep = trim($dep);
            if ($dep !== $name && isset($creates[$dep])) {
                $deps[] = $dep;
            }
        }
    }
    $refs[$name] = $deps;
}

$ordered = [];
$state = [];
$visit = function ($name) use (&$visit, &$ordered, &$state, $refs, $creates) {
    if (isset($state[$name]) || !isset($creates[$name])) {
        return;
    }
    $state[$name] = true; // visiting (cycle guard)
    foreach ($refs[$name] as $dep) {
        $visit($dep);
    }
    $state[$name] = false;
    $ordered[] = $name;
};
foreach (array_keys($creates) as $name) {
    $visit($name);
}

$out = "-- ===========================================================\n";
$out .= "--  مخطط قاعدة البيانات - " . (defined('APP_ROOT') ? APP_ROOT : '') . "\n";
$out .= '--  نسخة كاملة: أوامر إنشاء فقط (CREATE TABLE)، بدون أي ALTER.\n';
$out .= '--  ' . date('Y-m-d H:i') . "\n";
$out .= "--  عدد الجداول: " . count($creates) . "\n";
$out .= "-- ===========================================================\n\n";
$out .= "SET NAMES utf8mb4;\n";
$out .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

foreach ($ordered as $name) {
    $sql = rtrim($creates[$name], ";\n");
    $out .= "-- ---------------------------------------------------------\n";
    $out .= "-- جدول: " . $name . "\n";
    $out .= "-- ---------------------------------------------------------\n";
    $out .= $sql . ";\n\n";
}

if ($views) {
    $out .= "-- ---------------------------------------------------------\n";
    $out .= "-- Views\n";
    $out .= "-- ---------------------------------------------------------\n";
    foreach ($views as $name => $sql) {
        $out .= rtrim($sql, ";\n") . ";\n\n";
    }
}

$out .= "SET FOREIGN_KEY_CHECKS = 1;\n";

echo "B64START\n";
echo chunk_split(base64_encode($out), 4000, "\n");
echo "B64END\n";
echo 'TABLES=' . count($creates) . ' VIEWS=' . count($views) . "\n";