<?php
/**
 * Phase 1 self-test: engine correctness + enforcement switch behaviour.
 * Run: php tools/rbac_selftest.php
 */

if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
require_once $root . '/core/Permissions.php';

$pass = 0;
$fail = 0;

function t($label, $actual, $expected)
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        return;
    }
    $fail++;
    echo "FAIL: $label -> got " . var_export($actual, true) . ", expected " . var_export($expected, true) . "\n";
}

// ── enforcement switch ────────────────────────────────────────────────
t('enforced() off by default', Permissions::enforced(), false);

// ── every stored JSON shape ───────────────────────────────────────────
$admin   = array('role_name' => 'admin',   'permissions' => '{"all":true}');
$editor  = array('role_name' => 'editor',  'permissions' => '{"articles":["create","edit","publish","delete"],"comments":["moderate"]}');
$author  = array('role_name' => 'author',  'permissions' => '{"articles":["create","edit_own"]}');
$sub     = array('role_name' => 'subscriber','permissions' => '{"premium_content":true,"comments":["create"]}');
$reader  = array('role_name' => 'reader',  'permissions' => '{"comments":["create"]}');
$none    = array('role_name' => 'reader',  'permissions' => null);

t('admin bypasses everything', Permissions::can($admin, 'backup.import'), true);
t('admin bypasses unknown perm', Permissions::can($admin, 'made.up.thing'), true);

t('editor create', Permissions::can($editor, 'articles.create'), true);
t('editor edit', Permissions::can($editor, 'articles.edit'), true);
t('editor publish', Permissions::can($editor, 'articles.publish'), true);
t('editor delete', Permissions::can($editor, 'articles.delete'), true);
t('editor moderate', Permissions::can($editor, 'comments.moderate'), true);
t('editor CANNOT backup', Permissions::can($editor, 'backup.import'), false);
t('editor CANNOT users', Permissions::can($editor, 'users.manage'), false);
t('editor CANNOT settings', Permissions::can($editor, 'settings.manage'), false);
t('editor bare entity match', Permissions::can($editor, 'articles'), true);
t('editor not in comments.create', Permissions::can($editor, 'comments.create'), false);

t('author create', Permissions::can($author, 'articles.create'), true);
t('author edit_own', Permissions::can($author, 'articles.edit_own'), true);
t('author CANNOT edit any', Permissions::can($author, 'articles.edit'), false);
t('author CANNOT publish any', Permissions::can($author, 'articles.publish'), false);

t('legacy premium_content maps', Permissions::can($sub, 'articles.premium'), true);
t('subscriber comment create', Permissions::can($sub, 'comments.create'), true);
t('subscriber no admin', Permissions::can($sub, 'articles.edit'), false);

t('reader only comments', Permissions::can($reader, 'comments.create'), true);
t('reader nothing else', Permissions::can($reader, 'dashboard.view'), false);
t('null permissions => nothing', Permissions::can($none, 'comments.create'), false);
t('guest (no user) => nothing', Permissions::can(null, 'comments.create'), false);

// ── alternate shapes ─────────────────────────────────────────────────
t('nested object form', Permissions::can(array('role_name' => 'x', 'permissions' => '{"articles":{"create":true}}'), 'articles.create'), true);
t('string value form', Permissions::can(array('role_name' => 'x', 'permissions' => '{"articles":"create"}'), 'articles.create'), true);
t('entity wildcard form', Permissions::can(array('role_name' => 'x', 'permissions' => '{"articles":"*"}'), 'articles.publish'), true);
t('global wildcard form', Permissions::can(array('role_name' => 'x', 'permissions' => '{"*":true}'), 'backup.import'), true);
t('flat token list', Permissions::can(array('role_name' => 'x', 'permissions' => '["articles.create","settings.manage"]'), 'settings.manage'), true);
t('false value ignored', Permissions::can(array('role_name' => 'x', 'permissions' => '{"articles":{"create":false}}'), 'articles.create'), false);
t('array already decoded', Permissions::can(array('role_name' => 'x', 'permissions' => array('articles' => array('create'))), 'articles.create'), true);
t('marker key ignored', Permissions::can(array('role_name' => 'x', 'permissions' => '{"_v2":1,"articles":["create"]}'), 'articles.create'), true);
t('marker does not grant', Permissions::can(array('role_name' => 'x', 'permissions' => '{"_v2":1}'), 'articles.create'), false);
t('broken json is safe', Permissions::can(array('role_name' => 'x', 'permissions' => '{not json'), 'articles.create'), false);

// ── catalog integrity ────────────────────────────────────────────────
t('catalog is not empty', count(Permissions::all()) > 0, true);
t('every group entity exists in catalog', true, true);
foreach (Permissions::groups() as $group => $entities) {
    foreach ($entities as $entity) {
        if (!Permissions::actionsFor($entity)) {
            $fail++;
            echo "FAIL: group '$group' references unknown entity '$entity'\n";
            continue 2;
        }
        $pass++;
    }
}
t('split dotted', Permissions::split('articles.edit'), array('articles', 'edit'));
t('split bare', Permissions::split('articles'), array('articles', ''));

// ── describe ─────────────────────────────────────────────────────────
t('describe admin', Permissions::describe($admin), 'كل الصلاحيات');
t('describe reader', Permissions::describe($reader), 'كتابة تعليقات');
t('describe none', Permissions::describe($none), 'بدون صلاحيات إدارية');

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);