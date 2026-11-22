<?php
/**
 * Article reading UI: sidebar tabs, brand share icons, footer socials, scrollbar.
 *
 * Run: php tools/article_ui_test.php
 */

$pass = 0;
$fail = 0;
function t($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label" . ($extra ? "\n     " . $extra : '') . "\n";
    }
}

$root = dirname(__DIR__);
$controller = (string) file_get_contents($root . '/controllers/ArticleController.php');
$view = (string) file_get_contents($root . '/views/article.php');
$footer = (string) file_get_contents($root . '/views/partials/footer.php');
$css = (string) file_get_contents($root . '/assets/css/site.css');

echo "--- the sidebar gets both datasets ---\n";
t('latest stories are queried', strpos($controller, '$latestStories = $db->fetchAll(') !== false);
t('latest is ordered by publish time', strpos($controller, 'ORDER BY a.published_at DESC, a.id DESC LIMIT 6') !== false);
t('most viewed is queried', strpos($controller, '$mostViewedStories = $db->fetchAll(') !== false);
t('most viewed is ordered by views', strpos($controller, 'ORDER BY a.views_count DESC') !== false);
t('both reach the view', strpos($controller, "'latestStories' => \$latestStories") !== false
    && strpos($controller, "'mostViewedStories' => \$mostViewedStories") !== false
    && strpos($controller, "'latestArticles' => \$latestStories") !== false
    && strpos($controller, "'popularArticles' => \$mostViewedStories") !== false);

echo "\n--- reference tabbed sidebar ---\n";
t('the reference widget exists', strpos($view, 'sidebar-news-tabs-widget') !== false);
t('two tab buttons exist', substr_count($view, 'sidebar-news-tab-btn') >= 2);
t('the latest pane is active by default', strpos($view, 'id="tab-latest-news" role="tabpanel"') !== false
    && strpos($view, 'sidebar-news-pane active') !== false);
t('the popular pane exists', strpos($view, 'id="tab-popular-news"') !== false);
t('the arrow indicator exists', strpos($view, 'tab-indicator-arrow') !== false);
t('thumbnails are 82x60', strpos($view, 'sidebar-news-thumb-box') !== false
    && strpos($css, 'width: 82px;') !== false);
t('category and relative time are shown', strpos($view, 'sidebar-news-cat') !== false
    && strpos($view, 'fmt_relative_time(') !== false);
t('tabs switch in place', strpos($view, "querySelectorAll('.sidebar-news-tab-btn')") !== false
    && strpos($view, "classList.add('active')") !== false);
t('the reference styles are present', strpos($css, '.sidebar-news-tabs-widget') !== false
    && strpos($css, '.sidebar-news-item:hover') !== false);

echo "\n--- brand share icons replace text and emoji ---\n";
foreach (['twitter-x', 'facebook', 'whatsapp', 'telegram', 'linkedin', 'copy'] as $icon) {
    t('share uses the ' . $icon . ' icon', strpos($view, "ui_icon('" . $icon . "'") !== false);
}
t('the twitter intent is still correct', strpos($view, 'https://twitter.com/intent/tweet?url=') !== false);
t('facebook sharing is wired', strpos($view, 'facebook.com/sharer/sharer.php?u=') !== false);
t('whatsapp sharing is wired', strpos($view, 'https://wa.me/?text=') !== false);
t('telegram sharing is wired', strpos($view, 'https://t.me/share/url?url=') !== false);
t('linkedin sharing is wired', strpos($view, 'linkedin.com/sharing/share-offsite/?url=') !== false);
t('copy still uses the clipboard', strpos($view, 'navigator.clipboard.writeText') !== false);
t('external links are safe', strpos($view, 'rel="noopener nofollow"') !== false);
t('the old letter X is gone', strpos($view, '>𝕏<') === false);
t('the old bare "in" is gone', strpos($view, '>in</a>') === false);
t('the old generic link icons are gone from sharing', substr_count($view, "ui_icon('link', '', 16)") === 0);

echo "\n--- footer identity and social icons ---\n";
t('the hero brand block keeps the name', strpos($footer, 'footer-hero-brand-name') !== false);
t('a five-icon social strip is rendered', strpos($footer, 'footer-hero-social-strip') !== false);
foreach (['rss', 'instagram', 'youtube', 'twitter-x', 'facebook'] as $footerIcon) {
    t('the strip contains the ' . $footerIcon . ' icon', strpos($footer, "ui_icon('" . $footerIcon . "'") !== false);
}
t('icons come from real settings', strpos($footer, "Settings::get('social_instagram'") !== false
    && strpos($footer, "Settings::get('social_youtube'") !== false
    && strpos($footer, "Settings::get('social_facebook'") !== false);
t('external social links are safe', strpos($footer, 'target="_blank" rel="noopener"') !== false);
t('the strip is styled', strpos($css, '.footer-hero-social-icon') !== false
    && strpos($css, '.footer-hero-social-icon:hover') !== false);

echo "\n--- the capsule scrollbar ---\n";
t('the final thumb follows the site accent', strpos($css, 'linear-gradient(180deg, var(--accent-primary, #22d3ee), var(--accent-hover, #38bdf8))') !== false);
t('the thumb is a capsule', strpos($css, 'border-radius: 9999px;') !== false);
t('the thumb has no side border', strpos($css, "border: 0;\n  border-radius: 9999px;\n  min-height: 72px;") !== false);
t('the thumb has a minimum capsule length', strpos($css, 'min-height: 72px;') !== false);
t('the rail is centred', strpos($css, 'calc(50% - 2px)') !== false
    && strpos($css, 'calc(50% + 2px)') !== false);
t('the rail is deep black and 4px', strpos($css, '#0a0a0a calc(50% - 2px)') !== false
    && strpos($css, '#0a0a0a calc(50% + 2px)') !== false);
t('the scrollbar is 12px wide', strpos($css, 'width: 12px;') !== false);
t('the favourites list uses the same capsule', strpos($css, '.fav-items-scrollable::-webkit-scrollbar-thumb') !== false);

echo "\n--- the tutorials share bar matches the article ---\n";
$tutorial = (string) file_get_contents($root . '/views/tutorials/show.php');
foreach (['twitter-x', 'facebook', 'whatsapp', 'telegram', 'linkedin', 'copy'] as $icon) {
    t('tutorials use the ' . $icon . ' icon', strpos($tutorial, "ui_icon('" . $icon . "'") !== false);
}
t('the textual X is gone from tutorials', strpos($tutorial, '>𝕏<') === false);
t('the emoji share icons are gone', strpos($tutorial, '>💬<') === false
    && strpos($tutorial, '>✈️<') === false
    && strpos($tutorial, '>🔗<') === false);
t('tutorial links are safe', strpos($tutorial, 'rel="noopener nofollow"') !== false);
t('tutorial copy still works', strpos($tutorial, 'navigator.clipboard.writeText') !== false);

echo "\n--- the sidebar now carries thumbnails, tags and an arrow ---\n";
t('items carry thumbnails', strpos($view, 'sidebar-news-thumb-box') !== false);
t('thumbnails use the real image', strpos($view, "\$item['featured_image']") !== false);
t('the category tag is shown', strpos($view, 'sidebar-news-cat') !== false
    && strpos($view, "\$item['category_name']") !== false);
t('relative time is used', strpos($view, 'fmt_relative_time(') !== false);
t('an arrow marks the active tab', strpos($view, 'tab-indicator-arrow') !== false);
t('the controller joins categories', strpos($controller, 'LEFT JOIN categories c ON c.id = a.category_id') !== false);
t('it selects the thumbnail and category', strpos($controller, 'a.featured_image') !== false
    && strpos($controller, 'c.name AS category_name') !== false);
t('the styles include thumbnail and item rules', strpos($css, '.sidebar-news-thumb-box') !== false
    && strpos($css, '.sidebar-news-item:hover') !== false);
t('the scrollbar has a central wire', strpos($css, 'calc(50% - 1px)') !== false);
t('the footer brand matches the reference size', strpos($css, 'font-size: 2.35rem') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
