<?php
/**
 * Editorial/Verge/Minimal/Cyber grid cards — shared by page 1 and load-more.
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
    <article class="news-card" <?= news_card_attrs($article) ?>>
        <div class="card-img-wrap">
            <span class="card-tag"><?= home_card_e($article['category_name'] ?: 'تقنية') ?></span>
            <button class="card-bookmark-btn"
                    data-article-id="<?= home_card_e($article['id']) ?>"
                    data-title="<?= home_card_e($article['title']) ?>"
                    data-url="<?= home_card_e(app_url('article/' . $article['slug'])) ?>"
                    data-category="<?= home_card_e($article['category_name'] ?: 'تقنية') ?>"
                    title="حفظ للقراءة"><?= ui_icon('bookmark-star', '', 14) ?></button>
            <?php if (!empty($article['featured_image'])): ?>
                <img src="<?= home_card_e(app_url($article['featured_image'])) ?>" alt="<?= home_card_e($article['title']) ?>" loading="lazy" onerror="this.onerror=null;this.src='<?= home_card_e(\FallbackImage::general()) ?>';">
            <?php else: ?>
                <div style="width:100%;height:100%;background:var(--bg-surface-elevated);display:grid;place-items:center;color:var(--accent-primary);font-size:2rem"><?= ui_icon('general', '', 32) ?></div>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <h3><a href="<?= home_card_e(app_url('article/' . $article['slug'])) ?>"><?= home_card_e($article['title']) ?></a></h3>
            <p><?= home_card_e($article['excerpt'] ?: 'تفاصيل شاملة حول أحدث الأخبار والتحليلات المتخصصة مع شرح مبسط وموثوق.') ?></p>
            <div class="card-footer">
                <span style="display:inline-flex;align-items:center;gap:5px"><?= ui_icon('author', '', 13) ?> بقلم <?= home_card_e($article['author_name'] ?: 'فريق التحرير') ?></span>
                <a class="read-more-link" href="<?= home_card_e(app_url('article/' . $article['slug'])) ?>">قراءة التفاصيل ←</a>
            </div>
        </div>
    </article>
<?php endforeach; ?>
