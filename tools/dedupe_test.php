<?php
/**
 * Duplicate-story guard + favourites tab.
 * Run: php tools/dedupe_test.php
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

class FakeDb
{
    public $rows = array();

    public function __construct(array $rows = array())
    {
        $this->rows = $rows;
    }
    public function fetchAll($sql, array $p = array())
    {
        return $this->rows;
    }
}

require_once $root . '/core/NewsDeduplicator.php';

echo "--- title normalisation ---\n";
$n = function ($s) { return NewsDeduplicator::normalise($s); };
t('arabic letter variants collapse', $n('الأخبار') === $n('الاخبار'));
t('ta marbuta folds to ha', $n('رؤساء') === $n('رؤساة'));
t('alef maqsura folds to ya', $n('مصطفى') === $n('مصطفي'));
t('diacritics are ignored', $n('أَنبأ enrgy') !== '' && NewsDeduplicator::normalise('قَاضِي') === NewsDeduplicator::normalise('قاضي'));
t('tatweel is ignored', $n('الـLor est') !== '' && NewsDeduplicator::normalise('الاســلام') === NewsDeduplicator::normalise('الاسلام'));
t('arabic-indic digits fold', $n('خبر ٢٠٢٥') === $n('خبر 2025'));
t('punctuation is ignored', $n('خبر: عاجل!') === $n('خبر عاجل'));
t('case and spacing are ignored', $n('Apple   Event') === $n('apple event'));

echo "\n--- the same story from different feeds ---\n";
$db = new FakeDb(array(
    array('id' => 10, 'title' => 'Israel launches satellite into orbit', 'title_ar' => 'إطلاق satellit يبحر', 'slug' => 'a', 'source_name' => 'Haaretz', 'created_at' => '2026-01-01'),
));

$exact = NewsDeduplicator::findMatch($db, 'Israel launches satellite into orbit');
t('an identical headline is a duplicate', $exact !== null && $exact['id'] === 10);

$caseDiff = NewsDeduplicator::findMatch($db, 'ISRAEL LAUNCHES SATELLITE INTO ORBIT');
t('case differences are caught', $caseDiff !== null);

$reworded = NewsDeduplicator::findMatch($db, 'Israel launches new satellite into orbit today');
t('a reworded headline is caught', $reworded !== null);

$other = NewsDeduplicator::findMatch($db, 'European leaders meet about the economy');
t('a different story is not a duplicate', $other === null);

echo "\n--- it reports the match clearly ---\n";
t('it returns the stored article id', $exact['id'] === 10);
t('it names the existing source', $exact['source'] === 'Haaretz');
t('it gives a confidence score', isset($exact['score']) && $exact['score'] > 0 && $exact['score'] <= 100);

echo "\n--- safety rails ---\n";
t('very short titles are not judged', NewsDeduplicator::findMatch($db, 'AI') === null);
t('an empty database yields nothing', NewsDeduplicator::findMatch(new FakeDb(array()), 'Israel launches satellite into orbit') === null);
t('a database failure does not block publishing', NewsDeduplicator::findMatch(new class { public function fetchAll($s, $p = array()) { throw new RuntimeException('down'); } }, 'Israel launches satellite into orbit') === null);

echo "\n--- wired into both publishing paths ---\n";
$cron = (string) file_get_contents($root . '/cron/rss_auto_publish.php');
t('the scheduled job checks duplicates', strpos($cron, 'NewsDeduplicator::findMatch') !== false);
t('it skips instead of publishing', strpos($cron, 'مكرر من مصدر آخر') !== false);
t('it loads the class', strpos($cron, "require_once \$root . '/core/NewsDeduplicator.php'") !== false);

$agg = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('manual publishing checks too', strpos($agg, 'NewsDeduplicator::findMatch') !== false);
t('the editor is told which article matched', strpos($agg, "'duplicate' => true") !== false);
t('and can open it', strpos($agg, "'edit_url' => app_url('admin/articles/'") !== false);

echo "\n--- favourites live in the database ---\n";
$sources = (string) file_get_contents($root . '/controllers/admin/RssSourcesController.php');
t('a favorites column is ensured', strpos($sources, "'is_favorite' => 'TINYINT(1)") !== false);
t('a toggle action exists', strpos($sources, 'function toggleFavorite') !== false);

$routes = (string) file_get_contents($root . '/index.php');
t('the toggle route is registered', strpos($routes, 'toggle-favorite') !== false);

$aggView = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
t('the pin button posts to the server', strpos($aggView, 'pinForm_') !== false && strpos($aggView, 'toggle-favorite') !== false);
t('the favourites tab is a real link', strpos($aggView, 'admin/news-feeds?tab=favorites') !== false);
t('no dead client filter remains', strpos($aggView, "chip.style.display = show") === false);

t('a dedicated favourites view exists', is_file($root . '/views/admin/aggregator/favorites.php'));
$fav = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('it shows published items as published', strpos($fav, 'منشور') !== false);
t('it offers the edit link', strpos($fav, "'/edit'") !== false);
t('it explains an empty favourites tab', strpos($fav, 'لا يوجد مصدر في المفضلة') !== false);
t('feed failures are reported', strpos($fav, 'تعذّر جلب الخلاصة') !== false);
t('items per source is adjustable', strpos($fav, 'per=') !== false);

$agg2 = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
t('the controller builds the favourites page', strpos($agg2, 'function favoritesView') !== false);
t('it reads only favourite sources', strpos($agg2, 'WHERE s.is_favorite = 1') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);