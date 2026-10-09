<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h2 class="h3 fw-bold mb-1">الأدوار والصلاحيات</h2>
    <p class="text-muted mb-0">تحديد ما يستطيع كل دور فعله داخل لوحة التحكم.</p>
  </div>
  <a href="<?= app_url('admin/users') ?>" class="btn btn-outline-secondary">المستخدمون</a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if (!empty($success)): ?>
  <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<?php if (!$enforced): ?>
  <div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div>
      <strong>إلغاء الصلاحيات غير مفعّل بعد.</strong>
      التعديلات هنا محفوظة الآن، ولن تقيّد الوصول فعلياً حتى يتم تفعيل زر الأمان
      (<code>RBAC_ENFORCE</code>).
    </div>
  </div>
<?php endif; ?>

<?php if (!empty($seed['created']) || !empty($seed['updated'])): ?>
  <div class="alert alert-warning">
    تم تجهيز الصلاحيات الافتراضية —
    <strong>أُنشئ:</strong> <?= htmlspecialchars(implode('، ', $seed['created']) ?: '—') ?> ·
    <strong>حُدِّث:</strong> <?= htmlspecialchars(implode('، ', $seed['updated']) ?: '—') ?>
    <?php if (!empty($seed['error'])): ?>
      <div class="mt-1 text-danger"><?= htmlspecialchars($seed['error']) ?></div>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="card border-0 shadow-sm rounded-4">
  <div class="card-body p-0">
    <div class="table-responsive">
      <table class="table align-middle mb-0">
        <thead class="table-light">
          <tr>
            <th class="ps-4">الدور</th>
            <th>المستخدمون</th>
            <th>الصلاحيات</th>
            <th>النطاق</th>
            <th class="pe-4 text-end">إجراء</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($roles as $role): ?>
          <tr>
            <td class="ps-4">
              <div class="fw-bold"><?= htmlspecialchars($role['name_ar']) ?></div>
              <div class="text-muted small"><?= htmlspecialchars($role['name']) ?><?= $role['is_default'] ? ' · افتراضي' : '' ?></div>
            </td>
            <td><span class="badge bg-secondary-subtle text-secondary"><?= (int) $role['users'] ?></span></td>
            <td style="max-width:420px">
              <div class="small"><?= htmlspecialchars($role['summary']) ?></div>
              <?php if (!$role['is_super']): ?>
                <code class="small text-muted"><?= count($role['tokens']) ?> صلاحية</code>
              <?php endif; ?>
            </td>
            <td>
              <?php if ($role['is_super']): ?>
                <span class="badge bg-primary-subtle text-primary">دور خارق — كل الصلاحيات</span>
              <?php else: ?>
                <span class="badge bg-light text-dark"><?= (int) $role['granted'] ?> / <?= (int) $role['total'] ?></span>
              <?php endif; ?>
            </td>
            <td class="pe-4 text-end">
              <?php if ($role['is_super']): ?>
                <span class="text-muted small">غير قابل للتعديل</span>
              <?php else: ?>
                <a href="<?= app_url('admin/roles/' . $role['id'] . '/edit') ?>" class="btn btn-sm btn-outline-primary">تعديل الصلاحيات</a>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>