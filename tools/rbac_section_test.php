<?php
/**
 * Sidebar section-level control: a role can hide a whole section, not just items.
 * Run: php tools/rbac_section_test.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
define('RBAC_ENFORCE', false);
define('RBAC_SUPER_ROLE', 'admin');
require_once $root . '/core/Permissions.php';
require_once $root . '/core/AdminNav.php';

$pass = 0;
$fail = 0;
function t($label, $actual, $expected = null)
{
    global $pass, $fail;
    // With an explicit expectation compare by identity (so false / null / [] are
    // valid expectations); otherwise treat the value as a boolean condition.
    $ok = func_num_args() >= 3 ? ($actual === $expected) : ((bool) $actual);
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . "\n";
    $ok ? $pass++ : $fail++;
}

$defaults = json_decode(file_get_contents($root . '/config/role_permissions.default.json'), true);
function basePerms(array $defaults, $role)
{
    return json_encode($defaults[$role], JSON_UNESCAPED_UNICODE);
}

// 1. no restriction -> derive from permissions
$me = array('role_name' => 'managing_editor', 'permissions' => basePerms($defaults, 'managing_editor'));
t('no _sections => null (derive)', Permissions::sectionAccess($me), null);
t('managing_editor sees every section by default', Permissions::canSeeSection($me, 'ai'), true);
t('managing_editor sees system by default (perm based)', Permissions::canSeeSection($me, 'system'), true);

// 2. explicit list -> only those sections
$perms = json_decode(basePerms($defaults, 'managing_editor'), true);
$perms['_sections'] = array('general', 'content');
$restricted = array('role_name' => 'managing_editor', 'permissions' => json_encode($perms, JSON_UNESCAPED_UNICODE));

t('explicit list is returned', Permissions::sectionAccess($restricted), array('general', 'content'));
t('listed section allowed', Permissions::canSeeSection($restricted, 'general'), true);
t('listed section allowed 2', Permissions::canSeeSection($restricted, 'content'), true);
t('unlisted section hidden (ai)', Permissions::canSeeSection($restricted, 'ai'), false);
t('unlisted section hidden (system)', Permissions::canSeeSection($restricted, 'system'), false);
t('unlisted section hidden (publishing)', Permissions::canSeeSection($restricted, 'publishing'), false);
t('unlisted section hidden (interaction)', Permissions::canSeeSection($restricted, 'interaction'), false);

// 3. permissions are NOT affected by the section restriction
t('hiding a section does not revoke its permissions',
    Permissions::can($restricted, 'classifier.manage'), true);
t('hiding a section does not grant anything',
    Permissions::can($restricted, 'settings.manage'), false);

// 4. empty list means "hide everything" (explicit)
$perms2 = json_decode(basePerms($defaults, 'editor'), true);
$perms2['_sections'] = array();
$none = array('role_name' => 'editor', 'permissions' => json_encode($perms2, JSON_UNESCAPED_UNICODE));
t('empty list hides all', Permissions::sectionAccess($none), array());
t('empty list: general hidden', Permissions::canSeeSection($none, 'general'), false);
t('empty list: content hidden', Permissions::canSeeSection($none, 'content'), false);

// 5. super role always sees everything
$admin = array('role_name' => 'admin', 'permissions' => basePerms($defaults, 'admin'));
t('super role ignores restriction', Permissions::sectionAccess($admin), null);
t('super role sees ai', Permissions::canSeeSection($admin, 'ai'), true);

// 6. section keys are normalised
$perms3 = json_decode(basePerms($defaults, 'author'), true);
$perms3['_sections'] = array(' Content ', 'GENERAL', '', 'general');
$norm = array('role_name' => 'author', 'permissions' => json_encode($perms3, JSON_UNESCAPED_UNICODE));
$acc = Permissions::sectionAccess($norm);
t('section keys lowercased/trimmed', $acc[0], 'content');
t('duplicate kept as-is (idempotent check)', in_array('general', $acc, true), true);

// 7. every AdminNav group is a valid key we can control
foreach (AdminNav::sections() as $s) {
    $onlyThis = $perms3;
    $onlyThis['_sections'] = array($s['key']);
    $u = array('role_name' => 'author', 'permissions' => json_encode($onlyThis, JSON_UNESCAPED_UNICODE));
    $allowed = 0;
    foreach (AdminNav::sections() as $other) {
        if (Permissions::canSeeSection($u, $other['key'])) {
            $allowed++;
        }
    }
    t('section "' . $s['key'] . '" can be isolated', $allowed === 1);
}

// 8. malformed input is safe
t('guest sees no section', Permissions::canSeeSection(null, 'general'), false);
t('broken json is safe', Permissions::canSeeSection(array('role_name' => 'x', 'permissions' => '{oops'), 'general'), true);
t('non-array _sections is ignored', Permissions::canSeeSection(array('role_name' => 'x', 'permissions' => '{"_sections":"general"}'), 'general'), true);

// 9. roles controller + views expose the control
$ctrl = file_get_contents($root . '/controllers/admin/RolesController.php');
$view = file_get_contents($root . '/views/admin/roles/edit.php');
$list = file_get_contents($root . '/views/admin/roles/index.php');
$layout = file_get_contents($root . '/views/layouts/admin.php');
t('controller reads sections from POST', strpos($ctrl, "_POST['sections']") !== false);
t('controller passes sections to view', strpos($ctrl, "'sections'") !== false);
t('edit view renders section checkboxes', strpos($view, 'name="sections[]"') !== false);
t('edit view lists nav groups', strpos($view, 'navGroups') !== false);
t('roles list shows section state', strpos($list, "role['sections']") !== false);
t('layout applies section filter', strpos($layout, 'canSeeSection') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);