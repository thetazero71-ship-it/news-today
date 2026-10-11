<?php

class RssSourcesController extends AdminController
{
 public function index()
 {
 $this->guardPermission('sources.manage');
 $db = new Database();
 $this->ensureColumns($db);

 $q = trim((string) ($_GET['q'] ?? ''));
 $catId = (int) ($_GET['category_id'] ?? 0);

 $where = [];
 $params = [];

 if ($q !== '') {
 $where[] = '(s.name LIKE :q OR s.url LIKE :q)';
 $params[':q'] = "%{$q}%";
 }
 if ($catId > 0) {
 $where[] = 's.category_id = :cat';
 $params[':cat'] = $catId;
 }

 $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
 $sql = "SELECT s.*, c.name as category_name 
 FROM rss_sources s 
 LEFT JOIN categories c ON c.id = s.category_id 
 {$whereSql} 
 ORDER BY s.id ASC";
 
  $sources = $db->fetchAll($sql, $params);
  $categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');

  // New-articles signal per feed (last 24h), matched by stored source_name.
  // Lets admins spot at a glance which feeds actually delivered fresh news.
  $newWindowHours = 24;
  $newMap = [];
  try {
  $newRows = $db->fetchAll("
  SELECT source_name, COUNT(*) AS new_count, MAX(created_at) AS latest_at
  FROM articles
  WHERE source_name IS NOT NULL AND source_name <> ''
  AND created_at >= DATE_SUB(NOW(), INTERVAL " . (int) $newWindowHours . " HOUR)
  GROUP BY source_name
  ");
  foreach ($newRows as $row) {
  $newMap[(string) $row['source_name']] = $row;
  }
  } catch (Throwable $e) {
  $newMap = [];
  }

  $this->view('admin/rss_sources/index', [
  'sources' => $sources,
  'categories' => $categories,
  'q' => $q,
  'catId' => $catId,
  'newMap' => $newMap,
  'newWindowHours' => $newWindowHours
  ]);
 }

 public function create()
 {
 $this->guardPermission('sources.manage');
 $db = new Database();
 $categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
 $this->view('admin/rss_sources/form', [
 'categories' => $categories,
 'mode' => 'create'
 ]);
 }

 public function store()
 {
 $this->postGuardPermission('sources.manage');
 $data = Sanitizer::cleanArray($_POST);
 $name = trim($data['name'] ?? '');
 $url = trim($data['url'] ?? '');
 $categoryId = (int) ($data['category_id'] ?? 0) ?: null;
 $autoFetch = empty($data['auto_fetch']) ? 0 : 1;

 if (empty($name) || empty($url)) {
 Session::flash('error', 'اسم المصدر ورابط الـ RSS مطلوبان.');
 return $this->redirect('admin/rss-sources/create');
 }

 $db = new Database();
 self::ensureColumns($db);

 if ($err = self::validateUrl($url)) {
 Session::flash('error', $err);
 return $this->redirect('admin/rss-sources/create');
 }

 if ($dup = self::findDuplicate($db, $url, 0)) {
 Session::flash('error', 'هذا الرابط مسجّل مسبقاً باسم «' . $dup['name'] . '». لا يمكن إضافة نفس الخلاصة مرتين.');
 return $this->redirect('admin/rss-sources/create');
 }

 $db->query("INSERT INTO rss_sources (name, url, category_id, auto_fetch, auto_publish, auto_publish_translate)
 VALUES (:name, :url, :category_id, :auto_fetch, :auto_publish, :auto_publish_translate)", [
 ':name' => $name,
 ':url' => self::normalise($url),
 ':category_id' => $categoryId,
 ':auto_fetch' => $autoFetch,
 ':auto_publish' => empty($data['auto_publish']) ? 0 : 1,
 ':auto_publish_translate' => empty($data['auto_publish_translate']) ? 0 : 1
 ]);

 $id = $db->lastInsertId();
 $this->audit('create', 'rss_source', $id, null, $data);
 Session::flash('notice', self::thirdPartyWarning($url));
 Session::flash('success', "تمت إضافة المصدر التقني \"{$name}\" بنجاح.");
 return $this->redirect('admin/rss-sources');
 }

 public function edit($id)
 {
 $this->guardPermission('sources.manage');
 $db = new Database();
 $source = $db->fetch("SELECT * FROM rss_sources WHERE id = :id", [':id' => (int) $id]);
 if (!$source) {
 http_response_code(404);
 return $this->view('errors/404');
 }
 $categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
 $this->view('admin/rss_sources/form', [
 'source' => $source,
 'categories' => $categories,
 'mode' => 'edit'
 ]);
 }

 public function update($id)
 {
 $this->postGuardPermission('sources.manage');
 $db = new Database();
 $old = $db->fetch("SELECT * FROM rss_sources WHERE id = :id", [':id' => (int) $id]);
 if (!$old) {
 return $this->redirect('admin/rss-sources');
 }

 $data = Sanitizer::cleanArray($_POST);
 $name = trim($data['name'] ?? '');
 $url = trim($data['url'] ?? '');
 $categoryId = (int) ($data['category_id'] ?? 0) ?: null;
 $autoFetch = empty($data['auto_fetch']) ? 0 : 1;

 if ($err = self::validateUrl($url)) {
 Session::flash('error', $err);
 return $this->redirect('admin/rss-sources/' . (int) $id . '/edit');
 }

 if ($dup = self::findDuplicate($db, $url, (int) $id)) {
 Session::flash('error', 'هذا الرابط مسجّل مسبقاً باسم «' . $dup['name'] . '». اختر رابطاً مختلفاً.');
 return $this->redirect('admin/rss-sources/' . (int) $id . '/edit');
 }

 $db->query("UPDATE rss_sources SET name = :name, url = :url, category_id = :category_id, auto_fetch = :auto_fetch,
 auto_publish = :auto_publish, auto_publish_translate = :auto_publish_translate WHERE id = :id", [
 ':name' => $name,
 ':url' => self::normalise($url),
 ':category_id' => $categoryId,
 ':auto_fetch' => $autoFetch,
 ':auto_publish' => empty($data['auto_publish']) ? 0 : 1,
 ':auto_publish_translate' => empty($data['auto_publish_translate']) ? 0 : 1,
 ':id' => (int) $id
 ]);

 $this->audit('update', 'rss_source', $id, $old, $data);
 Session::flash('success', "تم تحديث بيانات المصدر \"{$name}\" بنجاح.");
 return $this->redirect('admin/rss-sources');
 }

 public function delete($id)
 {
 $this->postGuardPermission('sources.manage');
 $db = new Database();
 $old = $db->fetch("SELECT * FROM rss_sources WHERE id = :id", [':id' => (int) $id]);
 $db->query("DELETE FROM rss_sources WHERE id = :id", [':id' => (int) $id]);
 $this->audit('delete', 'rss_source', $id, $old, null);
 Session::flash('success', 'تم حذف المصدر من القائمة نهائياً.');
 return $this->redirect('admin/rss-sources');
 }

 /**
  * عملية جماعية على المصادر المحددة (حذف / تفعيل / تعطيل / نقل).
  */
 public function bulk()
 {
 $this->postGuardPermission('sources.manage');
 $db = new Database();
 $action = (string) ($_POST['bulk_action'] ?? '');

 $ids = $_POST['ids'] ?? [];
 if (is_string($ids)) {
 $ids = array_filter(explode(',', $ids));
 }
 $ids = array_values(array_unique(array_filter(array_map('intval', (array) $ids))));
 if (!$ids) {
 Session::flash('error', 'لم يتم تحديد أي مصدر. فعّل مربع الاختيار 옆 المصدر أولاً.');
 return $this->redirect('admin/rss-sources');
 }
 if (count($ids) > 200) {
 $ids = array_slice($ids, 0, 200);
 }
 $marks = implode(',', $ids);

 // استجابة فورية للواجهة: بلا إعادة تحميل الصفحة
 if ($this->wantsJson()) {
 return $this->bulkJson($db, $action, $ids, $marks);
 }

 switch ($action) {
 case 'delete':
 $db->query("DELETE FROM rss_sources WHERE id IN ({$marks})");
 Session::flash('success', 'تم حذف ' . count($ids) . ' مصدراً.');
 break;

 case 'enable':
 case 'disable':
 $on = $action === 'enable' ? 1 : 0;
 $db->query("UPDATE rss_sources SET auto_fetch = {$on} WHERE id IN ({$marks})");
 Session::flash('success', ($on ? 'تم تفعيل' : 'تم تعطيل') . ' ' . count($ids) . ' مصدراً.');
 break;

 case 'category':
 $cat = (int) ($_POST['bulk_category_id'] ?? 0);
 if ($cat <= 0) {
 Session::flash('error', 'اختر القسم أولاً.');
 break;
 }
 $db->query("UPDATE rss_sources SET category_id = :cat WHERE id IN ({$marks})", [':cat' => $cat]);
 Session::flash('success', 'تم نقل ' . count($ids) . ' مصدراً إلى القسم المحدد.');
 break;

 case 'check':
 $db->query("UPDATE rss_sources SET last_checked_at = NULL, fail_count = 0 WHERE id IN ({$marks})");
 Session::flash('success', 'تم وضع ' . count($ids) . ' مصدراً في قائمة الفحص القادمة.');
 break;

 case 'auto-on':
 $db->query("UPDATE rss_sources SET auto_publish = 1 WHERE id IN ({$marks})");
 Session::flash('success', 'تم تفعيل النشر التلقائي لـ' . count($ids) . ' مصدراً — ستصل أخبارها الموقع فوراً.');
 break;

 case 'auto-off':
 $db->query("UPDATE rss_sources SET auto_publish = 0 WHERE id IN ({$marks})");
 Session::flash('success', 'تم إيقاف النشر التلقائي لـ' . count($ids) . ' مصدراً — ستبقى الخبر تنتظر موافقتك.');
 break;

 default:
 Session::flash('error', 'إجراء غير معروف.');
 }

 $this->audit('bulk', 'rss_source', null, null, ['action' => $action, 'ids' => $ids]);
 return $this->redirect('admin/rss-sources');
 }

 /** @return string|null warning when the feed is served by a third party */
 private static function thirdPartyWarning(string $url): ?string
 {
 require_once dirname(__DIR__, 2) . '/core/FeedFetcher.php';
 $notice = FeedFetcher::thirdPartyNotice($url);
 return $notice ? $notice['warning'] : null;
 }

 /**
  * أعمدة التحكم بالنشر التلقائي. تُضاف عند الحاجة حتى تعمل المنصة على
  * قواعد بيانات أُنشئت قبل هذه الميزة.
  */
 public static function ensureColumns($db): void
 {
 $wanted = array(
 'auto_publish' => 'TINYINT(1) NOT NULL DEFAULT 0',
 'auto_publish_translate' => 'TINYINT(1) NOT NULL DEFAULT 0',
 'is_favorite' => 'TINYINT(1) NOT NULL DEFAULT 0',
 );
 try {
 $have = array();
 foreach ($db->fetchAll('SHOW COLUMNS FROM rss_sources') as $row) {
 if (!empty($row['Field'])) {
 $have[strtolower((string) $row['Field'])] = true;
 }
 }
 foreach ($wanted as $col => $definition) {
 if (!isset($have[$col])) {
 $db->query("ALTER TABLE rss_sources ADD COLUMN `{$col}` {$definition}");
 }
 }
 } catch (Throwable $e) {
 // بلا صلاحيات ALTER: تعمل الميزة على ما هو موجود فقط
 }
 }

 /** مبدّل سريع: تشغيل/إيقاف النشر التلقائي لمصدر واحد */
 public function toggleAuto($id)
 {
 $this->postGuardPermission('sources.manage');
 $db = new Database();
 self::ensureColumns($db);
 $cid = (int) $id;
 $src = $db->fetch("SELECT * FROM rss_sources WHERE id = :id", array(':id' => $cid));
 if (!$src) {
 Session::flash('error', 'المصدر غير موجود.');
 return $this->redirect('admin/rss-sources');
 }

 $now = (int) ($src['auto_publish'] ?? 0) === 1 ? 0 : 1;
 $db->query('UPDATE rss_sources SET auto_publish = :v WHERE id = :id', array(':v' => $now, ':id' => $cid));

 if ($this->wantsJson()) {
 return $this->json(array(
  'ok' => true,
  'auto_publish' => (bool) $now,
  'name' => $src['name'],
  'message' => $now
  ? 'النشر التلقائي مفعّل لـ«' . $src['name'] . '» — سيظهر خبره في الموقع فور وصوله.'
  : 'تم إيقاف النشر التلقائي لـ«' . $src['name'] . '» — سيبقى يحتاج موافقتك.',
 ));
 }

 $this->audit($now ? 'auto-on' : 'auto-off', 'rss_source', $cid, $src, null);
 Session::flash('success', $now
 ? 'النشر التلقائي مفعّل لـ«' . $src['name'] . '» — سيظهر خبره في الموقع فور وصوله.'
 : 'تم إيقاف النشر التلقائي لـ«' . $src['name'] . '» — سيبقى يحتاج موافقتك.');
 return $this->redirect('admin/rss-sources');
 }

 /** مبدّل سريع: وضع/إخراج مصدر من المفضلة */
 public function toggleFavorite($id)
 {
 $this->postGuardPermission('sources.manage');
 $db = new Database();
 self::ensureColumns($db);
 $cid = (int) $id;
 $src = $db->fetch("SELECT id, name, is_favorite FROM rss_sources WHERE id = :id", array(':id' => $cid));
 if (!$src) {
 Session::flash('error', 'المصدر غير موجود.');
 return $this->redirect('admin/rss-sources');
 }

 $now = (int) ($src['is_favorite'] ?? 0) === 1 ? 0 : 1;
 $db->query('UPDATE rss_sources SET is_favorite = :v WHERE id = :id', array(':v' => $now, ':id' => $cid));
 $this->audit($now ? 'favourite' : 'unfavourite', 'rss_source', $cid, $src, null);

 if ($this->wantsJson()) {
 return $this->json(array('ok' => true, 'favorite' => (bool) $now, 'name' => $src['name']));
 }

 $back = ($_POST['back'] ?? '') === 'favorites'
  ? 'admin/news-feeds?tab=favorites'
  : 'admin/rss-sources';
 Session::flash('success', $now
 ? 'أُضيف «' . $src['name'] . '» إلى المفضلة.'
 : 'أُزيل «' . $src['name'] . '» من المفضلة.');
 return $this->redirect($back);
 }

 /** طلب من الواجهة لا من نموذج كامل */
 private function wantsJson(): bool
 {
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
  && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
  return true;
  }
  return ($_POST['respond_with'] ?? '') === 'json';
 }

 /** تنفيذ عملية جماعية وإرجاع JSON (لا إعادة تحميل) */
 private function bulkJson($db, string $action, array $ids, string $marks)
 {
  $n = count($ids);
  $reply = function ($type, $message) use ($action, $ids) {
  return $this->json([
  'ok' => $type === 'success',
  'type' => $type,
  'message' => $message,
  'action' => $action,
  'ids' => $ids,
  ]);
  };

  switch ($action) {
  case 'delete':
  $db->query("DELETE FROM rss_sources WHERE id IN ({$marks})");
  $msg = 'تم حذف ' . $n . ' مصدراً.';
  break;

  case 'enable':
  case 'disable':
  $on = $action === 'enable' ? 1 : 0;
  $db->query("UPDATE rss_sources SET auto_fetch = {$on} WHERE id IN ({$marks})");
  $msg = ($on ? 'تم تفعيل' : 'تم تعطيل') . ' ' . $n . ' مصدراً.';
  break;

  case 'category':
  $cat = (int) ($_POST['bulk_category_id'] ?? 0);
  if ($cat <= 0) {
  return $reply('error', 'اختر القسم أولاً.');
  }
  $db->query("UPDATE rss_sources SET category_id = :cat WHERE id IN ({$marks})", [':cat' => $cat]);
  $msg = 'تم نقل ' . $n . ' مصدراً إلى القسم المحدد.';
  break;

  case 'check':
  $db->query("UPDATE rss_sources SET last_checked_at = NULL, fail_count = 0 WHERE id IN ({$marks})");
  $msg = 'تم وضع ' . $n . ' مصدراً في قائمة الفحص القادمة.';
  break;

  case 'auto-on':
  $db->query("UPDATE rss_sources SET auto_publish = 1 WHERE id IN ({$marks})");
  $msg = 'تم تفعيل النشر التلقائي لـ' . $n . ' مصدراً.';
  break;

  case 'auto-off':
  $db->query("UPDATE rss_sources SET auto_publish = 0 WHERE id IN ({$marks})");
  $msg = 'تم إيقاف النشر التلقائي لـ' . $n . ' مصدراً.';
  break;

  default:
  return $reply('error', 'إجراء غير معروف.');
  }

  $this->audit('bulk', 'rss_source', null, null, ['action' => $action, 'ids' => $ids]);
  return $reply('success', $msg);
 }

 /** @param array $payload */
 private function json(array $payload)
 {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  return null;
 }

 /**
  * Canonical form of a feed URL, so the same feed typed in different ways
  * is recognised as one source.
  */
 public static function normalise(string $url): string
 {
 $url = trim($url);
 $url = preg_replace('#^(https?)://#i', '', $url);
 $url = preg_replace('#^www\.#i', '', $url);
 $url = preg_replace('/[?#].*$/', '', $url);
 $url = rtrim($url, '/');
 return 'https://' . strtolower($url);
 }

 /** @return string|null error message when the URL is not usable */
 private static function validateUrl(string $url): ?string
 {
 if ($url === '') {
 return 'رابط الخلاصة مطلوب.';
 }
 if (!preg_match('#^https?://#i', $url)) {
 return 'الرابط يجب أن يبدأ بـ https:// أو http://';
 }
 if (mb_strlen($url) > 500) {
 return 'الرابط طويل جداً (الحد 500 حرف).';
 }
 $host = parse_url($url, PHP_URL_HOST);
 if (!$host || strpos($host, '.') === false) {
 return 'الرابط غير صالح — تأكد من كتابة نطاق صحيح.';
 }
 return null;
 }

 /** @return array|null the row of an already registered source */
 private static function findDuplicate($db, string $url, int $ignoreId = 0): ?array
 {
 $want = self::normalise($url);
 try {
 $rows = $db->fetchAll('SELECT id, name, url FROM rss_sources');
 } catch (Throwable $e) {
 return null;
 }
 foreach ($rows as $row) {
 if ((int) $row['id'] === $ignoreId) {
 continue;
 }
 if (self::normalise((string) $row['url']) === $want) {
 return $row;
 }
 }
 return null;
 }
}
