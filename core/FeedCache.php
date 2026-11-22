<?php

/**
 * FeedCache — كاش قرصي لخلاصات المجمّع مع احتفاظ 48 ساعة.
 *
 * الهدف: مصدر هادئ أو معطّل مؤقتاً لا يُفرغ صفحة الأخبار. عند نجاح الجلب
 * تُحفظ العناصر parsed كما هي، وعند فشل الجلب التالي تُستخدم النسخة المحفوظة
 * ما دام عمرها أقل من 48 ساعة، فلا تختفي الأخبار فجأة.
 */
class FeedCache
{
    /** مدة الاحتفاظ بالثواني (48 ساعة). */
    public const TTL = 172800;

    /** @var string */
    private $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = rtrim($dir ?: dirname(__DIR__) . '/storage/cache/aggregator_feeds', '/\\');
    }

    public function directory(): string
    {
        return $this->dir;
    }

    /** مسار ملف المصدر (ثابت لكل رابط بعد التطبيع). */
    public function path(string $url): string
    {
        return $this->dir . DIRECTORY_SEPARATOR . sha1(self::normaliseUrl($url)) . '.json';
    }

    /**
     * نسخة صالحة (أقل من 48 ساعة) أو null.
     *
     * @return array{items: array, fetched_at: int}|null
     */
    public function get(string $url): ?array
    {
        $file = $this->path($url);
        if (!is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false || $raw === '') {
            return null;
        }

        $data = json_decode($raw, true);
        if (!is_array($data) || !isset($data['fetched_at']) || !isset($data['items']) || !is_array($data['items'])) {
            return null;
        }

        $age = time() - (int) $data['fetched_at'];
        if ($age < 0 || $age > self::TTL || empty($data['items'])) {
            @unlink($file);
            return null;
        }

        return [
            'items' => $data['items'],
            'fetched_at' => (int) $data['fetched_at'],
        ];
    }

    /**
     * نسخة حديثة فقط (عمر أقل من $maxAge ثانية) — للاستجابة الفورية في
     * صفحات الإدارة بدل انتظار الشبكة في كل تحميل.
     *
     * @return array{items: array, fetched_at: int}|null
     */
    public function getNewer(string $url, int $maxAge): ?array
    {
        $hit = $this->get($url);
        if ($hit === null) {
            return null;
        }
        if ($maxAge > 0 && (time() - $hit['fetched_at']) > $maxAge) {
            return null;
        }
        return $hit;
    }

    /**
     * حفظ نتيجة جلب ناجح. يحذف الملفات المنتهية أحياناً باحتمال بسيط
     * حتى لا يتراكم الكاش على الاستضافة المشتركة.
     *
     * @param array<int, array> $items
     */
    public function put(string $url, array $items): bool
    {
        if (empty($items)) {
            return false;
        }

        if (!is_dir($this->dir) && !@mkdir($this->dir, 0777, true) && !is_dir($this->dir)) {
            return false;
        }

        $payload = json_encode([
            'url' => $url,
            'fetched_at' => time(),
            'items' => array_values($items),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($payload === false) {
            return false;
        }

        $ok = @file_put_contents($this->path($url), $payload, LOCK_EX) !== false;
        if ($ok && random_int(1, 50) === 1) {
            $this->prune();
        }

        return $ok;
    }

    /**
     * حذف كل الملفات الأقدم من 48 ساعة.
     *
     * @return int عدد الملفات المحذوفة
     */
    public function prune(): int
    {
        if (!is_dir($this->dir)) {
            return 0;
        }

        $removed = 0;
        $files = glob($this->dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        foreach ($files as $file) {
            $mtime = @filemtime($file);
            if ($mtime !== false && (time() - $mtime) > self::TTL) {
                if (@unlink($file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }

    private static function normaliseUrl(string $url): string
    {
        $url = trim($url);
        $url = preg_replace('#^https?://#i', '', $url);

        // Hostnames are case-insensitive; paths keep their case.
        $parts = explode('/', (string) $url, 2);
        $parts[0] = strtolower((string) preg_replace('#^www\.#i', '', $parts[0]));
        $url = implode('/', $parts);

        return rtrim($url, '/');
    }
}
