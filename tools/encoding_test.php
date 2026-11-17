<?php
/**
 * Encoding guard.
 *
 * Bug class: UTF-8 Arabic text was read as cp1256 and written back as UTF-8,
 * so «تمت الترجمة» became «طھظ…طھ ط§ظ„طھط±ط¬ظ…ط©». It hit
 * AggregatorController (fixed in 580a028) and then CronController.
 *
 * Run: php tools/encoding_test.php
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

// UTF-8 bytes that only appear when Arabic is decoded as cp1256:
// ط§ظ„ · طھظ… · ظ…ط · ط¹ظ„ظ‰ · ╪
$signatures = array(
    "\xD8\xB7\xC2\xA7\xD8\xB8\xE2\x80\x9E",
    "\xD8\xB7\xDB\xBE\xD8\xB8\xE2\x80\xA6",
    "\xD8\xB8\xE2\x80\xA6\xD8\xB7",
    "\xD8\xB7\xC2\xB9\xD8\xB8\xE2\x80\x9E\xD8\xB8\xE2\x80\xB0",
    "\xE2\x95\x9C",
);

function looks_mojibake($text, array $signatures)
{
    foreach ($signatures as $sig) {
        if (strpos($text, $sig) !== false) {
            return true;
        }
    }
    return false;
}

echo "--- the detector itself works ---\n";
$sample = "\xD8\xB7\xDB\xBE\xD8\xB8\xE2\x80\xA6\xD8\xB7\xD8\xAA \xD8\xB7\xD8\xA7\xD9\x84\xD8\xAA\xD8\xB1\xD8\xAC\xD9\x85\xD8\xA9";
t('it catches the reported «طھظ…طھ» sample', looks_mojibake($sample, $signatures));
t('it ignores correct Arabic', !looks_mojibake('تمت الترجمة والنشر بنجاح', $signatures));

echo "\n--- no source file carries cp1256 mojibake ---\n";
$extensions = array('php', 'js', 'json', 'css', 'html');
$skipDirs = array('.git', 'vendor', 'node_modules', 'storage', 'uploads');
$self = str_replace('\\', '/', __FILE__);
$scanned = 0;
$bad = array();
$invalidUtf8 = array();
$replacement = array();

$it = new RecursiveIteratorIterator(
    new RecursiveCallbackFilterIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        function ($file) use ($skipDirs) {
            return !($file->isDir() && in_array($file->getFilename(), $skipDirs, true));
        }
    )
);

foreach ($it as $file) {
    if (!$file->isFile()) {
        continue;
    }
    $ext = strtolower($file->getExtension());
    if (!in_array($ext, $extensions, true)) {
        continue;
    }
    $path = str_replace('\\', '/', $file->getPathname());
    if ($path === $self) {
        continue;
    }
    $text = (string) file_get_contents($file->getPathname());
    $scanned++;
    $rel = ltrim(str_replace(str_replace('\\', '/', $root), '', $path), '/');
    if (looks_mojibake($text, $signatures)) {
        $bad[] = $rel;
    }
    if (preg_match('//u', $text) !== 1) {
        $invalidUtf8[] = $rel;
    }
    if (strpos($text, "\xEF\xBF\xBD") !== false) {
        $replacement[] = $rel;
    }
}

t("scanned $scanned code files", $scanned > 100);
t('no file contains Arabic decoded as cp1256', count($bad) === 0, implode(', ', $bad));
t('every file is valid UTF-8', count($invalidUtf8) === 0, implode(', ', $invalidUtf8));
t('no U+FFFD replacement characters', count($replacement) === 0, implode(', ', $replacement));

echo "\n--- the two repaired files read correctly ---\n";
$agg = (string) file_get_contents($root . '/controllers/admin/AggregatorController.php');
$cron = (string) file_get_contents($root . '/controllers/admin/CronController.php');

t('AggregatorController keeps the translate & publish message', strpos($agg, 'تمت الترجمة والنشر بنجاح بعنوان') !== false);
t('AggregatorController keeps the publish-without-translation message', strpos($agg, 'تم النشر الفوري المباشر بنجاح بدون ترجمة') !== false);
t('CronController pause flash is Arabic', strpos($cron, "Session::flash('success', 'تم إرسال أمر الإيقاف المؤقت") !== false);
t('CronController resume flash is Arabic', strpos($cron, "Session::flash('success', 'تم استئناف مهمة النشر التلقائي") !== false);
t('CronController stop flash is Arabic', strpos($cron, "Session::flash('success', 'تم إيقاف مهمة النشر التلقائي") !== false);
t('CronController comments are Arabic', strpos($cron, '// إحصائيات اليوم') !== false && strpos($cron, '// آخر 20 سجل') !== false);
t('CronController status text is Arabic', strpos($cron, "'جلب من مصدر محدد...'") !== false);
t('no cp1256 mojibake left anywhere in CronController', !looks_mojibake($cron, $signatures));

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
