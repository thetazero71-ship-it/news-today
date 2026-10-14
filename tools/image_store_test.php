<?php
/**
 * ImageStore: images must live on our own server.
 * Run: php tools/image_store_test.php
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
require_once $root . '/core/ImageStore.php';

echo "--- remote vs local ---\n";
t('http url is remote', ImageStore::isRemote('https://x.com/a.jpg'));
t('http url is remote', ImageStore::isRemote('http://x.com/a.jpg'));
t('empty is not remote', ImageStore::isRemote('') === false);
t('null is not remote', ImageStore::isRemote(null) === false);
t('local path is not remote', ImageStore::isRemote('uploads/articles/2026/10/a.jpg') === false);
t('local path is local', ImageStore::isLocal('uploads/articles/2026/10/a.jpg'));
t('remote url is not local', ImageStore::isLocal('https://x.com/a.jpg') === false);
t('empty is not local', ImageStore::isLocal('') === false);

echo "\n--- nothing stored without a real remote url ---\n";
t('empty url stores nothing', ImageStore::storeFromUrl('') === '');
t('plain text stores nothing', ImageStore::storeFromUrl('not-a-url') === '');
t('local path is never re-downloaded', ImageStore::storeFromUrl('uploads/articles/2026/10/a.jpg') === '');
t('oversized url is refused', ImageStore::storeFromUrl('https://x.com/' . str_repeat('a', 3000)) === '');

echo "\n--- validation rejects junk (via reflection, no network) ---\n";
$inspect = new ReflectionMethod('ImageStore', 'inspect');
$inspect->setAccessible(true);

t('empty bytes rejected', $inspect->invoke(null, '') === null);
t('null rejected', $inspect->invoke(null, null) === null);
t('plain text rejected', $inspect->invoke(null, 'hello world, not an image') === null);
t('html error page rejected', $inspect->invoke(null, '<!DOCTYPE html><html><body>Error 750</body></html>') === null);
t('svg rejected (can carry scripts)', $inspect->invoke(null, '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"></svg>') === null);

// a real 1x1 png: valid format, but a tracking pixel — must be refused
$pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
t('tracking pixel refused', $inspect->invoke(null, $pixel) === null);

if (function_exists('imagecreatetruecolor')) {
    $im = imagecreatetruecolor(400, 300);
    ob_start();
    imagejpeg($im, null, 80);
    $photo = (string) ob_get_clean();
    imagedestroy($im);
    $ok = $inspect->invoke(null, $photo);
    t('a real photo passes', is_array($ok) && $ok['ext'] === 'jpg' && $ok['width'] === 400);
} else {
    echo "SKIP a real photo passes (no GD available)\n";
}

echo "\n--- constants are sane ---\n";
t('8MB cap', ImageStore::MAX_BYTES === 8388608);
t('no svg in the allow-list', !in_array('image/svg+xml', array_keys(ImageStore::ALLOWED_MIME), true));
t('jpeg/png/webp/gif allowed', count(ImageStore::ALLOWED_MIME) === 4);

echo "\n--- publish paths localize the image ---\n";
$agg = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('all 3 manual publish paths store locally', substr_count($agg, 'ImageStore::storeFromUrl($featuredImage)') === 3);
t('failure keeps the remote url', strpos($agg, "if (\$stored !== '') { \$featuredImage = \$stored; }") !== false);

$cron = (string) file_get_contents($root . '/cron/rss_auto_publish.php');
t('the auto job stores locally', strpos($cron, 'ImageStore::storeFromUrl($featuredImage)') !== false);
t('the auto job logs the outcome', strpos($cron, 'حُفظت الصورة محلياً') !== false);

$articles = (string) file_get_contents($root . '/controllers/admin/ArticlesController.php');
t('the article form localizes on create', strpos($articles, "\$data['featured_image'] = self::localizeImage") !== false);
t('update skips unchanged images', strpos($articles, "(\$data['featured_image'] ?? null) !== (\$old['featured_image'] ?? null)") !== false);

echo "\n--- repair tool ---\n";
t('repair action exists', strpos($articles, 'function repairImages') !== false);
t('repair is batched with a time budget', strpos($articles, '$budget = 25') !== false);
t('repair re-resolves dead images', strpos($articles, 'FetchOg::resolve((string) $row[' . "'source_url'])") !== false);
t('repair never deletes the old url on failure', strpos($articles, 'UPDATE articles SET featured_image = :img') !== false);
t('repair route registered', strpos((string) file_get_contents($root . '/index.php'), "admin/articles/repair-images") !== false);
$view = (string) file_get_contents($root . '/views/admin/articles/index.php');
t('repair button rendered', strpos($view, 'imageRepairBtn') !== false);
t('repair shows progress', strpos($view, 'imageRepairBar') !== false);
t('repair batches automatically', strpos($view, 'return batch(data.next)') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);