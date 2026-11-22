<?php
/**
 * Admin publishing cards: publisher badge + per-user published count.
 *
 * Bug: a published aggregator card showed only the feed date, with no author
 * and no real publish time, so two cards looked identical. The users table
 * also had no way to see how many published articles each account owns.
 *
 * Run: php tools/admin_cards_test.php
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

echo "--- one shared status map for both tabs ---\n";
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('the map helper exists', strpos($controller, 'private function articleStatusMap') !== false);
t('it selects the real publish time', strpos($controller, 'a.published_at, a.created_at') !== false);
t('it resolves the publisher name', strpos($controller, 'u.username AS author_name') !== false
    && strpos($controller, 'LEFT JOIN users u ON u.id = a.author_id') !== false);
t('it stores the publish time per URL', strpos($controller, "#published_at']") !== false);
t('it stores the publisher per URL', strpos($controller, "#publisher']") !== false);
t('the applier sets both fields', strpos($controller, 'private function applyArticleStatus') !== false
    && strpos($controller, "\$item['publisher_name'] =") !== false
    && strpos($controller, "\$item['published_at'] =") !== false);
t('the aggregator uses the shared map', strpos($controller, '$this->articleStatusMap($db)') !== false);
t('the favorites tab uses the same map', substr_count($controller, '$this->articleStatusMap($db)') === 2);
t('the old duplicated status query is gone', strpos($controller, 'SELECT id, source_url, status FROM articles') === false);

echo "\n--- the aggregator card shows who and when ---\n";
$aggView = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
t('published state is computed once per card', substr_count($aggView, "\$itemPublished =") === 1);
t('a publisher badge is rendered', strpos($aggView, 'publisher-info-badge') !== false);
t('the publisher name is shown', strpos($aggView, "\$item['publisher_name']") !== false);
t('the publish time is shown in site time', strpos($aggView, "fmt_date(\$item['published_at'], 'Y-m-d H:i')") !== false);
t('the time is LTR monospace', strpos($aggView, 'font-monospace" dir="ltr"') !== false);
t('the badge only appears for published cards', strpos($aggView, '<?php if ($itemPublished): ?>') !== false);

echo "\n--- favorites shows the same badge ---\n";
$favView = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('favorites has the publisher badge', strpos($favView, 'publisher-info-badge') !== false);
t('favorites uses the publisher name', strpos($favView, "\$item['author_name']") !== false);
t('favorites uses the publish time', strpos($favView, "\$item['published_at']") !== false);
t('favorites keeps the feed date LTR', strpos($favView, 'dir="ltr"') !== false
    && strpos($favView, 'font-monospace') !== false);

echo "\n--- the users table counts published articles ---\n";
$usersController = (string) file_get_contents($root . '/controllers/admin/UsersController.php');
$usersView = (string) file_get_contents($root . '/views/admin/users/index.php');
t('the count is a grouped join', strpos($usersController, 'LEFT JOIN (') !== false
    && strpos($usersController, 'COUNT(*) AS published_count') !== false
    && strpos($usersController, "WHERE status = 'published'") !== false);
t('the count is aliased for the view', strpos($usersController, 'AS published_articles_count') !== false);
t('the column exists in the table header', strpos($usersView, 'الأخبار المنشورة') !== false);
t('the cell reads the count', strpos($usersView, "\$u['published_articles_count']") !== false);
t('the count links to filtered articles', strpos($usersView, "admin/articles?author_id=' . (int) \$u['id'] . '&status=published") !== false);
t('the empty-state colspan matches the new column', strpos($usersView, 'colspan="8"') !== false);

echo "\n--- the author filter actually works ---\n";
$articlesController = (string) file_get_contents($root . '/controllers/admin/ArticlesController.php');
$articlesView = (string) file_get_contents($root . '/views/admin/articles/index.php');
t('the controller reads author_id', strpos($articlesController, "\$_GET['author_id']") !== false);
t('it filters by author', strpos($articlesController, 'a.author_id = :author_id') !== false);
t('it passes the filter to the view', strpos($articlesController, "'authorId' => \$authorId") !== false);
t('the form preserves the author', strpos($articlesView, 'name="author_id"') !== false);
t('pagination preserves the author', strpos($articlesView, "'author_id' => (int) (\$authorId ?? 0)") !== false);
t('the reset button appears for an author filter', strpos($articlesView, "!empty(\$authorId)") !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
