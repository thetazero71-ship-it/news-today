<?php

/**
 * UserActivityHelper — تشخيص نشاط المستخدم وجلساته.
 *
 * يجمع ما هو موجود فعلاً في القاعدة (user_sessions + activity_logs) ويعرضه
 * في شاشة تعديل المستخدم: آخر IP والبلد، الجهاز والمتصفح، سجل الجلسات،
 * وآخر عمليات التدقيق. لا يعتمد على أي خدمة خارجية إلزامية: كشف البلد
 * يستعمل ترويسات CDN إن وُجدت، ثم كاش محلي، ثم استعلاماً خفيفاً اختيارياً.
 */
class UserActivityHelper
{
    /** مدة كاش نتيجة Geolocation (ثوانٍ). */
    const GEO_TTL = 604800;

    /**
     * إنشاء جدول الجلسات إن لم يكن موجوداً (قواعد بيانات قديمة).
     */
    public static function ensureTable($db): void
    {
        try {
            $db->query(
                "CREATE TABLE IF NOT EXISTS user_sessions (
                    id int(10) unsigned NOT NULL AUTO_INCREMENT,
                    user_id int(10) unsigned NOT NULL,
                    ip_address varchar(45) DEFAULT NULL,
                    user_agent varchar(500) DEFAULT NULL,
                    device_type varchar(30) DEFAULT NULL,
                    location varchar(190) DEFAULT NULL,
                    login_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    logout_at timestamp NULL DEFAULT NULL,
                    PRIMARY KEY (id),
                    KEY idx_user_sessions_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (Throwable $e) {
            // القاعدة قد تمنع CREATE؛ عندها نكتفي بالجدول الموجود إن وُجد.
        }
    }

    /**
     * تسجيل دخول جديد بجهازه وبصمته.
     */
    public static function recordLogin($db, int $userId, ?string $ip = null, ?string $ua = null): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            self::ensureTable($db);
            $ip = trim((string) ($ip ?? ($_SERVER['REMOTE_ADDR'] ?? '')));
            $ua = trim((string) ($ua ?? ($_SERVER['HTTP_USER_AGENT'] ?? '')));
            $parsed = self::parseUserAgent($ua);
            $db->query(
                'INSERT INTO user_sessions (user_id, ip_address, user_agent, device_type, location) VALUES (?, ?, ?, ?, ?)',
                [$userId, mb_substr($ip, 0, 45, 'UTF-8'), mb_substr($ua, 0, 500, 'UTF-8'), $parsed['device'], self::countryForIp($ip)]
            );
        } catch (Throwable $e) {
            // التشخيص لا يجوز أن يمنع تسجيل الدخول.
        }
    }

    /**
     * إغلاق آخر جلسة مفتوحة للمستخدم.
     */
    public static function recordLogout($db, int $userId): void
    {
        if ($userId <= 0) {
            return;
        }
        try {
            $db->query(
                'UPDATE user_sessions SET logout_at = CURRENT_TIMESTAMP WHERE user_id = ? AND logout_at IS NULL ORDER BY id DESC LIMIT 1',
                [$userId]
            );
        } catch (Throwable $e) {
            // تجاهل: قد يكون الجدول غير موجود بعد.
        }
    }

    /**
     * ملخّص كامل للعرض في شاشة المستخدم.
     *
     * @return array{
     *   last_ip: string, country: string, device: string, browser: string, os: string,
     *   last_login: string, last_seen: string, login_count: int,
     *   sessions: array<int, array>, logs: array<int, array>
     * }
     */
    public static function summary($db, int $userId): array
    {
        self::ensureTable($db);

        $sessions = [];
        $logs = [];
        try {
            $sessions = $db->fetchAll(
                'SELECT id, ip_address, user_agent, device_type, location, login_at, logout_at
                 FROM user_sessions WHERE user_id = ? ORDER BY id DESC LIMIT 10',
                [$userId]
            );
        } catch (Throwable $e) {
            $sessions = [];
        }
        try {
            $logs = $db->fetchAll(
                'SELECT action, description, ip_address, user_agent, created_at
                 FROM activity_logs WHERE user_id = ? ORDER BY id DESC LIMIT 12',
                [$userId]
            );
        } catch (Throwable $e) {
            $logs = [];
        }

        $lastSession = $sessions[0] ?? null;
        $lastLog = $logs[0] ?? null;
        $lastIp = trim((string) ($lastSession['ip_address'] ?? ($lastLog['ip_address'] ?? '')));
        $lastUa = (string) ($lastSession['user_agent'] ?? ($lastLog['user_agent'] ?? ''));
        $parsed = self::parseUserAgent($lastUa);

        $loginCount = 0;
        try {
            $row = $db->fetch('SELECT login_count FROM users WHERE id = ? LIMIT 1', [$userId]);
            $loginCount = (int) ($row['login_count'] ?? 0);
        } catch (Throwable $e) {
            $loginCount = count($sessions);
        }

        return [
            'last_ip' => $lastIp,
            'country' => (string) ($lastSession['location'] ?? '') ?: self::countryForIp($lastIp),
            'device' => (string) ($lastSession['device_type'] ?? '') ?: $parsed['device'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'last_login' => (string) ($lastSession['login_at'] ?? ''),
            'last_seen' => (string) ($lastLog['created_at'] ?? ($lastSession['login_at'] ?? '')),
            'login_count' => $loginCount,
            'sessions' => $sessions,
            'logs' => $logs,
        ];
    }

    /**
     * تحليل سلسلة User-Agent إلى نظام/متصفح/نوع جهاز.
     *
     * @return array{os: string, browser: string, device: string}
     */
    public static function parseUserAgent(string $ua): array
    {
        $ua = trim($ua);
        if ($ua === '') {
            return ['os' => 'غير معروف', 'browser' => 'غير معروف', 'device' => 'غير معروف'];
        }

        $os = 'نظام آخر';
        if (preg_match('/Windows NT 10/i', $ua)) $os = 'Windows 10/11';
        elseif (preg_match('/Windows NT/i', $ua)) $os = 'Windows';
        elseif (preg_match('/Android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iPhone|iPad|iPod/i', $ua)) $os = 'iOS';
        elseif (preg_match('/Mac OS X/i', $ua)) $os = 'macOS';
        elseif (preg_match('/CrOS/i', $ua)) $os = 'ChromeOS';
        elseif (preg_match('/Linux/i', $ua)) $os = 'Linux';

        $browser = 'متصفح آخر';
        if (preg_match('/Edg\//i', $ua)) $browser = 'Microsoft Edge';
        elseif (preg_match('/OPR\/|Opera/i', $ua)) $browser = 'Opera';
        elseif (preg_match('/SamsungBrowser/i', $ua)) $browser = 'Samsung Internet';
        elseif (preg_match('/Firefox\//i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/Chrome\/|CriOS/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/Safari\//i', $ua)) $browser = 'Safari';
        elseif (preg_match('/bot|crawl|spider|slurp|bingpreview/i', $ua)) $browser = 'زاحف آلي';

        $device = 'سطح مكتب';
        if (preg_match('/bot|crawl|spider|slurp|bingpreview/i', $ua)) $device = 'زاحف آلي';
        elseif (preg_match('/iPad|Tablet/i', $ua)) $device = 'جهاز لوحي';
        elseif (preg_match('/Mobi|iPhone|Android.*Mobile/i', $ua)) $device = 'هاتف';

        return ['os' => $os, 'browser' => $browser, 'device' => $device];
    }

    /**
     * بلد الـ IP: ترويسات CDN أولاً، ثم كاش، ثم استعلام خفيف اختياري.
     * يفشل بهدوء ويعيد «غير معروف» بدل تعطيل الصفحة.
     */
    public static function countryForIp(string $ip): string
    {
        $ip = trim($ip);
        if ($ip === '') {
            return 'غير معروف';
        }
        if (self::isPrivateIp($ip)) {
            return 'شبكة محلية';
        }

        foreach (['HTTP_CF_IPCOUNTRY', 'HTTP_X_COUNTRY_CODE', 'GEOIP_COUNTRY_CODE'] as $header) {
            $code = trim((string) ($_SERVER[$header] ?? ''));
            if ($code !== '' && strtoupper($code) !== 'XX') {
                return strtoupper($code);
            }
        }

        $cacheKey = 'geoip_' . md5($ip);
        if (class_exists('Cache')) {
            $cached = Cache::get($cacheKey, null);
            if (is_string($cached)) {
                return $cached;
            }
        }

        $country = '';
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            $context = stream_context_create(['http' => ['timeout' => 2, 'header' => "User-Agent: TechNews/1.0\r\n"]]);
            $raw = @file_get_contents('https://ip-api.com/json/' . rawurlencode($ip) . '?fields=status,country,countryCode', false, $context);
            if (is_string($raw) && $raw !== '') {
                $data = json_decode($raw, true);
                if (is_array($data) && ($data['status'] ?? '') === 'success') {
                    $country = trim((string) ($data['country'] ?? $data['countryCode'] ?? ''));
                }
            }
        }
        if ($country === '') {
            $country = 'غير معروف';
        }

        if (class_exists('Cache')) {
            Cache::set($cacheKey, $country, self::GEO_TTL);
        }

        return $country;
    }

    public static function isPrivateIp(string $ip): bool
    {
        if ($ip === '' || $ip === '::1') {
            return true;
        }
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false;
    }
}
