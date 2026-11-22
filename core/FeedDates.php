<?php

/**
 * FeedDates — استخراج تاريخ النشر من أي شكل خلاصة RSS/Atom/RDF.
 *
 * المشكلة التي يحلها: كانت الخلاصات تُقرأ من `pubDate` فقط. خلاصات كثيرة
 * (RDF وAtom ومواقع إخبارية) تستخدم `dc:date` أو `updated` أو `published`
 * أو `date`، فكان التاريخ يضيع وتظهر البطاقة بتاريخ الجلب بدل تاريخ الخبر.
 *
 * الترتيب: نجرّب كل الحقول المعروفة، ثم تاريخ الخلاصة نفسه (lastBuildDate /
 * updated) كتعافٍ ذاتي بدل الاعتماد على «الآن».
 */
class FeedDates
{
    /** حقول RSS 2.0 / RDF / Atom المدعومة بالترتيب. */
    private const ITEM_KEYS = ['pubDate', 'published', 'updated', 'date', 'created'];

    /** بادئات مساحات الأسماء المدعومة (تُقرأ بوضع البادئة في SimpleXML). */
    private const PREFIXES = ['dc', 'atom', 'rdf'];

    /**
     * تاريخ عنصر RSS 2.0 أو عنصر RDF.
     */
    public static function fromRssItem(SimpleXMLElement $item): string
    {
        $candidates = [];

        foreach (self::ITEM_KEYS as $key) {
            if (isset($item->{$key})) {
                $candidates[] = (string) $item->{$key};
            }
        }

        // dc:date / dc:created — شائع في RDF وخلاصات ووردبريس.
        foreach (['date', 'created'] as $key) {
            $candidates[] = (string) self::child($item, 'dc', $key);
        }

        // atom:updated / atom:published داخل عنصر RSS (خلاصات مختلطة).
        foreach (['updated', 'published'] as $key) {
            $candidates[] = (string) self::child($item, 'atom', $key);
        }

        return self::pick($candidates);
    }

    /**
     * تاريخ عنصر Atom.
     */
    public static function fromAtomEntry(SimpleXMLElement $entry): string
    {
        $candidates = [];

        foreach (['published', 'updated', 'date', 'created'] as $key) {
            if (isset($entry->{$key})) {
                $candidates[] = (string) $entry->{$key};
            }
        }

        foreach (['date', 'created'] as $key) {
            $candidates[] = (string) self::child($entry, 'dc', $key);
        }

        return self::pick($candidates);
    }

    /**
     * تاريخ الخلاصة نفسها — تعافٍ ذاتي عند غياب تاريخ العنصر.
     * يُرجع نصاً فارغاً إن لم يوجد، ليتولى المستدعي قرار البديل الأخير.
     */
    public static function fromChannel(SimpleXMLElement $xml): string
    {
        $candidates = [];

        if (isset($xml->channel)) {
            foreach (['lastBuildDate', 'pubDate', 'updated'] as $key) {
                if (isset($xml->channel->{$key})) {
                    $candidates[] = (string) $xml->channel->{$key};
                }
            }
        }

        foreach (['updated', 'published', 'date'] as $key) {
            if (isset($xml->{$key})) {
                $candidates[] = (string) $xml->{$key};
            }
        }

        foreach (['date', 'created'] as $key) {
            $candidates[] = (string) self::child($xml, 'dc', $key);
        }

        return self::pick($candidates);
    }

    /**
     * أول قيمة غير فارغة من قائمة مرشّحين.
     *
     * @param array<int, mixed> $candidates
     */
    public static function pick(array $candidates): string
    {
        foreach ($candidates as $candidate) {
            $value = trim((string) $candidate);
            if ($value !== '') {
                return $value;
            }
        }

        return '';
    }

    /**
     * قراءة عنصر في مساحة أسماء معيّنة مع تجاهل الأخطاء.
     * يُمرَّر البادئة (prefix) لأن SimpleXML يرفض URI عند تفعيل وضع البادئة.
     */
    private static function child(SimpleXMLElement $node, string $prefix, string $key): ?SimpleXMLElement
    {
        if (!in_array($prefix, self::PREFIXES, true)) {
            return null;
        }

        $children = $node->children($prefix, true);
        if (!$children || !isset($children->{$key})) {
            return null;
        }

        return $children->{$key};
    }
}
