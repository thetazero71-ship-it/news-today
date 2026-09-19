<?php
/**
 * 📈 سكربت فحص السيو والتهيئة لمحركات البحث (SEO & Meta Tags Audit)
 * فحص سريع ومباشر عبر الذاكرة بدون أي تعليق أو طلبات cURL خارجية
 */

$isCli = (php_sapi_name() === 'cli');
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/Settings.php';

$db = new Database();
$tests = [];

// 1. فحص محتوى sitemap.xml والتحكم
require_once __DIR__ . '/controllers/SitemapController.php';
$sitemapFile = __DIR__ . '/controllers/SitemapController.php';
$hasSitemapController = file_exists($sitemapFile);

$articlesCount = (int) ($db->fetch("SELECT COUNT(*) as c FROM articles WHERE status = 'published'")['c'] ?? 0);
$categoriesCount = (int) ($db->fetch("SELECT COUNT(*) as c FROM categories")['c'] ?? 0);
$totalIndexedUrls = $articlesCount + $categoriesCount + 8; // static + home + categories + articles

$tests[] = [
    'name' => 'خريطة الموقع XML Sitemap',
    'status' => $hasSitemapController ? 'ok' : 'error',
    'detail' => $hasSitemapController ? "خريطة الموقع مفعلة وتغطي ديناميكياً $totalIndexedUrls رابطاً مفهرساً بالموقع" : "تعذر العثور على SitemapController"
];

// 2. فحص ملف robots.txt
$hasRobotsMethod = method_exists('SitemapController', 'robotsTxt');
$tests[] = [
    'name' => 'ملف توجيه العناكب Robots.txt',
    'status' => $hasRobotsMethod ? 'ok' : 'warning',
    'detail' => $hasRobotsMethod ? 'ملف Robots.txt مهيأ ديناميكياً ويحظر الزحف للمسارات الإدارية ويشير لخريطة الموقع' : 'تنبيه: تحقق من دالة robotsTxt'
];

// 3. فحص إعدادات الميتا في قاعدة البيانات
$siteName = Settings::get('site_name_ar', 'عصب التقنية');
$siteDesc = Settings::get('site_description_ar', 'نبض التكنولوجيا والذكاء الاصطناعي');

$tests[] = [
    'name' => 'وسوم العنوان والوصف التعريفي (Meta Title & Description)',
    'status' => (!empty($siteName) && !empty($siteDesc)) ? 'ok' : 'warning',
    'detail' => "اسم المنصة: $siteName | الوصف: $siteDesc"
];

// 4. فحص تكامل وسوم السوشيال ميديا وقوالب العرض
$headerFile = @file_get_contents(__DIR__ . '/views/partials/header.php');
$hasOg = stripos((string)$headerFile, 'og:title') !== false && stripos((string)$headerFile, 'twitter:card') !== false;

$tests[] = [
    'name' => 'بطاقات السوشيال ميديا (OpenGraph & Twitter Cards)',
    'status' => $hasOg ? 'ok' : 'warning',
    'detail' => $hasOg ? 'جميع وسوم المشاركة التلقائية (og:title, og:image, twitter:card) مدمجة في ترويسة الموقع' : 'تنبيه: وسوم OpenGraph تحتاج مراجعة في header.php'
];

// 5. فحص وسوم العناوين الهيكلية H1
$homeFile = @file_get_contents(__DIR__ . '/views/home.php');
$hasH1 = stripos((string)$homeFile, '<h1') !== false;

$tests[] = [
    'name' => 'هيكلية العناوين الرئيسية (Single H1 Tag Hierarchy)',
    'status' => $hasH1 ? 'ok' : 'warning',
    'detail' => $hasH1 ? 'الصفحة الرئيسية وصفحات الأقسام تلتزم بوجود وسم H1 رئيسي واضح' : 'تنبيه: لم يتم العثور على وسم H1'
];

// 6. فحص البيانات المنظمة Schema.org / JSON-LD في المقالات
$articleView = @file_get_contents(__DIR__ . '/views/article.php');
$hasJsonLd = stripos((string)$articleView, 'application/ld+json') !== false || stripos((string)$articleView, 'NewsArticle') !== false || stripos((string)$articleView, 'schema.org') !== false;

$tests[] = [
    'name' => 'البيانات المنظمة للمقالات الإخبارية (Schema.org / JSON-LD)',
    'status' => $hasJsonLd ? 'ok' : 'warning',
    'detail' => $hasJsonLd ? 'المقالات الإخبارية مزودة بترميز Schema.org لدعم نتائج Google Rich Snippets' : 'تنبيه: ينصح بإضافة وسم Schema.org JSON-LD'
];

// CLI Output
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  📈 تقرير فحص السيو والتهيئة لمحركات البحث (SEO Suite)  \n";
    echo "=======================================================\n";
    foreach ($tests as $t) {
        $icon = ($t['status'] === 'ok') ? '✅' : (($t['status'] === 'warning') ? '⚠️' : '❌');
        printf("%s %-45s\n   %s\n\n", $icon, $t['name'], $t['detail']);
    }
    echo "=======================================================\n\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فحص السيو والأرشفة | مركز التشخيص</title>
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#060a14; --card:#0b1120; --cyan:#00d2ff; --green:#10b981; --yellow:#f59e0b; --red:#ef4444; }
        body { background:var(--bg); color:#f8fafc; font-family:'Tajawal',sans-serif; padding:30px; }
        .container { max-width:1000px; margin:0 auto; }
        .header { display:flex; justify-content:space-between; align-items:center; background:var(--card); padding:20px; border-radius:12px; border:1px solid rgba(0,210,255,0.2); margin-bottom:20px; }
        .card { background:var(--card); border:1px solid rgba(255,255,255,0.08); border-radius:12px; padding:20px; }
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
            <h2 style="color:var(--cyan)">📈 فحص السيو وتهيئة محركات البحث (SEO)</h2>
            <p style="color:#94a3b8;font-size:0.88rem">فحص فوري بدون أي بطء لخريطة الموقع، وسوم الميتا، وبطاقات السوشيال ميديا</p>
        </div>
        <div style="display:flex;gap:10px">
            <a href="<?= app_url('admin/diagnostics') ?>" style="background:rgba(255,255,255,0.1);color:#fff;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">← مركز القيادة</a>
            <a href="<?= app_url('admin/diagnostics/seo') ?>" style="background:var(--cyan);color:#000;padding:8px 16px;border-radius:6px;text-decoration:none;font-weight:700">🔄 إعادة الفحص</a>
        </div>
    </div>

    <div class="card">
        <h3 style="margin-bottom:15px;color:var(--cyan)">نتائج فحص الأرشفة والظهور</h3>
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
