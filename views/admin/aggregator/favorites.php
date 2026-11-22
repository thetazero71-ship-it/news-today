<?php
/**
 * تبويب المفضلة — كل المصادر المفضلة وأحدث أخبارها في مكان واحد.
 */
$favPublishFields = function (array $item, string $sourceName) {
    $fields = array(
        'title' => (string) ($item['title'] ?? ''),
        'excerpt' => (string) ($item['excerpt'] ?? ''),
        'content' => (string) ($item['content'] ?? ''),
        'source_url' => (string) ($item['link'] ?? ''),
        'source_name' => $sourceName,
        'featured_image' => (string) ($item['featured_image'] ?? ''),
    );
    $html = '';
    foreach ($fields as $name => $value) {
        $html .= '<input type="hidden" name="' . admin_e($name) . '" value="' . admin_e($value) . '">';
    }
    return $html;
};
?>
<style>
.fav-thumb { width: 72px; height: 54px; border-radius: 10px; overflow: hidden; background: #eef2f7; display: flex; align-items: center; justify-content: center; }
.fav-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.fav-thumb-ph { color: #94a3b8; font-size: 1.1rem; }
</style>
<div class="container-fluid">

 <?php if ($msg = Session::getFlash('success')): ?>
 <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
 <i class="bi bi-check-circle-fill me-1"></i> <?= admin_e($msg) ?>
 <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
 </div>
 <?php endif; ?>
 <?php if ($msg = Session::getFlash('error')): ?>
 <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
 <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= admin_e($msg) ?>
 <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
 </div>
 <?php endif; ?>

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
  <div class="btn-group btn-group-sm" role="group" aria-label="فلترة سريعة">
  <button type="button" class="btn btn-dark" data-fav-filter="all">الكل</button>
  <button type="button" class="btn btn-outline-secondary" data-fav-filter="new">غير منشور</button>
  <button type="button" class="btn btn-outline-secondary" data-fav-filter="published">منشور</button>
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
 <?php
 $src = $group['source'];
 $newCount = 0;
 foreach ($group['items'] as $gi) {
  if (($gi['import_status'] ?? '') !== 'published') $newCount++;
 }
 ?>
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
    <?php if (!empty($group['from_cache'])): ?>
    <span class="badge bg-info text-dark" title="المصدر لم يستجب الآن — تُعرض آخر نسخة محفوظة (48 ساعة)">
    <i class="bi bi-archive-fill me-1"></i>كاش
    </span>
    <?php endif; ?>
    <span class="badge bg-success-subtle text-success border fav-new-count" data-source-id="<?= (int) $src['id'] ?>" title="عناصر لم تُنشر بعد">
    <i class="bi bi-stars me-1"></i><?= (int) $newCount ?> جديد
    </span>
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
    <ul class="list-group list-group-flush fav-list-scroll">
    <?php foreach ($group['items'] as $item): ?>
    <?php
    $published = ($item['import_status'] ?? '') === 'published';
    $thumb = trim((string) ($item['featured_image'] ?? ''));
    if ($thumb !== '' && !preg_match('#^https?://#i', $thumb)) {
     $thumb = '';
    }
    ?>
    <li class="list-group-item d-flex align-items-center gap-3 fav-item" data-url="<?= admin_e($item['link'] ?? '') ?>" data-fav-duplicate="<?= !empty($item['duplicate_of']) ? '1' : '0' ?>" data-fav-status="<?= $published ? 'published' : 'new' ?>" data-fav-source="<?= (int) $src['id'] ?>">
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
      <div class="small text-muted" dir="ltr"><span class="font-monospace"><?= admin_e($item['pubDate'] ?? '') ?></span></div>
      <?php if (!empty($item['duplicate_of'])): ?>
      <div class="small text-warning-emphasis" title="مطابق لخبر سابق: <?= admin_e($item['duplicate_title'] ?? '') ?>">
      <i class="bi bi-files me-1"></i>خبر مكرر
      </div>
      <?php endif; ?>
      <?php if ($published && (!empty($item['publisher_name']) || !empty($item['published_at']))): ?>
      <div class="small d-flex align-items-center flex-wrap gap-1" style="color:#0f766e">
      <i class="bi bi-person-check-fill"></i>
      <?php if (!empty($item['publisher_name'])): ?><strong><?= admin_e($item['publisher_name']) ?></strong><?php endif; ?>
      <?php if (!empty($item['published_at'])): ?><span class="text-muted">·</span><span dir="ltr" class="font-monospace text-muted"><?= admin_e(fmt_date($item['published_at'], 'Y-m-d H:i')) ?></span><?php endif; ?>
      </div>
      <?php endif; ?>
     </div>
     <div class="d-flex flex-column gap-1 fav-actions" data-source-id="<?= (int) $src['id'] ?>" style="min-width:150px">
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
      <div class="btn-group">
      <button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" title="اختر طريقة النشر">
      <i class="bi bi-lightning-charge"></i> نشر
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
      <li>
      <form method="post" action="<?= admin_e(app_url('admin/news-feeds/translate-publish')) ?>" class="m-0 fav-publish-form" data-action="translate">
      <?= CSRF::field() ?>
      <?= $favPublishFields($item, (string) $src['name']) ?>
      <button type="submit" class="dropdown-item fw-semibold">
      <i class="bi bi-stars text-success me-1"></i> ترجمة ونشر (عربي)
      </button>
      </form>
      </li>
      <li>
      <form method="post" action="<?= admin_e(app_url('admin/news-feeds/fast-publish')) ?>" class="m-0 fav-publish-form" data-action="fast">
      <?= CSRF::field() ?>
      <?= $favPublishFields($item, (string) $src['name']) ?>
      <button type="submit" class="dropdown-item fw-semibold">
      <i class="bi bi-lightning-charge-fill text-primary me-1"></i> نشر فوري (بدون ترجمة)
      </button>
      </form>
      </li>
      <li><hr class="dropdown-divider"></li>
      <li>
      <form method="post" action="<?= admin_e(app_url('admin/news-feeds/draft-article')) ?>" class="m-0 fav-publish-form" data-action="draft">
      <?= CSRF::field() ?>
      <?= $favPublishFields($item, (string) $src['name']) ?>
      <button type="submit" class="dropdown-item">
      <i class="bi bi-pencil-square me-1"></i> تحرير كمسودة
      </button>
      </form>
      </li>
      </ul>
      </div>
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
  // removed from favourites: this card goes away without touching the page
  if (!e.detail || e.detail.favorite !== false) return;
  var id = String(e.detail.id || '');
  if (!id) return;
  var form = document.getElementById('pinForm_' + id);
  var card = form ? form.closest('.card') : null;
  var col = card ? card.closest('.col-12') : null;
  if (!col) return;

  col.style.transition = 'opacity .2s';
  col.style.opacity = '0';
  setTimeout(function () {
    col.remove();
    // last favourite removed: bring back the empty state
    if (!document.querySelector('.row.g-3 > .col-12')) {
      window.location.reload();
    }
  }, 200);
});
</script>

<script>
// ================= Rapid publishing deck (AJAX, in place) =================
(function () {
 const filterButtons = Array.prototype.slice.call(document.querySelectorAll('[data-fav-filter]'));
 let currentFilter = 'all';

 function applyFilter() {
  document.querySelectorAll('.fav-item').forEach(function (item) {
   const status = item.getAttribute('data-fav-status') || 'new';
   const show = currentFilter === 'all'
    || (currentFilter === 'new' ? status !== 'published' : status === currentFilter);
   item.style.display = show ? '' : 'none';
  });
 }

 filterButtons.forEach(function (btn) {
  btn.addEventListener('click', function () {
   currentFilter = btn.getAttribute('data-fav-filter') || 'all';
   filterButtons.forEach(function (b) {
    const on = b === btn;
    b.classList.toggle('btn-dark', on);
    b.classList.toggle('btn-outline-secondary', !on);
   });
   applyFilter();
  });
 });

 function deckToast(message, type) {
  if (window.adminToast) window.adminToast(message, type || 'info');
 }

 function refreshCounter(sourceId) {
  const counter = document.querySelector('.fav-new-count[data-source-id="' + sourceId + '"]');
  if (!counter) return;
  const m = (counter.textContent || '').match(/\d+/);
  const n = Math.max(0, (m ? parseInt(m[0], 10) : 1) - 1);
  counter.innerHTML = '<i class="bi bi-stars me-1"></i>' + n + ' جديد';
 }

 function markItem(item, state, editUrl, label) {
  item.setAttribute('data-fav-status', state);
  const actions = item.querySelector('.fav-actions');
  if (actions && editUrl) {
   actions.innerHTML = '<span class="btn btn-sm ' + (state === 'published' ? 'btn-success' : 'btn-warning text-dark') + ' disabled" style="pointer-events:none">'
    + '<i class="bi ' + (state === 'published' ? 'bi-check-circle-fill' : 'bi-pencil-square') + '"></i> ' + label + '</span>'
    + ' <a class="btn btn-sm btn-outline-primary" href="' + String(editUrl).replace(/"/g, '&quot;') + '"><i class="bi bi-pencil-square"></i> تعديل</a>';
  }
  refreshCounter(item.getAttribute('data-fav-source') || '');
  applyFilter();
 }

 document.querySelectorAll('.fav-publish-form').forEach(function (form) {
  form.addEventListener('submit', async function (event) {
   event.preventDefault();
   if (form.dataset.busy === '1') return;
   form.dataset.busy = '1';

   const item = form.closest('.fav-item');
   const action = form.getAttribute('data-action') || 'translate';
   const button = form.querySelector('button[type="submit"], button');
   const original = button ? button.innerHTML : '';
   if (button) {
    button.disabled = true;
    button.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> جارٍ التنفيذ…';
   }

   try {
    const res = await fetch(form.action, {
     method: 'POST',
     body: new FormData(form),
     credentials: 'same-origin',
     headers: { 'X-Requested-With': 'XMLHttpRequest' }
    });
    const data = await res.json();
    if (!data || !data.success) {
     throw new Error((data && (data.message || data.error)) || 'تعذر تنفيذ العملية.');
    }

    if (action === 'draft') {
     markItem(item, 'draft', data.edit_url || '', 'مسودة');
     deckToast('تم استيراد الخبر كمسودة — يمكنك تحريره الآن.', 'success');
    } else {
     markItem(item, 'published', data.edit_url || '', 'منشور');
     deckToast(data.message || 'تم النشر بنجاح.', 'success');
    }
   } catch (err) {
    deckToast(err.message || 'تعذر تنفيذ العملية.', 'error');
    if (button) {
     button.disabled = false;
     button.innerHTML = original;
    }
   } finally {
    form.dataset.busy = '0';
   }
  });
 });
})();
</script>