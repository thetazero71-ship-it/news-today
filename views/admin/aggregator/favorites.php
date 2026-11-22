<?php
/**
 * تبويب المفضلة — غرفة النشر السريع للأخبار المفضلة
 * أحدث الأخبار من المصادر المثبتة مرتبة: غير المنشور أولاً، مع النشر والترجمة الفورية بدون إعادة تحميل.
 */
$totalNew = (int) ($totalNew ?? 0);
$totalPublished = (int) ($totalPublished ?? 0);
$totalAll = (int) ($totalAll ?? 0);
?>
<style>
.fav-thumb {
  width: 72px;
  height: 54px;
  border-radius: 10px;
  overflow: hidden;
  background: #eef2f7;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: transform 0.2s ease;
}
.fav-thumb:hover { transform: scale(1.05); }
.fav-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }
.fav-thumb-ph { color: #94a3b8; font-size: 1.1rem; }
.text-truncate-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

/* Custom Sleek Scrollbar for News Lists */
.fav-items-scrollable {
  max-height: 480px;
  overflow-y: auto;
  overflow-x: hidden;
  scroll-behavior: smooth;
}
.fav-items-scrollable::-webkit-scrollbar { width: 6px; }
.fav-items-scrollable::-webkit-scrollbar-track { background: #f8fafc; border-radius: 8px; }
.fav-items-scrollable::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 8px; }
.fav-items-scrollable::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

.fav-item {
  transition: background-color 0.3s ease, opacity 0.25s ease, transform 0.25s ease;
}
.fav-item.is-published-item { background-color: #fbfcfe; }
.fav-item.just-published-flash { background-color: #ecfdf5 !important; }

.btn-fav-translate {
  background: linear-gradient(135deg, #10b981, #059669);
  color: #ffffff;
  border: none;
  font-weight: 700;
}
.btn-fav-translate:hover {
  background: linear-gradient(135deg, #059669, #047857);
  color: #ffffff;
}

html.admin-dark .fav-items-scrollable::-webkit-scrollbar-track { background: #0f172a; }
html.admin-dark .fav-items-scrollable::-webkit-scrollbar-thumb { background: #334155; }
html.admin-dark .fav-item.is-published-item { background-color: rgba(15, 23, 42, 0.4); }
html.admin-dark .publisher-info-badge,
html.admin-dark .publish-time-badge {
  background-color: #1e293b !important;
  color: #cbd5e1 !important;
  border-color: #334155 !important;
}
.publisher-info-badge, .publish-time-badge {
  font-size: 0.72rem !important;
  letter-spacing: 0.01em;
}
</style>

<div class="container-fluid">

 <!-- Unified Top Navigation Tabs: 1. All Sites | 2. Favorites -->
 <div class="d-flex align-items-center gap-2 mb-3">
  <a href="<?= admin_e(app_url('admin/news-feeds')) ?>" class="btn btn-sm btn-outline-dark fw-bold px-3 py-2 shadow-xs">
   <i class="bi bi-collection me-1"></i> كل المواقع والخلاصات
  </a>
  <a href="<?= admin_e(app_url('admin/news-feeds?tab=favorites')) ?>" class="btn btn-sm btn-dark fw-bold px-3 py-2 shadow-xs">
   <i class="bi bi-pin-angle-fill text-warning me-1"></i> المصادر المفضلة والمثبتة
  </a>
 </div>

 <?php if ($msg = Session::getFlash('success')): ?>
 <div class="alert alert-success alert-dismissible fade show shadow-sm rounded-4">
  <i class="bi bi-check-circle-fill me-1"></i> <?= admin_e($msg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
 </div>
 <?php endif; ?>
 <?php if ($msg = Session::getFlash('error')): ?>
 <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4">
  <i class="bi bi-exclamation-triangle-fill me-1"></i> <?= admin_e($msg) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
 </div>
 <?php endif; ?>

 <!-- Page Top Bar & Live Publishing Stats -->
 <div class="card border-0 shadow-sm rounded-4 bg-white p-3 mb-4">
  <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
   <div>
    <h2 class="h3 fw-bold mb-1 d-flex align-items-center gap-2">
     <i class="bi bi-pin-angle-fill text-warning"></i>
     <span>المفضلة — غرفة النشر السريع</span>
    </h2>
    <p class="text-muted small mb-0">
     كل مصادرك المفضلة في مكان واحد: الأخبار الجديدة بالأعلى، مع الترجمة والنشر المباشر بضغطة زر دون مغادرة الصفحة.
    </p>
   </div>

   <div class="d-flex gap-2 flex-wrap align-items-center">
    <span class="text-muted small d-none d-md-inline">عدد الأخبار لكل مصدر:</span>
    <div class="btn-group btn-group-sm" role="group" aria-label="عدد الأخبار لكل مصدر">
     <?php foreach (array(5, 8, 12, 20) as $n): ?>
     <a href="<?= admin_e(app_url('admin/news-feeds?tab=favorites&per=' . $n)) ?>"
        class="btn <?= (int) $perSource === $n ? 'btn-dark' : 'btn-outline-secondary' ?>"><?= $n ?></a>
     <?php endforeach; ?>
    </div>
   </div>
  </div>

  <!-- Live Counter Pills & Quick Filter Controls -->
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2 border-top">
   <div class="d-flex align-items-center gap-2 flex-wrap">
    <span class="badge bg-success bg-gradient px-3 py-2 fs-6 shadow-xs d-flex align-items-center gap-1" title="أخبار لم تُنشر بعد وجاهزة للنشر الفوري">
     <i class="bi bi-stars"></i>
     <strong id="favLiveNewCount"><?= $totalNew ?></strong> خبر جديد لم يُنشر
    </span>
    <span class="badge bg-primary-subtle text-primary border px-3 py-2 fs-6 shadow-xs d-flex align-items-center gap-1" title="أخبار نُشرت بالفعل للجمهور">
     <i class="bi bi-check-circle-fill"></i>
     <strong id="favLivePubCount"><?= $totalPublished ?></strong> منشور
    </span>
    <span class="badge bg-light text-dark border px-2 py-2 small d-none d-sm-inline" title="إجمالي الأخبار المسترجعة من المفضلة">
     الإجمالي: <strong id="favLiveTotalCount"><?= $totalAll ?></strong>
    </span>
   </div>

   <div class="d-flex align-items-center gap-2 flex-wrap">
    <div class="btn-group btn-group-sm" role="group" id="favFilterGroup">
     <button type="button" class="btn btn-dark active" data-filter="all" onclick="filterFavByStatus('all', this)">
      الكل (<span id="btnCntAll"><?= $totalAll ?></span>)
     </button>
     <button type="button" class="btn btn-outline-success" data-filter="new" onclick="filterFavByStatus('new', this)">
      غير منشور فقط (<span id="btnCntNew"><?= $totalNew ?></span>)
     </button>
     <button type="button" class="btn btn-outline-primary" data-filter="published" onclick="filterFavByStatus('published', this)">
      منشور (<span id="btnCntPub"><?= $totalPublished ?></span>)
     </button>
    </div>

    <div class="input-group input-group-sm" style="max-width:240px">
     <span class="input-group-text bg-light text-muted border-end-0"><i class="bi bi-search"></i></span>
     <input type="text" id="favSearchInput" class="form-control border-start-0 bg-light" placeholder="بحث في الأخبار..." oninput="filterFavNewsLive()">
    </div>
   </div>
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
 <div class="alert alert-warning d-flex align-items-center gap-2 rounded-4 shadow-sm mb-3">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <span><?= (int) $feedErrors ?> مصدر لم يستجب هذه المرة — سيُعاد المحاولة عند التحديث.</span>
 </div>
 <?php endif; ?>

 <!-- Sources Deck Grid -->
 <div class="row g-3" id="favoritesGrid">
 <?php foreach ($groups as $group): ?>
 <?php
 $src = $group['source'];
 $srcNew = (int) ($group['new_count'] ?? 0);
 $srcPub = (int) ($group['published_count'] ?? 0);
 ?>
 <div class="col-12 col-xl-6 fav-source-col">
  <div class="card border-0 shadow-sm rounded-4 bg-white h-100 overflow-hidden">
   <!-- Source Header -->
   <div class="card-header bg-white d-flex align-items-center gap-2 border-bottom py-3">
    <i class="bi bi-pin-angle-fill text-warning"></i>
    <div class="flex-grow-1 min-w-0">
     <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= admin_e(app_url('admin/news-feeds?source_id=' . (int) $src['id'])) ?>"
         class="fw-bold text-dark text-decoration-none text-truncate" title="فتح هذا المصدر في المجمّع">
       <?= admin_e($src['name']) ?>
      </a>
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
     </div>
     <div class="small text-muted text-truncate" style="max-width:320px"><?= admin_e($src['url']) ?></div>
    </div>

    <!-- Per-Source Counter Badges -->
    <div class="d-flex align-items-center gap-1">
     <span class="badge bg-success-subtle text-success border border-success-subtle source-badge-new" title="أخبار جديدة لم تُنشر من هذا المصدر">
      +<?= $srcNew ?> جديد
     </span>
     <span class="badge bg-light text-muted border source-badge-pub" title="أخبار منشورة من هذا المصدر">
      <?= $srcPub ?> منشور
     </span>

     <!-- Pin Toggle Button -->
     <form method="post" action="<?= admin_e(app_url('admin/rss-sources/' . (int) $src['id'] . '/toggle-favorite')) ?>"
           id="pinForm_<?= (int) $src['id'] ?>" class="d-inline ms-1">
      <?= CSRF::field() ?>
      <input type="hidden" name="back" value="favorites">
      <button type="submit" class="btn btn-sm btn-link text-danger p-0 is-pinned" title="إزالة من المفضلة">
       <i class="bi bi-pin-angle-fill text-warning" style="font-size:1.1rem"></i>
      </button>
     </form>
    </div>
   </div>

   <!-- Scrollable News Feed List -->
   <div class="card-body p-0 fav-items-scrollable">
    <?php if (!empty($group['error'])): ?>
    <div class="p-3 text-muted small">
     <i class="bi bi-plug me-1"></i>تعذّر جلب الخلاصة: <?= admin_e(mb_substr((string) $group['error'], 0, 120)) ?>
    </div>
    <?php elseif (empty($group['items'])): ?>
    <div class="p-3 text-muted small">لا توجد أخبار في هذه الخلاصة حالياً.</div>
    <?php else: ?>
    <ul class="list-group list-group-flush fav-items-list" id="sourceList_<?= (int) $src['id'] ?>">
    <?php foreach ($group['items'] as $item): ?>
    <?php
    $published = ($item['import_status'] ?? '') === 'published';
    $thumb = trim((string) ($item['featured_image'] ?? ''));
    if ($thumb !== '' && !preg_match('#^https?://#i', $thumb)) {
     $thumb = '';
    }
    ?>
    <li class="list-group-item d-flex align-items-center gap-3 fav-item <?= $published ? 'is-published-item' : '' ?>"
        data-status="<?= $published ? 'published' : 'new' ?>"
        data-url="<?= admin_e($item['link'] ?? '') ?>"
        data-title="<?= admin_e(mb_strtolower($item['title'] ?? '', 'UTF-8')) ?>"
        data-source-id="<?= (int) $src['id'] ?>">

     <!-- Thumbnail -->
     <div class="fav-thumb flex-shrink-0">
      <?php if ($thumb !== ''): ?>
      <img src="<?= admin_e($thumb) ?>" alt="" loading="lazy" width="72" height="54"
           onerror="this.outerHTML='<span class=&quot;fav-thumb-ph&quot;><i class=&quot;bi bi-image&quot;></i></span>'">
      <?php else: ?>
      <span class="fav-thumb-ph"><i class="bi bi-image"></i></span>
      <?php endif; ?>
     </div>

     <!-- Article Details -->
     <div class="flex-grow-1 min-w-0">
      <div class="fw-semibold text-dark text-truncate-2" style="line-height:1.45" title="<?= admin_e($item['title'] ?? '') ?>">
       <?= admin_e($item['title'] ?? '') ?>
      </div>
      <div class="d-flex align-items-center gap-2 mt-1 flex-wrap fav-item-meta">
       <?php
        $rawPub = $item['pubDate'] ?? '';
        if (empty($rawPub) || $rawPub === '-' || $rawPub === '0000-00-00 00:00') {
            $itTs = (int) ($item['timestamp'] ?? 0);
            if ($itTs <= 0) $itTs = (int) ($item['first_seen_at'] ?? 0);
            $displayDate = ($itTs > 0) ? fmt_date($itTs, 'Y-m-d H:i') : fmt_date('now', 'Y-m-d H:i');
        } else {
            $displayDate = $rawPub;
        }
        if ($displayDate === '-' || empty($displayDate)) {
            $displayDate = date('Y-m-d H:i');
        }
        ?>
        <small class="text-muted font-monospace" style="font-size:0.75rem" dir="ltr" title="تاريخ ووقت وصول الخبر من المصدر">
        <i class="bi bi-clock me-1"></i><?= admin_e($displayDate) ?>
       </small>
       <?php if ($published): ?>
       <span class="badge bg-success-subtle text-success border border-success-subtle small py-0 px-1 status-text-badge">منشور</span>
       <span class="badge bg-light text-dark border small py-0 px-2 d-inline-flex align-items-center gap-1 publisher-info-badge" title="الناشر">
        <i class="bi bi-person-fill text-primary"></i>
        <span class="publisher-name-val"><?= admin_e($item['author_name'] ?: 'مدير النظام') ?></span>
       </span>
       <span class="badge bg-light text-muted border small py-0 px-2 d-inline-flex align-items-center gap-1 publish-time-badge font-monospace" dir="ltr" title="تاريخ ووقت النشر الدقيق">
        <i class="bi bi-clock-history text-secondary"></i>
        <span class="published-at-val"><?= admin_e(fmt_date($item['published_at'] ?? '', 'Y-m-d H:i')) ?></span>
       </span>
       <?php else: ?>
       <span class="badge bg-warning-subtle text-dark border border-warning-subtle small py-0 px-1 status-text-badge">جديد</span>
       <?php endif; ?>
       <?php if (!empty($item['duplicate_info']['is_duplicate'])): ?>
       <span class="badge bg-warning text-dark small py-0 px-1" title="تغطية مكررة من مصدر آخر أو نُشرت سابقاً">
        <i class="bi bi-intersect me-1"></i> مكرر <?= (int) $item['duplicate_info']['score'] ?>%
       </span>
       <?php endif; ?>
      </div>
      <?php if (!empty($item['duplicate_info']['is_duplicate'])): ?>
      <div class="text-muted small text-truncate mt-1" style="font-size:0.72rem;max-width:350px">
       <?php if (!empty($item['duplicate_info']['is_published'])): ?>
        نُشر مسبقاً: <?= admin_e($item['duplicate_info']['matched_title']) ?>
       <?php else: ?>
        تناوله المصدر [<?= admin_e($item['duplicate_info']['matched_source']) ?>]: «<?= admin_e($item['duplicate_info']['matched_title']) ?>»
       <?php endif; ?>
      </div>
      <?php endif; ?>
     </div>

     <!-- Action Buttons Column -->
     <div class="d-flex flex-column gap-1 flex-shrink-0 fav-actions-col" style="min-width:130px">
      <?php if ($published): ?>
      <span class="btn btn-sm btn-success disabled w-100 py-1" style="pointer-events:none">
       <i class="bi bi-check-circle-fill me-1"></i> منشور
      </span>
      <?php if (!empty($item['article_id'])): ?>
      <a href="<?= admin_e(app_url('admin/articles/' . (int) $item['article_id'] . '/edit')) ?>"
         class="btn btn-sm btn-outline-primary w-100 py-1" title="تعديل المقال في المحرر">
       <i class="bi bi-pencil-square me-1"></i> تعديل
      </a>
      <?php endif; ?>
      <a href="<?= admin_e($item['link'] ?? '#') ?>" target="_blank" rel="noopener"
         class="btn btn-sm btn-light border text-muted w-100 py-1 small" title="فتح الرابط الأصلي">
       <i class="bi bi-box-arrow-up-right me-1"></i> المصدر
      </a>
      <?php else: ?>
      <!-- 1. Translate & Publish with AI (AJAX in-place) -->
      <form method="post" action="<?= admin_e(app_url('admin/news-feeds/translate-publish')) ?>" class="d-inline fav-publish-form" data-action="translate">
       <?= CSRF::field() ?>
       <input type="hidden" name="title" value="<?= admin_e($item['title'] ?? '') ?>">
       <input type="hidden" name="excerpt" value="<?= admin_e($item['excerpt'] ?? '') ?>">
       <input type="hidden" name="content" value="<?= admin_e($item['content'] ?? ($item['description'] ?? '')) ?>">
       <input type="hidden" name="source_url" value="<?= admin_e($item['link'] ?? '') ?>">
       <input type="hidden" name="source_name" value="<?= admin_e($src['name'] ?? '') ?>">
       <input type="hidden" name="featured_image" value="<?= admin_e($thumb) ?>">
       <button type="submit" class="btn btn-sm btn-fav-translate w-100 py-1 shadow-xs" title="ترجمة فورية بالذكاء الاصطناعي ونشر بالعربية">
        <i class="bi bi-translate me-1"></i> ترجمة ونشر
       </button>
      </form>

      <!-- 2. Fast Direct Publish (No Translation - AJAX in-place) -->
      <form method="post" action="<?= admin_e(app_url('admin/news-feeds/fast-publish')) ?>" class="d-inline fav-publish-form" data-action="fast">
       <?= CSRF::field() ?>
       <input type="hidden" name="title" value="<?= admin_e($item['title'] ?? '') ?>">
       <input type="hidden" name="excerpt" value="<?= admin_e($item['excerpt'] ?? '') ?>">
       <input type="hidden" name="content" value="<?= admin_e($item['content'] ?? ($item['description'] ?? '')) ?>">
       <input type="hidden" name="source_url" value="<?= admin_e($item['link'] ?? '') ?>">
       <input type="hidden" name="source_name" value="<?= admin_e($src['name'] ?? '') ?>">
       <input type="hidden" name="featured_image" value="<?= admin_e($thumb) ?>">
       <button type="submit" class="btn btn-sm btn-outline-primary fw-semibold w-100 py-1" title="نشر مباشر بدون ترجمة (للأخبار العربية)">
        <i class="bi bi-lightning-charge"></i> نشر فوري
       </button>
      </form>

      <!-- 3. Source Link -->
      <a href="<?= admin_e($item['link'] ?? '#') ?>" target="_blank" rel="noopener"
         class="btn btn-sm btn-light border text-muted w-100 py-1 small" title="فتح الرابط الأصلي">
       <i class="bi bi-box-arrow-up-right me-1"></i> المصدر
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
// ================= FAVORITES RAPID PUBLISHING & LIVE SORTING (ported from reference) =================
(function () {
  let currentFilter = 'all';

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function notify(msg, type) {
    if (window.adminToast) {
      window.adminToast(msg, type || 'success', 5000);
    } else {
      let t = document.getElementById('favToastBox');
      if (!t) {
        t = document.createElement('div');
        t.id = 'favToastBox';
        t.style.cssText = 'position:fixed;top:18px;left:50%;transform:translateX(-50%);z-index:1090;max-width:640px;width:calc(100% - 32px)';
        document.body.appendChild(t);
      }
      t.innerHTML = '<div class="alert alert-' + (type === 'error' ? 'danger' : 'success') + ' alert-dismissible fade show shadow-lg small mb-0">'
        + msg + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
      setTimeout(function () { t.innerHTML = ''; }, 6000);
    }
  }

  document.addEventListener('submit', function (e) {
    const form = e.target.closest('.fav-publish-form');
    if (!form) return;
    e.preventDefault();

    const btn = form.querySelector('button[type=submit]');
    if (!btn || btn.disabled) return;
    const originalHtml = btn.innerHTML;
    const action = form.getAttribute('data-action') || 'publish';
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> ' + (action === 'translate' ? 'جارٍ الترجمة والنشر...' : 'جارٍ النشر...');

    fetch(form.action, {
      method: 'POST',
      body: new FormData(form),
      headers: { 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    })
    .then(function (res) {
      return res.text().then(function (t) { return { ok: res.ok, status: res.status, text: t }; });
    })
    .then(function (r) {
      if (!r.text || !r.text.trim()) throw new Error('استجابة الخادم فارغة. تحقق من الاتصال وحاول مرة أخرى.');
      let data;
      try { data = JSON.parse(r.text); }
      catch (err) { throw new Error('استجابة غير صالحة من الخادم (HTTP ' + r.status + ').'); }
      if (!data || !data.success) throw new Error((data && (data.error || data.message)) || 'فشل النشر.');

      notify(esc(data.message || 'تم النشر بنجاح!'), 'success');

      const itemLi = form.closest('.fav-item');
      if (itemLi) {
        const actionsCol = itemLi.querySelector('.fav-actions-col');
        if (actionsCol) {
          let editBtn = '';
          if (data.edit_url) {
            editBtn = '<a href="' + esc(data.edit_url) + '" class="btn btn-sm btn-outline-primary w-100 py-1" title="تعديل المقال في المحرر">'
              + '<i class="bi bi-pencil-square me-1"></i> تعديل</a>';
          }
          const origSrcLink = itemLi.querySelector('a[target="_blank"]');
          const srcHref = origSrcLink ? origSrcLink.href : '#';
          actionsCol.innerHTML = '<span class="btn btn-sm btn-success disabled w-100 py-1" style="pointer-events:none">'
            + '<i class="bi bi-check-circle-fill me-1"></i> منشور</span>'
            + editBtn
            + '<a href="' + esc(srcHref) + '" target="_blank" rel="noopener" class="btn btn-sm btn-light border text-muted w-100 py-1 small" title="فتح الرابط الأصلي">'
            + '<i class="bi bi-box-arrow-up-right me-1"></i> المصدر</a>';
        }

        const statusBadge = itemLi.querySelector('.status-text-badge');
        if (statusBadge) {
          statusBadge.className = 'badge bg-success-subtle text-success border border-success-subtle small py-0 px-1 status-text-badge';
          statusBadge.textContent = 'منشور';
        }

        const metaRow = itemLi.querySelector('.fav-item-meta');
        if (metaRow) {
          const authorName = (data && data.author_name) ? data.author_name : 'مدير النظام';
          const pubTime = (data && data.published_at) ? data.published_at : '';
          if (!metaRow.querySelector('.publisher-info-badge')) {
            const publisherSpan = document.createElement('span');
            publisherSpan.className = 'badge bg-light text-dark border small py-0 px-2 d-inline-flex align-items-center gap-1 publisher-info-badge';
            publisherSpan.title = 'الناشر';
            publisherSpan.innerHTML = '<i class="bi bi-person-fill text-primary"></i> <span class="publisher-name-val">' + esc(authorName) + '</span>';
            metaRow.appendChild(publisherSpan);
          }
          if (pubTime && !metaRow.querySelector('.publish-time-badge')) {
            const timeSpan = document.createElement('span');
            timeSpan.className = 'badge bg-light text-muted border small py-0 px-2 d-inline-flex align-items-center gap-1 publish-time-badge font-monospace';
            timeSpan.setAttribute('dir', 'ltr');
            timeSpan.title = 'تاريخ ووقت النشر الدقيق';
            timeSpan.innerHTML = '<i class="bi bi-clock-history text-secondary"></i> <span class="published-at-val">' + esc(pubTime) + '</span>';
            metaRow.appendChild(timeSpan);
          }
        }

        itemLi.setAttribute('data-status', 'published');
        itemLi.classList.add('is-published-item', 'just-published-flash');

        const parentList = itemLi.parentElement;
        if (parentList) {
          setTimeout(function () {
            itemLi.classList.remove('just-published-flash');
            parentList.appendChild(itemLi);
            if (currentFilter === 'new') itemLi.style.display = 'none';
          }, 450);
        }

        const liveNew = document.getElementById('favLiveNewCount');
        const livePub = document.getElementById('favLivePubCount');
        const btnNew = document.getElementById('btnCntNew');
        const btnPub = document.getElementById('btnCntPub');
        if (liveNew) {
          const curNew = Math.max(0, parseInt(liveNew.textContent || '0', 10) - 1);
          liveNew.textContent = String(curNew);
          if (btnNew) btnNew.textContent = String(curNew);
        }
        if (livePub) {
          const curPub = parseInt(livePub.textContent || '0', 10) + 1;
          livePub.textContent = String(curPub);
          if (btnPub) btnPub.textContent = String(curPub);
        }

        const cardCol = itemLi.closest('.fav-source-col');
        if (cardCol) {
          const sBadgeNew = cardCol.querySelector('.source-badge-new');
          const sBadgePub = cardCol.querySelector('.source-badge-pub');
          if (sBadgeNew) {
            const m = (sBadgeNew.textContent || '').match(/\d+/);
            if (m) sBadgeNew.textContent = '+' + Math.max(0, parseInt(m[0], 10) - 1) + ' جديد';
          }
          if (sBadgePub) {
            const m = (sBadgePub.textContent || '').match(/\d+/);
            if (m) sBadgePub.textContent = (parseInt(m[0], 10) + 1) + ' منشور';
          }
        }
      }
    })
    .catch(function (err) {
      notify(err.message, 'error');
      btn.disabled = false;
      btn.innerHTML = originalHtml;
    });
  });

  window.filterFavByStatus = function (status, btnElement) {
    currentFilter = status;
    const group = document.getElementById('favFilterGroup');
    if (group && btnElement) {
      group.querySelectorAll('.btn').forEach(function (b) {
        b.classList.remove('btn-dark', 'btn-success', 'btn-primary', 'active');
        if (b.getAttribute('data-filter') === 'all') b.className = 'btn btn-outline-dark';
        if (b.getAttribute('data-filter') === 'new') b.className = 'btn btn-outline-success';
        if (b.getAttribute('data-filter') === 'published') b.className = 'btn btn-outline-primary';
      });
      if (status === 'all') btnElement.className = 'btn btn-dark active';
      if (status === 'new') btnElement.className = 'btn btn-success text-white active';
      if (status === 'published') btnElement.className = 'btn btn-primary text-white active';
    }

    document.querySelectorAll('.fav-item').forEach(function (it) {
      const itStatus = it.getAttribute('data-status') || 'new';
      if (status === 'all') it.style.display = '';
      else if (status === 'new') it.style.display = (itStatus === 'new') ? '' : 'none';
      else if (status === 'published') it.style.display = (itStatus === 'published') ? '' : 'none';
    });
  };

  window.filterFavNewsLive = function () {
    const input = document.getElementById('favSearchInput');
    const term = (input ? input.value : '').trim().toLowerCase();
    document.querySelectorAll('.fav-item').forEach(function (it) {
      const title = (it.getAttribute('data-title') || '').toLowerCase();
      const status = it.getAttribute('data-status') || 'new';
      const matchesText = !term || title.indexOf(term) !== -1;
      const matchesFilter = (currentFilter === 'all') || (currentFilter === status);
      it.style.display = (matchesText && matchesFilter) ? '' : 'none';
    });
  };

  document.addEventListener('admin:favorite-changed', function (e) {
    if (!e.detail || e.detail.favorite !== false) return;
    const id = String(e.detail.id || '');
    if (!id) return;
    const form = document.getElementById('pinForm_' + id);
    const card = form ? form.closest('.card') : null;
    const col = card ? card.closest('.col-12') : null;
    if (!col) return;
    col.style.transition = 'opacity .2s';
    col.style.opacity = '0';
    setTimeout(function () {
      col.remove();
      if (!document.querySelector('.row.g-3 > .col-12')) window.location.reload();
    }, 200);
  });
})();
</script>
