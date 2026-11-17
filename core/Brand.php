<?php

/**
 * Brand = the single source of truth for the platform name.
 *
 * Everything that shows the site name reads it from here, so changing the value
 * once (settings -> general -> site_name_ar) updates the whole platform.
 */
class Brand
{
    /** Last-resort defaults, only used when the settings table has no value. */
    const DEFAULT_NAME_AR = 'عصب التقنية';
    const DEFAULT_NAME_EN = 'AsabTech';
    const DEFAULT_TAGLINE = 'أحدث المستجدات';

    /** Old seeded taglines that mention tech/AI: replaced by the neutral default. */
    const LEGACY_TAGLINES = array(
        'أحدث أخبار التقنية والذكاء الاصطناعي',
        'أحدث مستجدات التكنولوجيا والذكاء الاصطناعي',
        'نبض التكنولوجيا والذكاء الاصطناعي',
    );

    private static $cache = array();

    private static function setting(string $key, string $fallback): string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $value = '';
        if (class_exists('Settings')) {
            try {
                $value = trim((string) Settings::get($key, ''));
            } catch (Throwable $e) {
                $value = '';
            }
        }
        if ($value === '') {
            $value = $fallback;
        }
        self::$cache[$key] = $value;
        return $value;
    }

    /** Arabic (primary) platform name - the one shown everywhere. */
    public static function name(): string
    {
        return self::setting('site_name_ar', self::DEFAULT_NAME_AR);
    }

    /** English platform name. */
    public static function nameEn(): string
    {
        return self::setting('site_name_en', self::DEFAULT_NAME_EN);
    }

    /** Short tagline, editable from Settings → General. */
    public static function tagline(): string
    {
        $value = self::setting('site_tagline', self::DEFAULT_TAGLINE);
        // Seeded values count as defaults, not user content: remove the tech/AI
        // wording without touching any custom phrase the owner typed.
        return in_array($value, self::LEGACY_TAGLINES, true) ? self::DEFAULT_TAGLINE : $value;
    }

    /** Meta / SEO description in Arabic. */
    public static function description(): string
    {
        return self::setting('site_description_ar', self::name() . ' - ' . self::DEFAULT_TAGLINE);
    }

    /** Public contact address. */
    public static function email(): string
    {
        return self::setting('site_email', '');
    }

    /** "name - suffix" helper for page titles. */
    public static function titleWith(string $suffix): string
    {
        $suffix = trim($suffix);
        return $suffix === '' ? self::name() : $suffix . ' | ' . self::name();
    }

    /**
     * Public URL of a stored brand asset, or '' when the local file is gone.
     *
     * A setting can point at a file that was never uploaded (or was deleted),
     * and printing that path puts a broken image on every page. Checking the
     * disk here keeps every surface honest, and the mtime suffix busts caches
     * when the same filename is replaced.
     */
    public static function assetUrl(string $key, string $fallback = ''): string
    {
        $value = trim(self::setting($key, ''));
        if ($value === '') {
            return $fallback;
        }
        if (preg_match('#^https?://#i', $value) === 1) {
            // A URL pointing back at this site is still a local file: verify it,
            // otherwise an old absolute URL can keep a 404 image on every page.
            $host = strtolower((string) parse_url($value, PHP_URL_HOST));
            $self = strtolower((string) parse_url(app_url(''), PHP_URL_HOST));
            if ($host !== '' && $host === $self) {
                $path = rawurldecode((string) parse_url($value, PHP_URL_PATH));
                $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
                $local = $root . '/' . ltrim($path, '/');
                if (!is_file($local)) {
                    return $fallback;
                }
                $url = app_url(ltrim($path, '/'));
                return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . (int) @filemtime($local);
            }
            return $value;
        }

        $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__);
        $local = $root . '/' . ltrim($value, '/');
        if (!is_file($local)) {
            return $fallback;
        }

        $url = app_url($value);
        return $url . (strpos($url, '?') === false ? '?' : '&') . 'v=' . (int) @filemtime($local);
    }

    public static function logoUrl(): string
    {
        return self::assetUrl('site_logo');
    }

    public static function faviconUrl(): string
    {
        return self::assetUrl('site_favicon');
    }

    /** Drop the per-request cache after the settings are saved. */
    public static function flush(): void
    {
        self::$cache = array();
    }

    /**
     * Stored copy: some settings and pages hold the platform name inside their
     * text (assistant welcome, newsletter copy, about page...). When the name
     * changes, rewrite those stored sentences so no old name is left behind.
     *
     * @return array<int,string> human-readable list of what was rewritten
     */
    public static function migrateReferences($db, string $oldName, string $newName): array
    {
        $changed = array();
        $oldName = trim($oldName);
        $newName = trim($newName);
        if ($oldName === '' || $newName === '' || $oldName === $newName || mb_strlen($oldName) < 2) {
            return $changed;
        }

        // 1. settings whose text embeds the brand
        $keys = array(
            'ai_assistant_welcome_message',
            'ai_assistant_placeholder',
            'ai_assistant_privacy_note',
            'newsletter_welcome_subject',
            'newsletter_welcome_body',
            'mail_from_name',
            'site_description_ar',
            'site_tagline',
            'meta_description',
            'meta_description_default',
        );
        try {
            $rows = $db->fetchAll(
                "SELECT `key`, `value` FROM settings
                 WHERE `key` IN ('" . implode("','", $keys) . "')"
            );
            foreach ($rows as $row) {
                $value = (string) $row['value'];
                if ($value === '' || strpos($value, $oldName) === false) {
                    continue;
                }
                $db->query(
                    "UPDATE settings SET `value` = ? WHERE `key` = ?",
                    array(str_replace($oldName, $newName, $value), $row['key'])
                );
                $changed[] = $row['key'];
            }
        } catch (Throwable $e) {
            // non-critical
        }

        // 2. static pages written from the seed templates
        try {
            $pages = $db->fetchAll("SELECT id, slug, content_ar FROM pages WHERE content_ar LIKE ?", array('%' . $oldName . '%'));
            foreach ($pages as $page) {
                $content = (string) $page['content_ar'];
                $db->query(
                    "UPDATE pages SET content_ar = ? WHERE id = ?",
                    array(str_replace($oldName, $newName, $content), $page['id'])
                );
                $changed[] = 'page:' . $page['slug'];
            }
        } catch (Throwable $e) {
            // non-critical
        }

        return $changed;
    }

    /**
     * Rewrite the static brand files (PWA manifest + service worker) so they
     * follow the saved name too. Static files cannot read the database, so they
     * are regenerated whenever the settings change.
     *
     * @return array{updated:string[],skipped:string[]}
     */
    public static function syncStaticFiles(string $root): array
    {
        $updated = array();
        $skipped = array();
        $nameAr = self::name();
        $nameEn = self::nameEn();

        $jsonFiles = array(
            '/manifest.json',
            '/manifest.webmanifest',
        );
        foreach ($jsonFiles as $rel) {
            $path = $root . $rel;
            if (!is_file($path) || !is_writable($path)) {
                $skipped[] = $rel;
                continue;
            }
            $raw = (string) file_get_contents($path);
            $new = preg_replace('/("name"\s*:\s*")[^"]*(")/u', '$1' . self::escapeJson($nameAr) . '$2', $raw, 1);
            $new = preg_replace('/("short_name"\s*:\s*")[^"]*(")/u', '$1' . self::escapeJson(self::shortName()) . '$2', (string) $new, 1);
            $new = preg_replace('/("description"\s*:\s*")[^"]*(")/u', '$1' . self::escapeJson(self::description()) . '$2', (string) $new, 1);
            $new = str_replace(
                array('{SITE_NAME}', '{SITE_DESCRIPTION}'),
                array(self::escapeJson($nameAr), self::escapeJson(self::description())),
                (string) $new
            );
            if (is_string($new) && $new !== '' && @file_put_contents($path, $new) !== false) {
                $updated[] = $rel;
            } else {
                $skipped[] = $rel;
            }
        }

        // service worker cache label
        $swPath = $root . '/sw.js';
        if (is_file($swPath) && is_writable($swPath)) {
            $raw = (string) file_get_contents($swPath);
            $new = preg_replace("/(const\\s+CACHE_NAME\\s*=\\s*')[^']*(')/u", '$1' . self::escapeJs($nameEn) . '-v1' . '$2', $raw, 1);
            // notification title keeps whatever was written before: always refresh it
            $new = preg_replace("/(title\\s*:\\s*')[^']*(')/u", '$1' . self::escapeJs($nameAr) . '$2', (string) $new, 1);
            $new = str_replace('{SITE_NAME}', self::escapeJs($nameAr), (string) $new);
            if (is_string($new) && $new !== '' && @file_put_contents($swPath, $new) !== false) {
                $updated[] = '/sw.js';
            } else {
                $skipped[] = '/sw.js';
            }
        } elseif (is_file($swPath)) {
            $skipped[] = '/sw.js';
        }

        return array('updated' => $updated, 'skipped' => $skipped);
    }

    /** Short label for the app icon (max 12 chars keeps it readable). */
    private static function shortName(): string
    {
        $name = preg_replace('/\s*[-–|].*$/u', '', self::name());
        $name = trim($name);
        return mb_substr($name === '' ? self::name() : $name, 0, 12, 'UTF-8');
    }

    private static function escapeJson(string $value): string
    {
        return str_replace(array('\\', '"'), array('\\\\', '\"'), $value);
    }

    private static function escapeJs(string $value): string
    {
        return str_replace(array('\\', "'"), array('\\\\', "\\'"), $value);
    }
}