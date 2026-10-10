<?php
/**
 * فحص مؤقت لبيئة التشغيل على الخادم (يحذف بعد الاستخدام).
 */
header('Content-Type: text/plain; charset=utf-8');

echo 'PHP_VERSION: ' . phpversion() . "\n";
echo 'PHP_SAPI: ' . php_sapi_name() . "\n";
echo 'mbstring: ' . (function_exists('mb_strtolower') ? 'yes' : 'no') . "\n";
echo 'mb_chr: ' . (function_exists('mb_chr') ? 'yes' : 'NO') . "\n";
echo 'json: ' . (function_exists('json_encode') ? 'yes' : 'no') . "\n";

try {
    $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    echo 'db: connected' . "\n";
    $row = $pdo->query('SELECT VERSION()')->fetchColumn();
    echo 'mysql: ' . $row . "\n";

    // does the exact expression shape used by the search run on this server?
    $sql = "SELECT LOWER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(a.title,'أ','ا'),'ى','ي'),'ة','ه'),'ـ',''),'ء','')) AS f FROM articles a LIMIT 1";
    $st = $pdo->query($sql);
    echo 'fold_expr: OK' . "\n";
    $st->fetchColumn();
} catch (Throwable $e) {
    echo 'db error: ' . $e->getMessage() . "\n";
}

require_once __DIR__ . '/core/SearchQuery.php';
try {
    echo 'normalize: [' . SearchQuery::normalize('الجيشُ 实') . "]\n";
    $w = SearchQuery::buildWhere(array(SearchQuery::normalize('الجيش')));
    echo 'buildWhere: ' . strlen($w[0]) . " bytes\n";
} catch (Throwable $e) {
    echo 'SearchQuery error: ' . $e->getMessage() . "\n";
}