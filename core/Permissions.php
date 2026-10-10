<?php

/**
 * Permission catalog + evaluation engine (role based access control).
 *
 * This class only DECIDES. It is not wired into the request flow yet: the
 * enforcement switch (config/rbac.php) is off, so behaviour is unchanged until
 * controllers start calling guardPermission() and RBAC_ENFORCE is turned on.
 *
 * Supported shapes in roles.permissions (all are read, nothing is rewritten):
 *   {"all": true}                              -> everything
 *   {"articles": ["create","edit"]}            -> articles.create, articles.edit
 *   {"articles": {"create": true}}             -> same
 *   {"articles": "create"}                     -> articles.create
 *   {"articles": "*"}                          -> every articles.* action
 *   {"*": true}                                -> everything
 *   ["articles.create", "comments.create"]     -> flat token list
 *   {"premium_content": true}                  -> legacy token, mapped to articles.premium
 */
class Permissions
{
    /** @var array<string, array<string, string>> entity => action => Arabic label */
    private static $catalog = array(
        'dashboard'   => array('view' => 'عرض لوحة التحكم'),
        'analytics'   => array('view' => 'عرض الإحصائيات والتحليلات'),
        'traffic'     => array(
            'view'   => 'عرض رادار الزيارات',
            'manage' => 'تنظيف بيانات الزيارات',
        ),
        'activity'    => array(
            'view'   => 'سجل النشاط',
            'manage' => 'تنظيف سجل النشاط',
        ),
        'profile'     => array('manage' => 'إدارة الملف الشخصي'),

        'articles'    => array(
            'view'         => 'عرض المقالات',
            'create'       => 'إنشاء مقال',
            'edit'         => 'تعديل أي مقال',
            'edit_own'     => 'تعديل مقالاته فقط',
            'publish'      => 'نشر أي مقال',
            'publish_own'  => 'نشر مقالاته فقط',
            'feature'      => 'تحديد مقال مميز',
            'delete'       => 'حذف المقالات',
            'premium'      => 'قراءة المحتوى المميز',
        ),
        'categories'  => array('manage' => 'إدارة الأقسام'),
        'pages'       => array('manage' => 'إدارة الصفحات'),
        'menus'       => array('manage' => 'إدارة القوائم'),
        'ads'         => array('manage' => 'إدارة الإعلانات'),
        'polls'       => array('manage' => 'إدارة الاستطلاعات'),
        'tutorials'   => array('manage' => 'إدارة الدروس'),
        'media'       => array(
            'view'   => 'عرض مكتبة الوسائط',
            'upload' => 'رفع الوسائط',
            'delete' => 'حذف الوسائط',
        ),

        'comments'    => array(
            'create'   => 'كتابة تعليقات',
            'moderate' => 'مراجعة التعليقات',
        ),
        'messages'    => array(
            'view'   => 'عرض رسائل التواصل',
            'reply'  => 'الرد على الرسائل',
            'manage' => 'حذف رسائل التواصل',
        ),
        'liveblog'    => array('manage' => 'إدارة التغطيات الحية'),

        'feeds'       => array(
            'view'    => 'عرض المصادر والأخبار المجمّعة',
            'publish' => 'ترجمة ونشر الأخبار',
        ),
        'sources'     => array('manage' => 'إدارة مصادر RSS'),
        'classifier'  => array('manage' => 'إدارة قواعد التصنيف'),
        'cron'        => array(
            'view'   => 'عرض مهام Cron',
            'manage' => 'تشغيل/إيقاف مهام Cron',
        ),
        'newsletter'  => array(
            'view'   => 'عرض النشرة البريدية',
            'send'   => 'إرسال campaigns',
            'manage' => 'إدارة المشتركين والقوالب',
        ),

        'ai'          => array(
            'logs'   => 'سجلات الذكاء الاصطناعي',
            'manage' => 'إدارة حصص المرشد ومحادثاته',
        ),
        'translation' => array(
            'view'   => 'سجل الترجمة',
            'manage' => 'حذف سجلات الترجمة',
        ),

        'settings'    => array(
            'view'   => 'عرض الإعدادات',
            'manage' => 'تعديل إعدادات المنصة',
        ),
        'users'       => array(
            'view'   => 'عرض المستخدمين',
            'manage' => 'إدارة المستخدمين',
        ),
        'roles'       => array('manage' => 'إدارة الأدوار والصلاحيات'),
        'apikeys'     => array('manage' => 'إدارة مفاتيح API'),
        'security'    => array(
            'view'   => 'عرض التنبيهات الأمنية',
            'manage' => 'إجراءات الحماية (حظر/إعدادات)',
        ),
        'backup'      => array(
            'export' => 'تصدير نسخة احتياطية',
            'import' => 'استيراد/استعادة قاعدة البيانات',
        ),
        'diagnostics' => array('view' => 'أدوات التشخيص'),
    );

    /** Sections used by the permissions screen. */
    private static $groups = array(
        'عام'         => array('dashboard', 'analytics', 'traffic', 'activity', 'profile'),
        'المحتوى'     => array('articles', 'categories', 'pages', 'menus', 'ads', 'polls', 'tutorials', 'media'),
        'التفاعل'     => array('comments', 'messages', 'liveblog'),
        'النشر والأتمتة' => array('feeds', 'sources', 'classifier', 'cron', 'newsletter'),
        'الذكاء الاصطناعي' => array('ai', 'translation'),
        'النظام (حساس)' => array('settings', 'users', 'roles', 'apikeys', 'security', 'backup', 'diagnostics'),
    );

    /** Legacy tokens kept working for roles created before this catalog. */
    private static $legacyAliases = array(
        'premium_content' => 'articles.premium',
    );

    public static function catalog(): array
    {
        return self::$catalog;
    }

    public static function groups(): array
    {
        return self::$groups;
    }

    /**
     * Flat list of every permission as "entity.action" => label.
     */
    public static function all(): array
    {
        $flat = array();
        foreach (self::$catalog as $entity => $actions) {
            foreach ($actions as $action => $label) {
                $flat[$entity . '.' . $action] = $label;
            }
        }
        return $flat;
    }

    public static function actionsFor(string $entity): array
    {
        return array_keys(self::$catalog[$entity] ?? array());
    }

    /**
     * Arabic name of an area (entity).
     */
    public static function entityLabel(string $entity): string
    {
        $labels = array(
            'dashboard'   => 'لوحة التحكم',
            'analytics'   => 'الإحصائيات',
            'traffic'     => 'رادار الزيارات',
            'activity'    => 'سجل النشاط',
            'profile'     => 'الملف الشخصي',
            'articles'    => 'المقالات',
            'categories'  => 'الأقسام',
            'pages'       => 'الصفحات',
            'menus'       => 'القوائم',
            'ads'         => 'الإعلانات',
            'polls'       => 'الاستطلاعات',
            'tutorials'   => 'الدروس',
            'media'       => 'مكتبة الوسائط',
            'comments'    => 'التعليقات',
            'messages'    => 'رسائل التواصل',
            'liveblog'    => 'التغطيات الحية',
            'feeds'       => 'المصادر والأخبار المجمّعة',
            'sources'     => 'مصادر RSS',
            'classifier'  => 'قواعد التصنيف التلقائي',
            'cron'        => 'المهام المجدولة',
            'newsletter'  => 'النشرة البريدية',
            'ai'          => 'الذكاء الاصطناعي',
            'translation' => 'الترجمة',
            'settings'    => 'إعدادات المنصة',
            'users'       => 'المستخدمون',
            'roles'       => 'الأدوار والصلاحيات',
            'apikeys'     => 'مفاتيح API',
            'security'    => 'الأمان والتنبيهات',
            'backup'      => 'النسخ الاحتياطي',
            'diagnostics' => 'أدوات التشخيص',
        );
        return $labels[$entity] ?? $entity;
    }

    /**
     * Enforcement switch. While off, guards behave exactly like guardAdmin().
     */
    public static function enforced(): bool
    {
        return defined('RBAC_ENFORCE') && RBAC_ENFORCE === true;
    }

    /**
     * Role that bypasses the catalog. Falls back to "admin" when the config
     * file has not been loaded (CLI tools, early autoload).
     */
    public static function superRole(): string
    {
        return defined('RBAC_SUPER_ROLE') ? (string) RBAC_SUPER_ROLE : 'admin';
    }

    /**
     * Split "articles.edit" into [entity, action]. A bare "articles" means
     * "any action on articles".
     */
    public static function split(string $permission): array
    {
        $permission = trim($permission);
        if (strpos($permission, '.') === false) {
            return array($permission, '');
        }
        return explode('.', $permission, 2);
    }

/**
     * Permissions that belong to the public site, not to the admin panel.
     * Holding one of these alone does NOT grant entry to the admin area.
     */
    private static $frontendOnly = array(
        'comments.create',
        'articles.premium',
    );

    /**
     * Every permission that unlocks something inside the admin panel.
     */
    public static function adminAreaPermissions(): array
    {
        $out = array();
        foreach (array_keys(self::all()) as $perm) {
            if (!in_array($perm, self::$frontendOnly, true)) {
                $out[$perm] = true;
            }
        }
        return $out;
    }

    /**
     * May this user reach the admin panel at all?
     */
    public static function canEnterAdmin(?array $user): bool
    {
        if (!$user) {
            return false;
        }
        if (mb_strtolower(trim((string) ($user['role_name'] ?? ''))) === mb_strtolower(self::superRole())) {
            return true;
        }
        foreach (array_keys(self::adminAreaPermissions()) as $perm) {
            if (self::can($user, $perm)) {
                return true;
            }
        }
        return false;
    }

    /**
 * Sections the role may see in the sidebar.
 *
 * Returns null when the role does not restrict sections (then visibility is
 * derived from the permissions themselves), otherwise a list of section keys.
 */
    public static function sectionAccess(?array $user): ?array
    {
        if (!$user) {
            return array();
        }
        if (mb_strtolower(trim((string) ($user['role_name'] ?? ''))) === mb_strtolower(self::superRole())) {
            return null; // super role sees everything
        }

        $raw = $user['permissions'] ?? null;
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
        }
        if (!is_array($raw) || !isset($raw['_sections']) || !is_array($raw['_sections'])) {
            return null; // derive from permissions
        }

        return array_values(array_filter(array_map(function ($s) {
            return mb_strtolower(trim((string) $s));
        }, $raw['_sections']), function ($s) {
            return $s !== '';
        }));
    }

    /**
     * Is this sidebar section allowed for the role?
     */
    public static function canSeeSection(?array $user, string $section): bool
    {
        $allowed = self::sectionAccess($user);
        if ($allowed === null) {
            return true;
        }
        return in_array(mb_strtolower($section), $allowed, true);
    }

    /**
     * Do the given user hold the permission?
     */
    public static function can(?array $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }

        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        // The super role bypasses the catalog entirely.
        $roleName = strtolower(trim((string) ($user['role_name'] ?? '')));
        if ($roleName !== '' && $roleName === mb_strtolower(self::superRole())) {
            return true;
        }

        $granted = self::grantedTokens($user);
        if (isset($granted['*']) || isset($granted['all'])) {
            return true;
        }

        list($entity, $action) = self::split($permission);

        // A bare entity means "any action on this entity".
        if ($action === '') {
            if (isset($granted[$entity]) || isset($granted[$entity . '*']) || isset($granted[$entity . '.*'])) {
                return true;
            }
            // "articles" also matches any actions.articles.* token
            foreach ($granted as $token => $_) {
                if (strpos($token, $entity . '.') === 0) {
                    return true;
                }
            }
            return false;
        }

        if (isset($granted[$entity . '.' . $action])) {
            return true;
        }

        // Entity-wide wildcard: {"articles": "*"} or {"articles.*": true}
        return isset($granted[$entity]) || isset($granted[$entity . '*']) || isset($granted[$entity . '.*']);
    }

    /**
     * Convenience wrapper around the currently signed-in user.
     */
    public static function check(string $permission): bool
    {
        if (class_exists('Auth')) {
            return self::can(Auth::user(), $permission);
        }
        return false;
    }

    /**
     * Normalise roles.permissions into a set of granted tokens.
     *
     * @return array<string, true>
     */
    public static function grantedTokens(?array $user): array
    {
        $raw = $user['permissions'] ?? null;
        if ($raw === null || $raw === '') {
            return array();
        }

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = (json_last_error() === JSON_ERROR_NONE) ? $decoded : null;
        }
        if (!is_array($raw)) {
            return array();
        }

        $tokens = array();

        // Flat list form: ["articles.create", ...]
        if (array_keys($raw) === range(0, count($raw) - 1)) {
            foreach ($raw as $item) {
                if (is_string($item) && trim($item) !== '') {
                    $tokens[mb_strtolower(trim($item))] = true;
                }
            }
            return self::applyAliases($tokens);
        }

        foreach ($raw as $key => $value) {
            $key = mb_strtolower(trim((string) $key));
            // Keys starting with "_" are markers (e.g. schema version), not permissions.
            if ($key === '' || $key[0] === '_') {
                continue;
            }

            if ($value === true || $value === 1 || $value === '1' || $value === '*') {
                $tokens[$key] = true;
                continue;
            }
            if ($value === false || $value === 0 || $value === '0' || $value === null || $value === '') {
                continue;
            }

            if (is_string($value)) {
                $value = trim($value);
                if ($value === '*' || $value === 'all') {
                    $tokens[$key] = true;
                } else {
                    $tokens[$key . '.' . mb_strtolower($value)] = true;
                }
                continue;
            }

            if (is_array($value)) {
                foreach ($value as $action) {
                    if ($action === true) {
                        $tokens[$key] = true;
                        continue;
                    }
                    if (!is_string($action)) {
                        continue;
                    }
                    $action = mb_strtolower(trim($action));
                    if ($action === '') {
                        continue;
                    }
                    if ($action === '*' || $action === 'all') {
                        $tokens[$key] = true;
                        continue;
                    }
                    $tokens[$key . '.' . $action] = true;
                }
                continue;
            }
        }

        return self::applyAliases($tokens);
    }

    private static function applyAliases(array $tokens): array
    {
        foreach (self::$legacyAliases as $legacy => $modern) {
            if (isset($tokens[$legacy])) {
                $tokens[$modern] = true;
            }
        }
        return $tokens;
    }

    /**
     * Human readable summary of what a role may do (used in the users list).
     */
    public static function describe(?array $user): string
    {
        if (isset($user['role_name']) && strtolower((string) $user['role_name']) === mb_strtolower(self::superRole())) {
            return 'كل الصلاحيات';
        }

        $tokens = self::grantedTokens($user);
        if (isset($tokens['*']) || isset($tokens['all'])) {
            return 'كل الصلاحيات';
        }
        unset($tokens['*'], $tokens['all']);

        if (empty($tokens)) {
            return 'بدون صلاحيات إدارية';
        }

        $labels = array();
        foreach (array_keys($tokens) as $token) {
            list($entity, $action) = self::split($token);
            $label = null;
            if (isset(self::$catalog[$entity])) {
                $actions = self::$catalog[$entity];
                if (isset($actions[$action])) {
                    $label = $actions[$action];
                } else {
                    foreach ($actions as $fallback) {
                        $label = $fallback;
                        break;
                    }
                }
            }
            $labels[$label === null ? $token : $label] = true;
        }

        $labels = array_keys($labels);
        if (count($labels) > 4) {
            return implode('، ', array_slice($labels, 0, 4)) . ' … (+' . (count($labels) - 4) . ')';
        }
        return implode('، ', $labels);
    }
}
