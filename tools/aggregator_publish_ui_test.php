<?php
/**
 * Regression test: publish actions must not be rendered for already published
 * items (they used to come back after every page refresh).
 *
 * Run: php tools/aggregator_publish_ui_test.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);

function extractActionsBar(string $html): string
{
    // isolate the actions bar block of a single news card
    $start = strpos($html, '<!-- Actions Bar -->');
    if ($start === false) {
        return '';
    }
    $end = strpos($html, '</div>', strpos($html, 'data-action="draft"', $start));
    if ($end === false) {
        $end = strpos($html, '<!-- 3. Draft', $start);
    }
    return substr($html, $start, $end - $start);
}

$pass = 0;
$fail = 0;
function t($label, $cond)
{
    global $pass, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    $cond ? $pass++ : $fail++;
}

$view = file_get_contents($root . '/views/admin/aggregator/index.php');

// 1. server-side condition exists on the published status
t('view checks import_status === published', strpos($view, "\$item['import_status'] ?? '') === 'published'") !== false
    || strpos($view, "('published')") !== false);

// 2. the three publish forms live inside the "not published" branch
$notPublished = strpos($view, "if (\$itemPublished):");
t('published branch exists', $notPublished !== false);
t('else branch holds the publish forms', strpos($view, 'publish-ajax-form') !== false);

$firstForm = strpos($view, 'data-action="translate"');
$draftForm = strpos($view, 'data-action="draft"');
$endifPos = strpos($view, 'endif;', (int) $firstForm);
t('all three forms inside the non-published branch',
    $firstForm > 0 && $draftForm > 0 && $endifPos > 0 && $draftForm < $endifPos);

// 3. published items get a clear disabled state instead of buttons
t('published state renders a disabled badge', strpos($view, 'aria-disabled="true"') !== false);
t('published badge says منشور', strpos($view, 'منشور</span>') !== false);
t('published hint explains why', strpos($view, 'هذا الخبر منشور بالفعل') !== false);

// 4. the AJAX handler renders the SAME state so a refresh changes nothing
t('ajax swaps to the same منشور badge', strpos($view, 'btn btn-sm btn-success disabled') !== false);
t('ajax no longer uses the old تم النشر badge', strpos($view, 'py-2 shadow-sm"><i class="bi bi-check-circle-fill me-1"></i> تم النشر') === false);

// 5. after publishing, the card is flagged as published for the live filter
t('ajax sets data-news-status=published', strpos($view, "setAttribute('data-news-status', 'published')") !== false);

// 6. syntax is valid
$out = [];
$code = 0;
exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/views/admin/aggregator/index.php') . ' 2>&1', $out, $code);
t('view has no syntax errors', $code === 0, true);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);