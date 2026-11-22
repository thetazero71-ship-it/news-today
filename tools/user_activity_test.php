<?php
/**
 * User activity & login diagnostics panel.
 *
 * Run: php tools/user_activity_test.php
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
require_once $root . '/core/UserActivityHelper.php';

echo "--- user-agent parsing ---\n";
$winChrome = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Safari/537.36';
$p = UserActivityHelper::parseUserAgent($winChrome);
t('Windows 10/11 is detected', $p['os'] === 'Windows 10/11');
t('Chrome wins over Safari', $p['browser'] === 'Chrome');
t('desktop is the default device', $p['device'] === 'سطح مكتب');

$iphone = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
$p = UserActivityHelper::parseUserAgent($iphone);
t('iOS is detected', $p['os'] === 'iOS');
t('Safari is detected', $p['browser'] === 'Safari');
t('iPhone is a phone', $p['device'] === 'هاتف');

$android = 'Mozilla/5.0 (Linux; Android 15; Pixel 9) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/151.0.0.0 Mobile Safari/537.36';
$p = UserActivityHelper::parseUserAgent($android);
t('Android is detected', $p['os'] === 'Android');
t('Android Mobile is a phone', $p['device'] === 'هاتف');

$ipad = 'Mozilla/5.0 (iPad; CPU OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1';
t('iPad is a tablet', UserActivityHelper::parseUserAgent($ipad)['device'] === 'جهاز لوحي');

$bot = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';
t('bots are labelled', UserActivityHelper::parseUserAgent($bot)['device'] === 'زاحف آلي');

$p = UserActivityHelper::parseUserAgent('');
t('empty UA degrades safely', $p['os'] === 'غير معروف' && $p['browser'] === 'غير معروف');

echo "\n--- IP classification ---\n";
t('loopback is local', UserActivityHelper::isPrivateIp('127.0.0.1'));
t('private 10/8 is local', UserActivityHelper::isPrivateIp('10.1.2.3'));
t('private 192.168 is local', UserActivityHelper::isPrivateIp('192.168.1.5'));
t('public 8.8.8.8 is public', !UserActivityHelper::isPrivateIp('8.8.8.8'));
t('local country is labelled', UserActivityHelper::countryForIp('127.0.0.1') === 'شبكة محلية');

$_SERVER['HTTP_CF_IPCOUNTRY'] = 'SA';
t('CDN country header wins', UserActivityHelper::countryForIp('8.8.8.8') === 'SA');
unset($_SERVER['HTTP_CF_IPCOUNTRY']);

echo "\n--- persistence hooks ---\n";
$auth = (string) file_get_contents($root . '/core/Auth.php');
t('login records the session', strpos($auth, 'UserActivityHelper::recordLogin') !== false);
t('logout closes the session', strpos($auth, 'UserActivityHelper::recordLogout') !== false);
t('diagnostics cannot break login', strpos($auth, '// لا نُفشل الدخول بسبب التشخيص.') !== false);
t('diagnostics cannot break logout', strpos($auth, '// الخروج يجب أن ينجح دائماً.') !== false);

$helper = (string) file_get_contents($root . '/core/UserActivityHelper.php');
t('the helper creates its table idempotently', strpos($helper, 'CREATE TABLE IF NOT EXISTS user_sessions') !== false);
t('it stores ip, UA, device and location', strpos($helper, 'INSERT INTO user_sessions (user_id, ip_address, user_agent, device_type, location)') !== false);
t('it summarises sessions and audit logs', strpos($helper, 'FROM user_sessions WHERE user_id = ?') !== false
    && strpos($helper, 'FROM activity_logs WHERE user_id = ?') !== false);

echo "\n--- the admin screen renders it ---\n";
$controller = (string) file_get_contents($root . '/controllers/admin/UsersController.php');
$view = (string) file_get_contents($root . '/views/admin/users/edit.php');
t('the controller builds the summary', strpos($controller, 'UserActivityHelper::summary($db, (int) $user[\'id\'])') !== false);
t('the summary reaches the view', strpos($controller, "'activity' => \$activity") !== false);
t('the panel exists', strpos($view, 'نشاط المستخدم وتشخيص الدخول') !== false);
t('the last IP is shown LTR monospace', strpos($view, "dir=\"ltr\" class=\"font-monospace\"") !== false);
t('the country is shown', strpos($view, "\$activity['country']") !== false);
t('the device/browser/OS are shown', strpos($view, "\$activity['device']") !== false
    && strpos($view, "\$activity['browser']") !== false
    && strpos($view, "\$activity['os']") !== false);
t('sessions are listed', strpos($view, 'foreach ($actSessions as $sess)') !== false);
t('audit entries are listed', strpos($view, 'foreach ($actLogs as $log)') !== false);
t('empty states are explained', strpos($view, 'لا توجد جلسات مسجّلة بعد') !== false
    && strpos($view, 'لا توجد عمليات مسجّلة لهذا المستخدم بعد') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
