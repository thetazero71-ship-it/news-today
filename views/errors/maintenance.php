<?php
/**
 * Maintenance mode page (HTTP 503).
 *
 * Standalone on purpose: it must render even when the public stylesheet, the
 * database-heavy header, or the page cache are unavailable. No admin login
 * link is exposed to visitors.
 */
$brandName = class_exists('Brand') ? Brand::name() : 'المنصة';
$brandTagline = class_exists('Brand') ? Brand::tagline() : '';
$brandLogo = class_exists('Brand') ? Brand::logoUrl() : '';
$message = '';
try {
    $message = trim((string) Settings::get('maintenance_message', ''));
} catch (Throwable $e) {
    $message = '';
}
if ($message === '') {
    $message = 'نُجري أعمال صيانة دورية لتحسين المنصة. سنعود قريباً بإذن الله.';
}
$initial = function_exists('mb_substr') ? mb_substr($brandName, 0, 1, 'UTF-8') : substr($brandName, 0, 1);
?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta http-equiv="refresh" content="60">
    <title>صيانة مؤقتة · <?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 28px 18px; font-family: "Segoe UI", Tahoma, Arial, sans-serif; color: #e2e8f0;
            background:
                radial-gradient(circle at 20% 10%, rgba(34, 211, 238, 0.18), transparent 45%),
                radial-gradient(circle at 85% 90%, rgba(59, 130, 246, 0.16), transparent 45%),
                #070b14;
        }
        .card {
            width: 100%; max-width: 720px; text-align: center; padding: 42px 30px 34px;
            border-radius: 26px; border: 1px solid rgba(148, 163, 184, 0.22);
            background: rgba(15, 23, 42, 0.72); backdrop-filter: blur(14px);
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.55);
        }
        .emblem {
            width: 132px; height: 132px; margin: 0 auto 18px; border-radius: 34px;
            display: flex; align-items: center; justify-content: center;
            background: radial-gradient(circle at 30% 20%, rgba(34, 211, 238, 0.35), rgba(15, 23, 42, 0.9));
            border: 1px solid rgba(34, 211, 238, 0.45);
            box-shadow: 0 0 45px rgba(34, 211, 238, 0.35), inset 0 0 24px rgba(34, 211, 238, 0.18);
        }
        .emblem img { width: 92px; height: 92px; object-fit: contain; border-radius: 22px; }
        .emblem span { font-size: 3rem; font-weight: 800; color: #22d3ee; }
        .kicker {
            display: inline-flex; align-items: center; gap: 8px; margin: 0 0 12px;
            padding: 6px 14px; border-radius: 999px; font-size: 0.78rem; font-weight: 700;
            letter-spacing: 0.4px; color: #67e8f9;
            background: rgba(34, 211, 238, 0.1); border: 1px solid rgba(34, 211, 238, 0.3);
        }
        .dot { width: 8px; height: 8px; border-radius: 50%; background: #22d3ee; box-shadow: 0 0 12px #22d3ee; animation: pulse 1.6s infinite; }
        @keyframes pulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.45; transform: scale(0.8); } }
        h1 { margin: 0 0 6px; font-size: clamp(1.5rem, 4vw, 2.1rem); color: #f8fafc; }
        .tagline { margin: 0 0 18px; font-size: 0.95rem; color: #94a3b8; }
        .message {
            margin: 0 auto 26px; max-width: 560px; padding: 18px 20px; border-radius: 16px;
            background: rgba(30, 41, 59, 0.7); border: 1px solid rgba(148, 163, 184, 0.18);
            font-size: 1.02rem; line-height: 1.9; color: #e2e8f0;
        }
        .meta { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-bottom: 24px; }
        .chip {
            display: inline-flex; align-items: center; gap: 7px; padding: 8px 14px; border-radius: 999px;
            font-size: 0.8rem; color: #cbd5e1; background: rgba(15, 23, 42, 0.85);
            border: 1px solid rgba(148, 163, 184, 0.2);
        }
        .chip strong { color: #22d3ee; font-weight: 700; }
        .actions { display: flex; gap: 10px; justify-content: center; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 8px; padding: 11px 22px; border-radius: 12px;
            border: 1px solid rgba(34, 211, 238, 0.45); background: rgba(34, 211, 238, 0.12);
            color: #67e8f9; font-weight: 700; font-size: 0.9rem; cursor: pointer; text-decoration: none;
        }
        .btn:hover { background: rgba(34, 211, 238, 0.2); }
        .foot { margin-top: 26px; font-size: 0.75rem; color: #64748b; }
        @media (max-width: 520px) { .card { padding: 32px 18px 26px; } .emblem { width: 108px; height: 108px; } }
    </style>
</head>
<body>
    <main class="card" role="main">
        <div class="emblem">
            <?php if ($brandLogo !== ''): ?>
                <img src="<?= htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?>">
            <?php else: ?>
                <span><?= htmlspecialchars($initial, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </div>

        <p class="kicker"><span class="dot"></span> صيانة مجدولة</p>
        <h1><?= htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if ($brandTagline !== ''): ?>
            <p class="tagline"><?= htmlspecialchars($brandTagline, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <div class="message"><?= nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8')) ?></div>

        <div class="meta">
            <span class="chip"><strong>503</strong> خدمة غير متاحة مؤقتاً</span>
            <span class="chip">تحديث تلقائي كل <strong>60</strong> ثانية</span>
        </div>

        <div class="actions">
            <button type="button" class="btn" onclick="window.location.reload()">إعادة المحاولة الآن</button>
        </div>

        <p class="foot">نعتذر عن الإزعاج — سنعود في أقرب وقت.</p>
    </main>
</body>
</html>
