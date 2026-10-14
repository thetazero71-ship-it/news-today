<?php

/**
 * ImageStore — الصور تُحفَظ على خادمنا، لا روابط خارجية تموت.
 *
 * المشكلة الجذرية: كانت featured_image رابطاً خارجياً (hotlink). متى حذفه
 * المصدر أو منع الربط الخارجي أو انتهت صلاحية رابطه، سقطت الصورة وظهرت
 * الافتراضية — حتى لأخبار نُشرت بصورة سليمة.
 *
 * الحل: عند النشر تُنزَّل الصورة إلى uploads/articles/ ويُحفَظ المسار المحلي.
 * إن تعذّر التنزيل يبقى الرابط الخارجي كما كان (لا شيء يُفقَد).
 */
class ImageStore
{
    /** أكبر حجم مقبول للصورة (8MB). */
    const MAX_BYTES = 8388608;

    /** أكبر عرض مقبول (1200px يكفي لشاشات الهواتف والبطاقات). */
    const MAX_WIDTH = 1200;

    /** أكبر ارتفاع مقبول (للصور الطولية جداً). */
    const MAX_HEIGHT = 1600;

    /** جودة إعادة الترميز (JPEG/WebP). */
    const QUALITY = 82;

    /** لا يُعاد الترميز إلا بتوفير حقيقي (10% على الأقل). */
    const MIN_SAVING = 0.10;

    /** أصغر أبعاد مقبولة — لرفض بكسلات التتبع والصور التالفة. */
    const MIN_WIDTH = 80;
    const MIN_HEIGHT = 60;

    /** صيغ مسموحة فقط (لا SVG إطلاقاً — قد يحمل سكربتات). */
    const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public static function isRemote(?string $url): bool
    {
        $u = trim((string) $url);
        return $u !== '' && (bool) preg_match('#^https?://#i', $u);
    }

    public static function isLocal(?string $path): bool
    {
        $p = trim((string) $path);
        return $p !== '' && !self::isRemote($p) && strpos($p, 'uploads/') === 0;
    }

    public static function baseDir(): string
    {
        $dir = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__)) . '/uploads/articles';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    /**
     * ينزّل صورة خارجية ويحفظها محلياً.
     *
     * @return string المسار المحلي (uploads/articles/...) أو '' عند الفشل
     */
    public static function storeFromUrl(string $url, string $context = 'articles'): string
    {
        $url = trim($url);
        if (!self::isRemote($url) || strlen($url) > 2000) {
            return '';
        }

        $data = self::download($url);
        if ($data === '') {
            return '';
        }

        $info = self::inspect($data);
        if ($info === null) {
            return '';
        }

        $sub = date('Y') . '/' . date('m');
        $dir = rtrim(self::baseDir(), '/\\') . '/' . $sub;
        if (!is_dir($dir) && !@mkdir($dir, 0777, true)) {
            return '';
        }

        $name = sha1($url . '|' . microtime(true) . '|' . mt_rand()) . '.' . $info['ext'];
        $full = $dir . '/' . $name;

        // ضغط فوري: الحجم الأصلي غالباً ضخم (متوسط 700KB حالياً)
        $data = self::compress($data, $info['mime']) ?? $data;

        if (@file_put_contents($full, $data, LOCK_EX) === false) {
            return '';
        }

        return 'uploads/' . $context . '/' . $sub . '/' . $name;
    }

    /**
     * حساب الأبعاد المستهدفة (دالة صرفة قابلة للاختبار).
     *
     * @return array{width:int,height:int,resize:bool}
     */
    public static function computeSize(int $w, int $h): array
    {
        if ($w <= 0 || $h <= 0) {
            return ['width' => 0, 'height' => 0, 'resize' => false];
        }
        $scale = max(1.0, $w / self::MAX_WIDTH, $h / self::MAX_HEIGHT);
        if ($scale <= 1.0) {
            return ['width' => $w, 'height' => $h, 'resize' => false];
        }
        return [
            'width'  => (int) round($w / $scale),
            'height' => (int) round($h / $scale),
            'resize' => true,
        ];
    }

    /**
     * يعيد ترميز الصورة بأبعاد وجودة الويب، أو null للإبقاء على الأصل.
     *
     * القواعد: بلا GD = الأصل. GIF متحرك = الأصل (إعادة الترميز تقتله).
     * الشفافية (PNG/WebP) محفوظة. لا يُستبدَل إلا بتوفير ≥10%.
     */
    public static function compress(string $data, string $mime): ?string
    {
        if ($data === '' || !function_exists('imagecreatefromstring')) {
            return null;
        }
        if ($mime === 'image/gif' && substr_count($data, "\x21\xF9\x04") > 1) {
            return null; // animated: keep every frame
        }

        $src = @imagecreatefromstring($data);
        if (!$src) {
            return null;
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $target = self::computeSize($w, $h);

        $img = $src;
        if ($target['resize']) {
            $img = imagecreatetruecolor($target['width'], $target['height']);
            if (!$img) {
                imagedestroy($src);
                return null;
            }
            // حافظ على الشفافية
            imagealphablending($img, false);
            imagesavealpha($img, true);
            $transparent = imagecolorallocatealpha($img, 0, 0, 0, 127);
            imagefill($img, 0, 0, $transparent);
            if (!imagecopyresampled($img, $src, 0, 0, 0, 0, $target['width'], $target['height'], $w, $h)) {
                imagedestroy($src);
                imagedestroy($img);
                return null;
            }
        } elseif (in_array($mime, ['image/png', 'image/webp'], true)) {
            // نفس الأبعاد لكن بإعادة ترميز محسّنة — جهّز نسخة نظيفة
            imagealphablending($src, false);
            imagesavealpha($src, true);
        }

        ob_start();
        $ok = false;
        if ($mime === 'image/jpeg') {
            $ok = imagejpeg($img, null, self::QUALITY);
        } elseif ($mime === 'image/png') {
            $ok = imagepng($img, null, 6);
        } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
            $ok = imagewebp($img, null, self::QUALITY);
        } elseif ($mime === 'image/gif' && function_exists('imagegif')) {
            $ok = imagegif($img);
        }
        $out = $ok ? (string) ob_get_clean() : (function () { ob_end_clean(); return ''; })();

        if ($img !== $src) {
            imagedestroy($img);
        }
        imagedestroy($src);

        if (!$ok || $out === '') {
            return null;
        }
        // لا تستبدل إلا بتوفير حقيقي
        if (strlen($out) >= strlen($data) * (1 - self::MIN_SAVING)) {
            return null;
        }
        return $out;
    }

    /**
     * ضغط ملف محلي في مكانه (لإعادة ضغط الصور القديمة لمرة واحدة).
     *
     * @return array{before:int,after:int,saved:bool}
     */
    public static function compressFile(string $fullPath): array
    {
        $before = is_file($fullPath) ? (int) filesize($fullPath) : 0;
        $result = ['before' => $before, 'after' => $before, 'saved' => false];
        if ($before <= 0) {
            return $result;
        }
        $data = @file_get_contents($fullPath);
        if (!is_string($data) || $data === '') {
            return $result;
        }
        $info = self::inspect($data);
        if ($info === null) {
            return $result;
        }
        $out = self::compress($data, $info['mime']);
        if ($out === null) {
            return $result;
        }
        if (@file_put_contents($fullPath, $out, LOCK_EX) === false) {
            return $result;
        }
        clearstatcache(true, $fullPath);
        return ['before' => $before, 'after' => (int) filesize($fullPath), 'saved' => true];
    }

    /**
     * يفحص رابط صورة دون تنزيلها كاملة (للتشخيص والإصلاح).
     *
     * @return array{ok:bool,code:int,mime:string,bytes:int}
     */
    public static function check(string $url): array
    {
        $out = ['ok' => false, 'code' => 0, 'mime' => '', 'bytes' => 0];
        if (!self::isRemote($url)) {
            return $out;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 3,
            CURLOPT_NOBODY         => false,
            CURLOPT_HTTPGET        => true,
            CURLOPT_RANGE          => '0-65535',
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
        ]);
        $body = (string) curl_exec($ch);
        $out['code'] = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $out['mime'] = strtolower(trim((string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE)));
        if (($p = strpos($out['mime'], ';')) !== false) {
            $out['mime'] = trim(substr($out['mime'], 0, $p));
        }
        curl_close($ch);

        if ($out['code'] >= 200 && $out['code'] < 300 && isset(self::ALLOWED_MIME[$out['mime']])) {
            // بعض الخوادم تكذب في الـ Content-Type: نتحقق من البايتات نفسها
            $info = self::inspect($body === '' ? null : $body, true);
            $out['ok'] = $info !== null;
            if ($info !== null) {
                $out['mime'] = $info['mime'];
            }
        }
        return $out;
    }

    /** تنزيل محدود الحجم مع مهلات صارمة. */
    private static function download(string $url): string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => ['Accept: image/avif,image/webp,image/*,*/*;q=0.8'],
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!is_string($body) || $body === '' || $code < 200 || $code >= 300) {
            return '';
        }
        if (strlen($body) > self::MAX_BYTES) {
            return '';
        }
        return $body;
    }

    /**
     * يتحقق أن البايتات صورة حقيقية بالصيغة والأبعاد المقبولة.
     *
     * @return array{mime:string,ext:string,width:int,height:int}|null
     */
    private static function inspect($data, bool $partial = false): ?array
    {
        if (!is_string($data) || $data === '') {
            return null;
        }

        $mime = '';
        if (function_exists('finfo_open')) {
            try {
                $fi = finfo_open(FILEINFO_MIME_TYPE);
                if ($fi) {
                    $mime = strtolower(trim((string) finfo_buffer($fi, $data)));
                    if (($p = strpos($mime, ';')) !== false) {
                        $mime = trim(substr($mime, 0, $p));
                    }
                    finfo_close($fi);
                }
            } catch (Throwable $e) {
                $mime = '';
            }
        }
        if ($mime === '' || !isset(self::ALLOWED_MIME[$mime])) {
            return null;
        }

        $size = @getimagesizefromstring($data);
        if (!is_array($size) || ($size[0] ?? 0) < self::MIN_WIDTH || ($size[1] ?? 0) < self::MIN_HEIGHT) {
            // العيّنة الجزئية (أول 64KB) قد لا تكفي لقراءة الأبعاد
            if ($partial) {
                return ['mime' => $mime, 'ext' => self::ALLOWED_MIME[$mime], 'width' => 0, 'height' => 0];
            }
            return null;
        }

        return [
            'mime'   => $mime,
            'ext'    => self::ALLOWED_MIME[$mime],
            'width'  => (int) $size[0],
            'height' => (int) $size[1],
        ];
    }
}