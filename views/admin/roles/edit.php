<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
    <h2 class="h3 fw-bold mb-1">صلاحيات: <?= htmlspecialchars($role['name_ar'] ?: $role['name']) ?></h2>
    <p class="text-muted mb-0">
      <code><?= htmlspecialchars($role['name']) ?></code> ·
      <span class="badge bg-secondary-subtle text-secondary"><?= (int) $users ?> مستخدم</span>
    </p>
  </div>
  <a href="<?= app_url('admin/roles') ?>" class="btn btn-outline-secondary">← الأدوار</a>
</div>

<?php if (!empty($error)): ?>
  <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form action="<?= app_url('admin/roles/' . (int) $role['id'] . '/update') ?>" method="post" id="rolesForm">
  <?= CSRF::field() ?>

  <div class="card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div class="fw-bold">تحديد الكل / إلغاء الكل</div>
      <div class="d-flex gap-2">
        <button type="button" class="btn btn-sm btn-outline-primary" data-toggle-all="1">تحديد الكل</button>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-toggle-all="0">إلغاء الكل</button>
      </div>
    </div>
  </div>

  <div class="card border-0 shadow-sm rounded-4 mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
      <div>
        <span class="fw-bold">أقسام القائمة الجانبية</span>
        <div class="text-muted small">
          <?php if ($sections === null): ?>
            تُعرض تلقائياً حسب صلاحيات الدور (لا يوجد تقييد يدوي).
          <?php else: ?>
            معروض لهذا الدور: <?= count($sections) ?> من <?= count($navGroups) ?> — أي قسم غير محدَّد يختفي بالكامل.
          <?php endif; ?>
        </div>
      </div>
      <?php if ($sections !== null): ?>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" id="clearSections">إلغاء التقييد (حسب الصلاحيات)</button>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <div class="row g-2">
      <?php foreach ($navGroups as $g): ?>
        <?php $checked = ($sections === null) ? true : in_array($g['key'], $sections, true); ?>
        <div class="col-12 col-md-6 col-lg-4">
          <div class="form-check border rounded-3 px-3 py-2 bg-light">
            <input class="form-check-input ms-2" type="checkbox" name="sections[]" value="<?= htmlspecialchars($g['key']) ?>"
                   id="sec_<?= htmlspecialchars($g['key']) ?>" <?= $checked ? 'checked' : '' ?>>
            <label class="form-check-label" for="sec_<?= htmlspecialchars($g['key']) ?>">
              <i class="bi <?= htmlspecialchars($g['icon']) ?>"></i>
              <?= htmlspecialchars($g['label']) ?>
            </label>
          </div>
        </div>
      <?php endforeach; ?>
      </div>
    </div>
  </div>

  <?php foreach (Permissions::groups() as $groupName => $entities): ?>
    <div class="card border-0 shadow-sm rounded-4 mb-3">
      <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <span class="fw-bold"><?= htmlspecialchars($groupName) ?></span>
        <button type="button" class="btn btn-sm btn-link text-decoration-none p-0" data-toggle-all="1" data-group="<?= htmlspecialchars($groupName) ?>">تحديد القسم</button>
      </div>
      <div class="card-body">
        <div class="row g-3">
        <?php foreach ($entities as $entity): ?>
          <div class="col-12">
            <div class="fw-semibold mb-2"><?= htmlspecialchars(Permissions::entityLabel($entity)) ?> <code class="small text-muted"><?= htmlspecialchars($entity) ?></code></div>
            <div class="d-flex flex-wrap gap-2">
            <?php foreach (Permissions::actionsFor($entity) as $action): ?>
              <?php $perm = $entity . '.' . $action; ?>
              <div class="form-check form-check-inline border rounded-3 px-3 py-2 bg-light">
                <input class="form-check-input ms-2" type="checkbox" name="permissions[]" value="<?= htmlspecialchars($perm) ?>"
                       id="perm_<?= htmlspecialchars(str_replace('.', '_', $perm)) ?>"
                       <?= in_array($perm, $tokens, true) ? 'checked' : '' ?>>
                <label class="form-check-label" for="perm_<?= htmlspecialchars(str_replace('.', '_', $perm)) ?>">
                  <?= htmlspecialchars(Permissions::all()[$perm] ?? $action) ?>
                </label>
              </div>
            <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>
    </div>
  <?php endforeach; ?>

  <div class="card border-0 shadow-sm rounded-4">
    <div class="card-body d-flex justify-content-between align-items-center flex-wrap gap-2">
      <span class="text-muted small">إلغاء تحديد أي صلاحية يمنعها الدور فوراً بعد تفعيل الإلزام.</span>
      <button type="submit" class="btn btn-primary px-4 fw-bold">حفظ الصلاحيات</button>
    </div>
  </div>
</form>

<script>
document.querySelectorAll('[data-toggle-all]').forEach(function (btn) {
  btn.addEventListener('click', function () {
    var wanted = btn.getAttribute('data-toggle-all') === '1';
    var scope = btn.hasAttribute('data-group') ? btn.closest('.card') : document.getElementById('rolesForm');
    scope.querySelectorAll('input[name="permissions[]"]').forEach(function (box) {
      box.checked = wanted;
    });
  });
});

var clearBtn = document.getElementById('clearSections');
if (clearBtn) {
  clearBtn.addEventListener('click', function () {
    document.querySelectorAll('input[name="sections[]"]').forEach(function (box) {
      box.checked = true;
    });
  });
}
</script>