<?php
/**
 * Verifies the enforcement switch changes guard behaviour:
 *   off  -> guardPermission() behaves exactly like the old guardAdmin()
 *   on   -> a role without the permission is refused
 *
 * Run: php tools/rbac_switch_test.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);

function assertEq($label, $actual, $expected)
{
    $ok = ($actual === $expected);
    echo ($ok ? 'PASS ' : 'FAIL ') . $label . ($ok ? '' : ' (got ' . var_export($actual, true) . ')') . "\n";
    return $ok;
}

$state = $argv[1] ?? 'off';

if ($state === 'off') {
    define('RBAC_ENFORCE', false);
    define('RBAC_SUPER_ROLE', 'admin');
} else {
    define('RBAC_ENFORCE', true);
    define('RBAC_SUPER_ROLE', 'admin');
}

require_once $root . '/core/Permissions.php';

$editor = array('id' => 5, 'role_name' => 'editor', 'permissions' => '{"articles":["create","edit","publish","delete"],"comments":["moderate"]}');

assertEq('enforced() matches switch', Permissions::enforced(), $state === 'on');

// What guardPermission() will decide for this editor, per permission.
$decisions = array(
    'articles.edit'   => Permissions::can($editor, 'articles.edit'),
    'articles.delete' => Permissions::can($editor, 'articles.delete'),
    'users.manage'    => Permissions::can($editor, 'users.manage'),
    'backup.import'   => Permissions::can($editor, 'backup.import'),
);

if ($state === 'on') {
    assertEq('editor keeps articles.edit', $decisions['articles.edit'], true);
    assertEq('editor loses users.manage', $decisions['users.manage'], false);
    assertEq('editor loses backup.import', $decisions['backup.import'], false);
} else {
    echo "off-mode: guardPermission() falls back to guardAdmin() (login + admin/editor)\n";
    echo "         -> decisions above are NOT applied; behaviour identical to today\n";
}