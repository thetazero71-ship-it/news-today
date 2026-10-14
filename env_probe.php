<?php
/** فحص مؤقت: إثبات الضغط على الخادم بنسخ تجريبية (يُحذف بعده) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/ImageStore.php';
header('Content-Type: text/plain; charset=utf-8');

function fmt($b): string
{
    if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
    if ($b >= 1024) return round($b / 1024, 1) . ' KB';
    return $b . ' B';
}

try {
    $db = new Database();
    $rows = $db->fetchAll("SELECT featured_image FROM articles WHERE featured_image LIKE 'uploads/%' ORDER BY id DESC LIMIT 3");
    $root = defined('APP_ROOT') ? APP_ROOT : __DIR__;
    $totalBefore = 0;
    $totalAfter = 0;
    foreach ($rows as $r) {
        $full = $root . '/' . ltrim($r['featured_image'], '/');
        if (!is_file($full)) {
            echo "missing: {$r['featured_image']}\n";
            continue;
        }
        $data = file_get_contents($full);
        $info = ['mime' => 'image/webp'];
        $size = @getimagesize($full);
        if ($size && !empty($size['mime'])) {
            $info['mime'] = $size['mime'];
        }
        $before = strlen($data);
        $t = microtime(true);
        $out = ImageStore::compress($data, $info['mime']);
        $ms = round((microtime(true) - $t) * 1000);
        if ($out === null) {
            echo basename($full) . " ({$size[0]}x{$size[1]} " . fmt($before) . "): kept as-is\n";
            continue;
        }
        $after = strlen($out);
        $totalBefore += $before;
        $totalAfter += $after;
        // تحقق أن الناتج صورة سليمة بنفس الأبعاد المستهدفة
        $check = @getimagesizefromstring($out);
        echo basename($full) . " {$size[0]}x{$size[1]} " . fmt($before) . " => "
            . ($check ? $check[0] . 'x' . $check[1] . ' ' . $check['mime'] . ' ' : 'INVALID! ')
            . fmt($after) . ' (-' . round(100 * (1 - $after / $before)) . "%) in {$ms}ms\n";
        // ملف مؤقت للتأكد من صلاحيته ثم حذف
        $tmp = sys_get_temp_dir() . '/cmp_' . basename($full);
        file_put_contents($tmp, $out);
        unlink($tmp);
    }
    if ($totalBefore > 0) {
        echo 'sample total: ' . fmt($totalBefore) . ' => ' . fmt($totalAfter)
            . ' (-' . round(100 * (1 - $totalAfter / $totalBefore)) . "%)\n";
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n";
}
echo "done\n";