<?php

class HomeController extends Controller
{
    public function index()
    {
        $articleModel  = new Article();
        $categoryModel = new Category();
        $tutorialModel = new Tutorial();

        $perPage = (int) Settings::get('articles_per_page', 12);
        if ($perPage < 1) $perPage = 12;

        $pollModel     = new Poll();

        $userId = Auth::user()['id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $ipHash = hash('sha256', $ip . $ua);

        $activePoll = null;
        if (Settings::get('enable_polls', '1') == '1') {
            $activePoll = $pollModel->getActiveFeatured($userId, $ipHash);
        }

        $this->view('home', array(
            'articles'   => $articleModel->getLatest($perPage),
            'popular'    => $articleModel->getPopular(5),
            'radar'      => $articleModel->getTrendingRadar(),
            'categories' => $categoryModel->getAll(),
            'tutorials'  => $tutorialModel->getPublished(6),
            'activePoll' => $activePoll,
        ));
    }

    public function loadMore()
    {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');

        $perPage = (int) Settings::get('articles_per_page', 12);
        if ($perPage < 1) $perPage = 12;

        $page = max(2, (int) ($_GET['page'] ?? 2));
        // The first page hides the featured story, so it rendered perPage-1 cards.
        $offset = max(0, ($page - 1) * $perPage - 1);

        $articleModel = new Article();
        $rows = $articleModel->getLatest($perPage + 1, $offset);
        $hasMore = count($rows) > $perPage;
        if ($hasMore) {
            $rows = array_slice($rows, 0, $perPage);
        }

        ob_start();
        $articles = $rows;
        require APP_ROOT . '/views/partials/home-news-cards.php';
        $html = (string) ob_get_clean();

        echo json_encode([
            'success' => true,
            'page' => $page,
            'has_more' => $hasMore,
            'html' => $html,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return null;
    }
}
