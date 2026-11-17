<?php
/**
 * Brand single-source-of-truth tests.
 * Run: php tools/brand_test.php
 */

define('APP_ROOT', dirname(__DIR__));

$pass = 0;
$fail = 0;

function t($label, $cond)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label\n";
    }
}

function e($s)
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}
function app_url($p = '')
{
    return 'https://example.test/' . ltrim($p, '/');
}

/** In-memory Settings stub. */
class Settings
{
    public static $data = array();

    public static function get($key, $default = null)
    {
        return array_key_exists($key, self::$data) && self::$data[$key] !== '' ? self::$data[$key] : $default;
    }
    public static function clear()
    {
    }
}

require_once APP_ROOT . '/core/Brand.php';
require_once APP_ROOT . '/core/SEO.php';

echo "--- Brand reads the setting ---\n";
Settings::$data['site_name_ar'] = 'منصة الاختبار';
Settings::$data['site_name_en'] = 'TestHub';
Brand::flush();

t('name comes from settings', Brand::name() === 'منصة الاختبار');
t('english name comes from settings', Brand::nameEn() === 'TestHub');
t('titleWith appends the brand', Brand::titleWith('أخبار') === 'أخبار | منصة الاختبار');
t('titleWith with empty suffix', Brand::titleWith('') === 'منصة الاختبار');

echo "\n--- fallbacks when settings are empty ---\n";
Settings::$data = array();
Brand::flush();
t('falls back to the default name', Brand::name() === Brand::DEFAULT_NAME_AR);
t('description is never empty', Brand::description() !== '');
t('description contains the brand', strpos(Brand::description(), Brand::name()) !== false);

echo "\n--- one field drives every surface ---\n";
Settings::$data['site_name_ar'] = 'اسم جديد كلياً';
Brand::flush();
t('SEO title follows the field', strpos(SEO::renderMeta('صفحة', '', '', 'http://x/'), 'اسم جديد كلياً') !== false);
t('og:site_name follows the field', strpos(SEO::renderMeta('صفحة'), 'og:site_name" content="اسم جديد كلياً') !== false);

echo "\n--- no hardcoded brand left in PHP surfaces ---\n";
$skip = array(
    APP_ROOT . '/tools/brand_test.php',
    APP_ROOT . '/core/Brand.php',
);
$hits = array();
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(APP_ROOT, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $file) {
    $path = $file->getPathname();
    if (substr($path, -4) !== '.php' && substr($path, -4) !== '.js' && substr($path, -5) !== '.json') {
        continue;
    }
    if (in_array(str_replace('/', '\\', $path), array_map(function ($p) {
        return str_replace('/', '\\', $p);
    }, $skip), true)) {
        continue;
    }
    if (preg_match('/(views|controllers|core)\//', str_replace('\\', '/', $path)) !== 1) {
        continue;
    }
    $src = (string) file_get_contents($path);
    // ignore comments: a brand mention inside a // comment is not output
    $lines = preg_split('/\R/u', $src);
    foreach ($lines as $line) {
        $trim = ltrim($line);
        if (strpos($trim, '*') === 0 || strpos($trim, '//') === 0 || strpos($trim, '#') === 0) {
            continue;
        }
        if (strpos($line, Brand::DEFAULT_NAME_AR) !== false) {
            $hits[] = str_replace(APP_ROOT . '\\', '', $path);
            break;
        }
    }
}
t('no hardcoded brand name left in views/controllers/core', $hits === array());
if ($hits) {
    foreach (array_unique($hits) as $h) {
        echo "    still hardcoded: $h\n";
    }
}

echo "\n--- static file sync ---\n";
$tmp = sys_get_temp_dir() . '/brand_sync_test';
@mkdir($tmp, 0777, true);
file_put_contents($tmp . '/manifest.json', '{"name":"قديم","short_name":"قديم","description":"desc"}');
file_put_contents($tmp . '/sw.js', "const CACHE_NAME = 'old-v1';\ntitle: '{SITE_NAME}',\n");
Settings::$data['site_name_ar'] = 'اسم محدَّث';
Brand::flush();
$res = Brand::syncStaticFiles($tmp);
$mf = (string) file_get_contents($tmp . '/manifest.json');
$sw = (string) file_get_contents($tmp . '/sw.js');
t('manifest name rewritten', strpos($mf, '"name":"اسم محدَّث"') !== false);
t('manifest short_name rewritten', strpos($mf, '"short_name":"اسم محدَّث"') !== false);
t('sw placeholder replaced', strpos($sw, "'{SITE_NAME}'") === false && strpos($sw, 'اسم محدَّث') !== false);
t('sw cache label rewritten', strpos($sw, 'TestHub') !== false || strpos($sw, 'old-v1') === false);
t('sync reports updated files', in_array('/manifest.json', $res['updated'], true));

// a previously baked-in name must be refreshed too, not only the placeholder
file_put_contents($tmp . '/sw.js', "const CACHE_NAME = 'x-v1';\ntitle: 'اسم قديم جدا',\n");
Brand::flush();
Brand::syncStaticFiles($tmp);
$sw2 = (string) file_get_contents($tmp . '/sw.js');
t('stale sw title is refreshed on sync', strpos($sw2, 'اسم قديم جدا') === false && strpos($sw2, "title: 'اسم محدَّث'") !== false);

array_map('unlink', glob($tmp . '/*'));
@rmdir($tmp);

echo "\n--- migrating stored text that embeds the old name ---\n";

/** Tiny PDO-ish stub for migrateReferences(). */
class FakeDb
{
    public $rows;
    public $pages;
    public $updates = array();

    public function __construct($rows, $pages)
    {
        $this->rows = $rows;
        $this->pages = $pages;
    }

    public function fetchAll($sql, array $params = array())
    {
        if (strpos($sql, 'FROM settings') !== false) {
            return $this->rows;
        }
        return $this->pages;
    }

    public function query($sql, array $params = array())
    {
        $this->updates[] = $params;
        return true;
    }
}

$dbStub = new FakeDb(
    array(
        array('key' => 'ai_assistant_welcome_message', 'value' => 'مرحباً أنا مرشد عصب التقنية'),
        array('key' => 'newsletter_welcome_body', 'value' => 'نشرة عصب التقنية البريدية'),
        array('key' => 'unrelated_setting', 'value' => 'لا علاقة له'),
    ),
    array(array('id' => 7, 'slug' => 'about', 'content_ar' => '<p>عصب التقنية وجهة رقمية</p>'))
);

$changed = Brand::migrateReferences($dbStub, 'عصب التقنية', 'الاسم الجديد');
t('rewrote the assistant greeting', in_array('ai_assistant_welcome_message', $changed, true));
t('rewrote the newsletter copy', in_array('newsletter_welcome_body', $changed, true));
t('rewrote the static page', in_array('page:about', $changed, true));
t('left unrelated settings alone', in_array('unrelated_setting', $changed, true) === false);
$values = array_column($dbStub->updates, 0);
t('new name written into the stored text', in_array('مرحباً أنا مرشد الاسم الجديد', $values, true));
t('no old name left in the rewritten values', count(preg_grep('/عصب التقنية/u', $values)) === 0);

t('same name is a no-op', Brand::migrateReferences($dbStub, 'نفس الاسم', 'نفس الاسم') === array());
t('blank old name is refused', Brand::migrateReferences($dbStub, '', 'جديد') === array());

echo "\n--- brand assets: real files, sane size, honest preview ---\n";
$logoFile = APP_ROOT . '/uploads/brand/site_logo_1789925847.ico';
$iconFile = APP_ROOT . '/uploads/brand/site_favicon_1786883107.png';
t('logo asset is present in the repo', is_file($logoFile));
t('favicon asset is present in the repo', is_file($iconFile));

if (is_file($iconFile)) {
    $iconInfo = @getimagesize($iconFile);
    t('favicon is a real PNG', $iconInfo !== false && $iconInfo[2] === IMAGETYPE_PNG);
    t('favicon is at most 256px', $iconInfo !== false && $iconInfo[0] <= 256 && $iconInfo[1] <= 256);
    t('favicon stays under 100 KB', filesize($iconFile) < 100 * 1024);
}
if (is_file($logoFile)) {
    t('logo is a real ICO', substr((string) file_get_contents($logoFile, false, null, 0, 4), 0, 4) === "\x00\x00\x01\x00");
    t('logo stays under 150 KB', filesize($logoFile) < 150 * 1024);
}

$settingsView = (string) file_get_contents(APP_ROOT . '/views/admin/settings/index.php');
$settingsCtrl = (string) file_get_contents(APP_ROOT . '/controllers/admin/SettingsController.php');

t('preview checks the file on disk', strpos($settingsView, 'is_file($brandFile)') !== false);
t('missing file is named, not left as a broken icon', strpos($settingsView, 'الملف غير موجود على الخادم') !== false);
t('preview has an onerror fallback', strpos($settingsView, 'brandPreviewFailed') !== false);
t('preview box stays light in dark mode', strpos($settingsView, 'html.admin-dark .brand-preview-box') !== false);
t('uploads are capped at 5 MB', strpos($settingsCtrl, '5 * 1024 * 1024') !== false);
t('oversized images are downscaled on upload', strpos($settingsCtrl, 'normaliseBrandAsset') !== false);
t('favicon is capped at 256px', strpos($settingsCtrl, "\$key === 'site_favicon' ? 256 : 1024") !== false);

$uploadsHtaccess = (string) file_get_contents(APP_ROOT . '/uploads/.htaccess');
t('uploads htaccess is Apache 2.4-safe', strpos($uploadsHtaccess, 'mod_authz_core.c') !== false);
t('PHP is still denied in uploads', strpos($uploadsHtaccess, 'Require all denied') !== false);

echo "\n--- an asset that disappears never breaks a page ---\n";
Settings::$data['site_logo'] = 'uploads/brand/does_not_exist.png';
Brand::flush();
t('missing logo resolves to empty', Brand::logoUrl() === '');

Settings::$data['site_logo'] = 'uploads/brand/site_logo_1789925847.ico';
Brand::flush();
$logoUrl = Brand::logoUrl();
t('present logo resolves to a URL', strpos($logoUrl, 'site_logo_1789925847.ico') !== false);
t('present logo carries a cache-buster', preg_match('/\?v=\d+$/', $logoUrl) === 1);

Settings::$data['site_logo'] = 'https://cdn.example.com/logo.png';
Brand::flush();
t('remote logo is left untouched', Brand::logoUrl() === 'https://cdn.example.com/logo.png');

Settings::$data['site_favicon'] = 'uploads/brand/does_not_exist.png';
Brand::flush();
t('missing favicon resolves to empty', Brand::faviconUrl() === '');

Settings::$data['default_og_image'] = 'https://example.test/uploads/brand/missing.png';
Brand::flush();
t('missing self-hosted asset resolves to empty', Brand::assetUrl('default_og_image') === '');

Settings::$data['default_og_image'] = 'https://example.test/uploads/brand/site_favicon_1786883107.png';
Brand::flush();
t('present self-hosted asset is verified and versioned', preg_match('#/uploads/brand/site_favicon_1786883107\.png\?v=\d+$#', Brand::assetUrl('default_og_image')) === 1);

$indexSrc = (string) file_get_contents(APP_ROOT . '/index.php');
$adminLayout = (string) file_get_contents(APP_ROOT . '/views/layouts/admin.php');
t('favicon tag falls back when the file is missing', strpos($indexSrc, '!is_file($local) || filesize($local) > 200 * 1024') !== false);
t('public logo uses the checked URL', strpos($indexSrc, 'Brand::logoUrl()') !== false);
t('public logo has a badge fallback', strpos($indexSrc, 'previousElementSibling.style.display=') !== false);
t('admin sidebar uses the checked URL', strpos($adminLayout, 'Brand::logoUrl()') !== false);
t('admin sidebar can fall back to the badge', strpos($adminLayout, "this.nextElementSibling.style.display='flex'") !== false);

echo "\n--- social cards never point at a missing file ---\n";
t('bundled OG fallback exists', is_file(APP_ROOT . '/assets/images/icon-512.png'));

Settings::$data['site_logo'] = 'uploads/brand/site_logo_1789925847.ico';
Settings::$data['default_og_image'] = 'uploads/brand/does_not_exist.png';
Brand::flush();
$meta = SEO::renderMeta('صفحة');
t('missing default OG falls back to a bundled card', strpos($meta, 'assets/images/icon-512.png') !== false);
t('the old missing og-default.jpg is gone', strpos($meta, 'og-default.jpg') === false);

Settings::$data['default_og_image'] = 'https://example.test/uploads/brand/missing.png';
Brand::flush();
$meta = SEO::renderMeta('صفحة');
t('self-hosted missing OG falls back in meta', strpos($meta, 'assets/images/icon-512.png') !== false);

Settings::$data['default_og_image'] = 'https://cdn.example.com/card.png';
Brand::flush();
$meta = SEO::renderMeta('صفحة');
t('remote default OG is used as-is', strpos($meta, 'https://cdn.example.com/card.png') !== false);

Settings::$data['default_og_image'] = 'uploads/brand/site_favicon_1786883107.png';
Brand::flush();
$meta = SEO::renderMeta('صفحة');
t('present default OG is used', strpos($meta, 'site_favicon_1786883107.png') !== false);

$meta = SEO::renderMeta('صفحة', '', 'https://remote.example.com/a.jpg');
t('remote article image is not double-prefixed', strpos($meta, 'https://remote.example.com/a.jpg') !== false);
t('publisher logo comes from the brand helper', strpos($meta, 'site_logo_1789925847.ico') !== false);

echo "\n--- no view hardcodes a missing brand file ---\n";
$headerSrc = (string) file_get_contents(APP_ROOT . '/views/partials/header.php');
$articleSrc = (string) file_get_contents(APP_ROOT . '/views/article.php');
t('header uses the configured OG image', strpos($headerSrc, "Brand::assetUrl('default_og_image')") !== false);
t('header no longer points at og_share.png', strpos($headerSrc, 'og_share.png') === false);
t('header JSON-LD logo is checked', strpos($headerSrc, 'Brand::logoUrl()') !== false);
t('article OG falls back safely', strpos($articleSrc, "Brand::assetUrl('default_og_image')") !== false);
t('article no longer points at og_share.png', strpos($articleSrc, 'og_share.png') === false);
t('article publisher logo is checked', strpos($articleSrc, 'Brand::logoUrl()') !== false);
t('the missing uploads/brand/logo.png is gone', strpos($headerSrc . $articleSrc, 'uploads/brand/logo.png') === false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);