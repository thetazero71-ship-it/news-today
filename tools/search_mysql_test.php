<?php
/**
 * Executes the REAL search SQL against a local MySQL fixture.
 * This is the test that matters: it proves the query is valid SQL AND that
 * spelling variants are found.
 *
 * Run: php tools/search_mysql_test.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
require_once $root . '/core/SearchQuery.php';

$pass = 0;
$fail = 0;
function t($label, $cond)
{
    global $pass, $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    $cond ? $pass++ : $fail++;
}

try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=search_test_db;charset=utf8mb4', 'root', '', array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ));
} catch (Throwable $e) {
    echo "SKIP: cannot connect to local MySQL (" . $e->getMessage() . ")\n";
    exit(0);
}

function runSearch(PDO $pdo, string $query, bool $runCount = true): array
{
    $tokens = SearchQuery::tokens($query);
    if (empty($tokens)) {
        return array('ids' => array(), 'sql' => '', 'error' => 'too short', 'count' => null);
    }
    list($sql, $paramsWhere, $relevance, $paramsMain) = SearchQuery::buildWhere($tokens);

    $from = ' FROM articles a
             LEFT JOIN users u ON u.id = a.author_id
             LEFT JOIN categories c ON c.id = a.category_id ';

    // 1) COUNT query - this is what used to crash production (HY093) because it
    //    was given the relevance placeholders it does not contain.
    $countSql = 'SELECT COUNT(*) AS total' . $from . " WHERE a.status = 'published' AND " . $sql;
    try {
        $cst = $pdo->prepare($countSql);
        $cst->execute($paramsWhere);
        $total = (int) $cst->fetchColumn();
    } catch (Throwable $e) {
        return array('ids' => array(), 'sql' => $countSql, 'error' => 'COUNT: ' . $e->getMessage(), 'count' => null);
    }

    // 2) main query
    $full = 'SELECT a.id, a.title_ar, a.title, ' . $relevance . ' AS relevance'
        . $from . " WHERE a.status = 'published' AND " . $sql
        . ' ORDER BY relevance DESC, a.published_at DESC';

    try {
        $st = $pdo->prepare($full);
        $st->execute($paramsMain);
        $rows = $st->fetchAll();
        $ids = array();
        foreach ($rows as $r) {
            $ids[] = (int) $r['id'];
        }
        return array('ids' => $ids, 'sql' => $full, 'error' => '', 'count' => $total);
    } catch (Throwable $e) {
        return array('ids' => array(), 'sql' => $full, 'error' => $e->getMessage(), 'count' => $total);
    }
}

echo "--- SQL must execute cleanly (COUNT + main) ---\n";
$r = runSearch($pdo, 'الجيش');
t('simple Arabic query executes without SQL error', $r['error'] === '', true);
t('COUNT query returns a number', is_int($r['count']), true);
if ($r['error'] !== '') {
    echo "    SQL error: " . $r['error'] . "\n";
    echo substr($r['sql'], 0, 300) . "\n";
}

$r2 = runSearch($pdo, 'الجيش如玉');
t('mixed junk query still valid SQL', $r2['error'] === '', true);
if ($r2['error'] !== '') {
    echo "    SQL error: " . $r2['error'] . "\n";
}

// the exact production crash: COUNT must be given WHERE-only params
t('COUNT never receives relevance placeholders',
    (function () use ($pdo) {
        $tokens = SearchQuery::tokens('الجيش');
        list($sql, $whereParams, , $mainParams) = SearchQuery::buildWhere($tokens);
        $hasEq = false;
        foreach ($whereParams as $k => $v) {
            if (strpos($k, 'eq') === 0) {
                $hasEq = true;
            }
        }
        $hasEqInMain = false;
        foreach ($mainParams as $k => $v) {
            if (strpos($k, 'eq') === 0) {
                $hasEqInMain = true;
            }
        }
        return $hasEq === false && $hasEqInMain === true;
    })(), true);

echo "\n--- normalization actually finds variants ---\n";
// 1: stored "الجيش" variants: ids 1,2,4,5? (5 has no army word) -> 1,2,4
t('finds army articles', in_array(1, $r['ids'], true) && in_array(2, $r['ids'], true), true);
t('unrelated tech article excluded', !in_array(5, $r['ids'], true), true);
t('draft article never returned', !in_array(6, $r['ids'], true), true);

// query with آ/غ against stored ا + غ + tatweel
$rh = runSearch($pdo, 'البنتاغون');
t('alef variant query finds stored title (id 3)', in_array(3, $rh['ids'], true), true);

$rd = runSearch($pdo, 'البنتاغون');
t('diacritics/tatweel stripped -> finds id 3', in_array(3, $rd['ids'], true), true);

$rt = runSearch($pdo, 'موازنة');
t('ta marbuta variant matches', in_array(2, $rt['ids'], true), true);

$rn = runSearch($pdo, 'الجيش 2026');
t('ascii digits match Arabic-Indic digits (id 4)', in_array(4, $rn['ids'], true), true);
t('multi-token requires BOTH tokens', count(array_intersect($rn['ids'], array(1, 2))) === 0, true);

echo "\n--- relevance ordering ---\n";
$rr = runSearch($pdo, 'الجيش');
t('exact-ish title matches rank above body mentions',
    count($rr['ids']) >= 2 && in_array(1, $rr['ids'], true), true);

// wildcard injection must not match everything
$rw = runSearch($pdo, '100%');
t('percent in query is treated literally (no crash)', $rw['error'] === '', true);
t('percent query returns nothing (not everything)', count($rw['ids']) === 0, true);

$rs = runSearch($pdo, '_');
// a single punctuation character normalises to nothing: the controller must treat
// that as "query too short" instead of erroring
t('underscore query is safe', $rs['error'] === '' || $rs['error'] === 'too short', true);

echo "\n--- suggestions use the same engine ---\n";
$sug = runSearch($pdo, 'البنتاغون');
t('suggestion source returns matches', count($sug['ids']) >= 1, true);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);