<?php

/**
 * AppUrl — بناء روابط المنصة دون تكرار البادئة.
 *
 * المشكلة: `app_url($article['featured_image'])` على صورة خارجية جاهزة
 * (`https://cdn...`) كان ينتج `https://site/https://cdn...` فتفشل بطاقات
 * المشاركة. هذا الصنف يترك الروابط المطلقة كما هي ويبني النسبية فقط.
 */
class AppUrl
{
    /**
     * @param string $path المسار النسبي أو الرابط المطلق.
     * @param string $base أصل الموقع (مثل https://example.com) بلا شرطة أخيرة.
     */
    public static function make($path, string $base): string
    {
        $path = (string) $path;

        // رابط مطلق بأي بروتوكول، أو بروتوكول-نسبي (//cdn.example.com/x).
        if ($path !== '' && preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', $path)) {
            return $path;
        }

        $base = rtrim($base, '/');
        $path = ltrim($path, '/');

        return $path === '' ? $base : $base . '/' . $path;
    }

    /** هل القيمة رابط مطلق لا يحتاج بادئة؟ */
    public static function isAbsolute($path): bool
    {
        return preg_match('#^(?:[a-z][a-z0-9+.-]*:)?//#i', (string) $path) === 1;
    }
}
