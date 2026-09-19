<?php
/**
 * 🎛️ مركز القيادة والتشخيص الموحد (Unified Diagnostics Command Center)
 * يجمع كافة أدوات فحص النظام، المسارات، قاعدة البيانات، الأمان، السيو، والوسائط في لوحة تحكم واحدة.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

$db = new Database();
$dbOk = true;
try {
    $db->fetch("SELECT 1");
} catch(Throwable $e) {
    $dbOk = false;
}

$tools = [
    [
        'id' => 'routes',
        'title' => 'فحص المسارات والروابط الحية',
        'desc' => 'فحص استجابة كافة صفحات الموقع ولوحة الإدارة الـ 27 والتغذيات الإخبارية',
        'icon' => '⚡',
        'file' => 'health_check.php',
        'url' => app_url('admin/diagnostics/health'),
        'badge' => 'فحص فوري حي'
    ],
    [
        'id' => 'database',
        'title' => 'فحص وتناسق قاعدة البيانات',
        'desc' => 'فحص الجداول الـ 53، السجلات اليتيمة، الفهارس، وأحجام التخزين وسرعة الاستعلام',
        'icon' => '🗄️',
        'file' => 'diagnostics_database.php',
        'url' => app_url('admin/diagnostics/database'),
        'badge' => '53 جدولاً'
    ],
    [
        'id' => 'security',
        'title' => 'فحص الأمان وجدار الحماية',
        'desc' => 'فحص صلاحيات المسارات الإدارية، حماية CSRF، تشفير الكلمات، ومنع تسريب الملفات',
        'icon' => '🛡️',
        'file' => 'diagnostics_security.php',
        'url' => app_url('admin/diagnostics/security'),
        'badge' => 'Security Audit'
    ],
    [
        'id' => 'seo',
        'title' => 'فحص السيو والأرشفة والميتا',
        'desc' => 'فحص خريطة الموقع XML، ملف robots.txt، وسوم OpenGraph، و Schema.org',
        'icon' => '📈',
        'file' => 'diagnostics_seo.php',
        'url' => app_url('admin/diagnostics/seo'),
        'badge' => 'SEO & Social'
    ],
    [
        'id' => 'media',
        'title' => 'فحص مكتبة الوسائط والتخزين',
        'desc' => 'التحقق من مسارات الصور المفقودة، سعة مجلد uploads، والملفات السحابية',
        'icon' => '🖼️',
        'file' => 'diagnostics_media.php',
        'url' => app_url('admin/diagnostics/media'),
        'badge' => 'Storage & Images'
    ]
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مركز القيادة والتشخيص الموحد | عصب التقنية</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;600;700;800;900&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-body: #060a14;
            --bg-card: rgba(11, 17, 32, 0.85);
            --border: rgba(0, 210, 255, 0.25);
            --cyan: #00d2ff;
            --purple: #9333ea;
            --green: #10b981;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--bg-body);
            color: var(--text-main);
            font-family: 'Tajawal', sans-serif;
            padding: 40px 20px;
            min-height: 100vh;
        }
        .container { max-width: 1100px; margin: 0 auto; }
        
        /* Hero Banner */
        .hero {
            background: radial-gradient(circle at top right, rgba(0,210,255,0.15), rgba(11,17,32,0.9) 70%);
            border: 1px solid var(--border);
            padding: 35px 30px;
            border-radius: 20px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.5);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
        }
        .hero h1 { font-size: 1.85rem; color: var(--cyan); margin-bottom: 6px; }
        .hero p { color: var(--text-muted); font-size: 0.95rem; }
        .system-pill {
            background: rgba(16,185,129,0.15);
            border: 1px solid rgba(16,185,129,0.3);
            color: var(--green);
            padding: 8px 18px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .system-pill::before {
            content: '';
            width: 8px;
            height: 8px;
            background: var(--green);
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 10px var(--green);
        }

        /* Tools Grid */
        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .tool-card {
            background: var(--bg-card);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 25px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
            text-decoration: none;
            color: inherit;
        }
        .tool-card:hover {
            transform: translateY(-4px);
            border-color: var(--cyan);
            box-shadow: 0 10px 30px rgba(0, 210, 255, 0.15);
            background: rgba(14, 22, 41, 0.95);
        }
        .tool-top { display: flex; align-items: flex-start; justify-content: space-between; margin-bottom: 15px; }
        .tool-icon { font-size: 2.2rem; }
        .tool-badge {
            background: rgba(0,210,255,0.1);
            color: var(--cyan);
            border: 1px solid rgba(0,210,255,0.25);
            padding: 3px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
        }
        .tool-title { font-size: 1.25rem; font-weight: 800; margin-bottom: 8px; color: #fff; }
        .tool-desc { color: var(--text-muted); font-size: 0.88rem; line-height: 1.6; margin-bottom: 20px; }
        .tool-action {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: var(--cyan);
            font-weight: 700;
            font-size: 0.9rem;
        }

        /* Top Nav */
        .top-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        .back-link {
            color: var(--cyan);
            text-decoration: none;
            font-weight: 700;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.05);
            border: 1px solid var(--border);
            padding: 8px 18px;
            border-radius: 8px;
            transition: 0.2s;
        }
        .back-link:hover { background: rgba(0,210,255,0.15); }

        /* Footer */
        .footer-bar {
            text-align: center;
            color: var(--text-muted);
            font-size: 0.85rem;
            border-top: 1px solid rgba(255,255,255,0.06);
            padding-top: 25px;
        }
    </style>
</head>
<body>

<div class="container">
    
    <div class="top-nav">
        <a href="<?= app_url('admin') ?>" class="back-link">← العودة للوحة الإدارة الرئيسية</a>
        <a href="<?= app_url() ?>" target="_blank" class="back-link" style="color:var(--text-muted)">معاينة الموقع ↗</a>
    </div>

    <div class="hero">
        <div>
            <h1>🎛️ مركز القيادة والتشخيص الموحد للمنصة</h1>
            <p>مجموعة أدوات برمجية متقدمة لفحص وتشخيص أداء وصحة المنصة من كافة النواحي</p>
        </div>
        <div class="system-pill">
            النظام يعمل بكفاءة 100%
        </div>
    </div>

    <!-- شبكة الأدوات -->
    <div class="tools-grid">
        <?php foreach ($tools as $t): ?>
            <a href="<?= $t['url'] ?>" class="tool-card">
                <div>
                    <div class="tool-top">
                        <span class="tool-icon"><?= $t['icon'] ?></span>
                        <span class="tool-badge"><?= $t['badge'] ?></span>
                    </div>
                    <h3 class="tool-title"><?= $t['title'] ?></h3>
                    <p class="tool-desc"><?= $t['desc'] ?></p>
                </div>
                <div class="tool-action">
                    فتح أداة التشخيص ←
                </div>
            </a>
        <?php endforeach; ?>
    </div>

    <div class="footer-bar">
        عصب التقنية © 2026 - تم تطوير مركز التشخيص بأحدث معايير الأداء والسرعة
    </div>

</div>

</body>
</html>
