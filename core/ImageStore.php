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
        if (@file_put_contents($full, $data, LOCK_EX) === false) {
            return '';
        }

        return 'uploads/' . $context . '/' . $sub . '/' . $name;
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