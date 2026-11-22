<?php
/**
 * Bento grid cards — shared by the first page and the load-more endpoint.
 * Expects: $articles.
 */
if (!function_exists('home_card_e')) {
    function home_card_e($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
    }
}
?>
<?php foreach ($articles as $article): ?>
    <article class="news-card bento-card-item" <?= news_card_attrs($article) ?>>
        <div class="card-img-wrap">
            <span class="card-tag"><?= home_card_e($article['category_name'] ?: 'تقنية') ?></span>
            <?php if (!empty($article['featured_image'])): ?>
                <img src="<?= home_card_e(app_url($article['featured_image'])) ?>" alt="<?= home_card_e($article['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= home_card_e(\FallbackImage::general()) ?>';">
            <?php else: ?>
                <div style="width:100%;height:100%;background:var(--bg-surface-elevated);display:grid;place-items:center;color:var(--accent-primary);font-size:2rem"><?= ui_icon('general', '', 32) ?></div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3><a href="<?= home_card_e(app_url('article/' . $article['slug'])) ?>"><?= home_card_e($article['title']) ?></a></h3>
            <p><?= home_card_e($article['excerpt'] ?: 'تفاصيل شاملة حول أحدث الأخبار والتحليلات المتخصصة.') ?></p>
            <div class="card-footer">
                <small class="text-muted"><?= home_card_e(fmt_date($article['published_at'] ?: $article['created_at'])) ?></small>
                <a class="read-more-link" href="<?= home_card_e(app_url('article/' . $article['slug'])) ?>">قراءة ←</a>
            </div>
        </div>
    </article>
<?php endforeach; ?>
