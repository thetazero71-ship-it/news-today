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
    return '/' . ltrim($p, '/');
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
array_map('unlink', glob($tmp . '/*'));
@rmdir($tmp);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);