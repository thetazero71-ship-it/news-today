<?php

class CategoriesController extends AdminController
{
  public function index(){ $this->guardPermission('categories.manage'); $db=new Database(); $this->ensureVisibilityColumn($db); require_once __DIR__ . '/../../core/CategorySeeder.php'; CategorySeeder::ensure($db); $categories=$db->fetchAll($this->listQuery()); $this->view('admin/categories/index',array('categories'=>$categories,'flashSuccess'=>Session::getFlash('success'),'flashError'=>Session::getFlash('error'))); }
 public function create(){ $this->guardPermission('categories.manage'); $db=new Database(); $this->view('admin/categories/form',array('category'=>null,'parents'=>$db->fetchAll('SELECT id,name FROM categories ORDER BY name'))); }
 public function store(){ $this->postGuardPermission('categories.manage');$d=Sanitizer::cleanArray($_POST);$db=new Database(); $db->query('INSERT INTO categories (name,name_ar,name_en,slug,description_ar,color,parent_id,sort_order,is_visible) VALUES (:name,:name_ar,:name_en,:slug,:description_ar,:color,:parent_id,:sort_order,:is_visible)',array(':name'=>$d['name']??'',':name_ar'=>$d['name_ar']??($d['name']??''),':name_en'=>$d['name_en']??null,':slug'=>$this->slug($d['slug']??$d['name']??''),':description_ar'=>$d['description_ar']??null,':color'=>$d['color']??null,':parent_id'=>(int)($d['parent_id']??0)?:null,':sort_order'=>(int)($d['sort_order']??0),':is_visible'=>$this->wantsVisible($d)?1:0));$id=$db->lastInsertId();$this->audit('create','category',$id,null,$d);return $this->redirect('admin/categories'); }
 public function edit($id){$this->guardPermission('categories.manage');$db=new Database();$category=$db->fetch('SELECT * FROM categories WHERE id=:id',array(':id'=>(int)$id));$this->view('admin/categories/form',array('category'=>$category,'parents'=>$db->fetchAll('SELECT id,name FROM categories WHERE id<>:id ORDER BY name',array(':id'=>(int)$id))));}
 public function update($id){$this->postGuardPermission('categories.manage');$d=Sanitizer::cleanArray($_POST);$db=new Database();$old=$db->fetch('SELECT * FROM categories WHERE id=:id',array(':id'=>(int)$id));$db->query('UPDATE categories SET name=:name,name_ar=:name_ar,name_en=:name_en,slug=:slug,description_ar=:description_ar,color=:color,parent_id=:parent_id,sort_order=:sort_order,is_visible=:is_visible WHERE id=:id',array(':name'=>$d['name']??'',':name_ar'=>$d['name_ar']??($d['name']??''),':name_en'=>$d['name_en']??null,':slug'=>$this->slug($d['slug']??$d['name']??''),':description_ar'=>$d['description_ar']??null,':color'=>$d['color']??null,':parent_id'=>(int)($d['parent_id']??0)?:null,':sort_order'=>(int)($d['sort_order']??0),':is_visible'=>$this->wantsVisible($d, $old)?1:0,':id'=>(int)$id));$this->audit('update','category',$id,$old,$d);return $this->redirect('admin/categories');}

 /**
  * Hide / show a category. Nothing is deleted: the articles keep their
  * category, the section simply disappears from the public site.
  */
 public function toggleVisibility($id){
  $this->postGuardPermission('categories.manage');
  $db = new Database();
  $cid = (int) $id;
  $cat = $db->fetch('SELECT * FROM categories WHERE id = :id', array(':id' => $cid));
  if (!$cat) {
   return $this->redirect('admin/categories');
  }
  $currentlyHidden = array_key_exists('is_visible', $cat) && (int) $cat['is_visible'] === 0;
  $nowVisible = $currentlyHidden ? 1 : 0;
  try {
   $db->query('UPDATE categories SET is_visible = :v WHERE id = :id', array(':v' => $nowVisible, ':id' => $cid));
  } catch (Throwable $e) {
   $this->ensureVisibilityColumn($db);
   $db->query('UPDATE categories SET is_visible = :v WHERE id = :id', array(':v' => $nowVisible, ':id' => $cid));
  }
  $this->audit($nowVisible ? 'show' : 'hide', 'category', $cid, $cat);
  Session::flash(
   'success',
   $nowVisible
    ? 'تم إظهار التصنيف «' . ($cat['name_ar'] ?: $cat['name']) . '» — عاد ظاهراً في الموقع.'
    : 'تم إخفاء التصنيف «' . ($cat['name_ar'] ?: $cat['name']) . '» — لم يُحذف، والأخبار بقيت كما هي في قاعدة البيانات.'
  );
  return $this->redirect('admin/categories');
 }

 public function delete($id){
  $this->postGuardPermission('categories.manage');
  $db=new Database();
  $cid=(int)$id;
  $old=$db->fetch('SELECT * FROM categories WHERE id=:id',array(':id'=>$cid));
  if (!$old) {
   Session::flash('error', 'التصنيف غير موجود أو تم حذفه مسبقاً.');
   return $this->redirect('admin/categories');
  }
  $label = $old['name_ar'] ?: $old['name'];

  // 1. articles that would be orphaned: move them to the default category
  //    instead of dropping them out of every section
  $articles = (int) ($db->fetch("SELECT COUNT(*) AS c FROM articles WHERE category_id = :id", array(':id' => $cid))['c'] ?? 0);
  $keepArticles = !empty($_POST['detach_articles']);
  if ($articles > 0 && !$keepArticles) {
   Session::flash('error', 'لا يمكن حذف «' . $label . '» لأنه يحتوي على ' . $articles . ' خبر. يمكنك إخفاءه بدل حذفه.');
   return $this->redirect('admin/categories');
  }

  try {
   // 2. release every reference (articles, tutorials, any mapping table)
   $this->detachReferences($db, $cid);

   // 3. children become top-level
   try { $db->query('UPDATE categories SET parent_id = NULL WHERE parent_id = :id', array(':id' => $cid)); } catch (Throwable $e) {}

   $db->query('DELETE FROM categories WHERE id=:id',array(':id'=>$cid));
  } catch (Throwable $e) {
   Session::flash('error', 'تعذّر حذف التصنيف: ' . $e->getMessage());
   return $this->redirect('admin/categories');
  }

$this->audit('delete','category',$cid,$old);
 $message = 'تم حذف التصنيف «' . $label . '»' . ($articles > 0 ? '، وفُصلت ' . $articles . ' خبر منه.' : '.');
 $this->jsonOrFlash($message, $cid);
 return $this->redirect('admin/categories');
 }

 /** استجابة فورية للواجهة، وإلا رسالة عادية */
 private function jsonOrFlash(string $message, $cid)
 {
  $isAjax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH'])
  && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest')
  || ($_POST['respond_with'] ?? '') === 'json';
  if ($isAjax) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['ok' => true, 'id' => $cid, 'message' => $message], JSON_UNESCAPED_UNICODE);
  exit;
 }
 Session::flash('success', $message);
 }

 /** Empty category_id in every table that points at categories. */
 private function detachReferences($db, $cid){
  try {
   $tables = $db->fetchAll(
    "SELECT TABLE_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND COLUMN_NAME = 'category_id'"
   );
  } catch (Throwable $e) {
   $tables = array();
  }
  foreach ($tables as $row) {
   $table = (string) ($row['TABLE_NAME'] ?? $row[0] ?? '');
   if ($table === '' || !preg_match('/^[A-Za-z0-9_]+$/', $table)) {
    continue;
   }
   try {
    $db->query("UPDATE `{$table}` SET category_id = NULL WHERE category_id = :id", array(':id' => $cid));
   } catch (Throwable $e) {}
  }
 }

 /** Older installs may not have the column yet. */
 private function ensureVisibilityColumn($db){
  try { $db->query('ALTER TABLE categories ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1'); } catch (Throwable $e) {}
 }

 private function wantsVisible(array $d, $old = null): bool{
  if (array_key_exists('is_visible', $d)) {
   return !in_array((string) $d['is_visible'], array('0', 'false', 'off', ''), true);
  }
  if ($old !== null && array_key_exists('is_visible', $old)) {
   return (int) $old['is_visible'] === 1;
  }
  return true;
 }

 private function listQuery(){
  $sql = 'SELECT c.*, p.name parent_name,
        (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) AS articles_count
        FROM categories c LEFT JOIN categories p ON p.id=c.parent_id
        ORDER BY c.is_visible DESC, c.sort_order, c.name';
  return $sql;
 }

 private function slug($v){$s=strtolower(trim(preg_replace('/[^\p{L}\p{N}]+/u','-',(string)$v),'-'));return $s?:'category-'.time();}
}