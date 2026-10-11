<?php
/**
 * تبويب المفضلة — كل المصادر المفضلة وأحدث أخبارها في مكان واحد.
 */
?>
<style>
.fav-thumb { width: 72px; height: 54px; border-radius: 10px; overflow: hidden; background: #eef2f7; display: flex; align-items: center; justify-content: center; }
.fav-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.fav-thumb-ph { color: #94a3b8; font-size: 1.1rem; }
</style>
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
 id="pinForm_<?= (int) $src['id'] ?>" class="d-inline">
 <?= CSRF::field() ?>
 <input type="hidden" name="back" value="favorites">
 <button type="submit" class="btn btn-sm btn-link text-danger p-0 is-pinned" title="إزالة من المفضلة">
 <i class="bi bi-pin-angle-fill text-warning"></i>
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
    <?php
    $published = ($item['import_status'] ?? '') === 'published';
    $thumb = trim((string) ($item['featured_image'] ?? ''));
    if ($thumb !== '' && !preg_match('#^https?://#i', $thumb)) {
     $thumb = '';
    }
    ?>
    <li class="list-group-item d-flex align-items-center gap-3 fav-item" data-url="<?= admin_e($item['link'] ?? '') ?>">
     <div class="fav-thumb flex-shrink-0">
      <?php if ($thumb !== ''): ?>
      <img src="<?= admin_e($thumb) ?>" alt="" loading="lazy" width="72" height="54"
      onerror="this.outerHTML='<span class=&quot;fav-thumb-ph&quot;><i class=&quot;bi bi-image&quot;></i></span>'">
      <?php else: ?>
      <span class="fav-thumb-ph"><i class="bi bi-image"></i></span>
      <?php endif; ?>
     </div>
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
<script>
document.addEventListener('admin:favorite-changed', function (e) {
  // removed from favourites: the card goes away without touching the page
  if (e.detail && e.detail.favorite === false) {
    var form = document.querySelector('form[id^="pinForm_"] button.is-pinned');
    if (form) { /* keep going */ }
    document.querySelectorAll('.card').forEach(function (card) {
      var f = card.querySelector('form[id^="pinForm_"]');
      if (f && f.contains(document.activeElement)) {
        var col = card.closest('.col-12');
        if (col) { col.style.transition = 'opacity .2s'; col.style.opacity = '0'; setTimeout(function () { col.remove(); }, 200); }
      }
    });
  }
});
</script>