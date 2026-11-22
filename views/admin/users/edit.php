<?php
$activity = $activity ?? [];
$actSessions = $activity['sessions'] ?? [];
$actLogs = $activity['logs'] ?? [];
$lastSeen = (string) ($activity['last_seen'] ?? '');
$publishedCount = (int) ($publishedCount ?? 0);
$commentsCount = (int) ($commentsCount ?? 0);
$isActive = ($user['status'] ?? '') === 'active';
$isVerified = !empty($user['email_verified_at']);
$statusLabel = $isActive ? 'نشط' : (($user['status'] ?? '') === 'banned' ? 'محظور' : 'معلق');
$roleLabel = '';
foreach ($roles as $r) {
    if ((int) $r['id'] === (int) $user['role_id']) {
        $roleLabel = (string) ($r['name_ar'] ?: $r['name']);
        break;
    }
}
?>
<style>
.ue-page { --ue-radius: 18px; }
.ue-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; flex-wrap: wrap; margin-bottom: 18px; }
.ue-title { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin: 0 0 6px; font-size: 1.55rem; font-weight: 800; color: var(--adm-text, #0f172a); }
.ue-sub { margin: 0; color: var(--adm-text-mute, #64748b); font-size: 0.88rem; }
.ue-head-actions { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.ue-btn { display: inline-flex; align-items: center; gap: 6px; padding: 9px 14px; border-radius: 12px; font-weight: 700; font-size: 0.84rem; text-decoration: none; border: 1px solid var(--adm-border, #e2e8f0); color: var(--adm-text, #0f172a); background: var(--adm-btn-bg, #fff); transition: all .18s ease; }
.ue-btn:hover { border-color: #00f2fe; color: #0891b2; transform: translateY(-1px); }
.ue-btn.is-primary { background: rgba(0,242,254,.08); border-color: rgba(0,242,254,.35); color: #0891b2; }
.ue-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 14px; margin-bottom: 18px; }
@media (max-width: 1200px) { .ue-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .ue-stats { grid-template-columns: 1fr; } }
.ue-stat { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 18px; border-radius: var(--ue-radius); background: var(--adm-card-bg, #fff); border: 1px solid var(--adm-border, #e2e8f0); box-shadow: 0 8px 24px rgba(15,23,42,.06); min-height: 122px; }
.ue-stat-body { min-width: 0; }
.ue-stat-label { color: var(--adm-text-mute, #64748b); font-size: 0.8rem; font-weight: 700; margin-bottom: 6px; white-space: nowrap; }
.ue-stat-value { font-size: 1.18rem; font-weight: 800; color: var(--adm-text, #0f172a); line-height: 1.35; word-break: break-word; }
.ue-stat-meta { margin-top: 6px; font-size: 0.74rem; color: var(--adm-text-mute, #64748b); display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.ue-tile { width: 54px; height: 54px; border-radius: 16px; display: grid; place-items: center; font-size: 1.35rem; flex-shrink: 0; }
.ue-tile.purple { background: rgba(168,85,247,.16); color: #a855f7; }
.ue-tile.green  { background: rgba(16,185,129,.16); color: #10b981; }
.ue-tile.amber  { background: rgba(245,158,11,.16); color: #f59e0b; }
.ue-tile.cyan   { background: rgba(34,211,238,.16); color: #06b6d4; }
.ue-chip { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; border-radius: 8px; font-size: 0.72rem; font-weight: 700; text-decoration: none; border: 1px solid transparent; }
.ue-chip.info { background: rgba(59,130,246,.14); color: #2563eb; border-color: rgba(59,130,246,.25); }
.ue-chip.warn { background: rgba(245,158,11,.14); color: #b45309; border-color: rgba(245,158,11,.3); }
.ue-chip.muted { background: var(--adm-badge-bg, #f1f5f9); color: var(--adm-badge-cl, #475569); border-color: var(--adm-badge-bd, #e2e8f0); }
.ue-chip.ok { background: rgba(16,185,129,.14); color: #047857; border-color: rgba(16,185,129,.3); }
html.admin-dark .ue-chip.info { color: #60a5fa; }
html.admin-dark .ue-chip.warn { color: #fbbf24; }
html.admin-dark .ue-chip.ok { color: #34d399; }
.ue-card { background: var(--adm-card-bg, #fff); border: 1px solid var(--adm-border, #e2e8f0); border-radius: var(--ue-radius); box-shadow: 0 10px 28px rgba(15,23,42,.06); overflow: hidden; }
.ue-card-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 18px 20px; border-bottom: 1px solid var(--adm-border, #e2e8f0); }
.ue-card-title { margin: 0; font-size: 1.05rem; font-weight: 800; display: flex; align-items: center; gap: 8px; color: var(--adm-text, #0f172a); }
.ue-card-title i { color: #0891b2; }
.ue-card-sub { margin: 4px 0 0; font-size: 0.78rem; color: var(--adm-text-mute, #64748b); }
.ue-form { padding: 20px; }
.ue-grid { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
@media (max-width: 992px) { .ue-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
@media (max-width: 640px) { .ue-grid { grid-template-columns: 1fr; } }
.ue-field label { display: block; font-size: 0.8rem; font-weight: 700; color: var(--adm-text, #0f172a); margin-bottom: 6px; }
.ue-field .form-control, .ue-field .form-select { min-height: 44px; border-radius: 12px; }
.ue-submit-row { display: flex; justify-content: flex-end; margin-top: 18px; }
.ue-submit { display: inline-flex; align-items: center; gap: 8px; border: 0; border-radius: 12px; padding: 12px 26px; font-weight: 800; color: #fff; background: linear-gradient(135deg, #22d3ee, #8b5cf6); box-shadow: 0 8px 20px rgba(139,92,246,.28); cursor: pointer; }
.ue-submit:hover { filter: brightness(1.06); }
.ue-lower { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 14px; margin-top: 16px; }
@media (max-width: 992px) { .ue-lower { grid-template-columns: 1fr; } }
.ue-table { width: 100%; border-collapse: collapse; font-size: 0.8rem; }
.ue-table th, .ue-table td { padding: 9px 10px; border-bottom: 1px solid var(--adm-border, #e2e8f0); text-align: right; }
.ue-table th { color: var(--adm-text-mute, #64748b); font-weight: 700; background: var(--adm-badge-bg, #f8fafc); }
.ue-table td { color: var(--adm-text, #0f172a); }
.ue-empty { padding: 18px 20px; color: var(--adm-text-mute, #64748b); font-size: 0.82rem; }
</style>

<div class="ue-page">
    <div class="ue-head">
        <div>
            <h2 class="ue-title">
                <span>تعديل بيانات ورصد نشاط: <?= htmlspecialchars($user['username']) ?></span>
                <span class="ue-chip muted">#<?= (int) $user['id'] ?></span>
                <span class="ue-chip <?= $isActive ? 'ok' : 'warn' ?>"><?= htmlspecialchars($statusLabel) ?></span>
            </h2>
            <p class="ue-sub">عرض شامل لبيانات الحساب، عنوان IP الأخير، نشاطات المستخدم، والأجهزة المستخدمة.</p>
        </div>
        <div class="ue-head-actions">
            <a href="<?= admin_e(app_url('admin/users')) ?>" class="ue-btn"><i class="bi bi-arrow-right"></i> العودة للقائمة</a>
            <a href="<?= admin_e(app_url('admin/activity-log?user_id=' . (int) $user['id'])) ?>" class="ue-btn"><i class="bi bi-shield-check"></i> سجل الرقابة الشامل</a>
            <a href="<?= admin_e(app_url('admin/articles?author_id=' . (int) $user['id'] . '&status=published')) ?>" class="ue-btn is-primary"><i class="bi bi-newspaper"></i> الأخبار المنشورة (<?= number_format($publishedCount) ?>)</a>
        </div>
    </div>

    <div class="ue-stats">
        <div class="ue-stat">
            <div class="ue-stat-body">
                <div class="ue-stat-label">تاريخ التسجيل والتحقق</div>
                <div class="ue-stat-value" dir="ltr"><?= htmlspecialchars(fmt_date($user['created_at'] ?? '', 'Y-m-d')) ?></div>
                <div class="ue-stat-meta">
                    <a class="ue-chip info" href="<?= admin_e(app_url('admin/articles?author_id=' . (int) $user['id'] . '&status=published')) ?>"><i class="bi bi-newspaper"></i> أخبار <?= number_format($publishedCount) ?></a>
                    <span class="ue-chip <?= $isVerified ? 'ok' : 'warn' ?>"><?= $isVerified ? 'موثّق' : 'غير مؤكد' ?></span>
                    <span class="ue-chip muted"><i class="bi bi-chat-dots"></i> تعليق <?= number_format($commentsCount) ?></span>
                </div>
            </div>
            <span class="ue-tile purple"><i class="bi bi-person-vcard"></i></span>
        </div>

        <div class="ue-stat">
            <div class="ue-stat-body">
                <div class="ue-stat-label">آخر تسجيل دخول</div>
                <div class="ue-stat-value" dir="ltr"><?= $activity['last_login'] ? htmlspecialchars(fmt_date($activity['last_login'], 'H:i Y-m-d')) : '—' ?></div>
                <div class="ue-stat-meta">
                    <span><?= $activity['last_login'] ? 'منذ ' . htmlspecialchars(fmt_relative_time($activity['last_login'])) : 'لا يوجد بعد' ?></span>
                    <span>· الدخول: <?= (int) ($activity['login_count'] ?? 0) ?> مرة</span>
                </div>
            </div>
            <span class="ue-tile green"><i class="bi bi-shield-lock-fill"></i></span>
        </div>

        <div class="ue-stat">
            <div class="ue-stat-body">
                <div class="ue-stat-label">الجهاز والمتصفح الأخير</div>
                <div class="ue-stat-value"><?= htmlspecialchars(($activity['browser'] ?: 'غير معروف') . ' (' . ($activity['os'] ?: 'غير معروف') . ')') ?></div>
                <div class="ue-stat-meta">
                    <span class="ue-chip muted"><?= htmlspecialchars($activity['device'] ?: 'غير معروف') ?></span>
                </div>
            </div>
            <span class="ue-tile amber"><i class="bi bi-laptop"></i></span>
        </div>

        <div class="ue-stat">
            <div class="ue-stat-body">
                <div class="ue-stat-label">آخر عنوان IP زُصد</div>
                <div class="ue-stat-value" dir="ltr"><?= htmlspecialchars($activity['last_ip'] ?: '—') ?></div>
                <div class="ue-stat-meta">
                    <span class="ue-chip info"><?= htmlspecialchars($activity['country'] ?: 'غير معروف') ?></span>
                    <span>من: <?= htmlspecialchars($activity['country'] ?: 'غير معروف') ?></span>
                </div>
            </div>
            <span class="ue-tile cyan"><i class="bi bi-geo-alt-fill"></i></span>
        </div>
    </div>

    <div class="ue-card">
        <div class="ue-card-head">
            <div>
                <h3 class="ue-card-title"><i class="bi bi-pencil-square"></i> نموذج تعديل بيانات المستخدم</h3>
                <p class="ue-card-sub">يمكنك تعديل الاسم، البريد الإلكتروني، كلمة المرور، الدور، وحالة الحساب.</p>
            </div>
            <span class="ue-chip muted"><?= htmlspecialchars($roleLabel ?: 'قارئ مسجل') ?></span>
        </div>

        <form class="ue-form" action="<?= admin_e(app_url('admin/users/' . $user['id'] . '/update')) ?>" method="post">
            <?= CSRF::field() ?>
            <div class="ue-grid">
                <div class="ue-field">
                    <label for="ue_username">اسم المستخدم</label>
                    <input type="text" id="ue_username" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                </div>
                <div class="ue-field">
                    <label for="ue_email">البريد الإلكتروني</label>
                    <input type="email" id="ue_email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required dir="ltr">
                </div>
                <div class="ue-field">
                    <label for="ue_password">كلمة المرور الجديدة (اتركها فارغة إذا لم ترغب بالتغيير)</label>
                    <div class="password-input-wrap">
                        <input type="password" id="ue_password" name="password" class="form-control" placeholder="••••••••" autocomplete="new-password">
                        <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('ue_password', this)" title="إظهار / إخفاء كلمة المرور">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>
                <div class="ue-field">
                    <label for="ue_role">الدور والصلاحيات</label>
                    <select id="ue_role" name="role_id" class="form-select">
                        <?php foreach ($roles as $r): ?>
                            <option value="<?= (int) $r['id'] ?>" <?= (int) $user['role_id'] === (int) $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name_ar'] ?: $r['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="ue-field">
                    <label for="ue_status">الحالة</label>
                    <select id="ue_status" name="status" class="form-select">
                        <option value="active" <?= ($user['status'] ?? '') === 'active' ? 'selected' : '' ?>>نشط (Active)</option>
                        <option value="pending" <?= ($user['status'] ?? '') === 'pending' ? 'selected' : '' ?>>معلق (Pending)</option>
                        <option value="banned" <?= ($user['status'] ?? '') === 'banned' ? 'selected' : '' ?>>محظور (Banned)</option>
                    </select>
                </div>
                <div class="ue-field">
                    <label>معرّف الحساب</label>
                    <input type="text" class="form-control" value="#<?= (int) $user['id'] ?>" readonly dir="ltr">
                </div>
            </div>

            <div class="ue-submit-row">
                <button type="submit" class="ue-submit"><i class="bi bi-check-circle"></i> تحديث البيانات</button>
            </div>
        </form>
    </div>

    <div class="ue-lower">
        <div class="ue-card">
            <div class="ue-card-head">
                <h3 class="ue-card-title"><i class="bi bi-hdd-network"></i> آخر الجلسات</h3>
                <span class="ue-chip muted"><?= count($actSessions) ?> جلسة</span>
            </div>
            <?php if (empty($actSessions)): ?>
                <div class="ue-empty">لا توجد جلسات مسجّلة بعد — ستظهر عند أول تسجيل دخول بعد هذا التحديث.</div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="ue-table">
                        <thead><tr><th>الدخول</th><th>الخروج</th><th>IP</th><th>الجهاز</th><th>الموقع</th></tr></thead>
                        <tbody>
                        <?php foreach ($actSessions as $sess): ?>
                            <tr>
                                <td><span class="font-monospace" dir="ltr"><?= htmlspecialchars(fmt_date($sess['login_at'] ?? '', 'Y-m-d H:i')) ?></span></td>
                                <td><span class="font-monospace" dir="ltr"><?= !empty($sess['logout_at']) ? htmlspecialchars(fmt_date($sess['logout_at'], 'Y-m-d H:i')) : '—' ?></span></td>
                                <td><span class="font-monospace" dir="ltr"><?= htmlspecialchars($sess['ip_address'] ?: '—') ?></span></td>
                                <td><?= htmlspecialchars($sess['device_type'] ?: '—') ?></td>
                                <td><?= htmlspecialchars($sess['location'] ?: '—') ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <div class="ue-card">
            <div class="ue-card-head">
                <h3 class="ue-card-title"><i class="bi bi-clock-history"></i> آخر عمليات التدقيق</h3>
                <span class="ue-chip muted"><?= count($actLogs) ?> عملية</span>
            </div>
            <?php if (empty($actLogs)): ?>
                <div class="ue-empty">لا توجد عمليات مسجّلة لهذا المستخدم بعد.</div>
            <?php else: ?>
                <div style="overflow-x:auto">
                    <table class="ue-table">
                        <thead><tr><th>الوقت</th><th>العملية</th><th>الوصف</th><th>IP</th></tr></thead>
                        <tbody>
                        <?php foreach ($actLogs as $log): ?>
                            <tr>
                                <td><span class="font-monospace" dir="ltr"><?= htmlspecialchars(fmt_date($log['created_at'] ?? '', 'Y-m-d H:i')) ?></span></td>
                                <td><span class="ue-chip muted"><?= htmlspecialchars($log['action'] ?? '—') ?></span></td>
                                <td><?= htmlspecialchars(mb_strimwidth((string) ($log['description'] ?? ''), 0, 70, '…', 'UTF-8')) ?></td>
                                <td><span class="font-monospace" dir="ltr"><?= htmlspecialchars($log['ip_address'] ?: '—') ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
