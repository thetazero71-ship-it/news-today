<?php

/**
 * Search engine helpers: Arabic-aware normalization, safe LIKE patterns,
 * relevance ranking and the WHERE builder used by SearchController.
 *
 * Why normalization matters on this site: articles are bilingual (English feed
 * title + Arabic translation) and Arabic text is written with many equivalent
 * letter shapes. A raw LIKE '%الاحمرار%' misses "الأحمرار", "الاحمرار" with a
 * different alef, "ى/ي" swaps and tatweel/diacritics.
 */
class SearchQuery
{
    /** Longest query we accept (protects the database from huge scans). */
    const MAX_LENGTH = 120;

    /** Minimum characters before we bother hitting the database. */
    const MIN_LENGTH = 2;

    /** Only the first N words are matched in SQL (keeps the query small). */
    const MAX_MATCH_TOKENS = 4;

    /**
     * Canonical Arabic folding used on BOTH sides of the comparison:
     * PHP normalizes the query, SQL normalizes the column.
     */
    private static $foldMap = array(
        // alef family -> ا
        "\xD8\xA3" => "\xD8\xA7", // أ
        "\xD8\xA5" => "\xD8\xA7", // إ
        "\xD8\xA2" => "\xD8\xA7", // آ
        "\xD9\xB1" => "\xD8\xA7", // ٱ
        "\xD8\xB0" => "\xD8\xA7", // ٰ (superscript alef)
        // ya / alef maqsura -> ي
        "\xD9\x89" => "\xD9\x8A", // ى
        "\xD8\xA6" => "\xD9\x8A", // ئ
        "\xD9\x80" => "\xD9\x8A", // ھ (heh doachashmee) -> ي? no-op guard
        // waw with hamza -> و
        "\xD8\xA4" => "\xD9\x88", // ؤ
        // ta marbuta -> ه
        "\xD8\xA9" => "\xD9\x87", // ة
        // hamza carriers
        "\xD8\xA1" => "\xD8\xA7", // ء -> ا
        "\xD9\xA2" => "\xD8\xA7", // ٢
    );

    /** Removed entirely: tatweel, harakat and Quranic annotation marks. */
    private const STRIP_PATTERN = '/[\x{0640}\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u';

    /** Unicode codepoint of the Arabic-Indic zero (٠) and Extended zero (۰). */
    private const ARABIC_ZERO = 0x0660;
    private const EXTENDED_ZERO = 0x06F0;

    /**
     * Fold text to its canonical search form.
     */
    public static function normalize(string $text): string
    {
        if ($text === '') {
            return '';
        }
        $text = self::toUtf8($text);

        // digits: Arabic-Indic (٠) and Extended Arabic-Indic (۰) -> ASCII.
        // NOTE: mb_chr(), never chr() - chr() is byte based and would corrupt Latin text.
        static $digits = array();
        if (empty($digits)) {
            for ($i = 0; $i < 10; $i++) {
                $digits[mb_chr(self::ARABIC_ZERO + $i, 'UTF-8')] = (string) $i;
                $digits[mb_chr(self::EXTENDED_ZERO + $i, 'UTF-8')] = (string) $i;
            }
        }
        $text = strtr($text, $digits);

        // strip tatweel + harakat + Quranic marks
        $stripped = preg_replace(self::STRIP_PATTERN, '', $text);
        if (is_string($stripped)) {
            $text = $stripped;
        }

        // fold letter shapes
        $text = strtr($text, self::$foldMap);

        // punctuation -> space (keeps words apart), then collapse whitespace
        $text = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $text);
        $text = preg_replace('/\s+/u', ' ', $text);

        return mb_strtolower(trim((string) $text), 'UTF-8');
    }

    /**
     * Split a user query into meaningful tokens (deduplicated, length filtered).
     *
     * @return string[]
     */
    public static function tokens(string $q): array
    {
        $normalized = self::normalize($q);
        if ($normalized === '') {
            return array();
        }
        $parts = preg_split('/[\s,،؛;]+/u', $normalized, -1, PREG_SPLIT_NO_EMPTY);
        $out = array();
        foreach ($parts as $p) {
            $p = trim($p);
            if (mb_strlen($p, 'UTF-8') >= 2 && !in_array($p, $out, true)) {
                $out[] = $p;
            }
        }
        // very long queries: the first few words carry the intent
        return array_slice($out, 0, 6);
    }

    /**
     * Escape LIKE wildcards typed by the user so "%" stays a literal "%".
     */
    public static function likeEscape(string $value): string
    {
        return str_replace(array('\\', '%', '_'), array('\\\\', '\\%', '\\_'), $value);
    }

    /**
     * MySQL expression that folds a column the same way normalize() does.
     *
     * Used ALONE (no second raw LIKE): because both sides are folded, comparing
     * the folded column against the folded query matches every spelling variant
     * (أ/ا, ة/ه, ى/ي, hamza, tatweel, digits) at half the SQL size.
     */
    public static function foldExpr(string $column): string
    {
        $expr = "REPLACE($column, 'ـ', '')"; // tatweel
        foreach (array('أ', 'إ', 'آ', 'ٱ') as $ch) {
            $expr = "REPLACE($expr, '" . $ch . "', 'ا')";
        }
        $expr = "REPLACE($expr, 'ى', 'ي')";
        $expr = "REPLACE($expr, 'ئ', 'ي')";
        $expr = "REPLACE($expr, 'ؤ', 'و')";
        $expr = "REPLACE($expr, 'ة', 'ه')";
        $expr = "REPLACE($expr, 'ء', '')";
        // Arabic-Indic digits -> ASCII (mb_chr, never chr: chr() is byte based)
        for ($i = 0; $i < 10; $i++) {
            $expr = "REPLACE($expr, '" . mb_chr(self::ARABIC_ZERO + $i, 'UTF-8') . "', '$i')";
        }
        return "LOWER($expr)";
    }

    /**
     * Columns that participate in the search, with their relevance weight.
     *
     * @return array<string,int>
     */
    public static function fields(): array
    {
        return array(
            'a.title'        => 100,
            'a.title_ar'     => 100,
            'a.title_en'     => 95,
            'a.slug'         => 70,
            'a.excerpt'      => 60,
            'a.source_name'  => 45,
            'a.content'      => 40,
            'a.content_ar'   => 40,
            'a.content_en'   => 40,
        );
    }

    /**
     * Build the WHERE fragment for the given tokens.
     *
     * Every token must appear in at least one field (AND across tokens, OR across
     * fields) - that is what makes multi-word queries precise.
     *
     * @return array{0:string,1:array,2:string} [sql, params, relevanceSql]
     */
    public static function buildWhere(array $tokens): array
    {
        $fields = self::fields();

        // Relevance only needs the fields that actually drive ordering; keeping
        // it small prevents the SELECT from doubling in size.
        $ranked = array('a.title' => 100, 'a.title_ar' => 100, 'a.title_en' => 95,
                        'a.excerpt' => 60, 'a.source_name' => 45, 'a.content_ar' => 40,
                        'a.content' => 40);

        $tokens = array_slice(array_values($tokens), 0, self::MAX_MATCH_TOKENS);

        $clauses = array();
        $params = array();
        $relevance = array();

        foreach ($tokens as $i => $token) {
            $like = '%' . self::likeEscape($token) . '%';
            $name = 't' . $i;

            $orParts = array();
            $relevanceTerms = array();
            foreach ($fields as $column => $weight) {
                $p = ':' . $name . substr(md5($column), 0, 5);
                $params[$p] = $like;
                $folded = self::foldExpr($column);
                $orParts[] = "$folded LIKE $p ESCAPE '\\\\'";
                if (isset($ranked[$column])) {
                    $relevanceTerms[] = "IF($folded LIKE $p ESCAPE '\\\\', $weight, 0)";
                }
            }

            $clauses[] = '(' . implode(' OR ', $orParts) . ')';

            // exact normalized title match is the strongest signal
            // (":$eqName" must stay a literal placeholder for PDO)
            $eqName = 'eq' . $name;
            $params[$eqName] = $token;
            $relevanceTerms[] = 'IF(' . self::foldExpr('a.title') . ' = :' . $eqName . ', 130, 0)';
            $relevanceTerms[] = 'IF(' . self::foldExpr('a.title_ar') . ' = :' . $eqName . ', 130, 0)';
            $relevance[] = '(' . implode(' + ', $relevanceTerms) . ')';
        }

        return array(
            '(' . implode(' AND ', $clauses) . ')',
            $params,
            '(' . implode(' + ', $relevance) . ')',
        );
    }

    private static function toUtf8(string $text): string
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', 'UTF-8, Windows-1256');
            return is_string($converted) ? $converted : $text;
        }
        return $text;
    }

    /**
     * Highlight the query terms inside a snippet of text (safe HTML).
     */
    public static function highlight(string $html, string $text, int $length = 220): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
        if ($text === '') {
            return '';
        }

        $escaped = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        $pattern = '';
        foreach (self::tokens((string) $html) as $t) {
            $pattern .= preg_quote(htmlspecialchars($t, ENT_QUOTES, 'UTF-8'), '#') . '|';
        }
        if ($pattern === '') {
            return mb_substr($escaped, 0, $length, 'UTF-8') . (mb_strlen($escaped, 'UTF-8') > $length ? '…' : '');
        }
        $pattern = rtrim($pattern, '|');

        // cut around the first match so the reader sees WHY it matched
        $plainPos = mb_stripos($text, self::tokens((string) $html)[0] ?? '', 0, 'UTF-8');
        $start = 0;
        if ($plainPos !== false && $plainPos > 60) {
            $start = $plainPos - 60;
        }
        $snippet = mb_substr($text, $start, $length, 'UTF-8');
        $snippetHtml = htmlspecialchars(($start > 0 ? '…' : '') . $snippet, ENT_QUOTES, 'UTF-8');

        return preg_replace('#(' . $pattern . ')#ui', '<mark>$1</mark>', $snippetHtml);
    }
}