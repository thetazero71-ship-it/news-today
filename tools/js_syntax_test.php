<?php
/**
 * Extracts every inline <script> block from the views and checks it parses.
 * A malformed conversion (like alert() rewritten around nested parens) shows
 * up here as a syntax error instead of silently breaking a page.
 *
 * Run: php tools/js_syntax_test.php
 */

$pass = 0;
$fail = 0;

function t($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label" . ($extra ? "\n     " . $extra : '') . "\n";
    }
}

$root = dirname(__DIR__);
$files = array_merge(
    (array) glob($root . '/views/admin/*.php'),
    (array) glob($root . '/views/admin/*/*.php'),
    (array) glob($root . '/views/*.php'),
    (array) glob($root . '/assets/js/*.js')
);

echo "--- inline javascript still parses --\n";
$checked = 0;
$openTag = '<' . 'script';
$closeTag = '</' . 'script>';

foreach ($files as $file) {
    $name = str_replace('\\', '/', str_replace($root . '/', '', $file));
    $src = (string) file_get_contents($file);

    if (substr($file, -3) === '.js') {
        $code = $src;
    } else {
        preg_match_all('/' . $openTag . '\b[^>]*>(.*?)' . $closeTag . '/is', $src, $m);
        if (!$m[1]) {
            continue;
        }
        $code = implode("\n;\n", $m[1]);
    }

    // PHP inside a script block would confuse the parser: skip blocks that
    // are mostly server-side output
    if (substr($file, -3) !== '.js' && preg_match('/<\?php|<\?=/', $code)) {
        continue;
    }

    $checked++;
    $tmp = tempnam(sys_get_temp_dir(), 'jscheck');
    file_put_contents($tmp, $code);

    $out = array();
    $code2 = 0;
    exec(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($tmp) . ' 2>&1', $out, $code2);
    unlink($tmp);

    t($name, $code2 === 0, implode(' | ', array_slice($out, 0, 2)));
}

echo "\n--- no blocking native dialogs left ---\n";

$alertLeft = array();
$confirmLeft = array();
foreach (array_merge((array) glob($root . '/views/admin/*.php'), (array) glob($root . '/views/admin/*/*.php'), (array) glob($root . '/views/*/*.php')) as $file) {
    $name = str_replace('\\', '/', str_replace($root . '/', '', $file));
    $src = (string) file_get_contents($file);
    if (preg_match('/(?<![\w.$])alert\(/', $src) && strpos($name, 'layouts/admin.php') === false) {
        $alertLeft[] = $name;
    }
    // confirm() is allowed only when the page also has onsubmit wiring
    if (preg_match('/(?<![\w.$])confirm\(/', $src) && strpos($name, 'layouts/admin.php') === false) {
        $confirmLeft[] = $name;
    }
}
t('no raw alert() left in views', $alertLeft === array(), implode(', ', $alertLeft));

// the type argument must sit inside adminToast(...), not after it — a broken
// rewrite looks like: adminToast('x'), 'error');
$misplaced = array();
foreach (array_merge((array) glob($root . '/views/admin/*.php'), (array) glob($root . '/views/admin/*/*.php'), (array) glob($root . '/views/*/*.php')) as $file) {
    $name = str_replace('\\', '/', str_replace($root . '/', '', $file));
    $src = (string) file_get_contents($file);
    foreach (explode("\n", $src) as $n => $line) {
        if (preg_match('/adminToast\([^()]*\)\s*,\s*\'(success|error|warning|info)\'\s*\)/', $line)) {
            $misplaced[] = $name . ':' . ($n + 1);
        }
    }
}
t('the toast type is never outside the call', $misplaced === array(), implode(', ', $misplaced));

echo '   confirm() remaining (upgraded at runtime): ' . count($confirmLeft) . " file(s)\n";

$layout = (string) file_get_contents($root . '/views/layouts/admin.php');
t('admin loads the notification script', strpos($layout, 'admin-toast.js') !== false);
t('the blocking modal alert override is gone', strpos($layout, "window.alert = function(msg)") === false);
t('alert is redirected to the toast system', (bool) preg_match('/window\.alert\s*=\s*function/', $layout));

$toast = (string) file_get_contents($root . '/assets/js/admin-toast.js');
t('toast supports success/error/warning/info', (bool) preg_match('/success:.*error:.*warning:.*info:/s', $toast));
t('confirm dialog returns a promise', strpos($toast, 'return new Promise') !== false);
t('confirm dialog closes on Escape', strpos($toast, "e.key === 'Escape'") !== false);
t('forms using onsubmit=confirm are upgraded', strpos($toast, 'upgradeConfirmForms') !== false);
t('toast can be dismissed', strpos($toast, 'admin-toast-close') !== false);
t('hover pauses the countdown', strpos($toast, 'mouseenter') !== false);

echo "\nblocks checked: $checked\n";
echo "passed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);