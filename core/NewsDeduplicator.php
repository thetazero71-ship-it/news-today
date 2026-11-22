<?php

/**
 * Duplicate news guard.
 *
 * The same story is syndicated by many feeds. Publishing it several times
 * looks sloppy and wastes the editor's time, so every candidate is matched
 * against what is already published.
 */
class NewsDeduplicator
{
    /** How far back a duplicate is still considered the same story. */
    const DEFAULT_WINDOW_DAYS = 10;

    /** Token overlap above which two titles are "the same story". */
    const SIMILARITY = 0.80;

    /** كلمات شائعة وأفعال نشر لا تحمل معنى تمييزياً — تُستبعد قبل المقارنة. */
    const STOPWORDS = [
        'في', 'من', 'على', 'إلى', 'عن', 'مع', 'هذا', 'هذه', 'ذلك', 'تلك', 'التي', 'الذي', 'الذين',
        'اللتين', 'اللتان', 'كل', 'أو', 'ثم', 'بعد', 'قبل', 'خلال', 'حول', 'بين', 'حيث', 'كيف',
        'لماذا', 'ماذا', 'نحو', 'ضد', 'تحت', 'فوق', 'دون', 'غير', 'كما', 'كان', 'كانت', 'يكون',
        'تكون', 'تعلن', 'تطلق', 'يطلق', 'يعلن', 'يكشف', 'تكشف', 'تحديث', 'جديد', 'جديدة', 'جديداً',
        'رسمياً', 'رسمي', 'ميزة', 'ميزات', 'خصائص', 'خاصية', 'تقنية', 'تطبيق', 'برنامج', 'نظام',
        'أجهزة', 'هواتف', 'هاتف', 'شركة', 'مستخدمي', 'مستخدمين', 'عبر', 'أول', 'أحدث',
        'the', 'and', 'for', 'with', 'from', 'this', 'that', 'these', 'those', 'which', 'what',
        'when', 'where', 'how', 'why', 'who', 'whom', 'all', 'any', 'some', 'about', 'after',
        'before', 'between', 'into', 'through', 'during', 'over', 'under', 'again', 'further',
        'then', 'once', 'here', 'there', 'both', 'each', 'few', 'more', 'most', 'other', 'only',
        'own', 'same', 'than', 'too', 'very', 'can', 'will', 'just', 'should', 'now', 'launches',
        'launch', 'released', 'releases', 'release', 'announces', 'announced', 'announce',
        'unveils', 'unveiled', 'updates', 'updated', 'update', 'features', 'feature', 'official',
        'officially', 'first', 'look', 'review', 'guide', 'best', 'using',
    ];

    /** كاش داخلي للمقالات الحديثة أثناء مسح عدة خلاصات في طلب واحد. */
    private static $cachedRecentArticles = null;

    /** تصفير الكاش (للاختبارات أو بعد نشر خبر جديد في نفس الطلب). */
    public static function clearCache(): void
    {
        self::$cachedRecentArticles = null;
    }

    /**
     * المقالات المنشورة حديثاً مع كاش داخلي (بدل استعلام لكل خبر).
     */
    public static function getRecentArticles($db, int $windowDays = self::DEFAULT_WINDOW_DAYS): array
    {
        if (self::$cachedRecentArticles !== null) {
            return self::$cachedRecentArticles;
        }
        try {
            self::$cachedRecentArticles = $db->fetchAll(
                "SELECT id, title, title_ar, slug, source_name, created_at, published_at
                 FROM articles
                 WHERE created_at >= (NOW() - INTERVAL " . (int) $windowDays . " DAY)
                 ORDER BY id DESC
                 LIMIT " . (int) self::COMPARE_LIMIT
            );
        } catch (Throwable $e) {
            self::$cachedRecentArticles = [];
        }
        return self::$cachedRecentArticles;
    }

    /**
     * مطابقة عنوان مع قائمة مقالات سبق تحميلها.
     */
    public static function findMatchInArticles(array $rows, string $title, float $threshold = 0.65): ?array
    {
        $needle = self::normalise($title);
        if (mb_strlen($needle) < 8) {
            return null;
        }
        $needleTokens = self::tokens($needle);

        foreach ($rows as $row) {
            foreach (array($row['title'] ?? '', $row['title_ar'] ?? '') as $stored) {
                $storedNorm = self::normalise((string) $stored);
                if ($storedNorm === '') {
                    continue;
                }
                if ($storedNorm === $needle) {
                    return self::match($row, 1.0);
                }
                $short = mb_strlen($storedNorm) <= mb_strlen($needle) ? $storedNorm : $needle;
                $long = $short === $storedNorm ? $needle : $storedNorm;
                if (mb_strlen($short) >= 20 && strpos($long, $short) !== false) {
                    return self::match($row, 0.95);
                }
                $score = self::similarity($needleTokens, self::tokens($storedNorm));
                if ($score >= $threshold) {
                    return self::match($row, $score);
                }
            }
        }
        return null;
    }

    /**
     * مطابقة عنوان مع عناصر خلاصة أخرى (كشف التكرار بين المصادر).
     */
    public static function findMatchInFeedItems(string $title, array $feedItems, string $currentLink = '', float $threshold = 0.65): ?array
    {
        $needle = self::normalise($title);
        if (mb_strlen($needle) < 8) {
            return null;
        }
        $needleTokens = self::tokens($needle);
        $normCurrentLink = trim(strtolower($currentLink));

        foreach ($feedItems as $item) {
            $otherLink = trim(strtolower($item['link'] ?? ''));
            if ($normCurrentLink !== '' && $otherLink !== '' && $normCurrentLink === $otherLink) {
                continue;
            }
            $otherTitle = (string) ($item['title'] ?? '');
            $otherNorm = self::normalise($otherTitle);
            if ($otherNorm === '') {
                continue;
            }
            if ($otherNorm === $needle) {
                return ['is_duplicate' => true, 'is_published' => false, 'title' => $otherTitle,
                    'source' => (string) ($item['source_name'] ?? 'مصدر آخر'), 'score' => 100];
            }
            $short = mb_strlen($otherNorm) <= mb_strlen($needle) ? $otherNorm : $needle;
            $long = $short === $otherNorm ? $needle : $otherNorm;
            if (mb_strlen($short) >= 20 && strpos($long, $short) !== false) {
                return ['is_duplicate' => true, 'is_published' => false, 'title' => $otherTitle,
                    'source' => (string) ($item['source_name'] ?? 'مصدر آخر'), 'score' => 95];
            }
            $score = self::similarity($needleTokens, self::tokens($otherNorm));
            if ($score >= $threshold) {
                return ['is_duplicate' => true, 'is_published' => false, 'title' => $otherTitle,
                    'source' => (string) ($item['source_name'] ?? 'مصدر آخر'), 'score' => round($score * 100)];
            }
        }
        return null;
    }

    /** How many recent titles are compared (the window query is ordered by id). */
    const COMPARE_LIMIT = 400;

    /**
     * @return array|null the stored article when this story already exists
     */
    public static function findMatch($db, string $title, string $content = '', int $windowDays = self::DEFAULT_WINDOW_DAYS): ?array
    {
        $rows = self::getRecentArticles($db, $windowDays);
        return self::findMatchInArticles($rows, $title, self::SIMILARITY);
    }

    private static function match(array $row, float $score): array
    {
        return [
            'id'      => (int) $row['id'],
            'title'   => (string) ($row['title_ar'] ?: $row['title']),
            'slug'    => (string) ($row['slug'] ?? ''),
            'source'  => (string) ($row['source_name'] ?? ''),
            'score'   => round($score * 100),
        ];
    }

    /** Canonical form used for comparing headlines. */
public static function normalise(string $title): string
 {
  $t = mb_strtolower(trim($title), 'UTF-8');

  // تشكيل + علامات قرآنية + تطويل (رموز مكتوبة بأكواد حتى لا تتأثر بالترميز)
  $t = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}\x{0640}\x{08D3}-\x{08FF}\x{FDF2}-\x{FDFD}]/u', '', (string) $t);

$t = strtr((string) $t, array(
  "\u{0623}" => "\u{0627}", "\u{0625}" => "\u{0627}", "\u{0622}" => "\u{0627}", "\u{0671}" => "\u{0627}",
  "\u{0649}" => "\u{064A}", "\u{0626}" => "\u{064A}", "\u{0624}" => "\u{0648}",
  "\u{0621}" => '', "\u{0629}" => '',
  "\u{0660}" => '0', "\u{0661}" => '1', "\u{0662}" => '2', "\u{0663}" => '3', "\u{0664}" => '4',
  "\u{0665}" => '5', "\u{0666}" => '6', "\u{0667}" => '7', "\u{0668}" => '8', "\u{0669}" => '9',
  "\u{06F0}" => '0', "\u{06F1}" => '1', "\u{06F2}" => '2', "\u{06F3}" => '3', "\u{06F4}" => '4',
  "\u{06F5}" => '5', "\u{06F6}" => '6', "\u{06F7}" => '7', "\u{06F8}" => '8', "\u{06F9}" => '9',
  "\u{2019}" => "'", "\u{2018}" => "'", "\u{0060}" => "'",
  ));

  $t = preg_replace('/[^a-z0-9\p{L}\p{N}\s]+/u', ' ', $t);
  $t = preg_replace('/\s+/u', ' ', trim((string) $t));
  return (string) $t;
 }

    private static function tokens(string $normalised): array
    {
        $stop = array_flip(self::STOPWORDS);
        $words = array_values(array_unique(array_filter(explode(' ', $normalised), function ($w) use ($stop) {
            return mb_strlen($w) >= 3 && !isset($stop[$w]);
        })));
        return $words;
    }

    /**
     * وسم العناصر المكررة داخل قائمة خلاصة واحدة.
     *
     * القاعدة: أول ظهور للخبر يبقى «الأصل» متاحاً للنشر، وكل ظهور لاحق
     * يُوسم بـ duplicate_of (فهرس الأصل) كي تستطيع الواجهة تمييزه وإخفاءه.
     *
     * @param array<int, array> $items
     * @return array<int, array>
     */
    public static function annotateDuplicates(array $items): array
    {
        $seen = array();

        foreach ($items as $i => &$item) {
            $item['duplicate_of'] = null;
            $item['duplicate_score'] = 0;
            $item['duplicate_title'] = '';

            $norm = self::normalise((string) ($item['title'] ?? ''));
            if ($norm === '' || mb_strlen($norm) < 8) {
                continue;
            }
            $tokens = self::tokens($norm);
            if (count($tokens) < 2) {
                continue;
            }

            $bestIndex = null;
            $bestScore = 0.0;

            foreach ($seen as $j => $prev) {
                if ($prev['norm'] === $norm) {
                    $bestIndex = $j;
                    $bestScore = 1.0;
                    break;
                }

                // عنوان داخل عنوان («عاجل: X» مقابل «X»).
                $short = mb_strlen($prev['norm']) <= mb_strlen($norm) ? $prev['norm'] : $norm;
                $long = $short === $prev['norm'] ? $norm : $prev['norm'];
                if (mb_strlen($short) >= 20 && strpos($long, $short) !== false && $bestScore < 0.95) {
                    $bestIndex = $j;
                    $bestScore = 0.95;
                    continue;
                }

                $score = self::similarity($tokens, $prev['tokens']);
                if ($score >= self::SIMILARITY && $score > $bestScore) {
                    $bestIndex = $j;
                    $bestScore = $score;
                }
            }

            if ($bestIndex !== null) {
                $item['duplicate_of'] = (int) $bestIndex;
                $item['duplicate_score'] = (int) round($bestScore * 100);
                $item['duplicate_title'] = (string) ($items[$bestIndex]['title'] ?? '');
            } else {
                $seen[$i] = array('norm' => $norm, 'tokens' => $tokens);
            }
        }
        unset($item);

        return $items;
    }

/**
 * How likely two headlines are the same story.
 *
 * Two shapes are accepted:
 *  - coverage: the shorter headline is almost entirely inside the longer one
 *    ("X launches satellite" vs "X launches new satellite today").
 *  - blend: both headlines share most of their words (reworded copy).
 */
private static function similarity(array $a, array $b): float
 {
  if (count($a) < 2 || count($b) < 2) {
  return 0.0;
  }
  $shared = count(array_intersect($a, $b));
  if ($shared < 2) {
  return 0.0;
  }

  $coverage = $shared / min(count($a), count($b));
  $blend = $shared / count(array_unique(array_merge($a, $b)));

  if ($coverage >= 0.85) {
  return $coverage;
  }
  return $blend;
 }
}