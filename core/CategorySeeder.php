<?php

class CategorySeeder
{
    /**
     * Default sections of the site. The classifier can only route an article into
     * a category that really exists in the `categories` table, so the standard set
     * is created on demand (missing rows only).
     */
    public static function defaults(): array
    {
        return [
            ['slug' => 'economic',    'name' => 'اقتصادية', 'name_en' => 'Economic',     'sort' => 20, 'color' => '#0f9d58'],
            ['slug' => 'health',      'name' => 'صحيحة',    'name_en' => 'Health',       'sort' => 30, 'color' => '#e91e63'],
            ['slug' => 'sports',      'name' => 'رياضية',   'name_en' => 'Sports',       'sort' => 40, 'color' => '#ff9800'],
            ['slug' => 'science',     'name' => 'علوم',     'name_en' => 'Science',      'sort' => 50, 'color' => '#3f51b5'],
            ['slug' => 'technology',  'name' => 'تقنية',    'name_en' => 'Technology',   'sort' => 60, 'color' => '#2196f3'],
            ['slug' => 'society',     'name' => 'اجتماعية', 'name_en' => 'Society',      'sort' => 70, 'color' => '#9c27b0'],
        ];
    }

    /**
     * Create every missing default category. Safe to call on each request:
     * existing slugs are read first and only absent ones are inserted.
     *
     * @return array slugs that were created by this call
     */
    public static function ensure($db): array
    {
        $created = [];
        try {
            $existing = [];
            foreach ($db->fetchAll('SELECT slug FROM categories') as $row) {
                $existing[mb_strtolower(trim((string) ($row['slug'] ?? '')))] = true;
            }

            $columns = self::availableColumns($db);
            if (empty($columns)) {
                return [];
            }

            foreach (self::defaults() as $cat) {
                if (isset($existing[$cat['slug']])) {
                    continue;
                }
                if (self::insert($db, $cat, $columns)) {
                    $created[] = $cat['slug'];
                }
            }
        } catch (Throwable $e) {
            return $created;
        }
        return $created;
    }

    private static function availableColumns($db): array
    {
        $cols = [];
        foreach ($db->fetchAll('SHOW COLUMNS FROM categories') as $row) {
            if (!empty($row['Field'])) {
                $cols[strtolower((string) $row['Field'])] = true;
            }
        }
        return $cols;
    }

    private static function insert($db, array $cat, array $columns): bool
    {
        $values = [
            'name'           => $cat['name'],
            'name_ar'        => $cat['name'],
            'name_en'        => $cat['name_en'],
            'slug'           => $cat['slug'],
            'description_ar' => $cat['name'] . ' — أخبار ومحتوى ' . $cat['name'],
            'is_visible'     => 1,
            'color'          => $cat['color'],
            'sort_order'     => $cat['sort'],
        ];

        $cols = [];
        $params = [];
        $marks = [];
        foreach ($values as $col => $val) {
            if (!isset($columns[$col])) {
                continue;
            }
            $cols[] = $col;
            $marks[] = ':' . $col;
            $params[':' . $col] = $val;
        }

        if (!in_array('slug', $cols, true)) {
            return false;
        }

        $sql = 'INSERT INTO categories (' . implode(',', $cols) . ') VALUES (' . implode(',', $marks) . ')'
             . ' ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)';
        $db->query($sql, $params);
        return true;
    }
}
