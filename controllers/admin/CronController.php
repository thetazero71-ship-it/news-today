<?php

class CronController extends AdminController
{
 public function index()
 {
 $this->guardPermission('cron.view');
 $db = new Database();

 // ط¥ط­طµط§ط¦ظٹط§طھ ط§ظ„ظٹظˆظ…
 $todayStats = $db->fetch("
 SELECT 
 COUNT(*) as total_runs,
 SUM(articles_published) as total_published,
 SUM(articles_skipped) as total_skipped,
 SUM(articles_failed) as total_failed,
 MAX(created_at) as last_run
 FROM cron_logs
 WHERE DATE(created_at) = CURDATE()
 ");

 // ط¢ط®ط± 20 ط³ط¬ظ„
 $logs = $db->fetchAll("
 SELECT * FROM cron_logs ORDER BY created_at DESC LIMIT 20
 ");

 // ط¥ط¬ظ…ط§ظ„ظٹ ط§ظ„ظ…ظ‚ط§ظ„ط§طھ ط§ظ„ظ…ظ†ط´ظˆط±ط© طھظ„ظ‚ط§ط¦ظٹط§ظ‹ (ظ„ظ‡ط§ source_url)
 $autoTotal = $db->fetch("SELECT COUNT(*) as cnt FROM articles WHERE source_url IS NOT NULL AND source_url != ''");

 // ط£ط­ط¯ط« ط§ظ„ظ…ظ‚ط§ظ„ط§طھ ط§ظ„ظ…ظ†ط´ظˆط±ط© طھظ„ظ‚ط§ط¦ظٹط§ظ‹
 $recentAutoArticles = $db->fetchAll("
 SELECT a.id, a.title, a.title_ar, a.slug, a.source_name, a.source_url, a.category_id, a.created_at,
 c.name as category_name, c.slug as category_slug
 FROM articles a 
 LEFT JOIN categories c ON c.id = a.category_id 
 WHERE a.source_url IS NOT NULL AND a.source_url != ''
 ORDER BY a.id DESC 
 LIMIT 15
 ");

 // ظ‚ط§ط¦ظ…ط© ط¬ظ…ظٹط¹ ظ…طµط§ط¯ط± ط§ظ„ظ€ RSS ط§ظ„ظ…طھط§ط­ط© ظ…ط¹ طھطµظ†ظٹظپط§طھظ‡ط§
 $allSources = $db->fetchAll("
 SELECT s.id, s.name, s.url, s.category_id, s.last_fetched_at,
 c.name as category_name, c.slug as category_slug
 FROM rss_sources s
 LEFT JOIN categories c ON c.id = s.category_id
 ORDER BY s.id ASC
 ");

 // طھط´ط؛ظٹظ„ ظٹط¯ظˆظٹ ظ„ط¯ظˆط±ط© ط¬ط¯ظٹط¯ط© (ط§ظ„ظƒظ„ ط£ظˆ ظ…طµط§ط¯ط± ظ…ط­ط¯ط¯ط©)
 $manualResult = null;
 if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'run_now') {
 $cronFile = dirname(dirname(dirname(__FILE__))) . '/cron/rss_auto_publish.php';
 if (file_exists($cronFile)) {
 $statusFile = dirname(dirname(dirname(__FILE__))) . '/storage/cron_status.json';

 // ط¥ط؛ظ„ط§ظ‚ ظ‚ظپظ„ ط§ظ„ط¬ظ„ط³ط© ظپظˆط±ط§ظ‹ ظ„ظ…ظ†ط¹ طھط¹ظ„ظٹظ‚ ط§ظ„ظ…طھطµظپط­ ظ†ظ‡ط§ط¦ظٹط§ظ‹
 if (session_status() === PHP_SESSION_ACTIVE) {
 session_write_close();
 }

 // ظپط­طµ ط§ظ„ظ…طµط§ط¯ط± ط§ظ„ظ…ط®طھط§ط±ط©
 $sourceIds = [];
 if (!empty($_POST['source_ids']) && is_array($_POST['source_ids'])) {
 $sourceIds = array_filter(array_map('intval', $_POST['source_ids']));
 } elseif (!empty($_POST['single_source_id'])) {
 $sourceIds = [(int)$_POST['single_source_id']];
 }

  $process = self::spawnDetached($cronFile, $sourceIds);
  $pid = null;
  if (is_array($process) && isset($process['pid'])) {
  $pid = $process['pid'];
  }

 $totalSelected = !empty($sourceIds) ? count($sourceIds) : count($allSources);
 $statusText = !empty($sourceIds) 
 ? (count($sourceIds) === 1 ? 'ط¬ظ„ط¨ ظ…ظ† ظ…طµط¯ط± ظ…ط­ط¯ط¯...' : 'ط¬ظ„ط¨ ظ…ظ† ' . count($sourceIds) . ' ظ…طµط§ط¯ط± ظ…ط­ط¯ط¯ط©...') 
 : 'ط¨ط¯ط، ط¯ظˆط±ط© ط¬ط¯ظٹط¯ط© (ظƒط§ظپط© ط§ظ„ظ…طµط§ط¯ط±)...';

 @file_put_contents($statusFile, json_encode([
 'state' => 'running',
 'is_running' => true,
 'pid' => $pid,
 'current_source' => $statusText,
 'selected_source_ids' => $sourceIds,
 'progress_percent' => 2,
 'sources_processed' => 0,
 'total_sources' => $totalSelected,
 'articles_published' => 0,
 'articles_skipped' => 0,
 'articles_failed' => 0,
 'timestamp' => time()
 ], JSON_UNESCAPED_UNICODE));
 
 $manualResult = 'started';
 } else {
 $manualResult = 'not_found';
 }

 if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode([
 'ok' => ($manualResult === 'started'), 
 'result' => $manualResult, 
 'pid' => $pid ?? null,
 'source_count'=> !empty($sourceIds) ? count($sourceIds) : count($allSources)
 ]);
 exit;
 }
 }

$this->view('admin/cron/index', [
  'todayStats' => $todayStats,
  'logs' => $logs,
  'autoTotal' => (int) ($autoTotal['cnt'] ?? 0),
  'manualResult' => $manualResult,
  'recentAutoArticles' => $recentAutoArticles,
  'allSources' => $allSources,
  ]);
  }

 /**
  * تشغيل الدورة في الخلفية دون حجز طلب المستخدم.
  *
  * الاستضافة المشتركة تمنع proc_open/popen، لذلك يُطلب رابط السكربت لنفسه
  * عبر اتصال قصيرcomed ثم يُغلق الاتصال فوراً، فتعمل الدورة في الخلفية.
  *
  * @return array{pid:int|null,method:string}|null
  */
 private static function spawnDetached(string $cronFile, array $sourceIds): ?array
 {
  $secret = defined('CRON_SECRET') ? (string) CRON_SECRET : '';
  if ($secret === '') {
  $secret = 'cron_tnp_2026_secure_key';
  }
  $url = app_url('cron/rss_auto_publish.php?secret=' . rawurlencode($secret));
  if (!empty($sourceIds)) {
  $url .= '&sources=' . rawurlencode(implode(',', array_map('intval', $sourceIds)));
  }

  // 1) المحاولة عبر process control إن كان مسموحاً (خادم خاص / VPS)
  if (function_exists('proc_open')) {
  try {
  $phpBin = (defined('PHP_BINARY') && is_file(PHP_BINARY) && !preg_match('/(httpd|apache|nginx|fpm)/i', PHP_BINARY))
  ? PHP_BINARY : 'php';
  $cmd = escapeshellarg($phpBin) . ' -d max_execution_time=0 ' . escapeshellarg($cronFile);
  if (!empty($sourceIds)) {
  $cmd .= ' --sources=' . escapeshellarg(implode(',', array_map('intval', $sourceIds)));
  }
  $null = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') ? 'NUL' : '/dev/null';
  $desc = array(0 => array('file', $null, 'r'), 1 => array('file', $null, 'w'), 2 => array('file', $null, 'w'));
  $proc = @proc_open($cmd, $desc, $pipes, dirname($cronFile));
  if (is_resource($proc)) {
  $status = @proc_get_status($proc);
  return array('pid' => $status['pid'] ?? null, 'method' => 'proc_open');
  }
  } catch (Throwable $e) {
  // محظورة على هذه الاستضافة — نتابع بالطريقة الثانية
  }
  }

  // الطلب الذاتي يُفتح ثم يُغلق فوراً، فتعمل الدورة في الخلفية
  $opts = stream_context_create(array(
  'http' => array(
  'method' => 'GET',
  'timeout' => 2,
  'ignore_errors' => true,
  ),
  ));
  $fp = @fopen($url, 'r', false, $opts);
  if ($fp) {
  @fclose($fp);
  }
  return array('pid' => null, 'method' => 'http');
 }

 public function pause()
 {
 $this->postGuardPermission('cron.manage');
 $root = dirname(dirname(dirname(__FILE__)));
 $statusFile = $root . '/storage/cron_status.json';

 if (file_exists($statusFile)) {
 $json = @json_decode(file_get_contents($statusFile), true);
 if (is_array($json)) {
 $json['state'] = 'paused';
 $json['is_running'] = false;
 $json['timestamp'] = time();
 @file_put_contents($statusFile, json_encode($json, JSON_UNESCAPED_UNICODE));
 }
 }

 if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['ok' => true, 'state' => 'paused']);
 exit;
 }

 Session::flash('success', 'طھظ… ط¥ط±ط³ط§ظ„ ط£ظ…ط± ط§ظ„ط¥ظٹظ‚ط§ظپ ط§ظ„ظ…ط¤ظ‚طھ (Pause) ط¨ظ†ط¬ط§ط­.');
 return $this->redirect('admin/cron');
 }

 public function resume()
 {
 $this->postGuardPermission('cron.manage');
 $root = dirname(dirname(dirname(__FILE__)));
 $cronFile = $root . '/cron/rss_auto_publish.php';
 $statusFile = $root . '/storage/cron_status.json';

 $json = [];
 if (file_exists($statusFile)) {
 $json = @json_decode(file_get_contents($statusFile), true) ?: [];
 }

 // ط¥ط؛ظ„ط§ظ‚ ظ‚ظپظ„ ط§ظ„ط¬ظ„ط³ط© ظپظˆط±ط§ظ‹ ظ„ظ…ظ†ط¹ طھط¹ظ„ظٹظ‚ ط§ظ„ظ…طھطµظپط­
 if (session_status() === PHP_SESSION_ACTIVE) {
 session_write_close();
 }

$pid = null;
  if (file_exists($cronFile)) {
  $spawned = self::spawnDetached($cronFile, []);
  $pid = is_array($spawned) ? ($spawned['pid'] ?? null) : null;
  }

 $json['state'] = 'running';
 $json['is_running'] = true;
 $json['pid'] = $pid;
 $json['current_source'] = 'ط¬ط§ط±ظٹ ط§ظ„ط§ط³طھط¦ظ†ط§ظپ...';
 $json['timestamp'] = time();
 @file_put_contents($statusFile, json_encode($json, JSON_UNESCAPED_UNICODE));

 if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['ok' => true, 'state' => 'running', 'pid' => $pid]);
 exit;
 }

 Session::flash('success', 'طھظ… ط§ط³طھط¦ظ†ط§ظپ ظ…ظ‡ظ…ط© ط§ظ„ظ†ط´ط± ط§ظ„طھظ„ظ‚ط§ط¦ظٹ ط¨ظ†ط¬ط§ط­ ظ…ظ† ط­ظٹط« طھظˆظ‚ظپطھ.');
 return $this->redirect('admin/cron');
 }

 public function statusJson()
 {
$this->guardPermission('cron.view');

 // طھط­ط±ظٹط± ظ‚ظپظ„ ط§ظ„ط¬ظ„ط³ط© ظ…ط¨ط§ط´ط±ط© ط£ط«ظ†ط§ط، ط§ط³طھط¹ظ„ط§ظ… ط§ظ„ظ€ AJAX ط§ظ„ط­ظٹ
 if (session_status() === PHP_SESSION_ACTIVE) {
 session_write_close();
 }

 $root = dirname(dirname(dirname(__FILE__)));
 $statusFile = $root . '/storage/cron_status.json';
 $db = new Database();

 $statusData = [
 'is_running' => false,
 'state' => 'idle',
 'current_source' => '',
 'sources_processed' => 0,
 'total_sources' => 31,
 'progress_percent' => 0,
 'articles_published' => 0,
 'articles_skipped' => 0,
 'articles_failed' => 0,
 'pid' => null,
 'updated_at' => date('Y-m-d H:i:s')
 ];

 if (file_exists($statusFile)) {
 $json = @json_decode(file_get_contents($statusFile), true);
 if (is_array($json)) {
 $statusData = array_merge($statusData, $json);
 if (($json['state'] ?? '') === 'paused') {
 $statusData['is_running'] = false;
 $statusData['state'] = 'paused';
 } elseif (($json['state'] ?? '') === 'stopped') {
 $statusData['is_running'] = false;
 $statusData['state'] = 'stopped';
 $statusData['progress_percent'] = 0;
 } elseif (!empty($json['timestamp']) && (time() - $json['timestamp']) > 150) {
 $statusData['is_running'] = false;
 $statusData['state'] = 'idle';
 }
 }
 }

 // ط§ظ„طھط­ظ‚ظ‚ ظ…ظ† ظ‚ط§ط¹ط¯ط© ط§ظ„ط¨ظٹط§ظ†ط§طھ ظ„ط¹ط¯ط¯ ط§ظ„ظ…ظ‚ط§ظ„ط§طھ ط§ظ„ظ…ظ†ط´ظˆط±ط© ط§ظ„ظٹظˆظ…
 $todayPublished = (int) $db->fetch("SELECT COUNT(*) as c FROM articles WHERE DATE(created_at) = CURDATE() AND source_url IS NOT NULL")['c'];

 // ط¬ظ„ط¨ ط£ط­ط¯ط« 12 ظ…ظ‚ط§ظ„ ظ…ظ†ط´ظˆط± طھظ„ظ‚ط§ط¦ظٹط§ظ‹
 $recent = $db->fetchAll("
 SELECT a.id, a.title, a.title_ar, a.slug, a.source_name, a.source_url, a.category_id, a.created_at,
 c.name as category_name, c.slug as category_slug
 FROM articles a 
 LEFT JOIN categories c ON c.id = a.category_id 
 WHERE a.source_url IS NOT NULL AND a.source_url != ''
 ORDER BY a.id DESC 
 LIMIT 12
 ");

 $statusData['today_published_total'] = $todayPublished;
 $statusData['recent_articles'] = $recent;

 header('Content-Type: application/json; charset=utf-8');
 echo json_encode($statusData, JSON_UNESCAPED_UNICODE);
 exit;
 }

 public function stop()
 {
 $this->postGuardPermission('cron.manage');
 $root = dirname(dirname(dirname(__FILE__)));
 $statusFile = $root . '/storage/cron_status.json';

 $json = [];
 if (file_exists($statusFile)) {
 $json = @json_decode(file_get_contents($statusFile), true) ?: [];
 }

 $pid = (int) ($json['pid'] ?? 0);
 if ($pid > 0) {
 if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
 @exec("taskkill /F /PID {$pid} 2>&1");
 } else {
 @exec("kill -9 {$pid} 2>&1");
 }
 }

 // On Windows: Force kill all instances via WMIC
 if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
 @exec('wmic process where "CommandLine like \'%rss_auto_publish.php%\'" delete 2>&1');
 }

 $json['is_running'] = false;
 $json['state'] = 'stopped';
 $json['pid'] = null;
 $json['current_source'] = 'طھظ… ط§ظ„ط¥ظٹظ‚ط§ظپ ط¨ظ†ط¬ط§ط­';
 $json['progress_percent'] = 0;
 $json['timestamp'] = time();
 @file_put_contents($statusFile, json_encode($json, JSON_UNESCAPED_UNICODE));

 if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode(['ok' => true, 'state' => 'stopped']);
 exit;
 }

 Session::flash('success', 'طھظ… ط¥ظٹظ‚ط§ظپ ظ…ظ‡ظ…ط© ط§ظ„ظ†ط´ط± ط§ظ„طھظ„ظ‚ط§ط¦ظٹ ط¨ظ†ط¬ط§ط­.');
 return $this->redirect('admin/cron');
 }
}
