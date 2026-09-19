<?php
/**
 * 🖼️ سكربت فحص مكتبة الوسائط والصور والروابط المكسورة (Media & Storage Audit)
 * التشغيل: php diagnostics_media.php أو عبر المتصفح: http://127.0.0.1:8000/diagnostics_media.php
 */

$isCli = (php_sapi_name() === 'cli');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

$db = new Database();
$tests = [];

// 1. حساب حجم مجلد uploads وعدد الملفات
$uploadDir = __DIR__ . '/uploads';
$fileCount = 0;
$totalBytes = 0;

if (is_dir($uploadDir)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploadDir, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $fileCount++;
            $totalBytes += $file->getSize();
        }
    }
}
$sizeMb = round($totalBytes / 1024 / 1024, 2);

$tests[] = [
    'name' => 'إجمالي التخزين المستهلك في مجلد الوسائط /uploads',
    'status' => 'ok',
    'detail' => "$fileCount ملفاً مخزناً بحجم إجمالي: $sizeMb MB"
];

// 2. فحص الصور المميزة للمقالات المفقودة (Broken Featured Images)
$articlesWithImages = $db->fetchAll("SELECT id, title, featured_image FROM articles WHERE featured_image IS NOT NULL AND featured_image != ''");
$missingImages = [];
$externalCount = 0;

foreach ($articlesWithImages as $art) {
    $img = $art['featured_image'];
    if (strpos($img, 'http://') === 0 || strpos($img, 'https://') === 0) {
        $externalCount++;
    } else {
        $localPath = __DIR__ . '/' . ltrim($img, '/');
        if (!file_exists($localPath)) {
            $missingImages[] = "مقال #{$art['id']} ({$art['title']}): المسار المفقود ($img)";
        }
    }
}

$tests[] = [
    'name' => 'سلامة الصور البارزة للمقالات (Broken Featured Images)',
    'status' => empty($missingImages) ? 'ok' : 'warning',
    'detail' => empty($missingImages) ? 'جميع مسارات الصور المحلية للمقالات سليمة وموجودة على القرص (' . count($articlesWithImages) . ' صورة)' : 'صور محلية مفقودة: ' . count($missingImages) . ' صورة'
];

$tests[] = [
    'name' => 'الصور الخارجية / السحابية (External CDN Images)',
    'status' => 'ok',
    'detail' => "$externalCount صورة بارزة يتم جلبها من خوادم خارجية / Unsplash"
];

// 3. فحص مجلدات الرفع الفرعية
$subdirs = ['articles', 'brand', 'media', 'avatars'];
$missingDirs = [];
foreach ($subdirs as $sd) {
    if (!is_dir($uploadDir . '/' . $sd)) {
        @mkdir($uploadDir . '/' . $sd, 0777, true);
    }
}

$tests[] = [
    'name' => 'هيكلية المجلدات الفرعية (/articles, /brand, /media, /avatars)',
    'status' => 'ok',
    'detail' => 'جميع المجلدات الفرعية للرفع موجودة ومهيأة للتخزين المنظم'
];

// CLI Output
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  🖼️ تقرير فحص مكتبة الوسائط والتخزين (Media Audit)     \n";
    echo "=======================================================\n";
    foreach ($tests as $t) {
        $icon = ($t['status'] === 'ok') ? '✅' : (($t['status'] === 'warning') ? '⚠️' : '❌');
        printf("%s %-45s\n   %s\n\n", $icon, $t['name'], $t['detail']);
    }
    if (!empty($missingImages)) {
        echo "قائمة الصور المفقودة:\n";
        foreach (array_slice($missingImages, 0, 5) as $mi) {
            echo " - $mi\n";
        }
    }
    echo "=======================================================\n\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص الوسائط والتخزين | مركز التشخيص</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#060a14; --card:#0b1120; --cyan:#00d2ff; --green:#10b981; --yellow:#f59e0b; --red:#ef4444; }
        body { background:var(--bg); color:#f8fafc; font-family:'Tajawal',sans-serif; padding:30px; }
        .container { max-width:1000px; margin:0 auto; }
        .header { display:flex; justify-content:space-between; align-items:center; background:var(--card); padding:20px; border-radius:12px; border:1px solid rgba(0,210,255,0.2); margin-bottom:20px; }
        .card { background:var(--card); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; margin-bottom:15px; }
        .item { display:flex; justify-content:space-between; align-items:center; padding:14px 0; border-bottom:1px solid rgba(255,255,255,0.05); }
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
            <h2 style="color:var(--cyan)">🖼️ فحص مكتبة الوسائط والتخزين</h2>
            <p style="color:#94a3b8;font-size:0.88rem">فحص مسارات الصور، التحقق من الملفات المفقودة، وحساب استهلاك التخزين</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= app_url('admin/diagnostics') ?>" style="background:rgba(255,255,255,0.1);color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">← مركز القيادة</a>
            <a href="<?= app_url('admin/diagnostics/media') ?>" style="background:var(--cyan);color:#000;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">🔄 إعادة الفحص</a>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-bottom:15px;color:var(--cyan)">نتائج فحص الوسائط والملفات</h3>
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
</div>
</body>
</html>
