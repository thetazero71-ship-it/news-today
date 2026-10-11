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
    const SIMILARITY = 0.78;

    /** How many recent titles are compared (the window query is ordered by id). */
    const COMPARE_LIMIT = 400;

    /**
     * @return array|null the stored article when this story already exists
     */
    public static function findMatch($db, string $title, string $content = '', int $windowDays = self::DEFAULT_WINDOW_DAYS): ?array
    {
        $needle = self::normalise($title);
        if (mb_strlen($needle) < 8) {
            return null; // too short to judge safely
        }
        $needleTokens = self::tokens($needle);

        try {
            $rows = $db->fetchAll(
                "SELECT id, title, title_ar, slug, source_name, created_at, published_at
                 FROM articles
                 WHERE created_at >= (NOW() - INTERVAL " . (int) $windowDays . " DAY)
                 ORDER BY id DESC
                 LIMIT " . (int) self::COMPARE_LIMIT
            );
        } catch (Throwable $e) {
            return null;
        }

        foreach ($rows as $row) {
            foreach (array($row['title'] ?? '', $row['title_ar'] ?? '') as $stored) {
                $storedNorm = self::normalise((string) $stored);
                if ($storedNorm === '') {
                    continue;
                }

                // 1) exact match after normalisation - the common case
                if ($storedNorm === $needle) {
                    return self::match($row, 1.0);
                }

                // 2) one title contains the other (" Breaking: X " vs "X")
                $short = mb_strlen($storedNorm) <= mb_strlen($needle) ? $storedNorm : $needle;
                $long = $short === $storedNorm ? $needle : $storedNorm;
                if (mb_strlen($short) >= 20 && strpos($long, $short) !== false) {
                    return self::match($row, 0.95);
                }

                // 3) token overlap: catches reworded headlines
                $score = self::similarity($needleTokens, self::tokens($storedNorm));
                if ($score >= self::SIMILARITY) {
                    return self::match($row, $score);
                }
            }
        }

        return null;
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
        $words = array_values(array_unique(array_filter(explode(' ', $normalised), function ($w) {
            return mb_strlen($w) >= 3;
        })));
        return $words;
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
  if (!$a || !$b) {
  return 0.0;
  }
  $shared = count(array_intersect($a, $b));
  if ($shared === 0) {
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