<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
  <div>
  <h2 class="h3 fw-bold mb-1"><i class="bi bi-tags text-primary me-2"></i>إدارة التصنيفات</h2>
  <p class="text-muted mb-0">إنشاء وتعديل تصنيفات الأخبار التقنية وترتيب ظهورها في الموقع.</p>
  </div>
  <a href="<?= admin_e(app_url('admin/categories/create')) ?>" class="btn btn-primary fw-bold shadow-sm">
  <i class="bi bi-plus-lg me-1"></i> إضافة تصنيف جديد
  </a>
 </div>

 <?php if (!empty($flashSuccess)): ?>
 <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2">
 <i class="bi bi-check-circle-fill"></i>
 <span><?= admin_e($flashSuccess) ?></span>
 <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="إغلاق"></button>
 </div>
 <?php endif; ?>
 <?php if (!empty($flashError)): ?>
 <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2">
 <i class="bi bi-exclamation-triangle-fill"></i>
 <span><?= admin_e($flashError) ?></span>
 <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="إغلاق"></button>
 </div>
 <?php endif; ?>

<div class="card border-0 shadow-sm rounded-4 bg-white">
 <div class="table-responsive">
 <table class="table table-hover align-middle mb-0">
 <thead class="table-light">
 <tr>
 <th style="width:60px">#</th>
 <th>اسم التصنيف</th>
 <th>الاسم بالعربية</th>
 <th>الاسم بالإنجليزية</th>
 <th>الرابط (Slug)</th>
<th>الترتيب</th>
  <th class="text-center">الحالة</th>
  <th class="text-center">الأخبار</th>
  <th class="text-end" style="width:170px">الإجراءات</th>
  </tr>
  </thead>
  <tbody>
  <?php if (empty($categories)): ?>
  <tr>
  <td colspan="9" class="text-center py-5 text-muted">
  <i class="bi bi-tags d-block mb-2" style="font-size:2rem"></i>
  لا توجد تصنيفات بعد.
  </td>
  </tr>
  <?php else: ?>
  <?php foreach ($categories as $cat): ?>
  <?php
  $isHidden = array_key_exists('is_visible', $cat) && (int) $cat['is_visible'] === 0;
  $count = (int) ($cat['articles_count'] ?? 0);
  $label = $cat['name_ar'] ?: $cat['name'];
  ?>
  <tr class="<?= $isHidden ? 'table-secondary opacity-75' : '' ?>">
  <td><span class="text-muted small">#<?= (int) $cat['id'] ?></span></td>
  <td>
  <div class="d-flex align-items-center gap-2">
  <span class="badge" style="background:<?= admin_e($cat['color'] ?: '#00f2fe') ?>;width:12px;height:12px;padding:0;border-radius:50%"></span>
  <strong class="text-dark"><?= admin_e($cat['name']) ?></strong>
  </div>
  </td>
  <td><span><?= admin_e($label) ?></span></td>
  <td><span class="text-muted small"><?= admin_e($cat['name_en'] ?: '-') ?></span></td>
  <td><code class="text-primary"><?= admin_e($cat['slug']) ?></code></td>
  <td><span class="badge bg-light text-dark border"><?= (int) ($cat['sort_order'] ?? 0) ?></span></td>
  <td class="text-center">
  <?php if ($isHidden): ?>
  <span class="badge bg-secondary" title="مخفي من الموقع"><i class="bi bi-eye-slash me-1"></i> مخفي</span>
  <?php else: ?>
  <span class="badge bg-success" title="ظاهر في الموقع"><i class="bi bi-eye me-1"></i> ظاهر</span>
  <?php endif; ?>
  </td>
  <td class="text-center">
  <span class="badge bg-light text-dark border"><?= $count ?></span>
  </td>
  <td class="text-end">
  <div class="d-inline-flex gap-1">
  <a class="btn-action-icon btn-action-edit" href="<?= admin_e(app_url('admin/categories/' . $cat['id'] . '/edit')) ?>" title="تعديل التصنيف">
  <i class="bi bi-pencil"></i>
  </a>
<form method="post" action="<?= admin_e(app_url('admin/categories/' . $cat['id'] . '/toggle-visibility')) ?>" class="d-inline">
 <?= CSRF::field() ?>
 <button type="submit" class="btn-action-icon <?= $isHidden ? 'btn-action-edit' : 'btn-action-hide' ?>" title="<?= $isHidden ? 'إظهار التصنيف في الموقع' : 'إخفاء التصنيف دون حذفه' ?>">
 <i class="bi <?= $isHidden ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
 </button>
 </form>
 <form method="post" action="<?= admin_e(app_url('admin/categories/' . $cat['id'] . '/delete')) ?>" class="d-inline"
 data-ajax-delete="1" data-confirm="<?= $count > 0 ? "سيُحذف التصنيف «{$label}» نهائياً وسيُفصل عنه {$count} خبر (تبقى محفوظة)." : "هل تريد حذف التصنيف «{$label}» نهائياً؟" ?>">
 <?= CSRF::field() ?>
 <?php if ($count > 0): ?>
 <input type="hidden" name="detach_articles" value="1">
 <?php endif; ?>
 <button type="submit" class="btn-action-icon btn-action-delete" title="حذف التصنيف نهائياً<?= $count > 0 ? ' (مع فصل ' . $count . ' خبر)' : '' ?>">
 <i class="bi bi-trash3"></i>
 </button>
 </form>
  </div>
  </td>
  </tr>
  <?php endforeach; ?>
  <?php endif; ?>
  </tbody>
 </table>
 </div>
</div>
