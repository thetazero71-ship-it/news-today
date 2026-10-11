<?php
/**
 * Structural check: every inline JS block in a view must sit inside a script
 * tag. A block that leaks outside the tag is rendered as plain text and never
 * runs — that is exactly how the bulk-selection bar stopped working once.
 *
 * Run: php tools/view_script_test.php [view.php ...]
 */

$pass = 0;
$fail = 0;

function t($label, $cond)
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label\n";
    }
}

$root = dirname(__DIR__);
$files = array_slice($argv, 1);
if (!$files) {
    $files = array_merge(
        (array) glob($root . '/views/admin/*/*.php'),
        (array) glob($root . '/views/admin/*.php')
    );
}

echo "--- inline script blocks are well formed --\n";

$closeTag = '</' . 'script>';

foreach ($files as $file) {
    $full = strpos($file, '/') === 0 || preg_match('/^[A-Za-z]:/', $file) ? $file : $root . '/' . $file;
    if (!is_file($full)) {
        continue;
    }
    $name = str_replace('\\', '/', str_replace($root . '/', '', $full));
    $src = (string) file_get_contents($full);

    $open = preg_match_all('/<' . 'script\b/i', $src);
    $close = preg_match_all('/<\/' . 'script>/i', $src);

    t($name . ': balanced script tags', $open === $close);

    if ($open !== $close) {
        continue;
    }

    // remove every <script>...</script> block; the rest is template markup
    $stripped = preg_replace('/<' . 'script\b[^>]*>.*?<\/' . 'script>/is', '', $src);

    // an IIFE sitting outside a script tag is plain text, never executed
    $outside = preg_match('/^\s*\(function\s*\(/m', $stripped);
    t($name . ': no javascript leaking outside a script tag', !$outside);

    if ($outside) {
        foreach (explode("\n", (string) $stripped) as $n => $line) {
            if (preg_match('/^\s*\(function\s*\(/', $line)) {
                echo "    leaked at line " . ($n + 1) . ': ' . trim($line) . "\n";
            }
        }
    }
}

echo "\n--- the bulk selection wiring ---\n";

$view = (string) file_get_contents($root . '/views/admin/rss_sources/index.php');
t('selection script sits inside a script tag', (bool) preg_match('/<' . 'script>.*source-check.*<\/' . 'script>/s', $view));
t('select-all handler is bound', strpos($view, "checkAll.addEventListener('change'") !== false);
t('per-row boxes update the bar', strpos($view, "b.addEventListener('change', refresh)") !== false);
t('bar visibility is toggled', strpos($view, "bar.classList.toggle('d-none', n === 0)") !== false);
t('selection is sent as ids[]', strpos($view, "hidden.name = 'ids[]'") !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);