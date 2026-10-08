<?php
/**
 * تدقيق مؤقت: جدول الأدوار وصلاحياتها، وتوزيع المستخدمين، ومسارات لوحة التحكم.
 * محمي برمز ويُحذف بعد الاستخدام.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

if (!hash_equals('k7f2m9q4x8v3b6n1', (string) ($_GET['token'] ?? ''))) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = new Database();

    $rolesCols = [];
    foreach ($db->fetchAll('SHOW COLUMNS FROM roles') as $c) {
        $rolesCols[] = $c['Field'];
    }

    $roles = $db->fetchAll('SELECT * FROM roles ORDER BY id ASC');

    $usersByRole = $db->fetchAll(
        'SELECT role_id, COUNT(*) AS total,
                SUM(CASE WHEN status = "active" THEN 1 ELSE 0 END) AS active
         FROM users GROUP BY role_id ORDER BY role_id ASC'
    );

    $users = $db->fetchAll('SELECT id, username, email, role_id, status FROM users ORDER BY id ASC LIMIT 50');

    echo json_encode([
        'roles_columns'  => $rolesCols,
        'roles'          => $roles,
        'users_by_role'  => $usersByRole,
        'users'          => $users,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
