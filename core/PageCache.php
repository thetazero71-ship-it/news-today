<?php

/**
 * Full-page cache for public pages.
 *
 * The site runs on shared hosting where the PHP worker pool is tiny and the
 * machine is busy with other tenants. Every uncached hit spends a worker
 * process; when the pool is exhausted the gateway answers "502 Bad Gateway".
 * Serving a finished HTML page from disk keeps the site responsive under bursts.
 */
class PageCache
{
    /** Seconds a page stays fresh. */
    const TTL = 90;

    /** Seconds a page may still be served (stale) while it is being rebuilt. */
    const STALE_TTL = 900;

    private static $version = null;

    public static function dir(): string
    {
        $dir = dirname(__DIR__) . '/storage/pagecache';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * Should this request be answered from the cache at all?
     * Never for logged-in users, admins, POSTs, the panel or AJAX.
     */
    public static function isCacheableRequest(?string $method = null): bool
    {
        $method = $method ?? ($_SERVER['REQUEST_METHOD'] ?? 'GET');
        if (strtoupper($method) !== 'GET') {
            return false;
        }
        if (!empty($_POST)) {
            return false;
        }
        foreach (array('PHPSESSID', 'auth_token', 'remember_token') as $cookie) {
            if (!empty($_COOKIE[$cookie])) {
                return false;
            }
        }
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
            return false;
        }
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            return false;
        }

        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        // admin panel, api, live endpoints and anything that changes state
        foreach (array('/admin', '/api/', '/logout', '/login', '/register') as $blocked) {
            if (strpos($uri, $blocked) !== false) {
                return false;
            }
        }
        // non-HTML endpoints (feeds, sitemap, manifests, exports)
        if (preg_match('/\.(xml|json|txt|csv|rss|webmanifest|jpg|png|svg|webp|ico|css|js|pdf)$/i', $uri)) {
            return false;
        }
        if (strpos($uri, '/feed') === 0 || strpos($uri, '/sitemap') === 0 || strpos($uri, '/export') === 0) {
            return false;
        }
        // query strings that make the page unique per visitor
        foreach (array('preview', 'token', 'key', 'sig', 'code') as $skip) {
            if (isset($_GET[$skip])) {
                return false;
            }
        }
        return true;
    }

    private static function version(): string
    {
        if (self::$version !== null) {
            return self::$version;
        }
        $file = self::dir() . '/version.txt';
        $v = @file_get_contents($file);
        if ($v === false || $v === '') {
            $v = '1';
            @file_put_contents($file, $v);
        }
        self::$version = trim((string) $v);
        return self::$version;
    }

    /** Bumping the version invalidates every cached page at once. */
    public static function flush(): int
    {
        $count = 0;
        foreach ((array) @glob(self::dir() . '/page_*.html') as $file) {
            if (@unlink($file)) {
                $count++;
            }
        }
        @file_put_contents(self::dir() . '/version.txt', (string) (time()));
        self::$version = null;
        return $count;
    }

    private static function path(): string
    {
        $uri = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
        parse_str((string) parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_QUERY), $q);
        unset($q['__pc']);
        ksort($q);
        $qs = http_build_query($q);
        $key = md5($uri . '?' . $qs . '|v' . self::version());
        return self::dir() . '/page_' . $key . '.html';
    }

    /**
     * Sends a fresh/stale cached page if there is one.
     *
     * @return bool true when the response was served (caller must stop)
     */
    public static function serve(): bool
    {
        $file = self::path();
        if (!is_file($file)) {
            return false;
        }
        $mtime = @filemtime($file);
        if (!$mtime) {
            return false;
        }
        $age = time() - $mtime;
        if ($age > self::STALE_TTL) {
            @unlink($file);
            return false;
        }
        $html = @file_get_contents($file);
        if ($html === false || $html === '') {
            return false;
        }

        // لا إعادة بناء من داخل طلب الزائر: الطلب الذاتي يجمّد العامل الحالي
        // حتى 8 ثوانٍ ويُنتج 502. بدل ذلك تُخدم الصفحة القديمة الآن وتُبنى
        // زاحفة عند أول طلب يتجاوز مدة الصلاحية.
        header('X-Page-Cache: ' . ($age <= self::TTL ? 'HIT' : 'STALE'));
        header('X-Page-Cache-Age: ' . $age);
        header('Content-Type: text/html; charset=UTF-8');
        echo self::personalise($html);
        return true;
    }

    /**
     * Cached pages were rendered for somebody else, so the CSRF token inside
     * them must be swapped for this visitor's own token.
     */
    public static function personalise(string $html): string
    {
        if (strpos($html, '{{PC_CSRF}}') === false) {
            return $html;
        }
        return str_replace('{{PC_CSRF}}', self::csrfToken(), $html);
    }

    private static function csrfToken(): string
    {
        try {
            if (class_exists('CSRF')) {
                return (string) CSRF::getToken();
            }
        } catch (Throwable $e) {
            // fall through
        }
        return '';
    }

    /** Captures the freshly rendered page. Call at the very end of the request. */
    public static function store(string $html): void
    {
        if (trim($html) === '' || strlen($html) < 500) {
            return;
        }
        // never cache an error page or a page that already announced a hit
        if (stripos($html, '<title>') === false) {
            return;
        }
        if (stripos($html, 'الصفحة غير موجودة') !== false) {
            return;
        }

        // neutralise the session-bound CSRF token before writing to disk
        try {
            if (class_exists('CSRF')) {
                $token = htmlspecialchars((string) CSRF::getToken(), ENT_QUOTES, 'UTF-8');
                if ($token !== '') {
                    $html = str_replace($token, '{{PC_CSRF}}', $html);
                }
            }
        } catch (Throwable $e) {
            // no token in this page
        }

        @file_put_contents(self::path(), $html, LOCK_EX);
    }
}