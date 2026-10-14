<?php
/** فحص مؤقت: إثبات عمل التخزين المحلي للصور على الخادم (يُحذف بعده) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/ImageStore.php';
header('Content-Type: text/plain; charset=utf-8');

echo "== الكتابة ==\n";
$dir = ImageStore::baseDir();
echo 'dir: ' . $dir . "\n";
echo 'writable: ' . var_export(is_writable($dir), true) . "\n";
echo 'gd: ' . var_export(function_exists('imagecreatetruecolor'), true) . "\n";
echo 'curl: ' . var_export(function_exists('curl_init'), true) . "\n";
echo 'finfo: ' . var_export(function_exists('finfo_open'), true) . "\n";

echo "\n== حالة الصور الحالية ==\n";
try {
    $db = new Database();
    $r = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image IS NULL OR featured_image = ''");
    echo 'empty: ' . $r['c'] . "\n";
    $r = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image LIKE 'http%'");
    echo 'remote: ' . $r['c'] . "\n";
    $r = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image LIKE 'uploads/%'");
    echo 'local: ' . $r['c'] . "\n";
    $r = $db->fetchAll("SELECT featured_image FROM articles WHERE featured_image LIKE 'http%' ORDER BY id DESC LIMIT 3");
    foreach ($r as $x) {
        echo '  sample: ' . mb_substr($x['featured_image'], 0, 100) . "\n";
    }
} catch (Throwable $e) {
    echo 'db: ' . $e->getMessage() . "\n";
}

echo "\n== تنزيل تجريبي (ملف مؤقت ثم حذف) ==\n";
$test = 'https://www.google.com/images/branding/googlelogo/2x/googlelogo_color_272x92dp.png';
$t = microtime(true);
$local = ImageStore::storeFromUrl($test);
printf("store: %s in %.1fs\n", $local === '' ? 'FAILED' : $local, microtime(true) - $t);
if ($local !== '') {
    $full = (defined('APP_ROOT') ? APP_ROOT : __DIR__) . '/' . $local;
    echo 'bytes: ' . @filesize($full) . "\n";
    $size = @getimagesize($full);
    echo 'dims: ' . ($size ? $size[0] . 'x' . $size[1] . ' ' . $size['mime'] : '?') . "\n";
    echo 'served back: ' . var_export(@file_get_contents('http://news-today.42web.io/' . $local) !== false, true) . "\n";
    @unlink($full);
    echo "cleaned up\n";
}
echo "done\n";