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
    && strpos($controller, "'mostViewedStories' => \$mostViewedStories") !== false);

echo "\n--- two CSS-only tabs ---\n";
t('the tab card exists', strpos($view, 'sidebar-tabs-card') !== false);
t('the latest tab is checked by default', strpos($view, 'id="side-tab-latest" checked') !== false);
t('the viewed tab exists', strpos($view, 'id="side-tab-viewed"') !== false);
t('there are two radio inputs', substr_count($view, 'class="sidebar-tab-input"') === 2);
t('labels name both tabs', strpos($view, 'آخر الأخبار') !== false && strpos($view, 'الأكثر مشاهدة') !== false);
t('the latest panel exists', strpos($view, 'sidebar-panel-latest') !== false);
t('the viewed panel exists', strpos($view, 'sidebar-panel-viewed') !== false);
t('viewed items show the view count', strpos($view, 'مشاهدة') !== false && strpos($view, "number_format((int) (\$story['views_count']") !== false);
t('empty panels have a message', strpos($view, 'side-tab-empty') !== false);
t('tabs need no JavaScript', strpos($view, 'article-side-tab') !== false
    && strpos($css, '#side-tab-latest:checked ~ .sidebar-panel-latest') !== false
    && strpos($css, '#side-tab-viewed:checked ~ .sidebar-panel-viewed') !== false);

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
t('the brand block keeps the name', strpos($footer, 'footer-brand-name') !== false);
t('a social row is rendered', strpos($footer, 'footer-social-row') !== false);
t('each icon is a link', strpos($footer, 'class="footer-social-link"') !== false
    && strpos($footer, 'href="<?= view_e($social[\'url\']) ?>"') !== false);
t('icons come from real settings', strpos($footer, "Settings::get(\$social['key']") !== false);
t('empty settings are skipped', strpos($footer, "\$socialUrl !== ''") !== false);
t('at most five are shown', strpos($footer, 'array_slice($footerSocialLinks, 0, 5)') !== false);
t('icons use the brand SVGs', strpos($footer, "ui_icon(\$social['icon']") !== false);
t('external links are safe', strpos($footer, 'rel="external noopener nofollow"') !== false);

echo "\n--- the capsule scrollbar ---\n";
t('the gradient thumb exists', strpos($css, '::-webkit-scrollbar-thumb') !== false
    && strpos($css, 'linear-gradient(180deg, #22d3ee, #6366f1)') !== false);
t('the thumb is a capsule', strpos($css, 'border-radius: 999px') !== false);
t('it glows', strpos($css, 'box-shadow: inset 0 0 6px rgba(34, 211, 238, 0.45)') !== false);
t('firefox gets a thin colored bar', strpos($css, 'scrollbar-color: rgba(34, 211, 238, 0.55) transparent') !== false);
t('the share buttons keep an accessible style', strpos($css, '.action-btn.share-brand') !== false);
t('the footer circles have hover motion', strpos($css, '.footer-social-link:hover') !== false);

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
t('items carry thumbnails', strpos($view, 'side-tab-thumb') !== false);
t('thumbnails use the real image', strpos($view, "\$story['featured_image']") !== false);
t('the category tag is shown', strpos($view, 'side-tab-cat') !== false
    && strpos($view, "\$story['category_name']") !== false);
t('time is machine-readable for the relative engine', strpos($view, 'news-time-ago-static') !== false
    && strpos($view, "date('c', \$storyTs ?: time())") !== false);
t('an arrow marks each item', strpos($view, 'side-tab-arrow') !== false);
t('the controller joins categories', strpos($controller, 'LEFT JOIN categories c ON c.id = a.category_id') !== false);
t('it selects the thumbnail and category', strpos($controller, 'a.featured_image') !== false
    && strpos($controller, 'c.name AS category_name') !== false);
t('the styles include thumbnail and arrow rules', strpos($css, '.side-tab-thumb') !== false
    && strpos($css, '.side-tab-arrow') !== false);
t('the scrollbar has a central wire', strpos($css, 'rgba(148, 163, 184, 0.22)') !== false);
t('the footer brand is bolder', strpos($css, 'font-size: 1.25rem; font-weight: 900') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
