<?php
/** فحص مؤقت: تسجيل الصور المحلية الحالية في مكتبة الوسائط (يُحذف بعده) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/ImageStore.php';
header('Content-Type: text/plain; charset=utf-8');

try {
    $db = new Database();
    $before = (int) $db->fetch('SELECT COUNT(*) c FROM media')['c'];
    echo "media rows before: $before\n";

    $rows = $db->fetchAll("SELECT featured_image FROM articles WHERE featured_image LIKE 'uploads/%'");
    echo 'local images: ' . count($rows) . "\n";

    $ok = 0;
    $missing = 0;
    $root = defined('APP_ROOT') ? APP_ROOT : __DIR__;
    foreach ($rows as $r) {
        $rel = ltrim((string) $r['featured_image'], '/');
        if (!is_file($root . '/' . $rel)) {
            $missing++;
            continue;
        }
        if (ImageStore::registerInLibrary($rel)) {
            $ok++;
        }
    }
    $after = (int) $db->fetch('SELECT COUNT(*) c FROM media')['c'];
    echo "registered ok: $ok, files missing on disk: $missing\n";
    echo "media rows after: $after (added " . ($after - $before) . ")\n";

    echo "\nsample rows:\n";
    foreach ($db->fetchAll("SELECT file_path, mime_type, file_size, folder FROM media WHERE folder = 'articles' ORDER BY id DESC LIMIT 3") as $m) {
        echo '  ' . $m['file_path'] . ' | ' . $m['mime_type'] . ' | ' . $m['file_size'] . " B\n";
    }
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n";
}
echo "done\n";