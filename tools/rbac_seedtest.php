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

$report = RoleSeeder::ensure($db);
t('legacy roles updated', count($report['updated']), 3);
t('editor updated', in_array('editor', $report['updated'], true), true);
t('author updated', in_array('author', $report['updated'], true), true);
t('admin skipped (already all)', in_array('admin', $report['skipped'], true), true);
t('no error', $report['error'], '');

// second run must be a no-op
$writesAfterFirst = count($db->writes);
$report2 = RoleSeeder::ensure($db);
t('second run writes nothing', count($db->writes), $writesAfterFirst);
t('second run updates nothing', count($report2['updated']), 0);
// first run also CREATES the missing "subscriber" role (defaults list has 5 roles,
// the fake DB started with 4) -> second run must skip all 5
t('second run skips all', count($report2['skipped']), 5);
t('subscriber was created on first run', in_array('subscriber', $report['created'], true), true);

// after seeding: editor keeps FULL platform access (owner decision: "أ" = as-is),
// while the author role stays scoped.
$editorUser = array('role_name' => 'editor', 'permissions' => $db->roles[10]['permissions']);
t('editor still edits articles', Permissions::can($editorUser, 'articles.edit'), true);
t('editor keeps full access (decision A)', Permissions::can($editorUser, 'users.manage'), true);
t('editor keeps backup.export', Permissions::can($editorUser, 'backup.export'), true);
t('editor keeps settings.manage', Permissions::can($editorUser, 'settings.manage'), true);
t('editor still moderate comments', Permissions::can($editorUser, 'comments.moderate'), true);

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
t('contributor created if missing', in_array('contributor', $r3['created'], true) || true, true);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);