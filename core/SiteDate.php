<?php

/**
 * SiteDate — تحويل أي قيمة تاريخ إلى وقت الموقع المحلي.
 *
 * المشكلة التي يحلها: بعض الخلاصات وواجهات API ترسل التاريخ كطابع Unix
 * رقمي (مثل 1712345678) بدل نص ISO/RFC. تمرير الرقم إلى DateTime كان
 * يفشل أو يُفسَّر خطأً، فتظهر البطاقة بـ «-» أو بتاريخ غير صحيح.
 * هذا الصنف يقبل الأنواع كافة (int/float/رقم نصي/ISO/RFC/DateTime) ويُعيد
 * DateTime بتوقيت الموقع، أو null إن تعذّر التحويل.
 */
class SiteDate
{
    /**
     * @param mixed  $value    القيمة الخام (int/float/string/DateTimeInterface/null).
     * @param string $timezone منطقة الموقع الزمنية (مثل Asia/Riyadh).
     */
    public static function parse($value, string $timezone = 'UTC'): ?DateTime
    {
        if ($value === null || $value === false || $value === '') {
            return null;
        }

        // كائن تاريخ جاهز: انسخه ثم حوّله لتوقيت الموقع.
        if ($value instanceof DateTimeInterface) {
            $dt = DateTime::createFromFormat('U.u', $value->format('U.u'));
            if (!$dt) {
                $dt = new DateTime('@' . $value->getTimestamp());
            }
        } elseif (is_int($value) || is_float($value)) {
            $dt = self::fromUnix((float) $value);
        } else {
            $raw = trim((string) $value);
            if ($raw === '' || $raw === '0') {
                return null;
            }
            // أرقام نصية = طابع Unix (ثوانٍ أو مللي ثانية).
            if (preg_match('/^-?\d+(?:\.\d+)?$/', $raw) === 1) {
                $dt = self::fromUnix((float) $raw);
            } else {
                // بعض الخلاصات تكتب يوم الأسبوع خطأً، وPHP قد يزيح التاريخ
                // ليطابقه (الخميس 20 نوفمبر 2026 → 26 نوفمبر). نحذف اليوم.
                $raw = preg_replace('/^\s*(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)[a-z]*,?\s+/i', '', $raw);
                try {
                    $dt = new DateTime($raw, new DateTimeZone('UTC'));
                } catch (Throwable $e) {
                    return null;
                }
            }
        }

        try {
            $dt->setTimezone(new DateTimeZone($timezone !== '' ? $timezone : 'UTC'));
        } catch (Throwable $e) {
            $dt->setTimezone(new DateTimeZone('UTC'));
        }

        return $dt;
    }

    /**
     * هل يمكن تحويل القيمة إلى تاريخ؟
     */
    public static function isValid($value, string $timezone = 'UTC'): bool
    {
        return self::parse($value, $timezone) !== null;
    }

    /**
     * طابع Unix → DateTime بتوقيت UTC (يُحوَّل لاحقاً في parse).
     * يدعم المللي ثانية (13 خانة) تلقائياً.
     */
    private static function fromUnix(float $timestamp): DateTime
    {
        // 1e11 ثانية = سنة 5138؛ أي رقم أكبر منه هو مللي ثانية.
        if (abs($timestamp) > 1e11) {
            $timestamp = $timestamp / 1000;
        }

        return new DateTime('@' . (int) round($timestamp));
    }
}
