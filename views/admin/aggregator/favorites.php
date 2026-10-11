<?php
/**
 * تبويب المفضلة — كل المصادر المفضلة وأحدث أخبارها في مكان واحد.
 */
?>
<div class="container-fluid">

 <div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
  <div>
  <h2 class="h3 fw-bold mb-1">
  <i class="bi bi-pin-angle-fill text-warning me-2"></i>المفضلة
  </h2>
  <p class="text-muted mb-0">
  كل المصادر التي ثبّتَها في مكان واحد — اضغط الدبوس بجانب أي مصدر لإضافته أو إزالته.
  </p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
  <div class="btn-group btn-group-sm" role="group" aria-label="عدد الأخبار لكل مصدر">
  <?php foreach (array(3, 5, 8) as $n): ?>
  <a href="<?= admin_e(app_url('admin/news-feeds?tab=favorites&per=' . $n)) ?>"
  class="btn <?= (int) $perSource === $n ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= $n ?></a>
  <?php endforeach; ?>
  </div>
  <a href="<?= admin_e(app_url('admin/news-feeds')) ?>" class="btn btn-light border btn-sm">
  <i class="bi bi-grid me-1"></i> كل المصادر
  </a>
  </div>
 </div>

 <?php if (empty($groups)): ?>
 <div class="card border-0 shadow-sm rounded-4 bg-white">
  <div class="card-body text-center py-5">
  <i class="bi bi-pin-angle d-block mb-3" style="font-size:3rem;color:#cbd5e1"></i>
  <h5 class="fw-bold mb-2">لا يوجد مصدر في المفضلة بعد</h5>
  <p class="text-muted mb-4" style="max-width:520px;margin-inline:auto">
  افتح تبويب «كل المصادر» واضغط أيقونة الدبوس 📌 بجانب أي مصدر ليظهر هنا مع أحدث أخباره.
  </p>
  <a href="<?= admin_e(app_url('admin/news-feeds')) ?>" class="btn btn-primary fw-bold">
  <i class="bi bi-collection me-1"></i> تصفّح المصادر
  </a>
  </div>
 </div>
 <?php else: ?>

 <?php if ($feedErrors > 0): ?>
 <div class="alert alert-warning d-flex align-items-center gap-2">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <span><?= (int) $feedErrors ?> مصدر لم يستجب هذه المرة — سيُعاد المحاولة عند التحديث.</span>
 </div>
 <?php endif; ?>

 <div class="row g-3">
 <?php foreach ($groups as $group): ?>
 <?php $src = $group['source']; ?>
 <div class="col-12 col-xl-6">
  <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
   <div class="card-header bg-white d-flex align-items-center gap-2 border-bottom">
    <i class="bi bi-pin-angle-fill text-warning"></i>
    <div class="flex-grow-1">
    <a href="<?= admin_e(app_url('admin/news-feeds?source_id=' . (int) $src['id'])) ?>"
    class="fw-bold text-dark text-decoration-none"><?= admin_e($src['name']) ?></a>
    <div class="small text-muted text-truncate" style="max-width:100%"><?= admin_e($src['url']) ?></div>
    </div>
    <span class="badge bg-light text-dark border"><?= admin_e($src['category_name'] ?: 'عام') ?></span>
    <?php if ((int) ($src['auto_publish'] ?? 0) === 1): ?>
    <span class="badge bg-success text-white" title="النشر التلقائي مفعّل لهذا المصدر">
    <i class="bi bi-broadcast me-1"></i>تلقائي
    </span>
    <?php endif; ?>
    <form method="post" action="<?= admin_e(app_url('admin/rss-sources/' . (int) $src['id'] . '/toggle-favorite')) ?>"
    onsubmit="return adminConfirm('إزالة «<?= admin_e(addslashes($src['name'])) ?>» من المفضلة؟', {title:'إزالة من المفضلة', danger:true}).then(function(ok){ if(!ok){ var f=this; return false; } return true; }.bind(this))">
    <?= CSRF::field() ?>
    <input type="hidden" name="back" value="favorites">
    <button type="submit" class="btn btn-sm btn-link text-danger p-0" title="إزالة من المفضلة">
    <i class="bi bi-x-circle-fill"></i>
    </button>
    </form>
   </div>

   <div class="card-body p-0">
    <?php if (!empty($group['error'])): ?>
    <div class="p-3 text-muted small">
    <i class="bi bi-plug me-1"></i>تعذّر جلب الخلاصة: <?= admin_e(mb_substr((string) $group['error'], 0, 120)) ?>
    </div>
    <?php elseif (empty($group['items'])): ?>
    <div class="p-3 text-muted small">لا توجد أخبار في هذه الخلاصة حالياً.</div>
    <?php else: ?>
    <ul class="list-group list-group-flush">
    <?php foreach ($group['items'] as $item): ?>
    <?php $published = ($item['import_status'] ?? '') === 'published'; ?>
    <li class="list-group-item d-flex align-items-start gap-2">
     <div class="flex-grow-1">
      <div class="fw-semibold" style="line-height:1.5"><?= admin_e($item['title']) ?></div>
      <div class="small text-muted"><?= admin_e($item['pubDate'] ?? '') ?></div>
     </div>
     <div class="d-flex flex-column gap-1" style="min-width:150px">
      <?php if ($published): ?>
      <span class="btn btn-sm btn-success disabled" style="pointer-events:none">
      <i class="bi bi-check-circle-fill"></i> منشور
      </span>
      <?php if (!empty($item['article_id'])): ?>
      <a href="<?= admin_e(app_url('admin/articles/' . (int) $item['article_id'] . '/edit')) ?>"
      class="btn btn-sm btn-outline-primary">
      <i class="bi bi-pencil-square"></i> تعديل
      </a>
      <?php endif; ?>
      <?php else: ?>
      <a href="<?= admin_e($item['link']) ?>" target="_blank" rel="noopener"
      class="btn btn-sm btn-light border" title="الرابط الأصلي">
      <i class="bi bi-box-arrow-up-right"></i> المصدر
      </a>
      <a href="<?= admin_e(app_url('admin/news-feeds?source_id=' . (int) $src['id'])) ?>"
      class="btn btn-sm btn-primary">
      <i class="bi bi-lightning-charge"></i> نشر
      </a>
      <?php endif; ?>
     </div>
    </li>
    <?php endforeach; ?>
    </ul>
    <?php endif; ?>
   </div>
  </div>
 </div>
 <?php endforeach; ?>
 </div>
 <?php endif; ?>
</div>