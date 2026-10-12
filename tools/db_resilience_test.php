<?php
/**
 * Database resilience: a dropped connection must be re-established once so a
 * long external call (AI translation) cannot kill the auto-publish job.
 *
 * Run: php tools/db_resilience_test.php
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

$src = (string) file_get_contents(dirname(__DIR__) . '/core/Database.php');

echo "--- reconnect logic ---\n";
t('connection is built in its own method', strpos($src, 'private function connect()') !== false);
t('the constructor calls it', preg_match('/function __construct\(\)\s*\{\s*\$this->connect\(\)/', $src) === 1);
t('every query goes through the retrying runner', strpos($src, 'return $this->run($sql, $params);') !== false);
t('it retries exactly once', strpos($src, 'attempt === 0') !== false);
t('it reconnects before retrying', strpos($src, '$this->connect();') !== false);
t('it detects "server has gone away"', strpos($src, "'server has gone'") !== false || strpos($src, "'gone away'") !== false);
t('it detects a lost connection', strpos($src, "'lost connection'") !== false);
t('SQLSTATE 2006 is handled', strpos($src, "'2006'") !== false);
t('SQLSTATE 2013 is handled', strpos($src, "'2013'") !== false);
t('it does not swallow other errors', strpos($src, 'throw $e;') !== false);
t('fetchAll rides the same path', strpos($src, '$this->query($sql, $params)->fetchAll()') !== false);
t('the timezone is re-applied on reconnect', substr_count($src, "SET time_zone") === 1 && strpos($src, 'private function connect') !== false);

echo "\n--- the fetch job is scheduled ---\n";
$workflow = (string) file_get_contents(dirname(__DIR__) . '/.github/workflows/auto-fetch-news.yml');
t('a schedule exists', strpos($workflow, 'schedule:') !== false);
t('it runs every 5 minutes (fastest GitHub allows)', substr_count($workflow, "'*/5 * * * *'") === 1);
t('it can also be triggered by hand', strpos($workflow, 'workflow_dispatch:') !== false);
t('it carries the secret', strpos($workflow, 'CRON_SECRET') !== false);
t('it caps the sources per cycle', strpos($workflow, 'max_sources') !== false);
t('only one job runs at a time', strpos($workflow, 'concurrency:') !== false);

echo "\n--- run-now works without proc_open ---\n";
$ctrl = (string) file_get_contents(dirname(__DIR__) . '/controllers/admin/CronController.php');
t('a detached spawner exists', strpos($ctrl, 'function spawnDetached') !== false);
t('proc_open is only a fallback', strpos($ctrl, "if (function_exists('proc_open'))") !== false);
t('the HTTP path carries the secret', strpos($ctrl, 'cron/rss_auto_publish.php?secret=') !== false);
t('the HTTP path closes immediately', strpos($ctrl, "@fclose(\$fp)") !== false);
t('no unguarded proc_open remains', preg_match('/^\s*\$process = @proc_open/m', $ctrl) === 0);

echo "\n--- the cron script accepts remote limits ---\n";
$cron = (string) file_get_contents(dirname(__DIR__) . '/cron/rss_auto_publish.php');
t('max_sources parameter is read', strpos($cron, "max_sources") !== false);
t('it slices the source list', strpos($cron, 'array_slice($sources, 0, $maxSources)') !== false);
t('sources are ordered oldest-fetch first', strpos($cron, 'last_fetched_at ASC, id ASC') !== false);
t('a single cycle takes an exclusive lock', strpos($cron, 'LOCK_EX | LOCK_NB') !== false);
t('it stops on its time budget', strpos($cron, 'CRON_BUDGET_SECONDS') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);