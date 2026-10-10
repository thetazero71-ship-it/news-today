<?php

class Category extends Model
{
    /**
     * Categories shown across the site (navigation bar, archive, filters...).
     * Hidden categories stay in the database with their articles; they are only
     * taken out of the public lists.
     */
    public function getAll()
    {
        return $this->visibleQuery("SELECT * FROM categories ORDER BY sort_order ASC, name ASC");
    }

    /** Every category, hidden ones included (admin screens). */
    public function getAllWithHidden()
    {
        return $this->db->fetchAll(
            "SELECT * FROM categories ORDER BY sort_order ASC, name ASC"
        );
    }

    public function getBySlug($slug)
    {
        return $this->db->fetch(
            "SELECT * FROM categories WHERE slug = :slug LIMIT 1",
            array(':slug' => $slug)
        );
    }

    /** Like getBySlug() but refuses a category the editor has hidden. */
    public function getVisibleBySlug($slug)
    {
        return $this->visibleQuery(
            "SELECT * FROM categories WHERE slug = :slug LIMIT 1",
            array(':slug' => $slug)
        );
    }

    public function isHidden(array $category): bool
    {
        if (!array_key_exists('is_visible', $category)) {
            return false;
        }
        return (int) $category['is_visible'] === 0;
    }

    /**
     * Adds the visibility filter only when the column exists, so an older
     * database without the column keeps working.
     */
    private function visibleQuery(string $sql, array $params = [])
    {
        try {
            $injected = $this->injectVisibility($sql);
            if ($injected !== $sql) {
                $rows = $this->db->fetchAll($injected, $params);
                if ($rows !== false && $rows !== null) {
                    return $rows;
                }
            }
        } catch (Throwable $e) {
            // column missing on this install: fall through to the plain query
        }
        return $this->db->fetchAll($sql, $params);
    }

    /** Puts "is_visible = 1" before the ORDER BY, keeping the SQL valid. */
    private function injectVisibility(string $sql): string
    {
        $pos = stripos($sql, ' ORDER BY ');
        if ($pos !== false) {
            $head = substr($sql, 0, $pos);
            $tail = substr($sql, $pos);
            return $head . (stripos($head, ' WHERE ') !== false ? ' AND is_visible = 1 ' : ' WHERE is_visible = 1 ') . $tail;
        }
        return $sql . (stripos($sql, ' WHERE ') !== false ? ' AND is_visible = 1' : ' WHERE is_visible = 1');
    }
}