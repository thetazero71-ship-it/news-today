<?php
/**
 * Activity log attribution.
 *
 * Bug: ActivityLogger used "$user['id'] ?? null" but $user was never defined,
 * so every row was stored with user_id = NULL and the panel labelled human
 * actions as «نظام آلي».
 *
 * Run: php tools/activity_test.php
 */

define('APP_ROOT', dirname(__DIR__));

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

/** Stubs: capture exactly what the logger writes. */
class Database
{
    public static $params = array();
    public function query($sql, array $params = array())
    {
        self::$params = $params;
        return true;
    }
}
class Auth
{
    public static $user = null;
    public static function user()
    {
        return self::$user;
    }
}
class Session
{
    public static $data = array();
    public static function get($key, $default = null)
    {
        return array_key_exists($key, self::$data) ? self::$data[$key] : $default;
    }
}

$_SERVER['REMOTE_ADDR'] = '176.123.20.35';
$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 Test';

require_once APP_ROOT . '/core/ActivityLogger.php';

echo "--- a signed-in person is recorded ---\n";
Auth::$user = array('id' => 7, 'username' => 'sara');
Session::$data = array();
Database::$params = array();
ActivityLogger::log('update', 'article', 12);
t('signed-in user id is stored', Database::$params[':user_id'] === 7);
t('action is unchanged', Database::$params[':action'] === 'update');
t('entity id is unchanged', Database::$params[':entity_id'] === 12);

echo "\n--- session fallback when Auth has no loaded row ---\n";
Auth::$user = null;
Session::$data = array('auth_user_id' => 9);
Database::$params = array();
ActivityLogger::log('delete', 'comment', 3);
t('session user id is used', Database::$params[':user_id'] === 9);

echo "\n--- genuine automation stays unattributed ---\n";
Auth::$user = null;
Session::$data = array();
Database::$params = array();
ActivityLogger::log('auto_publish', 'feed');
t('cron work keeps user_id null', Database::$params[':user_id'] === null);

echo "\n--- secrets never enter the audit payload ---\n";
Auth::$user = array('id' => 7);
ActivityLogger::log('update', 'user', 7, array('password' => 'x', '_csrf' => 't', 'ok' => 1), array('password_hash' => 'h', 'keep' => 2));
$old = json_decode((string) Database::$params[':old_values'], true);
$new = json_decode((string) Database::$params[':new_values'], true);
t('old password is stripped', !isset($old['password']));
t('old csrf is stripped', !isset($old['_csrf']));
t('new password hash is stripped', !isset($new['password_hash']));
t('non-secret fields survive', isset($old['ok'], $new['keep']));

echo "\n--- the source cannot regress ---\n";
$logger = (string) file_get_contents(APP_ROOT . '/core/ActivityLogger.php');
$view = (string) file_get_contents(APP_ROOT . '/views/admin/activity/index.php');
$ctrl = (string) file_get_contents(APP_ROOT . '/controllers/admin/ActivityLogController.php');

t('logger resolves the signed-in user', strpos($logger, 'Auth::user()') !== false);
t('logger has a session fallback', strpos($logger, "Session::get('auth_user_id'") !== false);
t('logger passes the resolved id', strpos($logger, 'self::currentUserId()') !== false);
t('logger no longer uses an undefined $user', strpos($logger, "\$user['id'] ?? null") === false);
t('view no longer defaults every row to system', strpos($view, "admin_e(\$log['username'] ?? 'نظام آلي')") === false);
t('view names unregistered requests', strpos($view, 'مستخدم غير مسجّل') !== false);
t('view names deleted accounts', strpos($view, 'مستخدم محذوف') !== false);
t('view draws an actor icon', strpos($view, 'bi-person-fill') !== false && strpos($view, 'bi-robot') !== false);
t('CSV export uses the actor label', strpos($ctrl, '$this->actorName($row)') !== false);
t('CSV export selects user_id', preg_match('/SELECT l\.id, l\.user_id, u\.username[^\n]*new_values/s', $ctrl) === 1);
t('JSON export adds the actor label', strpos($ctrl, "\$row['actor'] = \$this->actorName(\$row);") !== false);
t('helper distinguishes deleted accounts', strpos($ctrl, 'مستخدم محذوف') !== false);
t('helper distinguishes automation', strpos($ctrl, "'نظام آلي'") !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
