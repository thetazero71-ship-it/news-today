<?php
/**
 * Phase 1 verification tool (CLI only).
 *
 * Renders the permission matrix for the roles currently stored in the site, so
 * the shape of every role can be reviewed BEFORE enforcement is switched on.
 *
 * Usage:
 *   php tools/rbac_matrix.php <roles.json> [out.md]
 *
 * <roles.json> accepts either the raw dump from the DB
 * ({"roles":[{"name":"editor","permissions":"{...}"}]}) or a simple
 * {"editor": "{...}"} map.
 */

if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
require_once $root . '/config/rbac.php';
require_once $root . '/core/Permissions.php';

$inFile = $argv[1] ?? '';
$outFile = $argv[2] ?? ($root . '/storage/rbac_matrix.md');
if (!is_file($inFile)) {
    fwrite(STDERR, "missing roles json\n");
    exit(1);
}

$raw = json_decode(file_get_contents($inFile), true);
if (!is_array($raw)) {
    fwrite(STDERR, "invalid json\n");
    exit(1);
}

$roles = array();
if (isset($raw['roles']) && is_array($raw['roles'])) {
    foreach ($raw['roles'] as $r) {
        $name = (string) ($r['name'] ?? '');
        if ($name !== '') {
            $roles[$name] = (string) ($r['permissions'] ?? '');
        }
    }
} else {
    foreach ($raw as $name => $perms) {
        $roles[(string) $name] = is_string($perms) ? $perms : json_encode($perms, JSON_UNESCAPED_UNICODE);
    }
}

$catalog = Permissions::all();
$groups  = Permissions::groups();

$md = array();
$md[] = '# مصفوفة الصلاحيات — RBAC';
$md[] = '';
$md[] = 'مولّد آلياً: `tools/rbac_matrix.php` — ' . date('Y-m-d H:i');
$md[] = '';
$md[] = 'حالة الإلزام الآن: **' . (Permissions::enforced() ? 'مفعّل' : 'مطفأ (سلوك الموقع كما هو)') . '**';
$md[] = '';

foreach ($roles as $name => $permsJson) {
    $user = array('role_name' => $name, 'permissions' => $permsJson);
    $md[] = '## الدور: `' . $name . '`';
    $md[] = '';
    $md[] = '- الصلاحيات المخزّنة: `' . $permsJson . '`';
    $md[] = '- ملخص: ' . Permissions::describe($user);
    $md[] = '- توكيلات مُطبَّعة: ' . implode('، ', array_keys(Permissions::grantedTokens($user)) ?: array('—'));
    $md[] = '';
    $md[] = '| القسم | الصلاحية | الحالة |';
    $md[] = '|---|---|---|';
    foreach ($groups as $groupName => $entities) {
        foreach ($entities as $entity) {
            foreach (Permissions::actionsFor($entity) as $action) {
                $perm = $entity . '.' . $action;
                $ok = Permissions::can($user, $perm);
                $md[] = '| ' . $groupName . ' | `' . $perm . '` — ' . $catalog[$perm] . ' | ' . ($ok ? '✅' : '—') . ' |';
            }
        }
    }
    $md[] = '';
}

// Risk report: what an editor/admin currently reaches that a "normal" editor should not
$md[] = '## تقرير المخاطر';
$md[] = '';
$md[] = '| الدور | عدد الصلاحيات | مناطق حساسة يمتلكها |';
$md[] = '|---|---|---|';
foreach ($roles as $name => $permsJson) {
    $user = array('role_name' => $name, 'permissions' => $permsJson);
    $granted = 0;
    $sensitive = array();
    foreach (array_keys($catalog) as $perm) {
        if (Permissions::can($user, $perm)) {
            $granted++;
            if (strpos($perm, 'backup.') === 0 || strpos($perm, 'users.') === 0
                || strpos($perm, 'roles.') === 0 || strpos($perm, 'settings.') === 0
                || strpos($perm, 'apikeys.') === 0 || strpos($perm, 'security.') === 0) {
                $sensitive[] = $perm;
            }
        }
    }
    $md[] = '| `' . $name . '` | ' . $granted . '/' . count($catalog) . ' | '
        . (count($sensitive) ? implode('، ', $sensitive) : '—') . ' |';
}
$md[] = '';

$dir = dirname($outFile);
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}
file_put_contents($outFile, implode("\n", $md));

// ASCII summary for the terminal
foreach ($roles as $name => $permsJson) {
    $user = array('role_name' => $name, 'permissions' => $permsJson);
    $n = 0;
    foreach (array_keys($catalog) as $perm) {
        if (Permissions::can($user, $perm)) {
            $n++;
        }
    }
    echo str_pad($name, 14) . ' granted ' . $n . '/' . count($catalog) . "\n";
}
echo "written: " . $outFile . "\n";