<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
 <div>
 <h2 class="h3 fw-bold mb-1">تعديل بيانات المستخدم: <?= htmlspecialchars($user['username']) ?></h2>
 </div>
 <a href="<?= app_url('admin/users') ?>" class="btn btn-outline-secondary">← العودة للقائمة</a>
</div>

<div class="card border-0 shadow-sm rounded-4">
 <div class="card-body p-4">
 <form action="<?= app_url('admin/users/' . $user['id'] . '/update') ?>" method="post">
 <?= CSRF::field() ?>

 <div class="row g-3">
 <div class="col-md-6">
 <label class="form-label fw-bold">اسم المستخدم</label>
 <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
 </div>
 <div class="col-md-6">
 <label class="form-label fw-bold">البريد الإلكتروني</label>
 <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
 </div>
 <div class="col-md-6">
 <label class="form-label fw-bold">كلمة المرور الجديدة (اتركها فارغة إذا لم ترغب بالتغيير)</label>
 <div class="password-input-wrap">
 <input type="password" id="admin_edit_pass" name="password" class="form-control" placeholder="••••••••">
 <button type="button" class="btn-toggle-password" onclick="togglePasswordVisibility('admin_edit_pass', this)" title="إظهار / إخفاء كلمة المرور">
 <i class="bi bi-eye"></i>
 </button>
 </div>
 </div>
 <div class="col-md-3">
 <label class="form-label fw-bold">الدور والصلاحيات</label>
 <select name="role_id" class="form-select">
 <?php foreach ($roles as $r): ?>
 <option value="<?= $r['id'] ?>" <?= $user['role_id'] == $r['id'] ? 'selected' : '' ?>><?= htmlspecialchars($r['name_ar'] ?: $r['name']) ?></option>
 <?php endforeach; ?>
 </select>
 </div>
 <div class="col-md-3">
 <label class="form-label fw-bold">الحالة</label>
 <select name="status" class="form-select">
 <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>نشط (Active)</option>
 <option value="pending" <?= $user['status'] === 'pending' ? 'selected' : '' ?>>معلق (Pending)</option>
 <option value="banned" <?= $user['status'] === 'banned' ? 'selected' : '' ?>>محظور (Banned)</option>
 </select>
 </div>
 </div>

 <hr class="my-4">
 <button type="submit" class="btn btn-primary px-4 py-2 fw-bold">تحديث البيانات</button>
 </form>
 </div>
</div>

<?php
$activity = $activity ?? [];
$actSessions = $activity['sessions'] ?? [];
$actLogs = $activity['logs'] ?? [];
$lastSeen = (string) ($activity['last_seen'] ?? '');
?>
<div class="card border-0 shadow-sm rounded-4 mt-4">
 <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center flex-wrap gap-2">
 <strong class="d-flex align-items-center gap-2">
 <i class="bi bi-activity text-primary"></i>
 <span>نشاط المستخدم وتشخيص الدخول</span>
 </strong>
 <?php if ($lastSeen !== ''): ?>
 <span class="badge bg-light text-dark border">
 آخر نشاط: <span dir="ltr" class="font-monospace"><?= htmlspecialchars(fmt_date($lastSeen, 'Y-m-d H:i')) ?></span>
 </span>
 <?php endif; ?>
 </div>
 <div class="card-body p-4">
 <div class="row g-3 mb-4">
 <div class="col-md-6 col-xl-3">
 <div class="p-3 h-100 rounded-4 border bg-light">
 <small class="text-muted d-block mb-1">آخر عنوان IP</small>
 <strong dir="ltr" class="font-monospace d-block"><?= htmlspecialchars($activity['last_ip'] ?: '—') ?></strong>
 <span class="badge bg-secondary-subtle text-dark border mt-2"><?= htmlspecialchars($activity['country'] ?: 'غير معروف') ?></span>
 </div>
 </div>
 <div class="col-md-6 col-xl-3">
 <div class="p-3 h-100 rounded-4 border bg-light">
 <small class="text-muted d-block mb-1">الجهاز</small>
 <strong class="d-block"><?= htmlspecialchars($activity['device'] ?: 'غير معروف') ?></strong>
 <small class="text-muted"><?= htmlspecialchars($activity['browser'] ?: '—') ?> · <?= htmlspecialchars($activity['os'] ?: '—') ?></small>
 </div>
 </div>
 <div class="col-md-6 col-xl-3">
 <div class="p-3 h-100 rounded-4 border bg-light">
 <small class="text-muted d-block mb-1">آخر دخول</small>
 <strong dir="ltr" class="font-monospace d-block"><?= $activity['last_login'] ? htmlspecialchars(fmt_date($activity['last_login'], 'Y-m-d H:i')) : '—' ?></strong>
 <small class="text-muted"><?= (int) ($activity['login_count'] ?? 0) ?> تسجيل دخول إجمالاً</small>
 </div>
 </div>
 <div class="col-md-6 col-xl-3">
 <div class="p-3 h-100 rounded-4 border bg-light">
 <small class="text-muted d-block mb-1">الجلسات المسجّلة</small>
 <strong class="d-block"><?= count($actSessions) ?></strong>
 <small class="text-muted">آخر <?= count($actLogs) ?> عملية تدقيق</small>
 </div>
 </div>
 </div>

 <div class="row g-4">
 <div class="col-xl-6">
 <h6 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-hdd-network text-primary"></i> آخر الجلسات</h6>
 <?php if (empty($actSessions)): ?>
 <div class="text-muted small border rounded-3 p-3">لا توجد جلسات مسجّلة بعد — ستظهر عند أول تسجيل دخول بعد هذا التحديث.</div>
 <?php else: ?>
 <div class="table-responsive">
 <table class="table table-sm align-middle mb-0">
 <thead class="table-light"><tr><th>الدخول</th><th>الخروج</th><th>IP</th><th>الجهاز</th><th>الموقع</th></tr></thead>
 <tbody>
 <?php foreach ($actSessions as $sess): ?>
 <tr>
 <td><small class="font-monospace" dir="ltr"><?= htmlspecialchars(fmt_date($sess['login_at'] ?? '', 'Y-m-d H:i')) ?></small></td>
 <td><small class="font-monospace" dir="ltr"><?= !empty($sess['logout_at']) ? htmlspecialchars(fmt_date($sess['logout_at'], 'Y-m-d H:i')) : '—' ?></small></td>
 <td><small class="font-monospace" dir="ltr"><?= htmlspecialchars($sess['ip_address'] ?: '—') ?></small></td>
 <td><small><?= htmlspecialchars($sess['device_type'] ?: '—') ?></small></td>
 <td><small><?= htmlspecialchars($sess['location'] ?: '—') ?></small></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>

 <div class="col-xl-6">
 <h6 class="fw-bold mb-3 d-flex align-items-center gap-2"><i class="bi bi-clock-history text-primary"></i> آخر عمليات التدقيق</h6>
 <?php if (empty($actLogs)): ?>
 <div class="text-muted small border rounded-3 p-3">لا توجد عمليات مسجّلة لهذا المستخدم بعد.</div>
 <?php else: ?>
 <div class="table-responsive">
 <table class="table table-sm align-middle mb-0">
 <thead class="table-light"><tr><th>الوقت</th><th>العملية</th><th>الوصف</th><th>IP</th></tr></thead>
 <tbody>
 <?php foreach ($actLogs as $log): ?>
 <tr>
 <td><small class="font-monospace" dir="ltr"><?= htmlspecialchars(fmt_date($log['created_at'] ?? '', 'Y-m-d H:i')) ?></small></td>
 <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($log['action'] ?? '—') ?></span></td>
 <td><small><?= htmlspecialchars(mb_strimwidth((string) ($log['description'] ?? ''), 0, 70, '…', 'UTF-8')) ?></small></td>
 <td><small class="font-monospace" dir="ltr"><?= htmlspecialchars($log['ip_address'] ?: '—') ?></small></td>
 </tr>
 <?php endforeach; ?>
 </tbody>
 </table>
 </div>
 <?php endif; ?>
 </div>
 </div>
 </div>
</div>
