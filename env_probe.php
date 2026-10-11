<?php
/**
 * فحص مؤقت: قياس حدود الاستضافة (مهلة الطلب + التزامن).
 * يُحذف فور انتهاء الفحص.
 */
require_once __DIR__ . '/config/database.php';

header('Content-Type: text/plain; charset=utf-8');

$db = (new ReflectionClass('Database'))->getName();
$pdo = null;
try {
    $d = new Database();
    $pdo = $d->pdo ?? null;
} catch (Throwable $e) {
}

$info = [
    'php'        => PHP_VERSION,
    'max_exec'   => ini_get('max_execution_time'),
    'memory'     => ini_get('memory_limit'),
    'loaded_cfg' => php_ini_loaded_file(),
    'disable_fn' => ini_get('disable_functions'),
    'sapi'       => PHP_SAPI,
];

echo "== info ==\n";
foreach ($info as $k => $v) {
    echo "$k: $v\n";
}

if (isset($_GET['sleep'])) {
    $seconds = max(1, min(120, (int) $_GET['sleep']));
    $started = microtime(true);
    // نبضة心跳 كل 5 ثوانٍ حتى لا تبتلع الواجهة الاتصال
    for ($i = 0; $i < $seconds; $i++) {
        sleep(1);
        if (connection_aborted()) {
            echo "client gone after {$i}s\n";
            exit;
        }
    }
    echo "slept {$seconds}s ok in " . round(microtime(true) - $started, 1) . "s\n";
    exit;
}

if (isset($_GET['db'])) {
    $t = microtime(true);
    try {
        $pdo->query('SELECT 1');
        echo "db ok in " . round((microtime(true) - $t) * 1000) . "ms\n";
    } catch (Throwable $e) {
        echo "db error: " . $e->getMessage() . "\n";
    }
    exit;
}