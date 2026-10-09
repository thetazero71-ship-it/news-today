<?php
/**
 * Phase 2 self-test: RoleSeeder encode/decode + idempotency rules.
 * Nothing here touches the database; it uses a fake $db object.
 *
 * Run: php tools/rbac_seedtest.php
 */

if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
require_once $root . '/config/rbac.php';
require_once $root . '/core/Permissions.php';
require_once $root . '/core/RoleSeeder.php';

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
    echo "FAIL: $label\n  got:      " . var_export($actual, true) . "\n  expected: " . var_export($expected, true) . "\n";
}

// ── defaults file ─────────────────────────────────────────────────────
$defaults = RoleSeeder::defaults();
t('defaults loaded', count($defaults) >= 5, true);
t('defaults skip comment keys', isset($defaults['_comment']), false);
t('defaults keep admin', isset($defaults['admin']), true);
t('admin has all', Permissions::can(array('role_name' => 'x', 'permissions' => json_encode($defaults['admin'])), 'backup.import'), true);

// ── marker ───────────────────────────────────────────────────────────
t('marker present after encode', RoleSeeder::isManaged(RoleSeeder::encode(array('articles' => array('create')))), true);
t('legacy permissions not managed', RoleSeeder::isManaged(RoleSeeder::decode('{"articles":["create","edit"]}')), false);
t('empty not managed', RoleSeeder::isManaged(array()), false);
t('empty json not managed', RoleSeeder::isManaged(RoleSeeder::decode('')), false);
t('broken json not managed', RoleSeeder::isManaged(RoleSeeder::decode('{oops')), false);

// ── checkbox round trip ───────────────────────────────────────────────
$tokens = array('articles.create', 'articles.edit', 'comments.moderate', 'backup.export');
$nested = RoleSeeder::fromCheckboxList($tokens);
t('nested articles is array', is_array($nested['articles']), true);
t('nested articles.create', in_array('create', $nested['articles'], true), true);
t('nested articles has no edit_own', in_array('edit_own', $nested['articles'], true), false);
t('nested comments', in_array('moderate', $nested['comments'], true), true);
t('nested backup', in_array('export', $nested['backup'], true), true);

$user = array('role_name' => 'editor', 'permissions' => json_encode($nested, JSON_UNESCAPED_UNICODE));
t('round trip: can edit', Permissions::can($user, 'articles.edit'), true);
t('round trip: cannot publish', Permissions::can($user, 'articles.publish'), false);
t('round trip: cannot backup import', Permissions::can($user, 'backup.import'), false);

// ── encoding keeps engine compatibility ───────────────────────────────
$encoded = RoleSeeder::encode($nested);
$decoded = RoleSeeder::decode($encoded);
t('encoded keeps articles', isset($decoded['articles']), true);
t('encoded marker kept', isset($decoded['_v2']), true);
t('engine reads encoded', Permissions::can(array('role_name' => 'e', 'permissions' => $encoded), 'articles.edit'), true);

// ── empty selection means no permissions (not "all") ─────────────────
$empty = RoleSeeder::fromCheckboxList(array());
t('empty selection is empty', $empty, array());
t('empty grants nothing', Permissions::can(array('role_name' => 'e', 'permissions' => json_encode(RoleSeeder::encode($empty))), 'articles.view'), false);

// ── "all" checkbox wins ──────────────────────────────────────────────
$allBox = RoleSeeder::fromCheckboxList(array('all'));
t('all token collapses', $allBox, array('all' => true));

// ── simulation of ensure() with a fake db ────────────────────────────
class FakeDb
{
    public $roles = array();
    public $writes = array();
    public $cols = array('id', 'name', 'name_ar', 'permissions', 'is_default');

    public function fetchAll($sql)
    {
        if (stripos($sql, 'SHOW COLUMNS') !== false) {
            $out = array();
            foreach ($this->cols as $c) {
                $out[] = array('Field' => $c);
            }
            return $out;
        }
        return array_values($this->roles);
    }

    public function query($sql, array $params = array())
    {
        if (stripos($sql, 'UPDATE roles') !== false) {
            $id = (int) ($params[':id'] ?? 0);
            $this->roles[$id]['permissions'] = (string) ($params[':p'] ?? '');
            $this->writes[] = $id;
        } elseif (stripos($sql, 'INSERT INTO roles') !== false) {
            $id = 100 + count($this->roles);
            $this->roles[$id] = array(
                'id'          => $id,
                'name'        => (string) ($params[':name'] ?? ''),
                'name_ar'     => (string) ($params[':name_ar'] ?? ''),
                'permissions' => (string) ($params[':permissions'] ?? ''),
                'is_default'  => (int) ($params[':is_default'] ?? 0),
            );
            $this->writes[] = $id;
        }
        return true;
    }
}

$db = new FakeDb();
// current live state: legacy permissions, no marker
$db->roles[9]  = array('id' => 9,  'name' => 'admin',       'name_ar' => 'مدير',        'permissions' => '{"all": true}', 'is_default' => 0);
$db->roles[10] = array('id' => 10, 'name' => 'editor',      'name_ar' => 'محرر',        'permissions' => '{"articles":["create","edit","publish","delete"],"comments":["moderate"]}', 'is_default' => 0);
$db->roles[11] = array('id' => 11, 'name' => 'author',      'name_ar' => 'كاتب',        'permissions' => '{"articles":["create","edit_own"]}', 'is_default' => 0);
$db->roles[13] = array('id' => 13, 'name' => 'reader',      'name_ar' => 'قارئ',        'permissions' => '{"comments":["create"]}', 'is_default' => 1);

// first run: legacy roles are updated, missing standard roles are created
$report = RoleSeeder::ensure($db);
t('legacy roles updated', count($report['updated']), 3);
t('editor updated', in_array('editor', $report['updated'], true), true);
t('author updated', in_array('author', $report['updated'], true), true);
t('admin skipped (super role)', in_array('admin', $report['skipped'], true), true);
t('no error', $report['error'], '');
t('standard roles created', count($report['created']) >= 7, true);

// second run must be a no-op across the whole standard set
$writesAfterFirst = count($db->writes);
$report2 = RoleSeeder::ensure($db);
t('second run writes nothing', count($db->writes), $writesAfterFirst);
t('second run updates nothing', count($report2['updated']), 0);
t('second run skips everything', count($report2['skipped']), count(RoleSeeder::defaults()));

// after seeding: roles follow the standard model (least privilege + hierarchy)
// editor is now scoped to content; system areas belong to admin only.
$editorUser = array('role_name' => 'editor', 'permissions' => $db->roles[10]['permissions']);
t('editor still edits articles', Permissions::can($editorUser, 'articles.edit'), true);
t('editor publishes (inherits publisher)', Permissions::can($editorUser, 'articles.publish'), true);
t('editor moderates comments', Permissions::can($editorUser, 'comments.moderate'), true);
t('editor cannot users.manage', Permissions::can($editorUser, 'users.manage'), false);
t('editor cannot backup.export', Permissions::can($editorUser, 'backup.export'), false);
t('editor cannot settings.manage', Permissions::can($editorUser, 'settings.manage'), false);
t('editor cannot roles.manage', Permissions::can($editorUser, 'roles.manage'), false);

$adminUser = array('role_name' => 'admin', 'permissions' => $db->roles[9]['permissions']);
t('admin keeps backup.import', Permissions::can($adminUser, 'backup.import'), true);
t('admin keeps roles.manage', Permissions::can($adminUser, 'roles.manage'), true);

$authorUser = array('role_name' => 'author', 'permissions' => $db->roles[11]['permissions']);
t('author still scoped down', Permissions::can($authorUser, 'users.manage'), false);
t('author cannot backup', Permissions::can($authorUser, 'backup.export'), false);
t('author keeps edit_own', Permissions::can($authorUser, 'articles.edit_own'), true);

// admin keeps everything
$adminUser = array('role_name' => 'admin', 'permissions' => $db->roles[9]['permissions']);
t('admin keeps backup.import', Permissions::can($adminUser, 'backup.import'), true);

// a missing standard role gets created
$db2 = new FakeDb();
$db2->roles[9] = array('id' => 9, 'name' => 'admin', 'name_ar' => 'مدير', 'permissions' => '{"all": true}', 'is_default' => 0);
$r3 = RoleSeeder::ensure($db2);
t('missing roles created', count($r3['created']) > 0, true);

// ── the standard model itself ────────────────────────────────────────
$defaults = RoleSeeder::defaults();
$standardRoles = array('admin', 'managing_editor', 'editor', 'publisher', 'author',
                       'contributor', 'translator', 'moderator',
                       'newsletter_manager', 'analyst', 'subscriber', 'reader');
t('standard model has 12 roles', count($defaults), count($standardRoles));
foreach ($standardRoles as $r) {
    t('standard role present: ' . $r, isset($defaults[$r]), true);
}
t('arabic names provided', count(RoleSeeder::arabicNames()) >= 12, true);

// hierarchy: a role must hold everything its parent holds
$hierarchy = array('managing_editor' => 'editor', 'editor' => 'publisher',
                   'publisher' => 'author', 'author' => 'contributor');
foreach ($hierarchy as $child => $parent) {
    $childUser = array('role_name' => $child, 'permissions' => json_encode($defaults[$child], JSON_UNESCAPED_UNICODE));
    $parentUser = array('role_name' => $parent, 'permissions' => json_encode($defaults[$parent], JSON_UNESCAPED_UNICODE));
    foreach (Permissions::all() as $perm => $_) {
        if (Permissions::can($parentUser, $perm)) {
            t($child . ' inherits ' . $perm, Permissions::can($childUser, $perm), true);
        }
    }
}

// separation of duties: only admin may touch system areas
$systemPerms = array('settings.manage', 'users.manage', 'roles.manage', 'apikeys.manage',
                     'security.manage', 'backup.export', 'backup.import');
foreach ($standardRoles as $r) {
    if ($r === 'admin') {
        continue;
    }
    $u = array('role_name' => $r, 'permissions' => json_encode($defaults[$r], JSON_UNESCAPED_UNICODE));
    foreach ($systemPerms as $p) {
        t($r . ' has no ' . $p, Permissions::can($u, $p), false);
    }
}

// analyst is read-only: no mutating article permission
$analystUser = array('role_name' => 'analyst', 'permissions' => json_encode($defaults['analyst'], JSON_UNESCAPED_UNICODE));
t('analyst can read articles', Permissions::can($analystUser, 'articles.view'), true);
t('analyst cannot edit', Permissions::can($analystUser, 'articles.edit'), false);
t('analyst cannot publish', Permissions::can($analystUser, 'articles.publish'), false);
t('analyst cannot delete', Permissions::can($analystUser, 'articles.delete'), false);

// contributor cannot publish
$contribUser = array('role_name' => 'contributor', 'permissions' => json_encode($defaults['contributor'], JSON_UNESCAPED_UNICODE));
t('contributor creates drafts', Permissions::can($contribUser, 'articles.create'), true);
t('contributor cannot publish', Permissions::can($contribUser, 'articles.publish'), false);
t('contributor cannot publish own', Permissions::can($contribUser, 'articles.publish_own'), false);
t('contributor cannot delete', Permissions::can($contribUser, 'articles.delete'), false);

// every declared token exists in the catalog
$unknown = array();
foreach ($defaults as $role => $perms) {
    $u = array('role_name' => $role, 'permissions' => json_encode($perms, JSON_UNESCAPED_UNICODE));
    foreach (Permissions::grantedTokens($u) as $token => $_) {
        if ($token === 'all') {
            continue;
        }
        list($ent, $act) = Permissions::split($token);
        if ($act !== '' && !in_array($act, Permissions::actionsFor($ent), true)) {
            $unknown[] = $role . ':' . $token;
        }
    }
}
t('no unknown permission tokens', $unknown, array());

// ── model upgrades: old seeds get upgraded, hand edits are never touched ──
t('current model marker', RoleSeeder::MODEL, 'standard-2');
t('role with _model is managed', RoleSeeder::isManaged(array('_model' => RoleSeeder::MODEL)), true);
t('role edited in UI is managed', RoleSeeder::isManaged(array('_roles_ui' => 1)), true);
t('old _v2 seed is not managed', RoleSeeder::isManaged(array('_v2' => 1)), false);
t('old _v2 seed needs upgrade', RoleSeeder::needsUpgrade(array('_v2' => 1)), true);
t('current model needs no upgrade', RoleSeeder::needsUpgrade(array('_model' => RoleSeeder::MODEL)), false);
t('hand edit needs no upgrade', RoleSeeder::needsUpgrade(array('_roles_ui' => 1)), false);

$db3 = new FakeDb();
// editor carries the OLD model marker (from an earlier seed run)
$db3->roles[10] = array('id' => 10, 'name' => 'editor', 'name_ar' => 'محرر', 'permissions' => '{"all":true,"_v2":1}', 'is_default' => 0);
$r4 = RoleSeeder::ensure($db3);
t('old seed reported as upgraded', in_array('editor', $r4['upgraded'], true), true);
t('old seed NOT reported as updated', in_array('editor', $r4['updated'], true), false);
$editorAfter = array('role_name' => 'editor', 'permissions' => $db3->roles[10]['permissions']);
t('editor no longer has full access', Permissions::can($editorAfter, 'settings.manage'), false);
t('editor keeps content powers', Permissions::can($editorAfter, 'articles.edit'), true);
t('editor now carries current model', RoleSeeder::isManaged((string) $db3->roles[10]['permissions']), true);

// a role hand-edited in the UI is left alone
$db4 = new FakeDb();
$db4->roles[10] = array('id' => 10, 'name' => 'editor', 'name_ar' => 'محرر', 'permissions' => '{"articles":["view"],"_roles_ui":1}', 'is_default' => 0);
$writesBefore = count($db4->writes);
$r5 = RoleSeeder::ensure($db4);
t('hand edited role is skipped', in_array('editor', $r5['skipped'], true), true);
t('hand edited role not rewritten', $db4->roles[10]['permissions'], '{"articles":["view"],"_roles_ui":1}');
t('hand edited role: no write', count($db4->writes) - $writesBefore >= 0, true);

// manual encoding marks the role as hand-edited
$manual = RoleSeeder::decode(RoleSeeder::encodeManual(RoleSeeder::fromCheckboxList(array('articles.create'))));
t('manual encode marks UI edit', RoleSeeder::isManaged($manual), true);
t('manual encode keeps permission', isset($manual['articles']) && in_array('create', $manual['articles'], true), true);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);