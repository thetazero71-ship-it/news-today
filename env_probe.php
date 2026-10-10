<?php
/**
 * فحص مؤقت: اختبار انتشار تغيير اسم المنصة (يُحذف بعد الاستخدام).
 *   ?set=<name>   -> يضبط الاسم الجديد
 *   ?restore=<b64>-> يعيد الاسم القديم
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Settings.php';

header('Content-Type: text/plain; charset=utf-8');

$db = new Database();

function currentName($db)
{
    $rows = $db->fetchAll("SELECT `value` FROM settings WHERE `key` = 'site_name_ar'");
    return $rows ? (string) $rows[0]['value'] : '';
}

if (isset($_GET['restore'])) {
    $old = base64_decode($_GET['restore'], true);
    if ($old === false || $old === '') {
        echo "bad restore payload\n";
        exit;
    }
    $new = currentName($db);
    $db->query("UPDATE settings SET `value` = ? WHERE `key` = 'site_name_ar'", [$old]);
    $db->query("UPDATE settings SET `value` = ? WHERE `key` = 'site_name'", [$old]);
    Settings::clear();
    require_once __DIR__ . '/core/Brand.php';
    Brand::flush();
    Brand::migrateReferences($db, $new, $old);
    Brand::flush();
    Brand::syncStaticFiles(__DIR__);
    echo "restored:" . base64_encode(currentName($db)) . "\n";
    echo "mf=" . base64_encode((string) @file_get_contents(__DIR__ . '/manifest.json')) . "\n";
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

    require_once __DIR__ . '/core/Brand.php';
    Brand::flush();
    if ($old !== '' && $old !== $new) {
        Brand::migrateReferences($db, $old, $new);
        Settings::clear();
        Brand::flush();
    }
    $sync = Brand::syncStaticFiles(__DIR__);

    echo "NEW_B64=" . base64_encode(currentName($db)) . "\n";
    echo "sync=" . json_encode($sync, JSON_UNESCAPED_UNICODE) . "\n";
    echo "mf=" . base64_encode((string) @file_get_contents(__DIR__ . '/manifest.json')) . "\n";
    echo "done\n";
    exit;
}

if (isset($_GET['sync'])) {
    require_once __DIR__ . '/core/Brand.php';
    Brand::flush();
    $sync = Brand::syncStaticFiles(__DIR__);
    echo "sync=" . json_encode($sync, JSON_UNESCAPED_UNICODE) . "\n";
    echo "mf=" . base64_encode((string) @file_get_contents(__DIR__ . '/manifest.json')) . "\n";
    echo "sw=" . base64_encode((string) @file_get_contents(__DIR__ . '/sw.js')) . "\n";
    exit;
}

echo "current:" . base64_encode(currentName($db)) . "\n";