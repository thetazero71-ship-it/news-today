<?php
/**
 * قراءة مؤقتة لقائمة مصادر RSS (اسم + رابط + القسم) لتوليد قواعد توجيه المصادر.
 * محمية برمز الوصول، وتُحذف بعد الاستخدام.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';

header('Content-Type: application/json; charset=utf-8');

$token = $_GET['token'] ?? '';
if (!hash_equals('k7f2m9q4x8v3b6n1', (string) $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $db = new Database();
    $rows = $db->fetchAll(
        'SELECT s.id, s.name, s.url, s.category_id, c.slug AS category_slug
         FROM rss_sources s
         LEFT JOIN categories c ON c.id = s.category_id
         ORDER BY s.name ASC'
    );
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int) $r['id'],
            'name' => (string) $r['name'],
            'url' => (string) $r['url'],
            'category_slug' => $r['category_slug'] ?? null,
        ];
    }
    echo json_encode(['count' => count($out), 'sources' => $out], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
