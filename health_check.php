<?php
/**
 * 🛠️ مركز التشخيص والفحص الشامل لعصب التقنية
 * Tech News Platform - Full Diagnostics & Health Check Suite
 */

$isCli = (php_sapi_name() === 'cli');

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

$baseUrl = 'http://127.0.0.1:8000';

// 1. فحص الاتصال بقاعدة البيانات
$dbStatus = false;
$dbError = '';
$dbCounts = [];
try {
    $db = new Database();
    $dbStatus = true;
    $dbCounts['articles'] = (int) ($db->fetch('SELECT COUNT(*) as c FROM articles')['c'] ?? 0);
    $dbCounts['categories'] = (int) ($db->fetch('SELECT COUNT(*) as c FROM categories')['c'] ?? 0);
    $dbCounts['users'] = (int) ($db->fetch('SELECT COUNT(*) as c FROM users')['c'] ?? 0);
    $dbCounts['comments'] = (int) ($db->fetch('SELECT COUNT(*) as c FROM comments')['c'] ?? 0);
} catch (Throwable $e) {
    $dbError = $e->getMessage();
}

// 2. قائمة المسارات المعتمدة للفحص
$routes = [
    // 🌐 الصفحات العامة
    ['group' => 'الصفحات العامة', 'url' => '/', 'name' => 'الصفحة الرئيسية'],
    ['group' => 'الصفحات العامة', 'url' => '/tutorials', 'name' => 'أكاديمية الشروحات والدروس'],
    ['group' => 'الصفحات العامة', 'url' => '/bookmarks', 'name' => 'المقالات المحفوظة والمفضلة'],
    ['group' => 'الصفحات العامة', 'url' => '/search?q=' . urlencode('ذكاء'), 'name' => 'البحث المتقدم'],
    ['group' => 'الصفحات العامة', 'url' => '/contact', 'name' => 'اتصل بنا'],
    ['group' => 'الصفحات العامة', 'url' => '/about', 'name' => 'من نحن'],
    ['group' => 'الصفحات العامة', 'url' => '/privacy', 'name' => 'سياسة الخصوصية'],
    ['group' => 'الصفحات العامة', 'url' => '/terms', 'name' => 'الشروط والأحكام'],
    ['group' => 'الصفحات العامة', 'url' => '/login', 'name' => 'تسجيل الدخول'],
    ['group' => 'الصفحات العامة', 'url' => '/register', 'name' => 'إنشاء حساب جديد'],
    ['group' => 'الصفحات العامة', 'url' => '/profile', 'name' => 'الملف الشخصي للمستخدم'],

    // 🤖 محركات البحث وتغذيات RSS
    ['group' => 'محركات البحث و RSS', 'url' => '/sitemap.xml', 'name' => 'خريطة الموقع Sitemap'],
    ['group' => 'محركات البحث و RSS', 'url' => '/rss.xml', 'name' => 'خلاصة RSS الرئيسية'],
    ['group' => 'محركات البحث و RSS', 'url' => '/feed', 'name' => 'تغذية Feed'],
    ['group' => 'محركات البحث و RSS', 'url' => '/robots.txt', 'name' => 'ملف روبوتات البحث Robots.txt'],
    ['group' => 'محركات البحث و RSS', 'url' => '/ads.txt', 'name' => 'ملف الإعلانات Ads.txt'],

    // 🛡️ لوحة التحكم - الأقسام الرئيسية الـ 27
    ['group' => 'لوحة الإدارة', 'url' => '/admin', 'name' => 'لوحة التحكم الرئيسية'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/analytics', 'name' => 'مركز الإحصائيات والتحليلات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/articles', 'name' => 'إدارة المقالات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/articles/create', 'name' => 'إنشاء مقال جديد'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/categories', 'name' => 'إدارة التصنيفات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/categories/create', 'name' => 'إنشاء تصنيف جديد'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/tutorials', 'name' => 'إدارة الشروحات والدروس'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/tutorials/create', 'name' => 'إنشاء درس جديد'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/news-feeds', 'name' => 'مجمع الأخبار الفوري'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/rss-sources', 'name' => 'مصادر خلاصات RSS'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/rss-sources/create', 'name' => 'إضافة مصدر RSS'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/classifier-rules', 'name' => 'قواعد التصنيف الذكي AI'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/comments', 'name' => 'إدارة ومراجعة التعليقات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/comments?status=pending', 'name' => 'التعليقات المعلقة'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/comments?status=approved', 'name' => 'التعليقات المعتمدة'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/comments?status=rejected', 'name' => 'التعليقات المرفوضة'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/comments?status=spam', 'name' => 'تعليقات السبام'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/messages', 'name' => 'صندوق رسائل التواصل'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/live-blog', 'name' => 'إدارة التغطيات الحية'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/live-blog/create', 'name' => 'إنشاء تغطية حية جديدة'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/polls', 'name' => 'إدارة استطلاعات الرأي'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/polls/create', 'name' => 'إنشاء استطلاع جديد'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/users', 'name' => 'إدارة المستخدمين والأعضاء'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/settings', 'name' => 'إعدادات الموقع والقوالب'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/api-keys', 'name' => 'مفاتيح API ومزودو الذكاء'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/translation-logs', 'name' => 'سجلات الترجمة والـ AI'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/media', 'name' => 'مكتبة الوسائط والصور'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/pages', 'name' => 'إدارة الصفحات الثابتة'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/pages/create', 'name' => 'إنشاء صفحة ثابتة'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/menus', 'name' => 'إدارة القوائم والروابط'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/ads', 'name' => 'إدارة الإعلانات والمساحات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/newsletter', 'name' => 'النشرات البريدية والمشتركون'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/activity-log', 'name' => 'سجل العمليات والنشاطات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/traffic-radar', 'name' => 'رادار الزيارات وروبوتات الزحف'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/security-alerts', 'name' => 'تنبيهات وجدار الحماية'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/backup', 'name' => 'النسخ الاحتياطي وتصدير البيانات'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/cron', 'name' => 'المهام المجدولة Cron'],
    ['group' => 'لوحة الإدارة', 'url' => '/admin/profile', 'name' => 'الملف الشخصي للمسؤول']
];

if ($dbStatus) {
    $arts = $db->fetchAll("SELECT slug, id, title FROM articles WHERE status = 'published' ORDER BY id DESC LIMIT 5");
    foreach ($arts as $a) {
        $routes[] = ['group' => 'المقالات المنشورة', 'url' => '/article/' . rawurlencode($a['slug']), 'name' => mb_substr($a['title'], 0, 45) . '...'];
        $routes[] = ['group' => 'لوحة الإدارة', 'url' => '/admin/articles/' . $a['id'] . '/edit', 'name' => 'تعديل مقال #' . $a['id']];
    }

    $cats = $db->fetchAll('SELECT slug, id, name FROM categories LIMIT 6');
    foreach ($cats as $c) {
        $routes[] = ['group' => 'أقسام الموقع', 'url' => '/category/' . $c['slug'], 'name' => 'قسم: ' . $c['name']];
        $routes[] = ['group' => 'محركات البحث و RSS', 'url' => '/feed/' . $c['slug'], 'name' => 'خلاصة قسم: ' . $c['name']];
    }

    // فحص روابط الإجراءات (صفحات تعديل GET) لكل الموارد بأحدث ID حقيقي
    $detailResources = [
        'admin/articles'          => 'articles',
        'admin/categories'        => 'categories',
        'admin/users'             => 'users',
        'admin/rss-sources'       => 'rss_sources',
        'admin/ads'               => 'ads',
        'admin/pages'             => 'pages',
        'admin/menus'             => 'menus',
        'admin/polls'             => 'polls',
        'admin/tutorials'         => 'tutorials',
    ];
    foreach ($detailResources as $resource => $table) {
        try {
            $rid = (int) ($db->fetch("SELECT MAX(id) as id FROM $table")['id'] ?? 0);
            if ($rid > 0) {
                $short = str_replace('admin/', '', $resource);
                $routes[] = ['group' => 'روابط الإجراءات', 'url' => "/$resource/$rid/edit", 'name' => "تعديل $short #$rid"];
            }
        } catch (Throwable $e) {
            // جدول غير موجود أو غير قابل للفحص — نتجاهل
        }
    }
}

// ==========================================
// 🖥️ تنفيذ الفحص في وضع CLI
// ==========================================
if ($isCli) {
    $cookieFile = sys_get_temp_dir() . '/tech_cli_cookie.txt';
    @unlink($cookieFile);

    // Login for CLI
    $ch = curl_init($baseUrl . '/login');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
    curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
    $loginHtml = curl_exec($ch);
    curl_close($ch);

    $cliEmail = getenv('TECH_ADMIN_EMAIL') ?: 'admin@test.com';
    $cliPass  = getenv('TECH_ADMIN_PASSWORD');
    if ($cliPass && $loginHtml && preg_match('/name=\"_csrf\"\s+value=\"([^\"]+)\"/', $loginHtml, $m)) {
        $ch = curl_init($baseUrl . '/login');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            '_csrf'    => $m[1],
            'email'    => $cliEmail,
            'password' => $cliPass
        ]));
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_exec($ch);
        curl_close($ch);
    }

    echo "\n=======================================================\n";
    echo "  🚀 تقرير الفحص والتشخيص الشامل لعصب التقنية  \n";
    echo "=======================================================\n";
    echo "قاعدة البيانات: " . ($dbStatus ? "متصلة بنجاح ✅" : "فشل الاتصال ❌ ($dbError)") . "\n";
    echo "عدد المسارات: " . count($routes) . "\n\n";

    $passed = 0; $failed = 0;
    foreach ($routes as $r) {
        $start = microtime(true);
        $ch = curl_init($baseUrl . $r['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $cookieFile);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        $content = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = round((microtime(true) - $start) * 1000, 1);
        curl_close($ch);

        $icon = ($httpCode >= 200 && $httpCode < 400) ? '✅' : '❌';
        if ($httpCode >= 200 && $httpCode < 400) $passed++; else $failed++;
        printf("%s [%-3d] %-35s %-45s (%s ms)\n", $icon, $httpCode, mb_substr($r['name'], 0, 32), $r['url'], $duration);
    }
    @unlink($cookieFile);
    echo "\n=======================================================\n";
    echo "النتيجة: الناجحة: $passed | الفاشلة: $failed\n";
    echo "=======================================================\n";
    exit($failed > 0 ? 1 : 0);
}

// ==========================================
// 🌐 العرض التفاعلي السريع في المتصفح عبر AJAX
// ==========================================
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مركز التشخيص وفحص الروابط الفوري</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #060a14;
            --bg-card: rgba(11, 17, 32, 0.9);
            --border: rgba(0, 210, 255, 0.2);
            --cyan: #00d2ff;
            --purple: #9333ea;
            --green: #10b981;
            --yellow: #f59e0b;
            --red: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --font-family: 'Tajawal', sans-serif;
            --font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: var(--font-family);
            padding: 30px 20px;
            min-height: 100vh;
        }
        .container { max-width: 1200px; margin: 0 auto; }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--bg-card);
            border: 1px solid var(--border);
            padding: 20px 25px;
            border-radius: 16px;
            margin-bottom: 25px;
            backdrop-filter: blur(12px);
        }
        .header-title h1 { font-size: 1.5rem; color: var(--cyan); margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .header-title p { color: var(--text-muted); font-size: 0.88rem; }
        .btn-refresh {
            background: linear-gradient(135deg, var(--cyan), var(--purple));
            color: #fff;
            border: none;
            padding: 10px 22px;
            border-radius: 8px;
            font-family: inherit;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s;
        }
        .btn-refresh:hover { opacity: 0.9; transform: translateY(-1px); }

        /* Progress Bar */
        .progress-box {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
        }
        .progress-header { display: flex; justify-content: space-between; font-size: 0.88rem; margin-bottom: 8px; }
        .progress-track { height: 8px; background: rgba(255,255,255,0.06); border-radius: 4px; overflow: hidden; }
        .progress-fill { height: 100%; width: 0%; background: linear-gradient(90deg, var(--cyan), var(--green)); transition: width 0.15s ease; }

        /* Metrics */
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .metric-card {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 12px;
            padding: 18px;
            text-align: center;
        }
        .metric-value { font-size: 1.8rem; font-weight: 800; font-family: var(--font-mono); margin: 6px 0; }
        .metric-label { font-size: 0.85rem; color: var(--text-muted); }

        /* Controls */
        .controls { display: flex; gap: 10px; margin-bottom: 20px; flex-wrap: wrap; align-items: center; }
        .filter-btn {
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
            color: var(--text-main);
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            font-family: inherit;
            font-size: 0.85rem;
        }
        .filter-btn.active, .filter-btn:hover {
            background: rgba(0, 210, 255, 0.15);
            border-color: var(--cyan);
            color: var(--cyan);
        }
        .search-box {
            margin-right: auto;
            background: rgba(0,0,0,0.4);
            border: 1px solid var(--border);
            color: #fff;
            padding: 8px 14px;
            border-radius: 8px;
            font-family: inherit;
            min-width: 250px;
        }

        /* Table */
        .table-wrap {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        table { width: 100%; border-collapse: collapse; text-align: right; }
        th {
            background: rgba(0,0,0,0.4);
            padding: 14px 18px;
            font-size: 0.85rem;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border);
        }
        td {
            padding: 12px 18px;
            font-size: 0.88rem;
            border-bottom: 1px solid rgba(255,255,255,0.04);
        }
        tr:hover td { background: rgba(0, 210, 255, 0.03); }
        .badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-family: var(--font-mono);
            font-weight: 700;
            font-size: 0.78rem;
        }
        .badge-loading { background: rgba(255,255,255,0.06); color: var(--text-muted); }
        .badge-200 { background: rgba(16, 185, 129, 0.15); color: var(--green); border: 1px solid rgba(16, 185, 129, 0.3); }
        .badge-302 { background: rgba(245, 158, 11, 0.15); color: var(--yellow); border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-error { background: rgba(239, 68, 68, 0.15); color: var(--red); border: 1px solid rgba(239, 68, 68, 0.3); }
        .url-link { color: var(--cyan); text-decoration: none; font-family: var(--font-mono); font-size: 0.82rem; }
        .url-link:hover { text-decoration: underline; }
        .group-tag { font-size: 0.75rem; color: var(--text-muted); background: rgba(255,255,255,0.05); padding: 2px 8px; border-radius: 4px; }
    </style>
</head>
<body>

<div class="container">
    
    <div class="header">
        <div class="header-title">
            <h1>⚡ مركز التشخيص الشامل وفحص الروابط</h1>
            <p>أداة فورية لاختبار استجابة كافة صفحات وعمليات الموقع بدون أي بطء أو تجميد</p>
        </div>
        <button onclick="startFullDiagnostics()" class="btn-refresh">🔄 بدء الفحص الفوري</button>
    </div>

    <!-- شريط التقدم الحي -->
    <div class="progress-box">
        <div class="progress-header">
            <span id="progressText">جاهز لبدء الفحص التفاعلي...</span>
            <span id="progressPercent" style="font-family:var(--font-mono);font-weight:700">0%</span>
        </div>
        <div class="progress-track">
            <div class="progress-fill" id="progressBar"></div>
        </div>
    </div>

    <!-- بطاقات العدادات -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-label">إجمالي المسارات</div>
            <div class="metric-value" style="color:var(--cyan)"><?= count($routes) ?></div>
        </div>
        <div class="metric-card">
            <div class="metric-label">المسارات السليمة (200 OK)</div>
            <div class="metric-value" id="countOk" style="color:var(--green)">0</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">إعادة التوجيه (302)</div>
            <div class="metric-value" id="countRedirect" style="color:var(--yellow)">0</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">الأخطاء (404 / 500)</div>
            <div class="metric-value" id="countError" style="color:var(--green)">0</div>
        </div>
        <div class="metric-card">
            <div class="metric-label">متوسط الاستجابة</div>
            <div class="metric-value" id="avgTime" style="color:var(--text-main)">-- ms</div>
        </div>
    </div>

    <!-- أزرار التصفية والبحث -->
    <div class="controls">
        <button class="filter-btn active" onclick="filterTable('all', event)">الكل</button>
        <button class="filter-btn" onclick="filterTable('error', event)">الأخطاء فقط</button>
        <button class="filter-btn" onclick="filterTable('ok', event)">الناجحة 200</button>
        <button class="filter-btn" onclick="filterTable('redirect', event)">إعادة التوجيه 302</button>
        <input type="text" id="searchInput" class="search-box" placeholder="🔍 بحث في الروابط أو الأسماء..." onkeyup="searchTable()">
    </div>

    <!-- جدول المسارات -->
    <div class="table-wrap">
        <table id="diagnosticsTable">
            <thead>
                <tr>
                    <th>الحالة</th>
                    <th>اسم الصفحة / العملية</th>
                    <th>القسم</th>
                    <th>الرابط المستهدف</th>
                    <th>زمن الاستجابة</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($routes as $idx => $r): ?>
                    <tr id="row-<?= $idx ?>" data-status="pending" data-url="<?= htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') ?>">
                        <td class="status-cell">
                            <span class="badge badge-loading">انتظار...</span>
                        </td>
                        <td><strong><?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?></strong></td>
                        <td><span class="group-tag"><?= htmlspecialchars($r['group'], ENT_QUOTES, 'UTF-8') ?></span></td>
                        <td><a href="<?= htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="url-link"><?= htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') ?></a></td>
                        <td class="time-cell" style="font-family:var(--font-mono);color:var(--text-muted)">-- ms</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

</div>

<script>
const routes = <?= json_encode($routes) ?>;
let countOk = 0;
let countRedirect = 0;
let countError = 0;
let totalTime = 0;
let completedCount = 0;

async function checkSingleRoute(route, index) {
    const row = document.getElementById('row-' + index);
    const statusCell = row.querySelector('.status-cell');
    const timeCell = row.querySelector('.time-cell');

    const startTime = performance.now();
    try {
        const response = await fetch(route.url, { method: 'GET', redirect: 'manual' });
        const duration = Math.round(performance.now() - startTime);
        totalTime += duration;

        let statusClass = 'badge-200';
        let statusText = '200 OK';
        let rowStatus = 'ok';

        if (response.status >= 200 && response.status < 300) {
            countOk++;
        } else if (response.status >= 300 && response.status < 400 || response.type === 'opaqueredirect') {
            statusClass = 'badge-302';
            statusText = '302 REDIRECT';
            rowStatus = 'redirect';
            countRedirect++;
        } else {
            statusClass = 'badge-error';
            statusText = response.status + ' ERROR';
            rowStatus = 'error';
            countError++;
        }

        row.setAttribute('data-status', rowStatus);
        statusCell.innerHTML = `<span class="badge ${statusClass}">${statusText}</span>`;
        timeCell.innerText = duration + ' ms';

    } catch (err) {
        const duration = Math.round(performance.now() - startTime);
        countError++;
        row.setAttribute('data-status', 'error');
        statusCell.innerHTML = `<span class="badge badge-error">ERR_NETWORK</span>`;
        timeCell.innerText = duration + ' ms';
    }

    completedCount++;
    updateCounters();
}

function updateCounters() {
    document.getElementById('countOk').innerText = countOk;
    document.getElementById('countRedirect').innerText = countRedirect;
    const errEl = document.getElementById('countError');
    errEl.innerText = countError;
    if (countError > 0) errEl.style.color = 'var(--red)';

    const percent = Math.round((completedCount / routes.length) * 100);
    document.getElementById('progressBar').style.width = percent + '%';
    document.getElementById('progressPercent').innerText = percent + '%';
    document.getElementById('progressText').innerText = `تم فحص ${completedCount} من أصل ${routes.length} مسار...`;

    if (completedCount > 0) {
        document.getElementById('avgTime').innerText = Math.round(totalTime / completedCount) + ' ms';
    }

    if (completedCount === routes.length) {
        document.getElementById('progressText').innerText = countError === 0 
            ? '🎉 اكتمل الفحص الشامل بنجاح! جميع الصفحات تعمل 100% بدون أي أخطاء.' 
            : `⚠️ اكتمل الفحص مع وجود ${countError} خطأ يتطلب المراجعة.`;
    }
}

async function startFullDiagnostics() {
    countOk = 0; countRedirect = 0; countError = 0; totalTime = 0; completedCount = 0;
    document.querySelectorAll('#diagnosticsTable tbody tr').forEach(r => {
        r.setAttribute('data-status', 'pending');
        r.querySelector('.status-cell').innerHTML = '<span class="badge badge-loading">جارِ الفحص...</span>';
        r.querySelector('.time-cell').innerText = '-- ms';
    });

    // Run in parallel chunks of 5 for optimal performance
    const chunkSize = 5;
    for (let i = 0; i < routes.length; i += chunkSize) {
        const chunk = routes.slice(i, i + chunkSize);
        await Promise.all(chunk.map((route, idx) => checkSingleRoute(route, i + idx)));
    }
}

function filterTable(status, event) {
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    if (event) event.target.classList.add('active');
    
    const rows = document.querySelectorAll('#diagnosticsTable tbody tr');
    rows.forEach(row => {
        if (status === 'all' || row.getAttribute('data-status') === status) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

function searchTable() {
    const q = document.getElementById('searchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#diagnosticsTable tbody tr');
    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

// Start auto diagnostics on load
window.addEventListener('DOMContentLoaded', () => {
    startFullDiagnostics();
});
</script>

</body>
</html>
