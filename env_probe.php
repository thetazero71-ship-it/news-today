<?php
/**
 * فحص مؤقت: اختبار انتشار تغيير اسم المنصة (يُحذف بعد الاستخدام).
 *   ?set=<name>   -> يضبط الاسم الجديد
 *   ?restore      -> يعيد الاسم القديم (base64)
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/models/Settings.php';

header('Content-Type: text/plain; charset=utf-8');

$db = new Database();

function currentName($db)
{
    $r = $db->fetchOne("SELECT `value` FROM settings WHERE `key` = 'site_name_ar'");
    return $r ? (string) $r['value'] : '';
}

if (isset($_GET['restore'])) {
    $old = base64_decode($_GET['restore'], true);
    if ($old === false || $old === '') {
        echo "bad restore payload\n";
        exit;
    }
    $db->query("UPDATE settings SET `value` = ? WHERE `key` = 'site_name_ar'", [$old]);
    Settings::clear();
    echo "restored-to:" . currentName($db) . "\n";
    exit;
}

if (isset($_GET['set'])) {
    $old = currentName($db);
    echo "OLD_B64=" . base64_encode($old) . "\n";
    $new = (string) $_GET['set'];
    $db->query("UPDATE settings SET `value` = ? WHERE `key` = 'site_name_ar'", [$new]);
    $db->query("INSERT INTO settings (`group`, `key`, `value`) VALUES ('general', 'site_name', ?)
                ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)", [$new]);
    $db->query("UPDATE settings SET `value` = ? WHERE `key` = 'mail_from_name' AND (`value` IS NULL OR `value` = '')", [$new]);
    Settings::clear();

    // نفس ما يفعله حفظ الإعدادات على الملفات الثابتة
    require_once __DIR__ . '/core/Brand.php';
    Brand::flush();
    $sync = Brand::syncStaticFiles(__DIR__);

    echo "NEW_B64=" . base64_encode(currentName($db)) . "\n";
    echo "sync=" . json_encode($sync, JSON_UNESCAPED_UNICODE) . "\n";
    echo "manifest=" . trim((string) @file_get_contents(__DIR__ . '/manifest.json')) . "\n";
    echo "done\n";
    exit;
}

echo "current:" . currentName($db) . "\n";