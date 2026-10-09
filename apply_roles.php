<?php
/**
 * صيانة مؤقتة للأدوار: يطبّق النموذج المعياري ثم يعيد تعيين دور محرر
 * إلى managing_editor (المدير التحريري). محمي برمز ويُحذف بعد التنفيذ.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Permissions.php';
require_once __DIR__ . '/core/RoleSeeder.php';

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('m4x8v3b6n1k7f2q', (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

$report = ['steps' => [], 'roles' => [], 'users' => [], 'error' => ''];

try {
    $db = new Database();

    // 1) apply the standard role model (creates missing roles, fills permissions)
    $seed = RoleSeeder::ensure($db);
    $report['steps'][] = 'seed: created=' . implode(',', $seed['created'])
        . ' | updated=' . implode(',', $seed['updated'])
        . ' | skipped=' . implode(',', $seed['skipped']);
    if ($seed['error'] !== '') {
        $report['error'] = $seed['error'];
    }

    // 2) move the former "editor" account to managing_editor (content lead)
    $target = $db->fetch("SELECT id FROM roles WHERE name = 'managing_editor' LIMIT 1");
    $oldEditor = $db->fetch("SELECT id FROM roles WHERE name = 'editor' LIMIT 1");
    if ($target) {
        $moved = $db->fetchAll(
            "SELECT id, username, email FROM users WHERE role_id = :rid AND status = 'active'",
            [':rid' => (int) $oldEditor['id']]
        );
        foreach ($moved as $u) {
            $db->query('UPDATE users SET role_id = :new WHERE id = :id', [
                ':new' => (int) $target['id'],
                ':id'  => (int) $u['id'],
            ]);
            $report['steps'][] = 'role change: ' . $u['username'] . ' (' . $u['email']
                . ') editor(id ' . $oldEditor['id'] . ') -> managing_editor(id ' . $target['id'] . ')';
        }
        if (!$moved) {
            $report['steps'][] = 'no active editor accounts to move';
        }
    } else {
        $report['error'] = 'managing_editor role was not created';
    }

    // 3) final state
    foreach ($db->fetchAll('SELECT id, name, name_ar, permissions FROM roles ORDER BY id ASC') as $r) {
        $user = ['role_name' => $r['name'], 'permissions' => $r['permissions']];
        $tokens = Permissions::grantedTokens($user);
        $granted = 0;
        foreach (array_keys(Permissions::all()) as $perm) {
            if (Permissions::can($user, $perm)) {
                $granted++;
            }
        }
        $report['roles'][] = [
            'id'       => (int) $r['id'],
            'name'     => $r['name'],
            'name_ar'  => $r['name_ar'],
            'granted'  => $granted,
            'total'    => count(Permissions::all()),
            'managed'  => RoleSeeder::isManaged((string) $r['permissions']),
        ];
    }

    foreach ($db->fetchAll(
        'SELECT u.id, u.username, u.email, u.status, r.name AS role_name
         FROM users u LEFT JOIN roles r ON r.id = u.role_id ORDER BY u.id ASC'
    ) as $u) {
        $report['users'][] = [
            'id'     => (int) $u['id'],
            'name'   => $u['username'],
            'email'  => $u['email'],
            'status' => $u['status'],
            'role'   => $u['role_name'],
        ];
    }
} catch (Throwable $e) {
    $report['error'] = $e->getMessage();
}

echo json_encode($report, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);