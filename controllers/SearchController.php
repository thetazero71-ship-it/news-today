<?php

class SearchController extends Controller
{
    public function index()
    {
        $raw = (string) ($_GET['q'] ?? '');
        $q = trim($raw);
        $tooLong = mb_strlen($q, 'UTF-8') > SearchQuery::MAX_LENGTH;
        if ($tooLong) {
            $q = mb_substr($q, 0, SearchQuery::MAX_LENGTH, 'UTF-8');
        }

        $category = (int) ($_GET['category_id'] ?? 0);
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : '';
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : '';
        $sort = in_array($_GET['sort'] ?? '', array('views', 'oldest', 'relevance'), true) ? $_GET['sort'] : 'relevance';
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = 10;

        $tokens = SearchQuery::tokens($q);
        $tooShort = $q !== '' && count($tokens) === 0;

        $db = new Database();
        $where = array("a.status = 'published'");
        $params = array();
        $relevanceSql = '0';

        if (!empty($tokens)) {
            list($searchSql, $searchParams, $relevanceSql) = SearchQuery::buildWhere($tokens);
            $where[] = $searchSql;
            $params = array_merge($params, $searchParams);
        } elseif ($q !== '' && $tooShort) {
            // single character / punctuation-only query: show nothing yet
            $where[] = '1 = 0';
        }

        if ($category > 0) {
            $where[] = 'a.category_id = :category_id';
            $params[':category_id'] = $category;
        }
        if ($from !== '') {
            $where[] = 'DATE(a.published_at) >= :date_from';
            $params[':date_from'] = $from;
        }
        if ($to !== '') {
            $where[] = 'DATE(a.published_at) <= :date_to';
            $params[':date_to'] = $to;
        }

        $condition = implode(' AND ', $where);
        $fromSql = ' FROM articles a
                    LEFT JOIN users u ON u.id = a.author_id
                    LEFT JOIN categories c ON c.id = a.category_id ';

        $total = (int) ($db->fetch('SELECT COUNT(*) AS total' . $fromSql . ' WHERE ' . $condition, $params)['total'] ?? 0);

        // ordering: relevance first (date as tie-breaker) unless the user chose otherwise
        if ($sort === 'views') {
            $order = 'a.views_count DESC, a.published_at DESC';
        } elseif ($sort === 'oldest') {
            $order = 'a.published_at ASC';
        } elseif (!empty($tokens)) {
            $order = 'relevance DESC, a.published_at DESC';
        } else {
            $order = 'a.published_at DESC';
        }

        $offset = ($page - 1) * $perPage;
        $articles = $db->fetchAll(
            'SELECT a.*, u.username AS author_name, c.name AS category_name, '
            . $relevanceSql . ' AS relevance'
            . $fromSql . ' WHERE ' . $condition
            . ' ORDER BY ' . $order . ' LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $offset,
            $params
        );

        $categories = $db->fetchAll('SELECT id, name, slug FROM categories ORDER BY name');

        // only offer alternatives when the search itself found nothing
        $suggestions = array();
        if (!empty($tokens) && $total === 0) {
            $suggestions = $this->suggestRows($q, 5);
        }

        $baseQuery = array('q' => $q);
        if ($category > 0) {
            $baseQuery['category_id'] = $category;
        }
        if ($from !== '') {
            $baseQuery['from'] = $from;
        }
        if ($to !== '') {
            $baseQuery['to'] = $to;
        }
        $baseQuery['sort'] = $sort;

        $this->view('search/results', compact(
            'q', 'articles', 'categories', 'category', 'from', 'to',
            'sort', 'page', 'perPage', 'total', 'suggestions', 'baseQuery', 'tooShort', 'tooLong'
        ));
    }

    /**
     * Live suggestions for the header search box.
     */
    public function suggest()
    {
        $q = (string) ($_GET['q'] ?? '');
        header('Content-Type: application/json; charset=utf-8');

        if (mb_strlen($q, 'UTF-8') < SearchQuery::MIN_LENGTH) {
            exit(json_encode(array(), JSON_UNESCAPED_UNICODE));
        }

        $rows = $this->suggestRows($q, 8);
        exit(json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<int, array{title:string,slug:string,url:string,category:string,date:string}>
     */
    private function suggestRows(string $q, int $limit): array
    {
        $tokens = SearchQuery::tokens($q);
        if (empty($tokens)) {
            return array();
        }

        $db = new Database();
        list($sql, $params) = SearchQuery::buildWhere($tokens);

        try {
            $rows = $db->fetchAll(
                'SELECT a.title, a.title_ar, a.slug, a.published_at, c.name AS category_name
                 FROM articles a
                 LEFT JOIN categories c ON c.id = a.category_id
                 WHERE a.status = \'published\' AND ' . $sql . '
                 ORDER BY a.published_at DESC
                 LIMIT ' . (int) $limit,
                $params
            );
        } catch (Throwable $e) {
            return array();
        }

        $out = array();
        foreach ($rows as $r) {
            $title = trim((string) ($r['title_ar'] ?: $r['title']));
            $out[] = array(
                'title'    => $title,
                'slug'     => (string) $r['slug'],
                'url'      => app_url('article/' . $r['slug']),
                'category' => (string) ($r['category_name'] ?? ''),
                'date'     => (string) ($r['published_at'] ?? ''),
            );
        }
        return $out;
    }
}