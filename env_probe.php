<?php
/**
 * فحص مؤقت: قياس حدود الاستضافة (مهلة الطلب + التزامن).
 * v4
 */
header('Content-Type: text/plain; charset=utf-8');

if (isset($_GET['sleep'])) {
    $seconds = max(1, min(150, (int) $_GET['sleep']));
    $started = microtime(true);
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

echo "php: " . PHP_VERSION . "\n";
echo "sapi: " . PHP_SAPI . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "max_input_time: " . ini_get('max_input_time') . "\n";
echo "memory_limit: " . ini_get('memory_limit') . "\n";
echo "ini file: " . php_ini_loaded_file() . "\n";
echo "disable_functions: " . ini_get('disable_functions') . "\n";
echo "opcache: " . (function_exists('opcache_get_status') && @opcache_get_status() ? 'on' : 'off/none') . "\n";
echo "output_buffering: " . ini_get('output_buffering') . "\n";
echo "server load avg: " . (function_exists('sys_getloadavg') ? implode(', ', sys_getloadavg()) : 'n/a') . "\n";
echo "disk_free_hdd: " . (function_exists('disk_free_space') ? round(@disk_free_space(APP_ROOT ?? __DIR__) / 1048576) . ' MB' : 'n/a') . "\n";