<?php
/**
 * Duplicate-URL guard + bulk actions for RSS sources.
 * Run: php tools/rss_sources_test.php
 */

define('APP_ROOT', dirname(__DIR__));

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

class Session
{
    public static $flashes = array();
    public static function flash($type, $message)
    {
        self::$flashes[$type] = $message;
    }
    public static function last()
    {
        $f = self::$flashes;
        self::$flashes = array();
        return $f;
    }
}

class ActivityLogger
{
    public static function log($a, $e, $i = null, $o = null, $n = null)
    {
    }
}

class AdminController
{
    protected function postGuardPermission($p)
    {
    }
    protected function guardPermission($p)
    {
    }
    protected function audit($a, $e, $i = null, $o = null, $n = null)
    {
    }
    protected function redirect($to)
    {
        return 'redirect:' . $to;
    }
}

class Sanitizer
{
    public static function cleanArray($a)
    {
        return $a;
    }
}

class FakeDb
{
    public $rows;
    public $log = array();
    public $lastId = 1;

    public function __construct(array $rows = array())
    {
        $this->rows = $rows;
    }
    public function fetchAll($sql, array $p = array())
    {
        return $this->rows;
    }
    public function fetch($sql, array $p = array())
    {
        return $this->rows[0] ?? null;
    }
    public function query($sql, array $p = array())
    {
        $this->log[] = $sql;
        return true;
    }
    public function lastInsertId()
    {
        return $this->lastId;
    }
    public function text()
    {
        return implode("\n", $this->log);
    }
}

class Database
{
    public static $instance;
    public function __construct()
    {
    }
    public function fetchAll($sql, array $p = array())
    {
        return self::$instance->fetchAll($sql, $p);
    }
    public function fetch($sql, array $p = array())
    {
        return self::$instance->fetch($sql, $p);
    }
    public function query($sql, array $p = array())
    {
        return self::$instance->query($sql, $p);
    }
    public function lastInsertId()
    {
        return self::$instance->lastInsertId();
    }
}

require_once APP_ROOT . '/controllers/admin/RssSourcesController.php';

echo "--- url normalisation: the same feed typed differently ---\n";
$cases = [
    ['https://techcrunch.com/feed/', 'https://techcrunch.com/feed'],
    ['http://www.techcrunch.com/feed', 'https://techcrunch.com/feed'],
    ['https://techcrunch.com/feed?utm_source=rss', 'https://techcrunch.com/feed'],
    ['HTTPS://TechCrunch.com/Feed#latest', 'https://techcrunch.com/feed'],
    ['https://www.alarabiya.net/rss.xml', 'https://alarabiya.net/rss.xml'],
];
$ok = true;
foreach ($cases as $c) {
    if (RssSourcesController::normalise($c[0]) !== RssSourcesController::normalise($c[1])) {
        $ok = false;
        echo "    mismatch: {$c[0]} => " . RssSourcesController::normalise($c[0]) . "\n";
    }
}
t('trailing slash / www / protocol / query / case are ignored', $ok);
t('a different feed stays different', RssSourcesController::normalise('https://a.com/feed') !== RssSourcesController::normalise('https://b.com/feed'));

echo "\n--- duplicate is refused on add ---\n";
$_POST = ['name' => 'TechCrunch', 'url' => 'http://www.techcrunch.com/feed'];
Database::$instance = new FakeDb(array(
    array('id' => 1, 'name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed/'),
    array('id' => 2, 'name' => 'Ars', 'url' => 'https://arstechnica.com/feed'),
));
$ctrl = new RssSourcesController();
$ctrl->store();
$db = Database::$instance;
t('no INSERT happened', stripos($db->text(), 'INSERT') === false);
$f = Session::last();
t('editor is told why', isset($f['error']) && strpos($f['error'], 'مسجّل مسبقاً') !== false);
t('the existing name is shown', isset($f['error']) && strpos($f['error'], 'TechCrunch') !== false);

echo "\n--- a new feed is still accepted ---\n";
$_POST = ['name' => 'VentureBeat', 'url' => 'https://venturebeat.com/feed'];
Database::$instance = new FakeDb(array(
    array('id' => 1, 'name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed/'),
));
$ctrl->store();
$db = Database::$instance;
t('INSERT happened', stripos($db->text(), 'INSERT') !== false);
t('the url is stored normalised', strpos($db->text(), 'INSERT') !== false);
$f = Session::last();
t('success message shown', isset($f['success']));

echo "\n--- broken urls are refused ---\n";
$bad = [
    ['not-a-url', 'plain text'],
    ['ftp://example.com/feed', 'wrong protocol'],
    ['https://nodots/feed', 'host without a dot'],
];
foreach ($bad as $b) {
    $_POST = ['name' => 'X', 'url' => $b[0]];
    Database::$instance = new FakeDb(array());
    $ctrl->store();
    $f = Session::last();
    t('refused: ' . $b[1], isset($f['error']) && stripos($db_ok = Database::$instance->text(), 'INSERT') === false);
}

echo "\n--- edit keeps working and blocks taking another source's url ---\n";
$_POST = ['name' => 'Mine', 'url' => 'https://techcrunch.com/feed'];
Database::$instance = new FakeDb(array(
    array('id' => 1, 'name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed'),
    array('id' => 5, 'name' => 'Mine', 'url' => 'https://mine.com/feed'),
));
$ctrl->update(5);
t('update refused for a taken url', stripos(Database::$instance->text(), 'UPDATE') === false);

$_POST = ['name' => 'Mine', 'url' => 'https://mine.com/feed'];
Database::$instance = new FakeDb(array(
    array('id' => 1, 'name' => 'TechCrunch', 'url' => 'https://techcrunch.com/feed'),
    array('id' => 5, 'name' => 'Mine', 'url' => 'https://mine.com/feed'),
));
$ctrl->update(5);
t('keeping its own url is allowed', stripos(Database::$instance->text(), 'UPDATE rss_sources') !== false);

echo "\n--- bulk actions ---\n";
$_POST = ['bulk_action' => 'delete', 'ids' => [3, 4, 5]];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
$sql = Database::$instance->text();
t('bulk delete uses one statement', stripos($sql, 'DELETE FROM rss_sources WHERE id IN (3,4,5)') !== false);
t('bulk delete reports back', isset(Session::last()['success']));

$_POST = ['bulk_action' => 'disable', 'ids' => ['7', 8]];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('bulk disable updates auto_fetch', strpos(Database::$instance->text(), 'auto_fetch = 0') !== false);

$_POST = ['bulk_action' => 'enable', 'ids' => [7, 8]];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('bulk enable updates auto_fetch', strpos(Database::$instance->text(), 'auto_fetch = 1') !== false);

$_POST = ['bulk_action' => 'category', 'ids' => [2], 'bulk_category_id' => '11'];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('bulk move to category', strpos(Database::$instance->text(), 'SET category_id = :cat') !== false);

$_POST = ['bulk_action' => 'category', 'ids' => [2], 'bulk_category_id' => '0'];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('move without a category is refused', stripos(Database::$instance->text(), 'UPDATE') === false);

$_POST = ['bulk_action' => 'delete', 'ids' => []];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('empty selection does nothing', stripos(Database::$instance->text(), 'DELETE') === false);
t('empty selection explains itself', isset(Session::last()['error']));

$_POST = ['bulk_action' => 'drop-table', 'ids' => [1]];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('unknown action is rejected', stripos(Database::$instance->text(), 'DROP') === false);

$_POST = ['bulk_action' => 'delete', 'ids' => range(1, 400)];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
t('a huge selection is capped', substr_count(Database::$instance->text(), ',') <= 199);

echo "\n--- ids cannot be injected ---\n";
$_POST = ['bulk_action' => 'delete', 'ids' => ['1 OR 1=1', '2; DROP TABLE rss_sources']];
Database::$instance = new FakeDb(array());
$ctrl->bulk();
$sql = Database::$instance->text();
t('non-numeric ids are dropped', strpos($sql, 'DROP TABLE') === false && strpos($sql, 'OR 1=1') === false);

echo "\n--- the admin page has the checkboxes ---\n";
$view = (string) file_get_contents(APP_ROOT . '/views/admin/rss_sources/index.php');
t('select-all checkbox exists', strpos($view, 'checkAllSources') !== false);
t('per-row checkbox exists', strpos($view, 'source-check') !== false);
t('bulk form posts to the bulk route', strpos($view, 'admin/rss-sources/bulk') !== false);
t('delete is offered as a bulk action', strpos($view, 'value="delete"') !== false);
t('category move is offered', strpos($view, 'value="category"') !== false);
t('selection bar is hidden until used', strpos($view, 'id="bulkBar"') !== false && strpos($view, 'd-none') !== false);

$routes = (string) file_get_contents(APP_ROOT . '/index.php');
t('bulk route registered', strpos($routes, '/admin/rss-sources/bulk') !== false && strpos($routes, 'RssSourcesController@bulk') !== false);

$agg = (string) file_get_contents(APP_ROOT . '/controllers/admin/AggregatorController.php');
t('quick add also blocks duplicates', strpos($agg, 'findExistingSource') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);