<?php
$pageTitle = 'محرك البحث الذكي | ' . Settings::get('site_name_ar', 'عصب التقنية');
$pageDesc  = 'ابحث في كافة أخبار وتحليلات وشروحات المنصة التقنية.';

$isSearching = ($q !== '');
$hasFilters  = ($category > 0 || $from !== '' || $to !== '');

// helper for building a URL that keeps every active filter
$searchUrl = function (array $overrides = array()) use ($baseQuery) {
    $params = array_merge($baseQuery, $overrides);
    return app_url('search') . '?' . http_build_query($params);
};

require_once APP_ROOT . '/views/partials/header.php';
?>

<main class="container page-shell" style="padding:24px 0 80px">

    <div class="search-filter-card">
        <span class="badge-tag">محرك البحث المتقدم</span>
        <h1 class="search-page-title">استكشف أرشيف الأخبار والتحليلات</h1>
        <p class="search-page-subtitle">بحث ذكي يتجاهل الفروق بين الألف والياء والتاء المربوطة — فيجد الخبر حتى لو اختلفت كتابته</p>

        <form method="get" action="<?= view_e(app_url('search')) ?>" class="search-filter-form" id="searchForm" autocomplete="off">
            <input type="text" name="q" value="<?= view_e($q) ?>" placeholder="اكتب كلمة أو جملة… مثال: الجيش الأمريكي" class="search-main-input" id="searchInput">

            <div id="searchSuggestBox" class="search-suggest-box" hidden></div>

            <div class="search-options-grid">
                <select name="category_id" class="search-select">
                    <option value="">جميع الأقسام</option>
                    <?php foreach ($categories as $cat): ?>
                        <option value="<?= view_e($cat['id']) ?>" <?= (int) $category === (int) $cat['id'] ? 'selected' : '' ?>>
                            <?= view_e($cat['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <input type="date" name="from" value="<?= view_e($from) ?>" class="search-date-input" title="من تاريخ">

                <select name="sort" class="search-select">
                    <option value="relevance" <?= $sort === 'relevance' ? 'selected' : '' ?>>الأكثر صلة</option>
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>الأحدث أولاً</option>
                    <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>الأكثر قراءة</option>
                    <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>الأقدم</option>
                </select>

                <button type="submit" class="btn-primary-glow search-submit-btn">
                    <span>بحث ⌕</span>
                </button>
            </div>
        </form>
    </div>

    <?php if ($isSearching): ?>
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
        <span style="font-size:0.95rem;color:var(--text-muted)">
            <?php if ($tooShort): ?>
                اكتب حرفين على الأقل للبحث
            <?php else: ?>
                عدد النتائج: <b style="color:var(--accent-primary)"><?= (int) $total ?></b>
                <?php if ($hasFilters): ?>
                    <span class="badge bg-secondary-subtle text-secondary" style="margin-inline-start:8px">مع فلاتر</span>
                <?php endif; ?>
            <?php endif; ?>
        </span>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
            <span style="font-size:0.85rem;color:var(--text-dim)">عنوان البحث: "<?= view_e($q) ?>"</span>
            <?php if ($hasFilters): ?>
                <a href="<?= view_e($searchUrl()) ?>" class="btn btn-sm btn-outline-secondary">مسح الفلاتر ✕</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="news-grid">
        <?php if (!$isSearching): ?>
            <div style="grid-column: 1 / -1; padding: 40px; text-align: center; background: var(--bg-surface); border-radius: var(--radius-card); border: 1px solid var(--border-subtle); color: var(--text-muted)">
                <div style="font-size: 3rem; margin-bottom: 12px">🔍</div>
                <h3>ابدأ البحث</h3>
                <p>اكتب كلمة مفتاحية في الحقل أعلاه — نبحث في العناوين والمحتوى العربي والإنجليزي والمصدر والقسم.</p>
            </div>
        <?php elseif (empty($articles)): ?>
            <div style="grid-column: 1 / -1; padding: 40px; text-align: center; background: var(--bg-surface); border-radius: var(--radius-card); border: 1px solid var(--border-subtle); color: var(--text-muted)">
                <div style="font-size: 3rem; margin-bottom: 12px"><?= $tooShort ? '⌨️' : '🔍' ?></div>
                <?php if ($tooShort): ?>
                    <h3>عبارة البحث قصيرة جداً</h3>
                    <p>اكتب حرفين على الأقل — حرف واحد يُعطي آلاف النتائج بلا فائدة.</p>
                <?php else: ?>
                    <h3>لم نعثر على نتائج مطابقة</h3>
                    <p>جرّب كلمات أقل، أو أزل الفلاتر (القسم/التاريخ).</p>
                    <?php if (!empty($suggestions)): ?>
                        <p style="margin-top:14px">هل تقصد أحد هذه؟</p>
                        <div style="display:flex;flex-direction:column;gap:6px;max-width:520px;margin:0 auto;text-align:start">
                            <?php foreach ($suggestions as $s): ?>
                                <a href="<?= view_e($s['url']) ?>" style="color:var(--accent-primary);text-decoration:none"><?= view_e($s['title']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <?php foreach ($articles as $article): ?>
                <?php
                $cardTitle = trim((string) ($article['title_ar'] ?: $article['title']));
                $excerptHtml = $isSearching
                    ? SearchQuery::highlight($q, (string) ($article['excerpt'] ?: $article['content']), 200)
                    : view_e($article['excerpt'] ?? '');
                ?>
                <article class="news-card" <?= news_card_attrs($article) ?>>
                    <div class="card-img-wrap">
                        <span class="card-tag"><?= view_e($article['category_name'] ?? 'أخبار') ?></span>
                        <button class="card-bookmark-btn"
                                data-article-id="<?= view_e($article['id']) ?>"
                                data-title="<?= view_e($cardTitle) ?>"
                                data-url="<?= view_e(app_url('article/' . $article['slug'])) ?>"
                                data-category="<?= view_e($article['category_name'] ?? '') ?>"
                                title="حفظ للقراءة">☆</button>
                        <?php if (!empty($article['featured_image'])): ?>
                            <img src="<?= view_e(app_url($article['featured_image'])) ?>" alt="<?= view_e($cardTitle) ?>" loading="lazy">
                        <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--bg-surface-elevated);display:grid;place-items:center;color:var(--accent-primary);font-size:2rem">⚡</div>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <h3><a href="<?= view_e(app_url('article/' . $article['slug'])) ?>"><?= view_e($cardTitle) ?></a></h3>
                        <p><?= $excerptHtml ?></p>
                        <div class="card-footer">
                            <span>
                                <?php if (!empty($article['source_name'])): ?>
                                    <?= view_e($article['source_name']) ?> ·
                                <?php endif; ?>
                                <?= view_e($article['author_name'] ?? 'فريق التحرير') ?>
                            </span>
                            <a class="read-more-link" href="<?= view_e(app_url('article/' . $article['slug'])) ?>">قراءة التفاصيل ←</a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?php if ($total > $perPage): ?>
        <?php $pages = (int) ceil($total / $perPage); ?>
        <nav style="display:flex;justify-content:center;gap:8px;margin-top:40px;flex-wrap:wrap">
            <?php if ($page > 1): ?>
                <a href="<?= view_e($searchUrl(array('page' => $page - 1))) ?>" class="filter-btn">السابق ›</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <?php if ($i > 1 && abs($i - $page) > 3 && $i !== 1 && $i !== $pages) continue; ?>
                <a href="<?= view_e($searchUrl(array('page' => $i))) ?>"
                   class="filter-btn <?= (int) $page === $i ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $pages): ?>
                <a href="<?= view_e($searchUrl(array('page' => $page + 1))) ?>" class="filter-btn">‹ التالي</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>

</main>

<style>
mark { background: rgba(255, 213, 0, .32); color: inherit; border-radius: 3px; padding: 0 2px; }
.search-suggest-box {
    position: absolute; inset-inline-start: 0; inset-inline-end: 0; top: 100%;
    background: var(--bg-surface); border: 1px solid var(--border-subtle);
    border-radius: 12px; margin-top: 6px; overflow: hidden; z-index: 60;
    box-shadow: 0 14px 34px rgba(0,0,0,.22);
}
.search-suggest-box a {
    display: flex; justify-content: space-between; gap: 10px; align-items: center;
    padding: 9px 14px; color: var(--text-primary); text-decoration: none;
    border-bottom: 1px solid var(--border-subtle); font-size: .9rem;
}
.search-suggest-box a:last-child { border-bottom: 0; }
.search-suggest-box a:hover, .search-suggest-box a.is-active { background: var(--bg-surface-elevated); }
.search-suggest-box small { color: var(--text-dim); font-size: .72rem; white-space: nowrap; }
.search-filter-form { position: relative; }
</style>

<script>
(function () {
    var input = document.getElementById('searchInput');
    var box = document.getElementById('searchSuggestBox');
    if (!input || !box) return;

    var timer = null, last = '', active = -1;

    function hide() { box.hidden = true; box.innerHTML = ''; active = -1; }

    function render(items) {
        if (!items.length) { hide(); return; }
        box.innerHTML = items.map(function (it, i) {
            var cat = it.category ? '<small>' + it.category + '</small>' : '';
            return '<a href="' + it.url + '" data-i="' + i + '">' +
                '<span>' + (it.title || '') + '</span>' + cat + '</a>';
        }).join('');
        box.hidden = false;
        active = -1;
    }

    input.addEventListener('input', function () {
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { hide(); return; }
        timer = setTimeout(function () {
            if (q === last) return;
            last = q;
            fetch('<?= app_url("search/suggest") ?>?q=' + encodeURIComponent(q), {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin'
            })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(hide);
        }, 220);
    });

    input.addEventListener('keydown', function (e) {
        var links = box.querySelectorAll('a');
        if (box.hidden || !links.length) return;
        if (e.key === 'ArrowDown') { e.preventDefault(); active = (active + 1) % links.length; paint(links); }
        else if (e.key === 'ArrowUp') { e.preventDefault(); active = (active - 1 + links.length) % links.length; paint(links); }
        else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); links[active].click(); }
        else if (e.key === 'Escape') { hide(); }
    });

    function paint(links) {
        links.forEach(function (a, i) { a.classList.toggle('is-active', i === active); });
    }

    document.addEventListener('click', function (e) {
        if (!box.contains(e.target) && e.target !== input) hide();
    });
})();
</script>

<?php require_once APP_ROOT . '/views/partials/footer.php'; ?>