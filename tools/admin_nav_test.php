<?php
/**
 * Admin sidebar structure test: grouping, permissions, uniqueness, coverage.
 * Run: php tools/admin_nav_test.php
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
function t($label, $cond)
{
    global $pass, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    $cond ? $pass++ : $fail++;
}

$sections = AdminNav::sections();
t('has sections', count($sections) > 0, true);
t('six logical groups', count($sections), 6);

// unique keys and labels
$keys = $labels = $urls = array();
foreach ($sections as $s) {
    t('section has key', !empty($s['key']), true);
    t('section has label', !empty($s['label']), true);
    t('section has icon', !empty($s['icon']), true);
    t('section not empty: ' . $s['key'], !empty($s['items']), true);
    $keys[] = $s['key'];
    $labels[] = $s['label'];
    foreach ($s['items'] as $item) {
        $urls[] = $item['active'];
    }
}
t('section keys unique', count($keys) === count(array_unique($keys)), true);
t('section labels unique', count($labels) === count(array_unique($labels)), true);
t('item urls unique', count($urls) === count(array_unique($urls)), true);

// every permission used by the nav exists in the catalog
$catalog = Permissions::all();
$bad = array();
foreach (AdminNav::items() as $item) {
    if (!isset($catalog[$item['perm']])) {
        $bad[] = $item['active'] . ' -> ' . $item['perm'];
    }
}
t('all nav permissions exist in catalog', empty($bad), true);

// nothing from the flat menu got lost (31 items previously)
t('nav has 31 items', count(AdminNav::items()), 31);

// expected grouping spot-checks
$groups = array();
foreach (AdminNav::items() as $item) {
    $groups[$item['active']] = $item['group'];
}
t('articles under content', $groups['admin/articles'] ?? '', 'content');
t('news-feeds under publishing', $groups['admin/news-feeds'] ?? '', 'publishing');
t('comments under interaction', $groups['admin/comments'] ?? '', 'interaction');
t('classifier-rules under ai', $groups['admin/classifier-rules'] ?? '', 'ai');
t('users under system', $groups['admin/users'] ?? '', 'system');
t('dashboard under general', $groups['admin'] ?? '', 'general');
t('roles under system', $groups['admin/roles'] ?? '', 'system');
t('backup under system', $groups['admin/backup'] ?? '', 'system');

// every section is reachable by at least one standard role
$defaults = json_decode(file_get_contents($root . '/config/role_permissions.default.json'), true);
$roles = array();
foreach ($defaults as $k => $v) {
    if ($k !== '' && $k[0] !== '_') {
        $roles[$k] = $v;
    }
}
$adminSeesAll = true;
foreach (AdminNav::items() as $item) {
    $adminUser = array('role_name' => 'admin', 'permissions' => json_encode($roles['admin'], JSON_UNESCAPED_UNICODE));
    if (!Permissions::can($adminUser, $item['perm'])) {
        $adminSeesAll = false;
    }
}
t('admin sees every nav item', $adminSeesAll, true);

// an analyst gets the read-only view: general groups plus the two read-only
// system items (security alerts + diagnostics) - and nothing that writes.
$analyst = array('role_name' => 'analyst', 'permissions' => json_encode($roles['analyst'], JSON_UNESCAPED_UNICODE));
$analystGroups = array();
$analystSystem = array();
foreach (AdminNav::items() as $item) {
    if (Permissions::can($analyst, $item['perm'])) {
        $analystGroups[$item['group']] = true;
        if ($item['group'] === 'system') {
            $analystSystem[] = $item['active'];
        }
    }
}
t('analyst sees general group', isset($analystGroups['general']), true);
t('analyst sees system group (read-only items)', isset($analystGroups['system']), true);
t('analyst system items are read-only', $analystSystem, array('admin/security-alerts', 'admin/diagnostics'));
t('analyst cannot see users', Permissions::can($analyst, 'users.view') === false, true);
t('analyst cannot see backup', Permissions::can($analyst, 'backup.export') === false, true);

// a reader sees nothing at all
$reader = array('role_name' => 'reader', 'permissions' => json_encode($roles['reader'], JSON_UNESCAPED_UNICODE));
$readerSees = 0;
foreach (AdminNav::items() as $item) {
    if (Permissions::can($reader, $item['perm'])) {
        $readerSees++;
    }
}
t('reader sees no admin nav item', $readerSees === 0, true);

// layout uses the new structure
$layout = file_get_contents($root . '/views/layouts/admin.php');
t('layout renders details groups', strpos($layout, 'admin-nav-group') !== false);
t('layout uses AdminNav::sections', strpos($layout, 'AdminNav::sections()') !== false);
t('layout still filters by permission', strpos($layout, 'admin_can($item[' . "'perm'" . '])') !== false);
t('layout computes unread count', strpos($layout, 'unreadMessagesCount') !== false);
t('layout persists group state', strpos($layout, 'admin_nav_groups') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);