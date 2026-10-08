<?php
/**
 * ينشئ أقسام الموقع الافتراضية الناقصة (اقتصادية، صحية، رياضية، علوم، تقنية، اجتماعية).
 * العملية آمنة للتكرار: تُنشأ الأقسام الغائبة فقط ولا تُعدّل الموجودة.
 * يعمل تلقائياً عند فتح صفحة الأقسام أو تصنيف المصادر أو نشر خبر، وهذا الملف merely للتشغيل الفوري.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/CategorySeeder.php';

header('Content-Type: text/html; charset=utf-8');

$created = [];
$error = '';
$rows = [];

try {
    $db = new Database();
    $created = CategorySeeder::ensure($db);
    $rows = $db->fetchAll('SELECT id, name, slug, sort_order FROM categories ORDER BY sort_order, id');
} catch (Throwable $e) {
    $error = $e->getMessage();
}

echo '<!doctype html><html dir="rtl" lang="ar"><meta charset="utf-8"><title>أقسام الموقع</title>';
echo '<body style="font-family:system-ui;padding:24px">';
echo '<h2>أقسام الموقع</h2>';

if ($error !== '') {
    echo '<p style="color:#b00">خطأ: ' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</p>';
}

if ($created !== []) {
    echo '<p style="color:#070">تم إنشاء: ' . htmlspecialchars(implode('، ', $created), ENT_QUOTES, 'UTF-8') . '</p>';
} elseif ($error === '') {
    echo '<p>كل الأقسام الافتراضية موجودة مسبقاً.</p>';
}

echo '<table cellpadding="6" border="1" style="border-collapse:collapse">';
echo '<tr><th>id</th><th>الاسم</th><th>slug</th><th>sort</th></tr>';
foreach ($rows as $r) {
    echo '<tr><td>' . (int) $r['id'] . '</td><td>' . htmlspecialchars((string) $r['name'], ENT_QUOTES, 'UTF-8')
       . '</td><td>' . htmlspecialchars((string) $r['slug'], ENT_QUOTES, 'UTF-8')
       . '</td><td>' . (int) $r['sort_order'] . '</td></tr>';
}
echo '</table></body></html>';
