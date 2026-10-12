<?php
/** فحص مؤقت: تحديث نموذج Gemini ثم اختبار ترجمة حقيقية (يُحذف بعده) */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Settings.php';
require_once __DIR__ . '/core/AiTranslator.php';

header('Content-Type: text/plain; charset=utf-8');
$db = new Database();

$db->query("UPDATE settings SET `value` = :v WHERE `key` = 'gemini_model'", [':v' => 'gemini-3.5-flash-lite']);
Settings::clear();
echo 'gemini_model = ' . Settings::get('gemini_model') . "\n";
echo 'ai_provider  = ' . Settings::get('ai_provider') . "\n";

$t0 = microtime(true);
$r = AiTranslator::translateArticle(
    'Markets rally as central bank cuts rates',
    'The central bank cut interest rates today, analysts said.'
);
echo 'time = ' . round(microtime(true) - $t0, 1) . "s\n";
echo 'used = ' . ($r['diagnostics']['used_provider'] ?? '?') . "\n";
echo 'title_ar = ' . mb_substr((string) ($r['title_ar'] ?? ''), 0, 80) . "\n";
echo 'translated_flag = ' . var_export($r['translated'] ?? null, true) . "\n";