<?php
/**
 * Phase 2/3 view smoke test: renders the roles screens with fixture data and
 * asserts they produce the expected markup (catches undefined vars / fatals
 * without needing an admin login).
 *
 * Run: php tools/rbac_viewtest.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
define('RBAC_ENFORCE', false);
define('RBAC_SUPER_ROLE', 'admin');
require_once $root . '/core/Permissions.php';

// ---- minimal stubs for the helpers the views use ----
if (!function_exists('app_url')) {
    function app_url($p = '')
    {
        return '/' . ltrim((string) $p, '/');
    }
}
if (!function_exists('admin_e')) {
    function admin_e($v)
    {
        return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
    }
}
if (!function_exists('admin_can')) {
    function admin_can($p)
    {
        return true;
    }
}
if (!class_exists('CSRF')) {
    class CSRF
    {
        public static function field()
        {
            return '<input type="hidden" name="_csrf" value="test">';
        }
    }
}

// ---- fixture rows exactly as RolesController builds them ----
function buildRoleRows()
{
    $all = Permissions::all();
    $rows = array();
    $data = array(
        array('id' => 9,  'name' => 'admin',  'name_ar' => 'مدير النظام الكامل', 'is_default' => 0, 'is_super' => true,  'users' => 1, 'perms' => '{"all":true}'),
        array('id' => 10, 'name' => 'editor', 'name_ar' => 'محرر رئيسي',          'is_default' => 0, 'is_super' => false, 'users' => 1, 'perms' => '{"all":true,"_v2":1}'),
        array('id' => 11, 'name' => 'author', 'name_ar' => 'كاتب ومحرر محتوى',    'is_default' => 0, 'is_super' => false, 'users' => 0, 'perms' => '{"articles":["view","create","edit_own"],"_v2":1}'),
        array('id' => 13, 'name' => 'reader', 'name_ar' => 'قارئ مسجل',          'is_default' => 1, 'is_super' => false, 'users' => 42, 'perms' => '{"comments":["create"],"_v2":1}'),
    );
    foreach ($data as $d) {
        $user = array('role_name' => $d['name'], 'permissions' => $d['perms']);
        $granted = 0;
        foreach (array_keys($all) as $perm) {
            if (Permissions::can($user, $perm)) {
                $granted++;
            }
        }
        $rows[] = array(
            'id'         => $d['id'],
            'name'       => $d['name'],
            'name_ar'    => $d['name_ar'],
            'is_default' => $d['is_default'],
            'is_super'   => $d['is_super'],
            'managed'    => true,
            'users'      => $d['users'],
            'granted'    => $granted,
            'total'      => count($all),
            'tokens'     => array_keys(Permissions::grantedTokens($user)),
            'summary'    => Permissions::describe($user),
        );
    }
    return $rows;
}

$pass = 0;
$fail = 0;
function expect($label, $cond)
{
    global $pass, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    $cond ? $pass++ : $fail++;
}

// ================= index view =================
$roles      = buildRoleRows();
$seed       = array('created' => array('subscriber'), 'updated' => array('editor', 'author', 'reader'), 'skipped' => array('admin'), 'error' => '');
$enforced   = false;
$success    = null;
$error      = null;

ob_start();
$e = null;
set_error_handler(function ($no, $str) use (&$e) { $e = $str; return true; });
include $root . '/views/admin/roles/index.php';
$html = ob_get_clean();
restore_error_handler();

expect('index: renders without php error', $e === null);
expect('index: shows page title', strpos($html, 'الأدوار والصلاحيات') !== false);
expect('index: shows admin role arabic name', strpos($html, 'مدير النظام الكامل') !== false);
expect('index: shows editor row', strpos($html, 'محرر رئيسي') !== false);
expect('index: shows author row', strpos($html, 'كاتب ومحرر محتوى') !== false);
expect('index: shows reader row', strpos($html, 'قارئ مسجل') !== false);
expect('index: editor has edit link', strpos($html, '/admin/roles/10/edit') !== false);
expect('index: admin marked not editable', strpos($html, 'غير قابل للتعديل') !== false);
expect('index: warns enforcement is off', strpos($html, 'إلغاء الصلاحيات غير مفعّل') !== false);
expect('index: shows full access badge for editor', strpos($html, 'وصول كامل') !== false);
expect('index: shows user counts', strpos($html, '>42<') !== false);

// enforcement ON banner disappears when enabled
$enforced = true;
ob_start();
include $root . '/views/admin/roles/index.php';
$htmlOn = ob_get_clean();
expect('index: no warning when enforced', strpos($htmlOn, 'إلغاء الصلاحيات غير مفعّل') === false);

// ================= edit view =================
$role = array('id' => 11, 'name' => 'author', 'name_ar' => 'كاتب ومحرر محتوى', 'permissions' => '{"articles":["view","create","edit_own"],"_v2":1}');
$tokens = array_keys(Permissions::grantedTokens(array('role_name' => 'author', 'permissions' => $role['permissions'])));
$users = 3;
$success = null;
$error = null;

ob_start();
$e2 = null;
set_error_handler(function ($no, $str) use (&$e2) { $e2 = $str; return true; });
include $root . '/views/admin/roles/edit.php';
$editHtml = ob_get_clean();
restore_error_handler();

expect('edit: renders without php error', $e2 === null);
expect('edit: posts to update route', strpos($editHtml, '/admin/roles/11/update') !== false);
expect('edit: has csrf field', strpos($editHtml, 'name="_csrf"') !== false);
expect('edit: renders all 6 groups', substr_count($editHtml, 'card-header bg-white') >= 6);
// count only real checkbox inputs (the JS selector string also contains the attribute)
$boxCount = substr_count($editHtml, 'type="checkbox" name="permissions[]"');
echo "debug: checkboxes in html = $boxCount / catalog permissions = " . count(Permissions::all()) . "\n";
expect('edit: has a checkbox per permission', $boxCount === count(Permissions::all()));
expect('edit: author articles.view is checked', preg_match('/value="articles\.view"[^>]*checked/s', $editHtml) === 1);
expect('edit: author articles.edit is NOT checked', preg_match('/value="articles\.edit"[^>]*checked/s', $editHtml) === 0);
expect('edit: shows sensitive group (backup)', strpos($editHtml, 'backup.export') !== false);
expect('edit: has select-all buttons', strpos($editHtml, 'data-toggle-all="1"') !== false);
expect('edit: shows entity labels arabic', strpos($editHtml, 'النسخ الاحتياطي') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);