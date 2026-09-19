<?php
/**
 * 🗄️ سكربت فحص سلامة وتناسق قاعدة البيانات والجداول (Database Integrity Suite)
 * التشغيل: php diagnostics_database.php أو عبر المتصفح: http://127.0.0.1:8000/diagnostics_database.php
 */

$isCli = (php_sapi_name() === 'cli');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

$db = new Database();
$tests = [];

// 1. فحص الاتصال والإصدار
try {
    $version = $db->fetch("SELECT VERSION() as v")['v'];
    $charset = $db->fetch("SELECT @@character_set_database as cs, @@collation_database as col");
    $tests[] = [
        'name' => 'الاتصال بمحرك MySQL / MariaDB',
        'status' => 'ok',
        'detail' => "الإصدار: $version | الترميز: {$charset['cs']} ({$charset['col']})"
    ];
} catch (Throwable $e) {
    $tests[] = ['name' => 'الاتصال بقاعدة البيانات', 'status' => 'error', 'detail' => $e->getMessage()];
}

// 2. فحص الجداول الأساسية
$expectedTables = [
    'articles', 'categories', 'users', 'comments', 'settings', 'live_blogs',
    'live_blog_entries', 'polls', 'poll_options', 'poll_votes', 'tutorials',
    'tutorial_steps', 'rss_sources', 'contact_messages', 'ads', 'newsletters',
    'newsletter_campaigns', 'media', 'pages', 'menus', 'menu_items', 'bookmarks',
    'activity_logs', 'security_alerts', 'translation_logs'
];

$existingTables = array_column($db->fetchAll("SHOW TABLES"), "Tables_in_" . DB_NAME);
$missingTables = array_diff($expectedTables, $existingTables);

$tests[] = [
    'name' => 'اكتمال الجداول الأساسية (' . count($expectedTables) . ' جدولاً)',
    'status' => empty($missingTables) ? 'ok' : 'error',
    'detail' => empty($missingTables) ? 'جميع الجداول الأساسية موجودة (' . count($existingTables) . ' جدولاً بالقاعدة)' : 'جداول مفقودة: ' . implode(', ', $missingTables)
];

// 3. فحص السجلات اليتيمة (Orphaned Records Integrity)
$orphans = [];

// مقالات بتصنيفات غير موجودة
$orphArts = $db->fetch("SELECT COUNT(*) as c FROM articles a LEFT JOIN categories c ON a.category_id = c.id WHERE a.category_id IS NOT NULL AND c.id IS NULL")['c'];
if ($orphArts > 0) $orphans[] = "$orphArts مقال مرتبط بتصنيف محذوف";

// تعليقات لمقالات محذوفة
$orphComms = $db->fetch("SELECT COUNT(*) as c FROM comments c LEFT JOIN articles a ON c.article_id = a.id WHERE a.id IS NULL")['c'];
if ($orphComms > 0) $orphans[] = "$orphComms تعليق لمقالات محذوفة";

// خيارات استطلاع لاستطلاعات محذوفة
$orphPollOpts = $db->fetch("SELECT COUNT(*) as c FROM poll_options po LEFT JOIN polls p ON po.poll_id = p.id WHERE p.id IS NULL")['c'];
if ($orphPollOpts > 0) $orphans[] = "$orphPollOpts خيار تصويت لاستطلاع محذوف";

// مفضلات لمقالات محذوفة
$orphBms = $db->fetch("SELECT COUNT(*) as c FROM bookmarks b LEFT JOIN articles a ON b.article_id = a.id WHERE a.id IS NULL")['c'];
if ($orphBms > 0) $orphans[] = "$orphBms إشارة مرجعية لمقال محذوف";

$tests[] = [
    'name' => 'سلامة العلاقات والروابط الخارجية (Foreign Keys & Orphans)',
    'status' => empty($orphans) ? 'ok' : 'warning',
    'detail' => empty($orphans) ? 'لا توجد أي سجلات معلقة أو مفقودة العلاقات' : 'تنبيه: ' . implode(' | ', $orphans)
];

// 4. فحص حجم الجداول وعدد السجلات
$tableStats = $db->fetchAll("
    SELECT table_name AS name, table_rows AS `rows`, 
           ROUND((data_length + index_length) / 1024 / 1024, 2) AS size_mb
    FROM information_schema.tables 
    WHERE table_schema = :db_name 
    ORDER BY (data_length + index_length) DESC LIMIT 10
", array(':db_name' => DB_NAME));

$totalDbSize = $db->fetch("
    SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS total_mb
    FROM information_schema.tables WHERE table_schema = :db_name
", array(':db_name' => DB_NAME))['total_mb'] ?? 0;

$tests[] = [
    'name' => 'حجم قاعدة البيانات واستهلاك التخزين',
    'status' => 'ok',
    'detail' => "الحجم الإجمالي: $totalDbSize MB عبر " . count($existingTables) . " جدولاً"
];

// 5. فحص أداء الفهارس وسرعة الاستعلام
$startQ = microtime(true);
$db->fetchAll("SELECT a.id, a.title, c.name FROM articles a JOIN categories c ON a.category_id = c.id ORDER BY a.id DESC LIMIT 100");
$queryTime = round((microtime(true) - $startQ) * 1000, 2);

$tests[] = [
    'name' => 'سرعة استجابة الاستعلامات المعقدة (JOIN + ORDER)',
    'status' => ($queryTime < 20) ? 'ok' : 'warning',
    'detail' => "زمن التنفيذ: $queryTime ms (" . ($queryTime < 20 ? "ممتاز جداً ⚡" : "مقبول") . ")"
];

// CLI Output
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  🗄️ تقرير فحص سلامة وتناسق قاعدة البيانات (DB Diagnostic)  \n";
    echo "=======================================================\n";
    foreach ($tests as $t) {
        $icon = ($t['status'] === 'ok') ? '✅' : (($t['status'] === 'warning') ? '⚠️' : '❌');
        printf("%s %-40s\n   %s\n\n", $icon, $t['name'], $t['detail']);
    }
    echo "---------------- أكبر 5 جداول حجماً -----------------\n";
    foreach (array_slice($tableStats, 0, 5) as $ts) {
        printf(" - %-25s : %-6s سجل (%s MB)\n", $ts['name'], number_format($ts['rows']), $ts['size_mb']);
    }
    echo "=======================================================\n\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص سلامة قاعدة البيانات | مركز التشخيص</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#060a14; --card:#0b1120; --cyan:#00d2ff; --green:#10b981; --yellow:#f59e0b; --red:#ef4444; }
        body { background:var(--bg); color:#f8fafc; font-family:'Tajawal',sans-serif; padding:30px; }
        .container { max-width:1000px; margin:0 auto; }
        .header { display:flex; justify-content:space-between; align-items:center; background:var(--card); padding:20px; border-radius:12px; border:1px solid rgba(0,210,255,0.2); margin-bottom:20px; }
        .card { background:var(--card); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; margin-bottom:15px; }
        .item { display:flex; justify-content:space-between; align-items:center; padding:12px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
        .badge { padding:4px 10px; border-radius:6px; font-weight:700; font-size:0.8rem; }
        .badge-ok { background:rgba(16,185,129,0.15); color:var(--green); border:1px solid rgba(16,185,129,0.3); }
        .badge-warning { background:rgba(245,158,11,0.15); color:var(--yellow); border:1px solid rgba(245,158,11,0.3); }
        .badge-error { background:rgba(239,68,68,0.15); color:var(--red); border:1px solid rgba(239,68,68,0.3); }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <div>
            <h2 style="color:var(--cyan)">🗄️ فحص سلامة وتناسق قاعدة البيانات</h2>
            <p style="color:#94a3b8;font-size:0.88rem">فحص الجداول، السجلات اليتيمة، الفهارس، ومعدلات الأداء</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= app_url('admin/diagnostics') ?>" style="background:rgba(255,255,255,0.1);color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">← مركز القيادة</a>
            <a href="<?= app_url('admin/diagnostics/database') ?>" style="background:var(--cyan);color:#000;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">🔄 إعادة الفحص</a>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-bottom:15px;color:var(--cyan)">نتائج الفحص</h3>
        <?php foreach ($tests as $t): ?>
            <div class="item">
                <div>
                    <strong style="display:block;margin-bottom:4px"><?= $t['name'] ?></strong>
                    <span style="color:#94a3b8;font-size:0.85rem"><?= $t['detail'] ?></span>
                </div>
                <span class="badge badge-<?= $t['status'] ?>"><?= strtoupper($t['status']) ?></span>
            </div>
        <?php endforeach; ?>
    </div>

    <div class="card">
        <h3 style="margin-bottom:15px;color:var(--cyan)">أكبر 10 جداول استهلاكاً للتخزين</h3>
        <table style="width:100%;text-align:right;border-collapse:collapse">
            <thead>
                <tr style="color:#94a3b8;border-bottom:1px solid rgba(255,255,255,0.1)">
                    <th style="padding:8px">اسم الجدول</th>
                    <th style="padding:8px">عدد السجلات</th>
                    <th style="padding:8px">حجم التخزين</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tableStats as $ts): ?>
                    <tr style="border-bottom:1px solid rgba(255,255,255,0.03)">
                        <td style="padding:8px;font-family:'JetBrains Mono'"><?= $ts['name'] ?></td>
                        <td style="padding:8px;font-family:'JetBrains Mono'"><?= number_format($ts['rows']) ?></td>
                        <td style="padding:8px;font-family:'JetBrains Mono';color:var(--cyan)"><?= $ts['size_mb'] ?> MB</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
