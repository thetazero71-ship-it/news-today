<?php
/**
 * Home news stream cards — shared by the first page and the load-more endpoint.
 * Expects: $articles (array of published article rows).
 */
if (!function_exists('home_card_e')) {
    function home_card_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<?php foreach ($articles as $article): ?>
    <article class="news-card" <?= news_card_attrs($article) ?>>
        <div class="card-img-wrap">
            <span class="card-tag"><?= home_card_e($article['category_name'] ?: 'بيانات صحفية') ?></span>
            <?php if (!empty($article['featured_image'])): ?>
                <img src="<?= home_card_e(app_url($article['featured_image'])) ?>" alt="<?= home_card_e($article['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= home_card_e(\FallbackImage::general()) ?>';">
            <?php else: ?>
                <div style="width:100%;height:100%;background:var(--bg-surface-elevated);display:grid;place-items:center;color:var(--accent-primary);font-size:2rem"><?= ui_icon('general', '', 32) ?></div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3><a href="<?= home_card_e(app_url('article/' . $article['slug'])) ?>"><?= home_card_e($article['title']) ?></a></h3>
            <div style="display:flex;align-items:center;gap:14px;font-size:0.82rem;color:var(--text-muted);margin-bottom:10px;flex-wrap:wrap">
                <span style="display:inline-flex;align-items:center;gap:4px"><?= ui_icon('calendar', '', 14) ?> <?= home_card_e(fmt_date($article['published_at'] ?: $article['created_at'])) ?></span>
                <span style="display:inline-flex;align-items:center;gap:4px"><?= ui_icon('author', '', 14) ?> <?= home_card_e($article['author_name'] ?: 'فريق التحرير') ?></span>
                <span style="display:inline-flex;align-items:center;gap:4px"><?= ui_icon('eye', '', 14) ?> <?= number_format((int) ($article['views_count'] ?? 0)) ?> قراءة</span>
            </div>
            <p><?= home_card_e($article['excerpt'] ?: 'تفاصيل شاملة وتحليلات معمقة تواكب أحدث المستجدات التقنية.') ?></p>
            <div class="card-footer">
                <a class="read-more-link" href="<?= home_card_e(app_url('article/' . $article['slug'])) ?>" style="font-weight:800">إقرأ المزيد ←</a>
            </div>
        </div>
    </article>
<?php endforeach; ?>
