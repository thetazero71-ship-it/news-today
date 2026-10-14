<?php
/** فحص مؤقت: سجل إصلاح الصور وحجمها (يُحذف بعده) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
header('Content-Type: text/plain; charset=utf-8');

function dirSize(string $dir): array
{
    $bytes = 0;
    $files = 0;
    if (!is_dir($dir)) {
        return [0, 0];
    }
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $f) {
        if ($f->isFile()) {
            $bytes += $f->getSize();
            $files++;
        }
    }
    return [$bytes, $files];
}

function fmt($b): string
{
    if ($b >= 1048576) return round($b / 1048576, 2) . ' MB';
    if ($b >= 1024) return round($b / 1024, 1) . ' KB';
    return $b . ' B';
}

try {
    $db = new Database();

    echo "== حالة الصور في قاعدة البيانات ==\n";
    $empty = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image IS NULL OR featured_image = ''")['c'];
    $remote = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image LIKE 'http%'")['c'];
    $local = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image LIKE 'uploads/%'")['c'];
    $other = $db->fetch("SELECT COUNT(*) c FROM articles WHERE featured_image IS NOT NULL AND featured_image != '' AND featured_image NOT LIKE 'http%' AND featured_image NOT LIKE 'uploads/%'")['c'];
    echo "  empty : $empty\n  remote: $remote\n  local : $local\n  other : $other\n";

    echo "\n== حجم مجلد الصور على القرص ==\n";
    $root = defined('APP_ROOT') ? APP_ROOT : __DIR__;
    foreach (['uploads/articles', 'uploads/brand', 'uploads'] as $d) {
        [$b, $n] = dirSize($root . '/' . $d);
        echo "  $d : $n file(s), " . fmt($b) . "\n";
    }

    echo "\n== عينة من الصور المحلية ==\n";
    foreach ($db->fetchAll("SELECT id, featured_image FROM articles WHERE featured_image LIKE 'uploads/%' ORDER BY id DESC LIMIT 5") as $r) {
        $full = $root . '/' . $r['featured_image'];
        echo '  #' . $r['id'] . ' ' . $r['featured_image'] . ' (' . (is_file($full) ? fmt(filesize($full)) : 'MISSING!') . ")\n";
    }

    echo "\n== مساحة القرص ==\n";
    echo '  free: ' . fmt((float) @disk_free_space($root)) . "\n";
} catch (Throwable $e) {
    echo 'EXCEPTION: ' . $e->getMessage() . "\n";
}
echo "done\n";