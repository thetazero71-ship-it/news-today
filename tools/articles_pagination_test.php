<?php
/**
 * Articles list pagination.
 *
 * Bug: the list was capped at "LIMIT 100" with no page links and no way to
 * choose the page size, so older articles were unreachable.
 *
 * Run: php tools/articles_pagination_test.php
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
$ctrl = (string) file_get_contents($root . '/controllers/admin/ArticlesController.php');
$view = (string) file_get_contents($root . '/views/admin/articles/index.php');

echo "--- the query is paginated, not capped ---\n";
t('no hard LIMIT 100 is left', strpos($ctrl, 'LIMIT 100') === false);
t('page size is whitelisted', strpos($ctrl, '$allowedPerPage = array(10, 25, 50, 100, 200);') !== false);
t('unknown page sizes fall back to 25', strpos($ctrl, 'if (!in_array($perPage, $allowedPerPage, true))') !== false);
t('LIMIT and OFFSET are applied', strpos($ctrl, 'LIMIT {$perPage} OFFSET {$offset}') !== false);
t('the page number is clamped', strpos($ctrl, 'if ($page > $totalPages)') !== false);
t('a total count is fetched', strpos($ctrl, 'SELECT COUNT(*) as total FROM articles a') !== false);
t('total pages are computed', strpos($ctrl, 'ceil($total / $perPage)') !== false);
t('the view receives perPage/page/total/totalPages', substr_count($ctrl, "'perPage' => \$perPage") === 1
    && substr_count($ctrl, "'totalPages' => \$totalPages") === 1
    && substr_count($ctrl, "'total' => \$total") >= 1);
t('the category slug is selected for the badge colour', strpos($ctrl, 'c.slug as category_slug') !== false);

echo "\n--- the page-size dropdown is in the filter bar ---\n";
t('per_page input exists', strpos($view, 'name="per_page"') !== false);
t('it submits on change', strpos($view, 'name="per_page" onchange="this.form.submit()"') !== false);
t('it renders the whitelisted sizes', strpos($view, 'foreach ($allowedPerPage as $n)') !== false);
t('the current size stays selected', strpos($view, '(int) $perPage === (int) $n ? \'selected\' : \'\'') !== false);
t('clearing filters keeps the chosen size', strpos($view, "admin/articles?per_page=' . (int) \$perPage") !== false);

echo "\n--- pagination controls preserve the filters ---\n";
t('a footer shows the visible range', strpos($view, 'عرض <strong>') !== false && strpos($view, 'من <strong>') !== false);
t('the footer shows the total', strpos($view, 'number_format((int) $total)') !== false);
t('pagination is hidden for a single page', strpos($view, 'if ($totalPages > 1):') !== false);
t('links are built with http_build_query', strpos($view, 'http_build_query($params)') !== false);
t('q/status/per_page are preserved', strpos($view, "'per_page' => (int) \$perPage") !== false
    && strpos($view, "'q' => \$q ?? ''") !== false
    && strpos($view, "'status' => \$status ?? ''") !== false);
t('first/prev/next/last links exist', strpos($view, '« الأولى') !== false
    && strpos($view, '‹ السابق') !== false
    && strpos($view, 'التالي ›') !== false
    && strpos($view, 'الأخيرة »') !== false);
t('a five-page window is drawn', strpos($view, '$startPage = max(1, (int) $page - 2)') !== false
    && strpos($view, '$endPage = min((int) $totalPages, (int) $page + 2)') !== false);
t('the active page is marked', strpos($view, "\$p === (int) \$page ? 'active' : ''") !== false);
t('links are escaped for HTML', strpos($view, 'admin_e($pageUrl(') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
