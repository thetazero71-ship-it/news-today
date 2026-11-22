<?php
/**
 * Maintenance mode guard.
 *
 * Bug: the dashboard exposed a "maitenance" status label but no setting row was
 * seeded and index.php never checked it, so the switch did nothing. This test
 * pins the whole chain: seed -> interceptor -> 503 page -> cache invalidation.
 *
 * Run: php tools/maintenance_test.php
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
$index = (string) file_get_contents($root . '/index.php');
$settings = (string) file_get_contents($root . '/controllers/admin/SettingsController.php');
$view = (string) file_get_contents($root . '/views/errors/maintenance.php');
$settingsView = (string) file_get_contents($root . '/views/admin/settings/index.php');

echo "--- the switch is a real, seeded setting ---\n";
t('site_status is seeded', strpos($settings, "'site_status' => [") !== false);
t('the default is active', strpos($settings, "'value' => 'active',\n  'value_type' => 'select',") !== false);
t('the three states are documented', strpos($settings, 'وضع الصيانة') !== false && strpos($settings, 'خاص') !== false);
t('the message is editable', strpos($settings, "'maintenance_message' => [") !== false);
t('the settings screen knows the three labels', strpos($settingsView, "'maintenance' => 'وضع الصيانة (Maintenance Mode)'") !== false
    && strpos($settingsView, "'active' => 'متاح للعامة ونشط (Active)'") !== false);

echo "\n--- index.php enforces it before anything else ---\n";
$maintenancePos = strpos($index, "\$__siteStatus === 'maintenance'");
t('the interceptor exists', $maintenancePos !== false);
t('it returns HTTP 503', strpos($index, 'http_response_code(503)') !== false);
t('it tells bots when to retry', strpos($index, "header('Retry-After: 3600')") !== false);
t('it forbids caching the 503', strpos($index, 'no-store, no-cache') !== false);
t('it runs before the page cache', $maintenancePos !== false
    && strpos($index, 'PageCache::serve()') !== false
    && $maintenancePos < strpos($index, 'PageCache::serve()'));
t('it runs before the security scan', $maintenancePos !== false
    && $maintenancePos < strpos($index, 'SecurityGuard::scanRequest()'));

echo "\n--- the owner, login, cron, API and assets stay reachable ---\n";
t('admin/login/cron/api are exempt', strpos($index, '#^/(admin|login|logout|cron|api)(/|$)#i') !== false);
t('static assets are exempt', strpos($index, '#\\.(css|js|mjs|json|webmanifest|png|jpe?g|gif|svg|ico|webp|avif|woff2?|ttf|eot|map|xml|txt)$#i') !== false);
t('a signed-in admin bypasses it', strpos($index, 'Permissions::canEnterAdmin(Auth::user())') !== false);
t('a database failure cannot freeze the site', strpos($index, 'catch (Throwable $__e)') !== false);

echo "\n--- the 503 page itself ---\n";
t('the page exists', $view !== '');
t('it is marked noindex', strpos($view, 'content="noindex, nofollow"') !== false);
t('it self-refreshes after a minute', strpos($view, 'http-equiv="refresh" content="60"') !== false);
t('it shows the platform identity', strpos($view, 'Brand::name()') !== false);
t('it shows the editable message', strpos($view, "Settings::get('maintenance_message'") !== false);
t('it reads the verified logo', strpos($view, 'Brand::logoUrl()') !== false);
t('it has a manual retry', strpos($view, 'window.location.reload()') !== false);
t('it never exposes an admin login link', strpos($view, '/login') === false && strpos($view, 'admin/login') === false);

echo "\n--- saving settings applies at once ---\n";
t('the public page cache is flushed on save', strpos($settings, 'PageCache::flush();') !== false);
t('the object cache is still flushed', strpos($settings, 'Cache::flush();') !== false);

echo "\n--- the page actually renders ---\n";
class Brand
{
    public static function name() { return 'منصة الاختبار'; }
    public static function tagline() { return 'عبارة الاختبار'; }
    public static function logoUrl() { return ''; }
}
class Settings
{
    public static function get($key, $default = null)
    {
        return $key === 'maintenance_message' ? 'رسالة صيانة تجريبية' : $default;
    }
}
ob_start();
include $root . '/views/errors/maintenance.php';
$html = (string) ob_get_clean();
t('it renders the brand name', strpos($html, 'منصة الاختبار') !== false);
t('it renders the message', strpos($html, 'رسالة صيانة تجريبية') !== false);
t('it renders the 503 state', strpos($html, '503') !== false);
t('it renders the retry button', strpos($html, 'إعادة المحاولة الآن') !== false);
t('it does not link to the admin area', stripos($html, 'admin') === false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
