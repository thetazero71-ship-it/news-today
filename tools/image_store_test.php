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

/** بديل قاعدة البيانات لاختبار التسجيل في المكتبة */
class TestMediaDb
{
    public static $rows = [];
    public static $queries = [];

    public function fetch($sql, array $p = [])
    {
        foreach (self::$rows as $row) {
            if (($row['file_path'] ?? null) === ($p[':p'] ?? null)) {
                return $row;
            }
        }
        return null;
    }
    public function fetchAll($sql, array $p = [])
    {
        return self::$rows;
    }
    public function query($sql, array $p = [])
    {
        self::$queries[] = [$sql, $p];
        if (stripos($sql, 'INSERT INTO media') !== false) {
            self::$rows[] = ['id' => count(self::$rows) + 1, 'file_path' => $p[':file_path']];
        }
        return true;
    }
}
if (!class_exists('Database')) {
    class_alias('TestMediaDb', 'Database');
}

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

echo "\n--- compression math (no GD needed) ---\n";
$s = ImageStore::computeSize(800, 600);
t('small image is untouched', $s['resize'] === false && $s['width'] === 800 && $s['height'] === 600);
$s = ImageStore::computeSize(2400, 1600);
t('wide image scales to 1200', $s['resize'] === true && $s['width'] === 1200 && $s['height'] === 800);
$s = ImageStore::computeSize(800, 3200);
t('tall image scales by height', $s['resize'] === true && $s['height'] === 1600 && $s['width'] === 400);
$s = ImageStore::computeSize(1200, 1600);
t('exact caps are untouched', $s['resize'] === false);
$s = ImageStore::computeSize(0, 0);
t('zero dimensions are safe', $s['resize'] === false && $s['width'] === 0);
$s = ImageStore::computeSize(1201, 100);
t('one pixel over still rescales', $s['resize'] === true && $s['width'] === 1200);

echo "\n--- compression guards ---\n";
t('animated gif is never touched', (function () {
    $frames = "\x21\xF9\x04" . 'a' . "\x21\xF9\x04" . 'b';
    return ImageStore::compress($frames, 'image/gif') === null;
})());
t('empty bytes give null', ImageStore::compress('', 'image/jpeg') === null);
t('unknown mime gives null', ImageStore::compress('whatever', 'image/bmp') === null);
if (!function_exists('imagecreatefromstring')) {
    t('without GD everything is kept as-is', ImageStore::compress('x', 'image/jpeg') === null);
}

echo "\n--- compression is wired everywhere ---\n";
$store = (string) file_get_contents($root . '/core/ImageStore.php');
t('downloads are compressed before saving', strpos($store, '$data = self::compress($data') !== false);
t('compressFile() exists for old images', strpos($store, 'function compressFile') !== false);
t('compressFile never writes on failure', strpos($store, "if (\$out === null)") !== false);

$articles = (string) file_get_contents($root . '/controllers/admin/ArticlesController.php');
t('one-time recompress action exists', strpos($articles, 'function recompressImages') !== false);
t('it is batched with a time budget', strpos($articles, '$budget = 25') !== false);
t('path traversal is blocked', strpos($articles, "strpos(\$real, \$base) !== 0") !== false);
t('it reports saved bytes', strpos($articles, "'saved_bytes'") !== false);
t('recompress route registered', strpos((string) file_get_contents($root . '/index.php'), "admin/articles/recompress-images") !== false);
$view = (string) file_get_contents($root . '/views/admin/articles/index.php');
t('compress button rendered', strpos($view, 'imageCompressBtn') !== false);
t('compress batches automatically', strpos($view, 'return cbatch(data.next, saved)') !== false);

echo "\n--- media library registration ---\n";
TestMediaDb::$rows = [];
TestMediaDb::$queries = [];
t('a new image is registered', ImageStore::registerInLibrary('uploads/articles/2026/10/abc123.jpg', 'https://x.com/pic.jpg', ['mime' => 'image/jpeg', 'width' => 1200, 'height' => 675], 45000));
t('one row was inserted', count(TestMediaDb::$rows) === 1);
$last = end(TestMediaDb::$queries);
t('folder is articles', ($last[1][':folder'] ?? '') === 'articles');
t('path is stored', ($last[1][':file_path'] ?? '') === 'uploads/articles/2026/10/abc123.jpg');
t('mime is stored', ($last[1][':mime_type'] ?? '') === 'image/jpeg');
t('size is stored', ($last[1][':file_size'] ?? 0) === 45000);
t('dimensions are stored', ($last[1][':dimensions'] ?? '') === '1200x675');
TestMediaDb::$queries = [];
t('an existing image is not duplicated', ImageStore::registerInLibrary('uploads/articles/2026/10/abc123.jpg'));
t('no second insert happened', TestMediaDb::$queries === []);
t('publishing stores register the file', strpos((string) file_get_contents($root . '/core/ImageStore.php'), 'self::registerInLibrary($relative') !== false);
t('old images are backfilled on recompress', strpos((string) file_get_contents($root . '/controllers/admin/ArticlesController.php'), "ImageStore::registerInLibrary(ltrim((string) (\$row['featured_image']") !== false);
t('registration never breaks publishing', strpos((string) file_get_contents($root . '/core/ImageStore.php'), 'catch (Throwable $e) {') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);