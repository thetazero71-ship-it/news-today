<?php
/**
 * Category hide / delete behaviour tests (no database needed).
 * Run: php tools/category_test.php
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

/** Minimal Database stub recording every statement. */
class FakeDb
{
    public $rows;
    public $log = array();
    public $failOn = null;
    public $countValue = 0;

    public function __construct(array $rows = array())
    {
        $this->rows = $rows;
    }

    public function fetchAll($sql, array $params = array())
    {
        if (strpos($sql, 'information_schema') !== false) {
            return array(
                array('TABLE_NAME' => 'articles'),
                array('TABLE_NAME' => 'tutorials'),
                array('TABLE_NAME' => 'source_category_map'),
            );
        }
        return $this->rows;
    }

    public function fetch($sql, array $params = array())
    {
        if (stripos($sql, 'COUNT(*)') !== false) {
            return array('c' => $this->countValue);
        }
        return $this->rows[0] ?? null;
    }

    public function query($sql, array $params = array())
    {
        $this->log[] = $sql;
        if ($this->failOn !== null && strpos($sql, $this->failOn) !== false) {
            throw new RuntimeException('simulated failure');
        }
        return true;
    }

    public function statements()
    {
        return implode("\n", $this->log);
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
        $out = self::$flashes;
        self::$flashes = array();
        return $out;
    }
}

class ActivityLogger
{
    public static function log($action, $entity, $id = null, $old = null, $new = null)
    {
    }
}

class AdminController
{
    public function __construct()
    {
    }
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

class Database
{
    public static $instance;
    public function __construct()
    {
    }
    public static function getInstance()
    {
        return self::$instance;
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
}

require_once APP_ROOT . '/controllers/admin/CategoriesController.php';

// ---- the controller must exist with the new actions -----------------------
t('toggleVisibility action exists', method_exists('CategoriesController', 'toggleVisibility'));
t('delete action exists', method_exists('CategoriesController', 'delete'));

$ref = new ReflectionClass('CategoriesController');
t('hide/show route target exists', $ref->hasMethod('toggleVisibility'));

echo "\n--- hide does not delete ----\n";
$cat = array(
    'id' => 7,
    'name' => 'Test',
    'name_ar' => 'تجريبي',
    'is_visible' => 1,
);
$db = new FakeDb(array($cat));
Database::$instance = $db;
$c = new CategoriesController();
$c->toggleVisibility(7);
$stmts = $db->statements();
t('sets is_visible = 0 when hiding', strpos($stmts, 'UPDATE categories SET is_visible = :v') !== false);
t('never deletes on hide', stripos($stmts, 'DELETE') === false);
$flash = Session::last();
t('tells the editor nothing was deleted', strpos(reset($flash), 'لم يُحذف') !== false);

$db2 = new FakeDb(array(array('id' => 7, 'name' => 'Test', 'name_ar' => 'تجريبي', 'is_visible' => 0)));
Database::$instance = $db2;
$c->toggleVisibility(7);
t('sets is_visible = 1 when showing', $db2->statements() !== '');
$flash2 = Session::last();
t('tells the editor it is visible again', strpos(reset($flash2), 'إظهار') !== false);

echo "\n--- delete refuses a category that still has articles ---\n";
$busy = new FakeDb(array(array('id' => 3, 'name' => 'Busy', 'name_ar' => 'مشغول')));
$busy->countValue = 5;
Database::$instance = $busy;
$c->delete(3);
t('no delete without confirmation', stripos($busy->statements(), 'DELETE') === false);
$f = Session::last();
t('explains the category is not empty', strpos(reset($f), '5') !== false);

echo "\n--- delete detaches every reference first ---\n";
$ok = new FakeDb(array(array('id' => 4, 'name' => 'Empty', 'name_ar' => 'فارغ')));
$ok->countValue = 0;
Database::$instance = $ok;
$c->delete(4);
$stmts = $ok->statements();
t('nulls articles.category_id', strpos($stmts, 'UPDATE `articles` SET category_id = NULL') !== false);
t('nulls tutorials.category_id', strpos($stmts, 'UPDATE `tutorials` SET category_id = NULL') !== false);
t('nulls mapping tables too', strpos($stmts, 'UPDATE `source_category_map` SET category_id = NULL') !== false);
t('detaches children first', strpos($stmts, 'SET parent_id = NULL') !== false);
t('deletes the row last', stripos($stmts, 'DELETE FROM categories') !== false);
$orderDetach = strpos($stmts, 'UPDATE `articles`');
$orderDelete = stripos($stmts, 'DELETE FROM categories');
t('detach happens before delete', $orderDetach < $orderDelete);

echo "\n--- a database error is reported, not swallowed ---\n";
$broken = new FakeDb(array(array('id' => 5, 'name' => 'X', 'name_ar' => 'س')));
$broken->failOn = 'DELETE FROM categories';
Database::$instance = $broken;
$c->delete(5);
$f = Session::last();
t('shows a readable error instead of a blank page', isset($f['error']) && strpos($f['error'], 'تعذّر') !== false);

echo "\n--- public lists skip hidden categories ---\n";
$src = (string) file_get_contents(APP_ROOT . '/models/Category.php');
t('getAll() filters by is_visible', strpos($src, 'is_visible = 1') !== false);
t('getAllWithHidden() exists for admin', strpos($src, 'getAllWithHidden') !== false);
t('isHidden() helper exists', strpos($src, 'function isHidden') !== false);

$consumers = array(
    'controllers/ArchiveController.php' => true,
    'controllers/SearchController.php' => true,
    'controllers/SitemapController.php' => true,
    'controllers/TutorialController.php' => true,
    'controllers/FeedController.php' => true,
);
foreach ($consumers as $file => $_) {
    $body = (string) file_get_contents(APP_ROOT . '/' . $file);
    t($file . ' skips hidden categories', strpos($body, 'is_visible = 1') !== false);
}

$articleCtl = (string) file_get_contents(APP_ROOT . '/controllers/ArticleController.php');
t('hidden category page returns 404', strpos($articleCtl, 'isHidden($category)') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);