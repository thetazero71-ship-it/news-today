<?php

class ArticlesController extends AdminController
{
 public function index()
 {
 $this->guardPermission('articles.view');
 $db = new Database();
 $q = trim((string) ($_GET['q'] ?? ''));
 $status = trim((string) ($_GET['status'] ?? ''));
 $where = array();
 $params = array();

 if ($q !== '') {
 $where[] = '(a.title LIKE :q OR a.slug LIKE :q)';
 $params[':q'] = "%{$q}%";
 }
 if ($status !== '') {
 $where[] = 'a.status = :status';
 $params[':status'] = $status;
 }

 $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';
 $sql = "SELECT a.*, c.name as category_name, u.username as author_name 
 FROM articles a 
 LEFT JOIN categories c ON c.id = a.category_id 
 LEFT JOIN users u ON u.id = a.author_id 
 {$whereSql} 
 ORDER BY a.id DESC LIMIT 100";
 $articles = $db->fetchAll($sql, $params);

 $this->view('admin/articles/index', array(
 'articles' => $articles,
 'q' => $q,
 'status' => $status,
 ));
 }

 public function create()
 {
 $this->guardPermission('articles.create');
 $db = new Database();
 $categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name');
 $this->view('admin/articles/form', array('categories' => $categories, 'mode' => 'create'));
 }

 public function store()
 {
 $this->postGuardPermission('articles.create');
 $data = Sanitizer::cleanArray($_POST);
 $slug = $this->slug($data['slug'] ?: $data['title']);
 $db = new Database();
 
 $categoryId = (int) ($data['category_id'] ?? 0);
 if ($categoryId <= 0) {
 $categoryId = get_default_category_id($db);
 }

 // رابط صورة خارجي في النموذج؟ نزّله لخادمنا فوراً حتى لا يموت لاحقاً
 $data['featured_image'] = self::localizeImage($data['featured_image'] ?? null);

 $db->query('INSERT INTO articles (title,title_ar,title_en,slug,content,content_ar,content_en,excerpt,featured_image,category_id,author_id,status,article_type,allow_comments,is_featured,is_premium,source_name,source_url,published_at,reading_time_minutes,views_count,shares_count) VALUES (:title,:title_ar,:title_en,:slug,:content,:content_ar,:content_en,:excerpt,:featured_image,:category_id,:author_id,:status,:article_type,:allow_comments,:is_featured,:is_premium,:source_name,:source_url,:published_at,:reading_time_minutes,:views_count,:shares_count)', array(
 ':title' => $data['title'] ?? '',
 ':title_ar' => $data['title_ar'] ?? ($data['title'] ?? ''),
 ':title_en' => $data['title_en'] ?? null,
 ':slug' => $slug,
 ':content' => $data['content'] ?? '',
 ':content_ar' => $data['content_ar'] ?? ($data['content'] ?? ''),
 ':content_en' => $data['content_en'] ?? null,
 ':excerpt' => $data['excerpt'] ?? null,
 ':featured_image' => $data['featured_image'] ?? null,
 ':category_id' => $categoryId,
 ':author_id' => Auth::user()['id'],
 ':status' => $data['status'] ?? 'draft',
 ':article_type' => $data['article_type'] ?? 'standard',
 ':allow_comments' => empty($data['allow_comments']) ? 0 : 1,
 ':is_featured' => empty($data['is_featured']) ? 0 : 1,
 ':is_premium' => empty($data['is_premium']) ? 0 : 1,
 ':source_name' => $data['source_name'] ?? null,
 ':source_url' => $data['source_url'] ?? null,
 ':published_at' => storage_datetime($data['published_at'] ?? ''),
 ':reading_time_minutes' => isset($data['reading_time_minutes']) && (int) $data['reading_time_minutes'] > 0 ? (int) $data['reading_time_minutes'] : $this->readingTime($data['content'] ?? ''),
 ':views_count' => isset($data['views_count']) ? max(0, (int) $data['views_count']) : 0,
 ':shares_count' => isset($data['shares_count']) ? max(0, (int) $data['shares_count']) : 0,
 ));
 $id = $db->lastInsertId();
 $this->audit('create', 'article', $id, null, $data);
 return $this->redirect('admin/articles');
 }

 public function edit($id)
 {
 $this->guardPermission(array('articles.edit', 'articles.edit_own'));
 $db = new Database();
 $article = $db->fetch('SELECT * FROM articles WHERE id=:id', array(':id' => (int) $id));
 if (!$article) {
 http_response_code(404);
 return $this->view('errors/404');
 }
 $this->view('admin/articles/form', array(
 'article' => $article,
 'categories' => $db->fetchAll('SELECT id,name FROM categories ORDER BY name'),
 'mode' => 'edit'
 ));
 }

 public function update($id)
 {
 $this->postGuardPermission(array('articles.edit', 'articles.edit_own'));
 $db = new Database();
 $old = $db->fetch('SELECT * FROM articles WHERE id=:id', array(':id' => (int) $id));
 if (!$old) {
 return $this->redirect('admin/articles');
 }
 $data = Sanitizer::cleanArray($_POST);
 $slug = $this->slug($data['slug'] ?: $data['title']);

 $categoryId = (int) ($data['category_id'] ?? 0);
 if ($categoryId <= 0) {
 $categoryId = get_default_category_id($db);
 }

 // رابط صورة خارجي جديد في النموذج؟ نزّله لخادمنا فوراً (لا نعيد تنزيل
 // ما لم يتغيّر حتى لا تتراكم نسخ مكررة).
 if (($data['featured_image'] ?? null) !== ($old['featured_image'] ?? null)) {
 $data['featured_image'] = self::localizeImage($data['featured_image'] ?? null);
 }

 $db->query('UPDATE articles SET title=:title,title_ar=:title_ar,title_en=:title_en,slug=:slug,content=:content,content_ar=:content_ar,content_en=:content_en,excerpt=:excerpt,featured_image=:featured_image,category_id=:category_id,status=:status,article_type=:article_type,allow_comments=:allow_comments,is_featured=:is_featured,is_premium=:is_premium,source_name=:source_name,source_url=:source_url,published_at=:published_at,reading_time_minutes=:reading_time_minutes,views_count=:views_count,shares_count=:shares_count,updated_at=CURRENT_TIMESTAMP WHERE id=:id', array(
 ':title' => $data['title'] ?? '',
 ':title_ar' => $data['title_ar'] ?? ($data['title'] ?? ''),
 ':title_en' => $data['title_en'] ?? null,
 ':slug' => $slug,
 ':content' => $data['content'] ?? '',
 ':content_ar' => $data['content_ar'] ?? ($data['content'] ?? ''),
 ':content_en' => $data['content_en'] ?? null,
 ':excerpt' => $data['excerpt'] ?? null,
 ':featured_image' => $data['featured_image'] ?? null,
 ':category_id' => $categoryId,
 ':status' => $data['status'] ?? 'draft',
 ':article_type' => $data['article_type'] ?? 'standard',
 ':allow_comments' => empty($data['allow_comments']) ? 0 : 1,
 ':is_featured' => empty($data['is_featured']) ? 0 : 1,
 ':is_premium' => empty($data['is_premium']) ? 0 : 1,
 ':source_name' => $data['source_name'] ?? null,
 ':source_url' => $data['source_url'] ?? null,
 ':published_at' => storage_datetime($data['published_at'] ?? ''),
 ':reading_time_minutes' => isset($data['reading_time_minutes']) && (int) $data['reading_time_minutes'] > 0 ? (int) $data['reading_time_minutes'] : $this->readingTime($data['content'] ?? ''),
 ':views_count' => isset($data['views_count']) ? max(0, (int) $data['views_count']) : (int) ($old['views_count'] ?? 0),
 ':shares_count' => isset($data['shares_count']) ? max(0, (int) $data['shares_count']) : (int) ($old['shares_count'] ?? 0),
 ':id' => (int) $id
 ));
 $this->audit('update', 'article', $id, $old, $data);
 return $this->redirect('admin/articles');
 }

 public function delete($id)
 {
 $this->postGuardPermission('articles.delete');
 $db = new Database();
 $old = $db->fetch('SELECT * FROM articles WHERE id=:id', array(':id' => (int) $id));
 $db->query('UPDATE articles SET status=\'archived\' WHERE id=:id', array(':id' => (int) $id));
 $this->audit('archive', 'article', $id, $old, null);
 return $this->redirect('admin/articles');
 }

 public function toggleFeatured($id)
 {
 $this->postGuardPermission('articles.feature');
 $db = new Database();
 $db->query('UPDATE articles SET is_featured=1-is_featured WHERE id=:id', array(':id' => (int) $id));
 $this->audit('toggle_featured', 'article', $id);
 return $this->redirect('admin/articles');
 }

 public function bulkAction()
 {
 $this->postGuardPermission('articles.publish');
 $ids = array_map('intval', (array) ($_POST['ids'] ?? array()));
 $action = $_POST['action'] ?? '';
 if ($ids && !in_array($action, array('publish', 'archive', 'delete'), true)) {
 $action = '';
 }
 if ($ids && $action) {
 $status = $action === 'publish' ? 'published' : 'archived';
 $marks = implode(',', array_fill(0, count($ids), '?'));
 $db = new Database();
 $db->query('UPDATE articles SET status=? WHERE id IN (' . $marks . ')', array_merge(array($status), $ids));
 $this->audit('bulk_' . $action, 'article', null, null, array('ids' => $ids));
 }
 return $this->redirect('admin/articles');
 }

 /**
 * أرشفة أو حذف الأخبار القديمة مجمّعة وفق نطاق تاريخ
 * POST: action=bulk-date-archive | bulk-date-delete، date_from و/أو date_to = YYYY-MM-DD
 */
 public function bulkExpire()
 {
 $this->postGuardPermission('articles.delete');
 $action = $_POST['action'] ?? '';
 $dateFrom = trim((string) ($_POST['date_from'] ?? ''));
 $dateTo = trim((string) ($_POST['date_to'] ?? ''));
 $scope = $_POST['scope'] ?? 'all'; // all | news

 $allowedActions = ['bulk-date-archive', 'bulk-date-delete'];
 if (!in_array($action, $allowedActions, true)) {
 Session::flash('error', 'إجراء غير صالح.');
 return $this->redirect('admin/articles');
 }

 $conditions = [];
 $params = [];

 if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
 $conditions[] = 'COALESCE(a.published_at, a.created_at) <= :date_to';
 $params[':date_to'] = $dateTo . ' 23:59:59';
 }
 if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
 $conditions[] = 'COALESCE(a.published_at, a.created_at) >= :date_from';
 $params[':date_from'] = $dateFrom . ' 00:00:00';
 }

 if (empty($conditions)) {
 Session::flash('error', 'يرجى تحديد تاريخ (من) و/أو (إلى) لأرشفة/حذف الأخبار القديمة.');
 return $this->redirect('admin/articles');
 }

 if ($scope === 'news') {
 $conditions[] = "a.article_type IN ('breaking','standard','video')";
 }

 $where = implode(' AND ', $conditions);

 $statusFilter = "a.status = 'published'";
 if ($action === 'bulk-date-delete') {
 $statusFilter = '1=1';
 }

 $sql = "SELECT a.id, a.title FROM articles a WHERE {$statusFilter} AND {$where}";
 $stmt = (new Database())->prepare($sql);
 $stmt->execute($params);
 $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
 $count = count($rows);

 if ($count > 0) {
 $ids = array_column($rows, 'id');
 $marks = implode(',', array_fill(0, count($ids), '?'));
 $db = new Database();
 if ($action === 'bulk-date-archive') {
 $db->query("UPDATE articles SET status='archived', updated_at=CURRENT_TIMESTAMP WHERE id IN ($marks)", $ids);
 $this->audit('bulk_date_archive', 'article', null, null, ['count' => $count, 'to' => $dateTo, 'from' => $dateFrom]);
 Session::flash('success', "تمت أرشفة {$count} خبراً قديماً بنجاح.");
 } else {
 // حذف نهائي مع تنظيف السجلات المرتبطة
 $db->query("DELETE FROM reactions WHERE article_id IN ($marks)", $ids);
 $db->query("DELETE FROM comments WHERE article_id IN ($marks)", $ids);
 $db->query("DELETE FROM article_tags WHERE article_id IN ($marks)", $ids);
 $db->query("DELETE FROM bookmarks WHERE article_id IN ($marks)", $ids);
 $db->query("DELETE FROM articles WHERE id IN ($marks)", $ids);
 $this->audit('bulk_date_delete', 'article', null, null, ['count' => $count, 'to' => $dateTo, 'from' => $dateFrom]);
 Session::flash('success', "تم حذف {$count} خبراً قديماً نهائياً (مع تفاعلات وتعليقات ووسوم مرتبطة).");
 }
 } else {
 Session::flash('info', 'لا توجد أخبار قديمة مطابقة للنطاق المحدد.');
 }

 return $this->redirect('admin/articles');
 }

 public function translatePreview()
 {
 header('Content-Type: application/json; charset=utf-8');
 try {
 $this->postGuardPermission(array('articles.edit', 'articles.edit_own'));
 require_once __DIR__ . '/../../core/AiTranslator.php';

 $title = trim($_POST['title'] ?? '');
 $content = trim($_POST['content'] ?? '');

 if (empty($title)) {
 echo json_encode(['success' => false, 'error' => 'عنوان المقال مطلوب للترجمة.'], JSON_UNESCAPED_UNICODE);
 exit;
 }

 $result = AiTranslator::translateArticle($title, $content ?: $title);

 echo json_encode([
 'success' => true,
 'data' => $result
 ], JSON_UNESCAPED_UNICODE);
 exit;
 } catch (Throwable $e) {
 echo json_encode([
 'success' => false,
 'error' => 'تعذر إتمام الترجمة: ' . $e->getMessage()
 ], JSON_UNESCAPED_UNICODE);
 exit;
 }
 }

 public function quickUpdateCategory()
 {
 $this->postGuardPermission('articles.edit');

 $articleId = (int) ($_POST['article_id'] ?? 0);
 $categoryId = (int) ($_POST['category_id'] ?? 0);

 if ($articleId <= 0 || $categoryId <= 0) {
 header('Content-Type: application/json; charset=utf-8');
 http_response_code(400);
 echo json_encode(['ok' => false, 'error' => 'بيانات الطلب غير صالحة']);
 exit;
 }

 $db = new Database();
 $cat = $db->fetch("SELECT id, name, slug FROM categories WHERE id = :id", [':id' => $categoryId]);
 if (!$cat) {
 header('Content-Type: application/json; charset=utf-8');
 http_response_code(404);
 echo json_encode(['ok' => false, 'error' => 'التصنيف المطلوب غير موجود']);
 exit;
 }

 $db->query("UPDATE articles SET category_id = :cid WHERE id = :aid", [
 ':cid' => $categoryId,
 ':aid' => $articleId
 ]);

 $badgeClass = 'bg-primary-subtle text-primary';
 if ($cat['slug'] === 'artificial-intelligence') $badgeClass = 'bg-purple-subtle text-purple';
 elseif ($cat['slug'] === 'cybersecurity') $badgeClass = 'bg-danger-subtle text-danger';
 elseif ($cat['slug'] === 'hardware-devices') $badgeClass = 'bg-info-subtle text-info';
 elseif ($cat['slug'] === 'software-development') $badgeClass = 'bg-success-subtle text-success';

 header('Content-Type: application/json; charset=utf-8');
 echo json_encode([
 'ok' => true,
 'article_id' => $articleId,
 'category_id' => $categoryId,
 'category_name' => $cat['name'],
 'category_slug' => $cat['slug'],
 'badge_class' => $badgeClass
 ], JSON_UNESCAPED_UNICODE);
 exit;
 }

 /**
  * إصلاح صور المقالات على دفعات (JSON).
  *
  * لكل مقال صورته خارجية أو مفقودة: تُنزَّل نسخة محلية وتُحفَظ في قاعدة
  * البيانات. المعطوب الذي لا يُستعاد تُعاد محاولة استخراجه من رابطه الأصلي.
  */
 public function repairImages()
 {
 $this->postGuardPermission('articles.edit');
 $db = new Database();

 $offset = max(0, (int) ($_POST['offset'] ?? 0));
 $limit = min(20, max(1, (int) ($_POST['limit'] ?? 8)));
 $started = microtime(true);
 $budget = 25; // نترك هامشاً قبل مهلة الاستضافة

 $where = "(featured_image IS NULL OR featured_image = '' OR featured_image LIKE 'http%')";
 $total = (int) ($db->fetch("SELECT COUNT(*) AS c FROM articles WHERE {$where}")['c'] ?? 0);
 $rows = $db->fetchAll(
 "SELECT id, title, featured_image, source_url FROM articles WHERE {$where} ORDER BY id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset
 );

 $fixed = 0;
 $broken = 0;
 $notes = [];
 $processed = 0;

 foreach ($rows as $row) {
 if ((microtime(true) - $started) > $budget) {
 break;
 }
 $processed++;
 $id = (int) $row['id'];
 $img = trim((string) ($row['featured_image'] ?? ''));
 $short = mb_substr((string) ($row['title'] ?? ('#' . $id)), 0, 45);

 // مسار محلي والملف موجود: سليم، لا نلمسه
 if (ImageStore::isLocal($img)) {
 $full = (defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2)) . '/' . $img;
 if (is_file($full)) {
 continue;
 }
 }

 $local = '';
 if (ImageStore::isRemote($img)) {
 // يعمل؟ نزّله مباشرة
 $probe = ImageStore::check($img);
 if ($probe['ok']) {
 $local = ImageStore::storeFromUrl($img);
 }
 // ميت؟ أعد استخراج صورة طازجة من المقال الأصلي
 if ($local === '' && !empty($row['source_url'])) {
 $fresh = FetchOg::resolve((string) $row['source_url']);
 if ($fresh !== '' && $fresh !== $img) {
 $local = ImageStore::storeFromUrl($fresh);
 }
 }
 } elseif ($img === '' && !empty($row['source_url'])) {
 // بلا صورة أصلاً: استخرج ثم نزّل
 $fresh = FetchOg::resolve((string) $row['source_url']);
 if ($fresh !== '') {
 $local = ImageStore::storeFromUrl($fresh);
 }
 }

 if ($local !== '') {
 $db->query('UPDATE articles SET featured_image = :img WHERE id = :id', [':img' => $local, ':id' => $id]);
 $fixed++;
 $notes[] = '✅ #' . $id . ' ' . $short;
 } else {
 $broken++;
 $notes[] = '❌ #' . $id . ' ' . $short . ' — تعذّر الاستعاضة';
 }
 }

 $next = $offset + $processed;
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode([
 'ok' => true,
 'total' => $total,
 'next' => $next,
 'done' => $next >= $total || $processed === 0,
 'fixed' => $fixed,
 'broken' => $broken,
 'notes' => array_slice($notes, 0, 12),
 ], JSON_UNESCAPED_UNICODE);
 exit;
 }

 /**
  * إعادة ضغط الصور المحلية لمرة واحدة (JSON، على دفعات).
  *
  * يعيد ترميز كل صورة محلية بأبعاد الويب وجودة 82 — يوفّر عادة 60-80%
  * من الحجم دون فرق مرئي. آمن: لا يُستبدَل الملف إلا بتوفير حقيقي.
  */
 public function recompressImages()
 {
 $this->postGuardPermission('articles.edit');
 $db = new Database();

 $offset = max(0, (int) ($_POST['offset'] ?? 0));
 $limit = min(30, max(1, (int) ($_POST['limit'] ?? 15)));
 $started = microtime(true);
 $budget = 25;

 $total = (int) ($db->fetch("SELECT COUNT(*) AS c FROM articles WHERE featured_image LIKE 'uploads/%'")['c'] ?? 0);
 $rows = $db->fetchAll(
 "SELECT id, featured_image FROM articles WHERE featured_image LIKE 'uploads/%' ORDER BY id DESC LIMIT " . (int) $limit . " OFFSET " . (int) $offset
 );

 $done = 0;
 $savedBytes = 0;
 $skipped = 0;
 $root = defined('APP_ROOT') ? APP_ROOT : dirname(__DIR__, 2);

 foreach ($rows as $row) {
 if ((microtime(true) - $started) > $budget) {
 break;
 }
 $done++;
 $full = $root . '/' . ltrim((string) ($row['featured_image'] ?? ''), '/');
 // أمان المسار: يجب أن يبقى داخل uploads/articles
 $real = realpath($full);
 $base = realpath($root . '/uploads/articles');
 if ($real === false || $base === false || strpos($real, $base) !== 0 || !is_file($real)) {
 $skipped++;
 continue;
 }
 $r = ImageStore::compressFile($real);
 if (!empty($r['saved'])) {
 $savedBytes += max(0, $r['before'] - $r['after']);
 }
 }

 $next = $offset + $done;
 header('Content-Type: application/json; charset=utf-8');
 echo json_encode([
 'ok' => true,
 'total' => $total,
 'next' => $next,
 'done' => $next >= $total || $done === 0,
 'processed' => $done,
 'saved_bytes' => $savedBytes,
 'saved_human' => $this->formatBytes($savedBytes),
 'skipped' => $skipped,
 ], JSON_UNESCAPED_UNICODE);
 exit;
 }

 private function formatBytes(int $bytes): string
 {
  if ($bytes >= 1048576) {
  return round($bytes / 1048576, 2) . ' MB';
  }
  if ($bytes >= 1024) {
  return round($bytes / 1024, 1) . ' KB';
  }
  return $bytes . ' B';
 }

 private function slug($value)
 {
 $slug = strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u', '-', (string) $value), '-'));
 return $slug ?: 'article-' . time();
 }

 private function readingTime($content)
 {
 $words = preg_split('/\s+/u', trim(strip_tags($content)));
 return max(1, (int) ceil(count($words) / 200));
 }

 /**
  * رابط صورة خارجي؟ يُنزَّل إلى خادمنا ويُعاد المسار المحلي.
  * أي قيمة أخرى (مسار محلي، فارغ) تمرّ كما هي.
  */
 private static function localizeImage($value)
 {
  if (!class_exists('ImageStore') || !ImageStore::isRemote($value)) {
  return $value;
  }
  try {
  $local = ImageStore::storeFromUrl((string) $value);
  return $local !== '' ? $local : $value;
  } catch (Throwable $e) {
  return $value;
  }
 }
}
