<?php
/**
 * فحص مؤقت: مخطط جدول articles الحقيقي على الخادم (يُحذف بعد الاستخدام).
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $db = new Database();
    $cols = array();
    foreach ($db->fetchAll('SHOW COLUMNS FROM articles') as $c) {
        $cols[] = $c['Field'];
    }
    echo 'ARTICLE_COLUMNS(' . count($cols) . '): ' . implode(', ', $cols) . "\n\n";

    $want = array('title', 'title_ar', 'title_en', 'slug', 'content', 'content_ar',
        'content_en', 'excerpt', 'source_name', 'source_url', 'category_id', 'status');
    foreach ($want as $w) {
        echo (in_array($w, $cols, true) ? '  OK   ' : '  MISS ') . $w . "\n";
    }

    $t = array();
    foreach ($db->fetchAll('SHOW COLUMNS FROM categories') as $c) {
        $t[] = $c['Field'];
    }
    echo "\nCATEGORY_COLUMNS: " . implode(', ', $t) . "\n";
} catch (Throwable $e) {
    echo 'error: ' . $e->getMessage() . "\n";
}