<?php
/**
 * Regression test for the admin-panel entry rule.
 *
 * Bug: the "لوحة الإدارة" button and every admin guard relied on
 * Auth::isAdmin() which only accepted the roles "admin" and "editor", so the
 * standard role "managing_editor" could not enter the panel at all.
 *
 * Run: php tools/rbac_entry_test.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
define('RBAC_ENFORCE', false);
define('RBAC_SUPER_ROLE', 'admin');
require_once $root . '/core/Permissions.php';

$pass = 0;
$fail = 0;
function t($label, $cond)
{
    global $pass, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    $cond ? $pass++ : $fail++;
}

$defaults = json_decode(file_get_contents($root . '/config/role_permissions.default.json'), true);

function roleUser(array $defaults, $name)
{
    $perms = array();
    foreach ($defaults as $key => $value) {
        if ($key !== '' && $key[0] !== '_') {
            $perms[$key] = $value;
        }
    }
    return array('role_name' => $name, 'permissions' => json_encode($perms[$name] ?? array(), JSON_UNESCAPED_UNICODE));
}

// the reported bug: managing_editor must be able to enter the panel
$me = roleUser($defaults, 'managing_editor');
t('managing_editor can enter admin panel', Permissions::canEnterAdmin($me));
t('managing_editor can edit articles', Permissions::can($me, 'articles.edit'));
t('managing_editor can manage categories', Permissions::can($me, 'categories.manage'));
t('managing_editor cannot touch users', Permissions::can($me, 'users.manage') === false);
t('managing_editor cannot restore backup', Permissions::can($me, 'backup.import') === false);
t('managing_editor cannot edit roles', Permissions::can($me, 'roles.manage') === false);
t('managing_editor cannot edit settings', Permissions::can($me, 'settings.manage') === false);

// other staff roles
foreach (array('publisher', 'author', 'contributor', 'moderator', 'translator', 'newsletter_manager', 'analyst') as $r) {
    t($r . ' can enter admin panel', Permissions::canEnterAdmin(roleUser($defaults, $r)));
}

// front-end only roles must stay OUT of the panel
$reader = roleUser($defaults, 'reader');
$subscriber = roleUser($defaults, 'subscriber');
t('reader cannot enter admin panel', Permissions::canEnterAdmin($reader) === false);
t('subscriber cannot enter admin panel', Permissions::canEnterAdmin($subscriber) === false);
t('reader still comments', Permissions::can($reader, 'comments.create'));
t('subscriber still reads premium', Permissions::can($subscriber, 'articles.premium'));

// admin always
$admin = roleUser($defaults, 'admin');
t('admin can enter admin panel', Permissions::canEnterAdmin($admin));
t('admin has everything', Permissions::can($admin, 'backup.import'));

// guest
t('guest cannot enter', Permissions::canEnterAdmin(null) === false);
t('no user cannot enter', Permissions::canEnterAdmin(array()) === false);

// legacy roles keep their old behaviour
$legacyEditor = array('role_name' => 'editor', 'permissions' => '{"articles":["view"]}');
t('legacy editor still allowed by isAdmin rules', Permissions::canEnterAdmin($legacyEditor));

// admin-area set excludes the two front-end capabilities
$area = array_keys(Permissions::adminAreaPermissions());
t('comments.create excluded from admin area', in_array('comments.create', $area, true) === false);
t('articles.premium excluded from admin area', in_array('articles.premium', $area, true) === false);
t('articles.view included in admin area', in_array('articles.view', $area, true));
t('dashboard.view included in admin area', in_array('dashboard.view', $area, true));

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);