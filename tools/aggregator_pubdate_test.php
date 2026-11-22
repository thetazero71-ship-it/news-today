<?php
/**
 * Feed publication dates: numeric timestamps and multi-namespace sources.
 *
 * Bug: cards showed "-" (or the fetch time) because `fmt_date()` could not
 * parse a numeric Unix timestamp, and `parseRssItem()` only read `pubDate`.
 * Feeds that publish `dc:date` / `atom:updated` / `published` lost their date.
 *
 * Run: php tools/aggregator_pubdate_test.php
 */

$pass = 0;
$fail = 0;
function t($label, $cond, $extra = '')
{
    global $pass, $fail;
    if ($cond) {
        $pass++;
        echo "PASS $label\n";
    } else {
        $fail++;
        echo "FAIL $label" . ($extra ? "\n     " . $extra : '') . "\n";
    }
}

$root = dirname(__DIR__);
require_once $root . '/core/SiteDate.php';
require_once $root . '/core/FeedDates.php';

echo "--- numeric timestamps are real dates ---\n";
$seconds = SiteDate::parse(1712345678, 'UTC');
t('int seconds parse', $seconds instanceof DateTime && $seconds->format('U') === '1712345678');
$numericString = SiteDate::parse('1712345678', 'UTC');
t('numeric string parses', $numericString instanceof DateTime && $numericString->format('U') === '1712345678');
$millis = SiteDate::parse('1712345678000', 'UTC');
t('13-digit milliseconds are detected', $millis instanceof DateTime && $millis->format('U') === '1712345678');
$float = SiteDate::parse(1712345678.0, 'UTC');
t('float seconds parse', $float instanceof DateTime && $float->format('U') === '1712345678');
$riyadh = SiteDate::parse(1712345678, 'Asia/Riyadh');
t('the site timezone is applied', $riyadh instanceof DateTime
    && $riyadh->format('U') === '1712345678'
    && $riyadh->getOffset() === 10800,
    $riyadh ? $riyadh->format('Y-m-d H:i P') : 'null');

echo "\n--- text dates keep working ---\n";
$iso = SiteDate::parse('2026-11-20T10:30:00Z', 'UTC');
t('ISO 8601 parses', $iso instanceof DateTime && $iso->format('Y-m-d H:i') === '2026-11-20 10:30');
$rfc = SiteDate::parse('Thu, 20 Nov 2026 10:00:00 GMT', 'UTC');
t('RFC 2822 parses', $rfc instanceof DateTime && $rfc->format('Y-m-d H:i') === '2026-11-20 10:00');
t('empty values are null', SiteDate::parse('') === null && SiteDate::parse(null) === null);
t('junk values are null', SiteDate::parse('not-a-date') === null);
$fromObject = SiteDate::parse(new DateTime('2026-11-20 12:00:00', new DateTimeZone('UTC')), 'UTC');
t('DateTime objects pass through', $fromObject instanceof DateTime && $fromObject->format('H:i') === '12:00');

echo "\n--- RSS items: every namespace is read ---\n";
$rssPubDate = simplexml_load_string(
    '<rss><channel><item><pubDate>Thu, 20 Nov 2026 09:00:00 GMT</pubDate></item></channel></rss>'
);
t('pubDate is read', FeedDates::fromRssItem($rssPubDate->channel->item[0]) === 'Thu, 20 Nov 2026 09:00:00 GMT');

$rssDc = simplexml_load_string(
    '<rss xmlns:dc="http://purl.org/dc/elements/1.1/"><channel><item>'
    . '<dc:date>2026-11-20T08:15:00Z</dc:date></item></channel></rss>'
);
t('dc:date is read', FeedDates::fromRssItem($rssDc->channel->item[0]) === '2026-11-20T08:15:00Z');

$rssAtom = simplexml_load_string(
    '<rss xmlns:atom="http://www.w3.org/2005/Atom"><channel><item>'
    . '<atom:updated>2026-11-20T07:00:00Z</atom:updated></item></channel></rss>'
);
t('atom:updated inside RSS is read', FeedDates::fromRssItem($rssAtom->channel->item[0]) === '2026-11-20T07:00:00Z');

$rssPlain = simplexml_load_string(
    '<rss><channel><item><published>2026-11-20T06:00:00Z</published>'
    . '<updated>2026-11-20T05:00:00Z</updated></item></channel></rss>'
);
t('published wins over updated', FeedDates::fromRssItem($rssPlain->channel->item[0]) === '2026-11-20T06:00:00Z');

$rssEmpty = simplexml_load_string('<rss><channel><item><title>x</title></item></channel></rss>');
t('a missing date is an empty string', FeedDates::fromRssItem($rssEmpty->channel->item[0]) === '');

echo "\n--- Atom entries ---\n";
$atom = simplexml_load_string(
    '<feed xmlns="http://www.w3.org/2005/Atom"><entry>'
    . '<published>2026-11-20T04:00:00Z</published><updated>2026-11-20T03:00:00Z</updated>'
    . '</entry></feed>'
);
t('atom published is read', FeedDates::fromAtomEntry($atom->entry[0]) === '2026-11-20T04:00:00Z');

$atomDc = simplexml_load_string(
    '<feed xmlns="http://www.w3.org/2005/Atom" xmlns:dc="http://purl.org/dc/elements/1.1/"><entry>'
    . '<dc:date>2026-11-20T02:00:00Z</dc:date></entry></feed>'
);
t('atom dc:date is read', FeedDates::fromAtomEntry($atomDc->entry[0]) === '2026-11-20T02:00:00Z');

echo "\n--- the feed itself is the self-heal fallback ---\n";
$channel = simplexml_load_string(
    '<rss><channel><lastBuildDate>Thu, 20 Nov 2026 01:00:00 GMT</lastBuildDate></channel></rss>'
);
t('RSS lastBuildDate is read', FeedDates::fromChannel($channel) === 'Thu, 20 Nov 2026 01:00:00 GMT');
$atomFeed = simplexml_load_string(
    '<feed xmlns="http://www.w3.org/2005/Atom"><updated>2026-11-19T22:00:00Z</updated></feed>'
);
t('Atom feed updated is read', FeedDates::fromChannel($atomFeed) === '2026-11-19T22:00:00Z');
t('pick skips blank candidates', FeedDates::pick(['', '  ', '2026-11-20']) === '2026-11-20');

echo "\n--- the controller wires it up ---\n";
$controller = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
$index = (string) file_get_contents($root . '/index.php');
$cron = (string) file_get_contents($root . '/cron/rss_auto_publish.php');
t('RSS parsing uses FeedDates', strpos($controller, 'FeedDates::fromRssItem($item)') !== false);
t('Atom parsing uses FeedDates', strpos($controller, 'FeedDates::fromAtomEntry($entry)') !== false);
t('the channel date is the fallback', strpos($controller, 'FeedDates::fromChannel($xml)') !== false);
t('the old pubDate-only read is gone', strpos($controller, '(string) $item->pubDate') === false);
t('a safe formatter prevents "-"', strpos($controller, 'private function formatFeedDate') !== false
    && strpos($controller, "fmt_date(\$raw, 'Y-m-d H:i', '')") !== false);
t('both parsers receive the fallback', strpos($controller, 'parseRssItem($entry, $feedDate)') !== false
    && strpos($controller, 'parseAtomItem($entry, $feedDate)') !== false);
t('index.php delegates to SiteDate', strpos($index, 'SiteDate::parse($dateStr, site_timezone())') !== false);
t('the cron uses the same extractor', strpos($cron, 'FeedDates::fromRssItem($item)') !== false
    && strpos($cron, 'FeedDates::fromAtomEntry($entry)') !== false);

echo "\n--- admin cards render the date left-to-right in monospace ---\n";
$aggView = (string) file_get_contents($root . '/views/admin/aggregator/index.php');
$favView = (string) file_get_contents($root . '/views/admin/aggregator/favorites.php');
t('aggregator cards are dir=ltr + monospace', strpos($aggView, 'dir="ltr" class="font-monospace"') !== false);
t('favorite cards are dir=ltr + monospace', strpos($favView, 'dir="ltr"') !== false
    && strpos($favView, 'font-monospace') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
