<?php
/**
 * Guard correctness: a page that renders a view must never demand a CSRF
 * token on a plain GET, and every state-changing POST must validate one.
 *
 * Regression test for: «Invalid CSRF token.» when opening the AI advisor
 * conversations/quotas tab (AiLogsController used postGuardPermission on GET).
 *
 * Run: php tools/guard_test.php
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
        echo "FAIL $label" . ($extra !== '' ? "\n     " . $extra : '') . "\n";
    }
}

$root = dirname(__DIR__);

function methodsOf(string $src): array
{
    preg_match_all('/(?:public|protected|private)\s+function\s+(\w+)\s*\(/', $src, $m, PREG_OFFSET_CAPTURE);
    $out = [];
    $names = $m[1];
    for ($i = 0; $i < count($names); $i++) {
        $start = $names[$i][1];
        $end = isset($names[$i + 1]) ? $names[$i + 1][1] : strlen($src);
        $out[$names[$i][0]] = substr($src, $start, $end - $start);
    }
    return $out;
}

echo "--- no view-rendering action may call postGuardPermission ---\n";
// A POST-only endpoint (update/delete/store...) must validate CSRF even when
// it renders a 404 view. Only a GET-reachable page must never demand a token.
$routes = [];
foreach (explode("\n", (string) file_get_contents($root . '/index.php')) as $line) {
    if (preg_match('/\$router->(get|post)\(\s*\'([^\']+)\'\s*,\s*\'([A-Za-z0-9_\\\\]+)@(\w+)\'/', $line, $m)) {
        $routes[$m[3] . '@' . $m[4]][] = strtoupper($m[1]);
    }
}

$bad = [];
$checked = 0;
$php = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/controllers', FilesystemIterator::SKIP_DOTS));
foreach ($php as $file) {
    if (substr($file->getPathname(), -4) !== '.php') {
        continue;
    }
    if (preg_match('/[\/\\\\]([A-Za-z0-9_]+)\.php$/', $file->getPathname(), $cm) === 0) {
        continue;
    }
    $controller = $cm[1];
    $src = (string) file_get_contents($file->getPathname());
    foreach (methodsOf($src) as $name => $body) {
        if (strpos($body, 'postGuardPermission') === false) {
            continue;
        }
        $checked++;
        $renders = strpos($body, 'renderAdmin(') !== false || strpos($body, '->view(') !== false;
        if (!$renders) {
            continue; // pure POST/JSON handler: CSRF is required there
        }
        $verbs = $routes[$controller . '@' . $name] ?? [];
        if (!in_array('GET', $verbs, true)) {
            continue; // POST-only endpoint: CSRF is required there
        }
        // a GET-rendering method that also handles a POST branch is only valid
        // when the guard lives inside a REQUEST_METHOD === 'POST' branch
        $guardedPost = (bool) preg_match("/REQUEST_METHOD.*POST/s", $body);
        if (!$guardedPost) {
            $short = str_replace($root . '/controllers/', '', $file->getPathname());
            $bad[] = $short . '::' . $name;
        }
    }
}
t('no GET page demands a CSRF token', $bad === [], implode(', ', $bad));
echo "guard usages reviewed: $checked\n";

echo "\n--- the reported tab itself ---\n";
$ai = (string) file_get_contents($root . '/controllers/admin/AiLogsController.php');
$aiMethods = methodsOf($ai);
t('index() guards with the view permission', strpos($aiMethods['index'], "guardPermission('ai.logs')") !== false);
t('index() no longer calls postGuardPermission', strpos($aiMethods['index'], 'postGuardPermission') === false);
t('conversation() guards with the view permission', strpos($aiMethods['conversation'], "guardPermission('ai.logs')") !== false);
t('conversation() no longer calls postGuardPermission', strpos($aiMethods['conversation'], 'postGuardPermission') === false);

echo "\n--- the POST handlers stay protected (no over-correction) ---\n";
t('reset() still validates CSRF', strpos($aiMethods['reset'], 'CSRF::validate()') !== false);
t('boost() still validates CSRF', strpos($aiMethods['boost'], 'CSRF::validate()') !== false);

$cron = (string) file_get_contents($root . '/controllers/admin/CronController.php');
$cronMethods = methodsOf($cron);
t('cron index keeps CSRF on its POST branch', strpos($cronMethods['index'], 'postGuardPermission') !== false);
t('cron index still renders for GET viewers', strpos($cronMethods['index'], 'guardPermission') !== false);

echo "\n--- the failure matches the reported message ---\n";
// A GET carries no token: validate() must produce exactly the text the user saw.
// (the snippet runs from a temp file — Windows quoting breaks `php -r`).
$snippet = '<?php' . "\n"
    . 'require ' . var_export($root . '/core/Session.php', true) . ";\n"
    . 'require ' . var_export($root . '/core/CSRF.php', true) . ";\n"
    . 'Session::start(); Session::set(\'_csrf_token\', str_repeat(\'a\', 64));' . "\n"
    . '$_POST = array(); $_SERVER += array(\'HTTP_ACCEPT\' => \'text/html\');' . "\n"
    . 'CSRF::validate(); echo \'SHOULD NOT PRINT\';' . "\n";
$tmp = tempnam(sys_get_temp_dir(), 'guardcsrf');
file_put_contents($tmp, $snippet);
$descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];
$proc = proc_open(PHP_BINARY . ' ' . escapeshellarg($tmp), $descriptors, $pipes);
$out = stream_get_contents($pipes[1]);
fclose($pipes[0]);
fclose($pipes[1]);
fclose($pipes[2]);
proc_close($proc);
@unlink($tmp);
t('a tokenless request produces the reported text', trim($out) === 'Invalid CSRF token.', var_export($out, true));

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);