# تقرير الإصلاحات التقني — منصة أخبار التقنية

> **آخر تحديث:** 17 نوفمبر 2026
> **الفرع:** `main` · **المستودع:** `thetazero71-ship-it/news-today`
> **النطاق الزمني:** 19 سبتمبر 2026 → 14 أكتوبر 2026 — **113 commit** إجمالاً، منها **98** منذ بدء مشروع الصلاحيات
> **آخر commit موثّق:** `4747b51`
> **حالة النشر:** جميع ما في هذا التقرير مُودَع ومنشور على `main` ومُوزَّع على الاستضافة (`news-today.42web.io`)، ما لم يُذكر خلاف ذلك صراحةً.

---

## 0. ملخص تنفيذي

| المؤشر | القيمة |
|---|---|
| المسارات الإدارية المحمية بصلاحية | **223 / 223 (100%)** |
| مسارات تعتمد على `guardAdmin()` القديم وحده | **0** |
| الصلاحيات المعرَّفة | **55** صلاحية موزعة على **30 كيانًا** و**6 مجموعات** |
| الأدوار | **12** دورًا بتسلسل هرمي (RBAC1) |
| كونترولرات الإدارة | **30** |
| تأكيدات اختبار الصلاحيات الآلية | **384** (كلها ناجحة) |
| ثغرات أمنية حقيقية اكتُشفت وأُغلقت | **3** |
| جداول قاعدة البيانات الموثّقة في `schema.sql` | **56** |
| مفتاح التفعيل `RBAC_ENFORCE` | **مطفأ (false)** — بانتظار قرار المالك |

**الخلاصة:** لوحة الإدارة كانت محمية بقاعدة واحدة بسيطة («admin أو editor فقط») وغير مقيَّدة بالصلاحيات المخزَّنة. اليوم كل فعل إداري يُفحص مقابل صلاحية محدَّدة، والأدوار تُدار من شاشة `/admin/roles`، مع احتفاظ `admin` و`managing_editor` بكامل قدراتهما التشغيلية.

---

## 1. الأمن والصلاحيات (RBAC)

### 1.1 الوضع قبل الإصلاح

- الأدوار موجودة في جدول `roles` كبيانات فقط، ولا يقرأها أي فحص تنفيذي.
- الحماية الفعلية كانت `Auth::isAdmin()` + `guardAdmin()`: **تسجيل دخول + الدور `admin` أو `editor`** فقط.
- النتيجة: أي مستخدم مسجَّل دوره غير هذين الدورين إمّا لا يدخل اللوحة إطلاقًا، أو — بعد أي توسيع للأدوار — يدخل **كل** صفحة إدارية دون قيود.
- الأزرار والقوائم تُعرض للجميع دون مراعاة الدور.

### 1.2 البنية المضافة

| الملف | الدور |
|---|---|
| `core/Permissions.php` | محرّك القرار: كتالوج 55 صلاحية، قراءة 7 أشكال مختلفة لعمود `permissions`، ودوال `can` · `check` · `grantedTokens` · `describe` · `canEnterAdmin` · `sectionAccess` · `enforced` · `superRole` |
| `config/rbac.php` | مفتاح الإيقاف `RBAC_ENFORCE` (افتراضيًا `false`، ويمكن تجاوزه بمتغيّر بيئة) + تعريف `RBAC_SUPER_ROLE = admin` |
| `config/role_permissions.default.json` | النموذج المعياري للأدوار الـ12 مع `_inherits` و`_arabic` |
| `core/RoleSeeder.php` | بذر الأدوار idempotent + علامات الإصدار `_model` و`_roles_ui` |
| `core/AdminController.php` | `guardAdmin()` (بقي كما هو) + `guardPermission()` + `postGuardPermission()` + `denyPermission()` بردّ **JSON 403** لطلبات AJAX |
| `core/Auth.php` | `isAdmin()` يعتمد الآن `canEnterAdmin()` بدل حصر الدور بالاسم |
| `controllers/admin/RolesController.php` + `views/admin/roles/{index,edit}.php` | شاشة إدارة الصلاحيات `/admin/roles` |
| `views/layouts/admin.php` | تقييد **31** عنصر قائمة/شريط علوي بـ `admin_can()` + شريط جانبي في 6 أقسام قابلة للطي |
| `index.php` | تحميل `config/rbac.php`، تعريف `admin_can()`، ومسارات `/admin/roles*` |
| `tools/rbac_*.php` · `tools/rbac_coverage.py` | الاختبارات الآلية وتقرير التغطية |

### 1.3 النموذج المعياري — 12 دورًا (تسلسل هرمي + أقل امتياز)

| # | الدور | الاسم العربي | يورث من | الصلاحيات | النطاق |
|---|---|---|---|---|---|
| 1 | `admin` | مدير النظام | — | **55 / 55** | كل شيء · دور خارق غير قابل للتعديل |
| 2 | `managing_editor` | المدير التحريري | `editor` | **36** | إدارة التحرير والنشر والتصنيف والتفاعل والنشرة والترجمة والإعلانات — دون صلاحيات النظام |
| 3 | `editor` | محرر رئيسي | `publisher` | **19** | تعديل/نشر/حذف أي مقال، التمييز، الإشراف على التعليقات، الوسائط |
| 4 | `publisher` | ناشر | `author` | **16** | النشر، RSS، التغطيات الحية، جدولة التحديث |
| 5 | `author` | كاتب ومحرر محتوى | `contributor` | **9** | مقالاته: إنشاء + تعديل + نشر + إحصاءات |
| 6 | `contributor` | مساهم | — | **7** | مسودات فقط (لا نشر) + وسائط + ملفه |
| 7 | `translator` | مترجم | — | **8** | الترجمة + تعديل مقالاته |
| 8 | `moderator` | مشرف التفاعل | — | **7** | التعليقات والرسائل والتغطيات الحية |
| 9 | `newsletter_manager` | مسؤول النشرة البريدية | — | **6** | النشرة + الإحصاءات |
| 10 | `analyst` | محلل (قراءة فقط) | — | **14** | كل الصلاحيات **العرضية** فقط — لا تعديل/نشر/حذف |
| 11 | `subscriber` | مشترك مميز | — | **2** | المحتوى المميز + التعليقات (واجهة فقط) |
| 12 | `reader` | قارئ مسجَّل | — | **1** | التعليقات (واجهة فقط) |

**تدرّج الصلاحيات مثبت آليًا:** `55 → 36 → 19 → 16 → 9 → 7`، وكل دور يرث صلاحيات والده بالكامل (اختبار 197 تأكيدًا في `tools/rbac_seedtest.php`).

### 1.4 مجموعات الصلاحيات الـ55

| المجموعة | العدد | الكيانات |
|---|---|---|
| عام | 7 | dashboard · analytics · traffic · activity · profile |
| المحتوى | 18 | articles (9) · categories · pages · menus · ads · polls · tutorials · media (3) |
| التفاعل | 6 | comments · messages (3) · liveblog |
| النشر والأتمتة | 9 | feeds · sources · classifier · cron · newsletter (3) |
| الذكاء الاصطناعي | 4 | ai · translation |
| النظام (حساس) | 11 | settings · users · roles · apikeys · security · backup · diagnostics |

### 1.5 مراحل التنفيذ

| المرحلة | المحتوى | الـ commit |
|---|---|---|
| **0** | خط أساس: 214 مسارًا · 37 منطقة · 118 عملية معدِّلة، ومفتاح الإيقاف | — |
| **1** | محرّك الصلاحيات + الحرّاس + JSON 403 + 71 تأكيدًا | `ea55d8f` |
| **2** | شاشة `/admin/roles` + البذر المحافظ + 43 تأكيدًا | `d85e18d` ثم `182fc81` |
| **3** | تقييد **31** عنصر قائمة/شريط علوي + 12 تأكيدًا | `084ffc2` |
| **4a + أمن** | **20** حارسًا ناقصًا + **4** صلاحيات جديدة (users, settings, live-blog, diagnostics) | `9f2f457` |
| **4b (محتوى)** | **38** عملية: Articles · Categories · Polls · Media · Menus · Tutorials + `AdminSimpleController` | `fd14084` |
| **4c (تفاعل)** | التعليقات والرسائل والتغطيات + `messages.manage` + تحويل الإشراف إلى POST | `a878639` |
| **4d (نشر)** | **35** عملية: RssSources · ClassifierRules · Newsletter · Cron · Aggregator | `5543cca` |
| **4e (حساس)** | **9** كونترولرات · **29** حارسًا: Settings · ApiKeys · SecurityAlerts · Profile · ActivityLog · Dashboard · TrafficRadar · TranslationLogs · AiLogs | `8b61d6f` + `c4f7a44` |
| **4f (نسخ احتياطي)** | **18** عملية مع فصل `backup.export` عن `backup.import` + آخر 7 مسارات قديمة | `72f9049` |
| **مراجعة المعايير** | نموذج الأدوار الـ12 + صلاحية `articles.feature` + 197 تأكيدًا | `bb18193` |
| **الترقية الحيّة** | تطبيق النموذج على الإنتاج ونقل `Zead` إلى `managing_editor` | `fe4aab1` ثم `5bbe5b8` |
| **إصلاح الدخول** | الموظفون (مثل المدير التحريري) يدخلون اللوحة بصلاحياتهم | `cc1e940` |
| **أقسام القائمة** | 6 أقسام قابلة للطي + إخفاء قسم كامل بحسب الدور | `75055bd` · `0bd9ef6` |

### 1.6 الثغرات الثلاث المكتشفة والمغلقة

| # | الثغرة | الخطورة | السيناريو الفعلي | الإصلاح | التحقق |
|---|---|---|---|---|---|
| 1 | **تصعيد صلاحيات** | حرجة | أي مستخدم مسجَّل (قارئ) يستطيع `POST /admin/users/store` مع `role_id=9` فينشئ حساب مدير | حراسة مسارات المستخدمين بـ `users.manage` و`users.create` | الطلب الآن 302 → `/login` ثم 403 للمستخدم غير المصرَّح |
| 2 | **تسريب صفحات تشخيص** | عالية | 6 صفحات `diagnostics_*.php` و`health_check.php` تُرجع 200 مع بيانات البيئة وقاعدة البيانات لزائر مجهول | فرض تسجيل الدخول + صلاحية `diagnostics.view` | الصفحات الآن 302 → `/login` |
| 3 | **تعديل بيانات عبر GET** | عالية | `GET /admin/comments/delete/{id}` يحذف تعليقًا دون أي فحص (CSRF عبر رابط/صورة) | تحويل كل عمليات الإشراف إلى **POST فقط** + حراسة `comments.moderate` | `audit_get_mutate.py` → 0 عمليات معدِّلة عبر GET |

كما أُصلح خلل CSRF منفصل: تبويب «المستشار الذكي» كان يرفض الحفظ برسالة `Invalid CSRF token` لأن حرّاس العرض كانت تتحقق من POST؛ فصل التحقق عن العرض، وأصبح زر تشغيل الـ cron عبر POST مُتحقَّقًا فعليًا (`4c0d667`).

### 1.7 فصل القراءة عن التنفيذ

| المنطقة | عرض | تنفيذ |
|---|---|---|
| الإعدادات | `settings.view` | `settings.manage` |
| النشرة البريدية | `newsletter.view` | `newsletter.send` · `newsletter.manage` |
| سجل النشاط | `activity.view` | `activity.manage` |
| التنبيهات الأمنية | `security.view` | `security.manage` |
| الترجمة | `translation.view` | `translation.manage` |
| الذكاء الاصطناعي | `ai.logs` | `ai.manage` |
| رادار الزيارات | `traffic.view` | `traffic.manage` |
| النسخ الاحتياطي | `backup.export` (14 عملية قراءة) | `backup.import` (4 عمليات استعادة) |

### 1.8 شاشة إدارة الصلاحيات

- المسار: `/admin/roles` (قائمة) و`/admin/roles/{id}/edit` (تحرير)، والحفظ عبر `POST /admin/roles/{id}/update`.
- عنصر القائمة مخفي عمّن لا يملك `roles.manage`.
- **دور `admin` غير قابل للتعديل** ولا يكتب عليه البذر أبدًا.
- الحفظ اليدوي يضع علامة `_roles_ui`، فأي دور عدّلته يدويًا **لا يُمَسّ** بتحديث النموذج لاحقًا.
- الأدوار القديمة (بعلامة `_v2` وحدها) **تُرقّى** تلقائيًا للنموذج الحالي عبر علامة `_model: standard-2` — وهذا ما صحّح دور `editor` الحيّ الذي بقي على 55/55.
- الصفحة تُظهر لكل دور: الاسم، العدد `n/55`، وهل هو «مُدار تلقائيًا» أم «مُحرَّر يدويًا».

### 1.9 سلوك التنفيذ وقت التشغيل

```
guardPermission('articles.edit')
  ├─ إن كان RBAC_ENFORCE=false والدور admin/editor  → السلوك القديم حرفيًا (بلا مفاجآت)
  ├─ إن كان الدور أي دور آخر                        → يُحاسب على صلاحياته فورًا
  └─ إن كان RBAC_ENFORCE=true                        → كل الأدوار تُحاسب على صلاحياتها
```

- `admin` و`managing_editor` لا يتأثران بالتفعيل: كلاهما يملك ما يحتاجه.
- الأدوار الواجهية (`reader` · `subscriber`) لا تدخل اللوحة إطلاقًا، لأن `canEnterAdmin()` يشترط صلاحية إدارية واحدة على الأقل — وصلاحيتا `comments.create` و`articles.premium` مستثناتان صراحةً من مجموعة صلاحيات اللوحة.
- `Auth::isAdmin()` يحتفظ بقبول `admin`/`editor` تاريخيًا، ثم يقبل أي دور يملك صلاحية إدارية.

### 1.10 الحالة الحيّة وقاعدة البيانات

| id | الدور | الصلاحيات | مُدار؟ |
|---|---|---|---|
| 9 | `admin` | 55/55 | لا (خارق) |
| 10 | `editor` | 19 | نعم (رُقّي) |
| 11 | `author` | 9 | نعم |
| 12 | `subscriber` | 2 | نعم |
| 13 | `reader` | 1 | نعم |
| 14 | `managing_editor` | 36 | نعم |
| 15 | `publisher` | 16 | نعم |
| 16 | `contributor` | 7 | نعم |
| 17 | `translator` | 8 | نعم |
| 18 | `moderator` | 7 | نعم |
| 19 | `newsletter_manager` | 6 | نعم |
| 20 | `analyst` | 14 | نعم |

**الحسابات:** `admin` (`admin@test.com`) → مدير النظام · `Zead` (`hanirami154@gmail.com`) → **المدير التحريري** (نُقل من `editor` بقرار المالك).

### 1.11 المتبقي — مفتاح التفعيل

`RBAC_ENFORCE` **ما زال `false`**. الصلاحيات كلها مخزَّنة ومحسوبة والحراس موجودون، لكن الحماية النافذة حاليًا هي الحرّاس + قاعدة الدخول الجديدة.

- **التفعيل:** commit واحد يضبط الافتراضي على `true` في `config/rbac.php` (أو `RBAC_ENFORCE=1` في بيئة الاستضافة).
- **التراجع الفوري:** إعادته `false` — بلا أي تغيير في البيانات أو المسارات.
- **قبل التفعيل يُنصح بـ:** تسجيل دخول بحساب `admin` وآخر بحساب `managing_editor` وتجربة: لوحة التحكم · مقال · قسم · نسخ احتياطي · إعدادات.

---

## 2. المحتوى والتصنيف

| الإصلاح | ماذا عولج | الـ commit |
|---|---|---|
| **ترميز عربي مشوّه** | نص عربي داخل `AggregatorController` كان مُفكَّك الترميز (UTF-8 قُرئ cp1256) فظهر رموزًا | `580a028` |
| **ستة أقسام جديدة** | `economic` · `health` · `sports` · `science` · `technology` · `society` مع بذر تلقائي ومصطلحات تصنيف | `7a90257` |
| **مصطلحات تصنيف** | إضافة مصطلحات للعسكري (`mali-tiry`) والسياسة (`cya-sah`) + عدم احتساب أقسام غير موجودة في القاعدة | `c57317c` |
| **ربط 147 مصدر RSS** | كل مصدر إلى قسمه الحقيقي مع أوزان صارمة/ذكية | `d6913fc` |
| **مسارات إدارة مفقودة** | `quick-update-category` · رفع وسائط التغطية الحية · تفاعلات المقال + تصحيح عناوين نقاط النهاية في الواجهات | `ac04428` |
| **أعمدة الأقسام الحقيقية** | إنشاء/تعديل القسم صار يستخدم `name_ar` · `description_ar` · `color` بدل أسماء أعمدة غير موجودة | `34e543f` |
| **انهيار مفتاح أجنبي** | «ترجمة ونشر» كان يفشل عند قسم غير صالح — أُضيف التحقق/الإنشاء | `4bee1fb` |
| **إدارة الأقسام** | حذف فعلي + إخفاء/إظهار دون حذف + رسائل نتيجة واضحة + زر تعديل بجوار المنشور | `f15c5a9` · `a34bcec` |

---

## 3. الاعتمادية والأداء

| المشكلة | السبب الجذري | الحل | الـ commit |
|---|---|---|---|
| **أخطاء 502 متقطعة** | كاش الصفحات كامل + قفل الـ cron + استهلاك عمّال الاستضافة المشتركة | إعادة تصميم الكاش وقفل الـ cron وميزانية زمنية للتشغيل | `5e79b9f` |
| **توقّف الصفحة 10 ثوانٍ** | الكاش يطلب الصفحة من الموقع نفسه أثناء طلب الزائر | منع الطلب الذاتي نهائيًا | `15f22a9` |
| **خطأ 500 في البحث** | استعلام `COUNT` يتلقى معاملات الترتيب (PDO `HY093`) | فصل مجموعتي المعاملات + اختبار MySQL تكاملي | `fb8c9a5` |
| **بحث عربي ضعيف** | لا تطبيع للألف/الياء/التاء المربوطة/الهمزة/التطويل/الأرقام | محرّك تطبيع عربي + كل الحقول ثنائية اللغة + ترتيب صلة + LIKE آمن + اقتراحات فورية | `bfd3c6e` |
| **انقطاع اتصال القاعدة** | الاستضافة تقطع الاتصال أثناء نداءات AI الطويلة | إعادة اتصال واحدة تلقائيًا | `26e7321` |
| **لا مرجع للمخطط** | لا نسخة schema في المستودع | لقطة create-only كاملة (56 جدولًا) في `schema.sql` بالجذر | `948b2d2` · `dc06043` · `e100937` |
| **ملفات قديمة لا تُحذف** | FTP لا يحذف | حذف صريح عبر FTP DELE + قائمة ملفات مسحوبة + بديل 404 عند رفض الحذف | `0f73077` · `0fb8830` · `22d1ade` · `62acf23` |

---

## 4. محرك النشر الآلي (RSS)

| الإصلاح | التفصيل | الـ commit |
|---|---|---|
| **جلب تلقائي كل 30 دقيقة** | GitHub Actions بدل `proc_open` غير المتاح على الاستضافة + زر «شغّل الآن» يعمل | `9bf876d` |
| **سجل التشغيل لا يُفقد** | إعادة محاولة + نسخة على القرص عند فشل الكتابة | `b9d66c2` |
| **كاتب حقيقي بدل id ثابت** | حل الكاتب عبر ربط جدول `roles` (جدول `users` بلا عمود دور) + إيقاف عند ميزانية الوقت | `27da737` · `19d9f44` |
| **نشر فوري لكل مصدر** | المدير يختار المصادر التي تُنشر فورًا، والترجمة اختيارية | `d7e0be7` |
| **صفحة المصادر** | العناصر المنشورة تظهر منشورة + تبويب «المفضلة» حقيقي + جدولة 5 دقائق | `c1540bf` |
| **منع تكرار الأخبار** | حجب القصة المكررة بين المصادر | `75ce08a` |
| **منع تكرار الروابط** | حجب عنوان تغذية مكرر بعد التطبيع + تحديد جماعي وإجراءات جماعية | `df59f23` |
| **تحذير التغذيات الوسيطة** | وسم مصادر تمر عبر `rss.app` / `feedburner` وتحذير إداري | `74e6aaa` |
| **اقتراح بدائل النطاق** | اقتراح `www`/بدون `www` للتغذيات المعطوبة | `9d2c56c` |

---

## 5. الترجمة والذكاء الاصطناعي

| الإصلاح | التفصيل | الـ commit |
|---|---|---|
| **رفض الترجمة الفاشلة** | رفض مخرجات غير مترجمة + ميزانية 14 ثانية + تقصير مهل المزوّدين + نموذج Gemini حقيقي | `571d33a` |
| **تصحيح اسم النموذج** | اسم غير معروف يُصحَّح تلقائيًا بدل إهدار كل نداء | `950a2fd` |
| **تتبّع النماذج الحيّة** | إعادة المحاولة بالنموذج الموصى به عند 404 + قائمة منسدلة صادقة | `2c0213b` |
| **تجاوز المترجم البطيء** | تجاهل المترجم المجاني عندما يوجد مزوّد AI حقيقي | `2d65186` |
| **لا نشر بترجمة فاشلة** | منع نشر قصة فشلت ترجمتها | `7bc029e` |
| **CSRF المستشار الذكي** | فصل تحقق العرض عن POST، وتحقق فعلي لزر cron | `4c0d667` |
| **رابط مكسور في محادثة المساعد** | كل رابط في إجابة الذكاء كان يظهر كـ `$1">العنوان` — النمط `$1` بلا مجموعة التقاط يبقى حرفيًا في JavaScript، ثم كان مُحوِّل الروابط المجرّدة يعيد كتابة `href` لرابط أُنشئ للتو فيُداخل وسمين `<a>`. أُعيد بناء التحويل: الروابط تُبنى مرة واحدة وتُحمى بعلامات مؤقتة، والرابط المجرّد يملك مجموعة التقاط، وعلامات الترقيم تبقى خارج الرابط | هذا الـ commit |

---

## 6. الصور والوسائط

| الإصلاح | التفصيل | الـ commit |
|---|---|---|
| **الصور على خادمنا** | تنزيل صورة الخبر وقت النشر + أداة إصلاح للمقالات القديمة | `79e838c` |
| **ضغط تلقائي** | ضغط الصور إلى حجم الويب عند الحفظ + ضغط دفعة قديمة لمرة واحدة | `4625c06` |
| **تسجيل في مكتبة الوسائط** | كل صورة منزَّلة تُسجَّل في المكتبة + تعبئة رجعية للقديم | `64b0644` |

---

## 7. تجربة لوحة التحكم

| الإصلاح | التفصيل | الـ commit |
|---|---|---|
| **حوارات النظام المستبدلة** | `alert`/`confirm` → إشعارات داخلية + نافذة تأكيد منسّقة | `20ff497` |
| **التحديد الجماعي المعطّل** | سكربت الشريط كان خارج وسم `<script>` | `fe0c261` |
| **تنفيذ بلا إعادة تحميل** | التثبيت والتبديل والحذف والعمليات الجماعية تعمل في مكانها + صور مصغّرة للمفضلة | `14eb14f` |
| **أزرار النشر بعد التحديث** | تظهر فقط للعناصر غير المنشورة، وحالة AJAX مطابقة لحالة الخادم | `ca63d47` |
| **اسم المنصة موحّد** | إعداد واحد يقود كل الواجهات (`Brand`) + ترحيل النصوص المخزَّنة + إعادة مزامنة الملفات الثابتة | `2abdf69` · `48e3124` · `35a6024` · `16d9cea` |
| **شريط جانبي منظّم** | 6 أقسام قابلة للطي مع تذكّر الحالة + إخفاء قسم كامل بحسب الدور | `75055bd` · `0bd9ef6` |

---

## 8. الاختبارات والتدقيقات

### 8.1 حزمة اختبار الصلاحيات (384 تأكيدًا)

| الأداة | التأكيدات | ما تثبته |
|---|---|---|
| `tools/rbac_selftest.php` | 71 | المحرّك، أشكال JSON السبعة، الحرّاس، JSON 403 |
| `tools/rbac_seedtest.php` | 214 | النموذج الـ12، الوراثة، فصل المهام، الترقية، الحفظ اليدوي، رفض الرموز المجهولة |
| `tools/rbac_viewtest.php` | 26 | رسم شاشتي الأدوار من بيانات تجريبية |
| `tools/rbac_entry_test.php` | 27 | دخول الموظفين للوحة ومنع القارئ/المشترك |
| `tools/rbac_section_test.php` | 34 | إظهار/إخفاء أقسام الشريط الجانبي بحسب الدور |
| `tools/rbac_navtest.php` | 12 | عناصر القائمة: كاتب 3 · مدير تحريري 28 · قارئ 0 |
| `tools/rbac_switch_test.php` | — | أثر مفتاح التفعيل (مطفأ/مفعّل) |
| `tools/rbac_matrix.php` | — | مصفوفة `دور × صلاحية` |
| `tools/rbac_coverage.py` | — | **223/223** مسارًا مربوطًا · 0 قديم · 50 صلاحية مستخدمة فعليًا |

### 8.2 أدوات تدقيق مستقلة (تُشغَّل بعد كل تعديل)

> هذه الأدوات سكربتات تدقيق محلية في `%TEMP%\opencode` (ليست جزءًا من حزمة الاختبار داخل المستودع) وتُشغَّل يدويًا بعد أي تعديل على الحرّاس.

| الأداة | النتيجة الحالية |
|---|---|
| `audit_unguarded.py` | 0 إجراء إداري بلا حارس |
| `audit_methods.py` | 0 تعارض طرق HTTP |
| `audit_get_mutate.py` | 0 عملية معدِّلة عبر GET |
| `audit_routes.py` | 6 نتائج إيجابية كاذبة معروفة (مسارات عامة مقصودة) |

### 8.3 اختبارات وظيفية أخرى في `tools/`

`admin_ajax_test` · `admin_nav_test` · `aggregator_publish_ui_test` · `ai_render_test.js` · `ai_translation_test` · `brand_test` · `category_test` · `db_resilience_test` · `dedupe_test` · `feeds_page_test` · `guard_test` · `image_store_test` · `js_syntax_test` · `pagecache_test` · `rss_sources_test` · `search_test` · `search_mysql_test` · `view_script_test`.

---

## 9. كيف تتحقق بنفسك

```powershell
# اختبارات الصلاحيات
php tools\rbac_selftest.php
php tools\rbac_seedtest.php
php tools\rbac_entry_test.php
php tools\rbac_section_test.php

# تغطية الحراس
python tools\rbac_coverage.py     # المتوقع: routes=223 bound=223 legacy=0

# التفعيل / التراجع
# config/rbac.php  →  RBAC_ENFORCE true|false
```

**فحص حيّ بعد كل نشر:** الصفحة الرئيسية 200 · الأقسام التسعة سليمة · المسارات المحمية 302 إلى `/login` · `/admin/roles` و`/admin/roles/{id}/edit` محميان.

---

## 10. ملاحظات وخطوات متبقية

1. **`RBAC_ENFORCE` مطفأ** — التفعيل قرار مالك منفصل، والتراجع بتغيير قيمة واحدة.
2. **`editor` صار محدودًا** بعد اعتماد المعايير: لم يعد يملك المستخدمين/الإعدادات/النسخ الاحتياطي. حساب المالك `Zead` نُقل إلى `managing_editor` (36 صلاحية) تفاديًا لأي تعطيل.
3. **`analyst` للقراءة فقط** — يرى الإحصاءات والسجلات ولا يعدّل شيئًا؛ مفيد لحسابات المراقبة والتقارير.
4. **`contributor` لا ينشر** — يكتب مسودات فقط (فصل مهام تحريري معتاد).
5. **ملفات مؤقتة**: كل سكربتات الصيانة المؤقتة (`apply_roles.php`، مجسّات التشخيص، ملفات probe) حُذفت من المستودع ومُنع الوصول إليها.
6. **مجموعة العمل تحتوي تعديلات غير مُودَعة** لا علاقة لها بهذا التقرير (ملفات تشخيص و`.htaccess` و`config/database.php` وغيرها) — لم تُمَس.

---

## 11. فهرس الـ commits الأساسية

```
4747b51  Remove temporary probe
64b0644  Register downloaded images in the media library + backfill old ones
4625c06  Compress images to web size on save + one-time recompress for old images
79e838c  Images live on our server: download at publish + repair tool for old articles
9d2c56c  Suggest www/apex variants for broken feeds
2c0213b  Gemini: track live models, retry recommended model on 404, honest dropdown
4c0d667  Fix Invalid CSRF token on AI advisor tab
7bc029e  Cron: never publish a story whose translation failed
2d65186  Translation: skip the slow free translator when a real AI provider is configured
950a2fd  Gemini: auto-correct an unknown model name
571d33a  Translation: reject untranslated output, 14s budget, real Gemini model
14eb14f  All admin actions run in place; favourites thumbnails
75ce08a  Block duplicate stories across sources + favourites as a full tab
c1540bf  Feeds page: auto-published items, favourites tab, 5-minute schedule
d7e0be7  Per-source auto publishing
19d9f44  Cron: pick the author through the roles join
27da737  Cron: resolve a real author, time budget between items
b9d66c2  Cron: never lose the run log (retry + disk fallback)
26e7321  Database: reconnect once during long AI calls
9bf876d  Automatic RSS fetching via GitHub Actions every 30 min
20ff497  Replace blocking alert/confirm with toasts and a styled modal
74e6aaa  Flag RSS feeds served through third-party wrappers
fe0c261  Fix bulk-selection JS outside its <script> tag
df59f23  RSS sources: block duplicate feed URLs + bulk actions
15f22a9  PageCache: never self-request during a visitor request
5e79b9f  Fix intermittent 502: page cache + cron lock + time budget
e100937  Move latest create-only schema to repo root schema.sql
948b2d2  Track latest database schema (55 tables, exported from production)
a34bcec  Categories admin: show flash messages
f15c5a9  Categories: working delete + hide/show; aggregator edit button
62acf23  Deploy: neutralise retired files with a 404 stub
22d1ade  Deploy: use curl FTP DELE for retired files
0f73077  Deploy: explicitly delete retired files
35a6024  Brand: migrate meta description settings
16d9cea  Brand: refresh stale service-worker notification title
48e3124  Brand rename migrates stored text
2abdf69  Platform name as a single setting (Brand helper)
fb8c9a5  Fix search 500: COUNT query PDO HY093
bfd3c6e  Arabic-aware search engine with relevance ranking
ca63d47  Fix publish buttons reappearing after refresh
0bd9ef6  Sidebar sections: per-role visibility control
75055bd  Admin sidebar: 6 collapsible logical sections
cc1e940  Fix: staff roles could not enter the admin panel
5bbe5b8  Role seeder: versioned model marker
fe4aab1  Temp: apply standard role model, promote editor to managing_editor
bb18193  Roles: standard RBAC model (12 roles, least privilege, SoD)
4bdfd5d  Temp: remove role maintenance script
72f9049  Batch 4f: backup centre permissions (export vs import)
8b61d6f  Batch 4e: settings, API keys, security, activity, AI, traffic
5543cca  Batch 4d: publishing ops (feeds, sources, classifier, newsletter, cron)
a878639  Batch 4c: comments, messages, live-blog; moderation POST-only
fd14084  Batch 4b: content areas
9f2f457  Batch 4a + security: 20 missing guards
084ffc2  Phase 3: hide 31 menu/topbar items by permission
182fc81  Keep editor full platform access (owner decision)
d85e18d  Phase 2: roles & permissions screen + RoleSeeder
ea55d8f  Phase 1: RBAC engine (49 permissions, 6 groups) + guards + tools
d6913fc  Map all 147 RSS sources to real categories
ac04428  Fix missing admin API routes
7a90257  Add six default categories with auto-seeding
c57317c  Auto-classification terms for military and politics
34e543f  Fix category create/edit: real categories columns
580a028  Fix mojibake in AggregatorController
4bee1fb  Fix FK crash on translate & publish
3e999cc  Fix admin diagnostics page: deploy tool scripts
```

---

*أُعِدّ هذا التقرير من سجل المستودع الفعلي وحزم الاختبار، وكل رقم فيه قابل لإعادة التحقق بالأوامر في القسم 9.*
