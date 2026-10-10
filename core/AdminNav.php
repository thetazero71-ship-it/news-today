<?php

/**
 * Admin sidebar structure: logical groups, each collapsible.
 *
 * Single source of truth for the sidebar. The layout only renders what the
 * signed-in user is allowed to see (checked with admin_can() per item), so a
 * group with no visible items is skipped entirely.
 */
class AdminNav
{
    /**
     * @return array<int, array{key:string,label:string,icon:string,items:array}>
     */
    public static function sections(): array
    {
        return array(
            array(
                'key'   => 'general',
                'label' => 'عام',
                'icon'  => 'bi-grid',
                'items' => array(
                    self::item('admin', 'الرئيسية (Dashboard)', 'bi-speedometer2', '', 'dashboard.view'),
                    self::item('admin/analytics', 'التحليلات والإحصاءات', 'bi-graph-up-arrow', '', 'analytics.view'),
                    self::item('admin/traffic-radar', 'رادار الزوار والعناكب', 'bi-broadcast-pin', 'text-info', 'traffic.view'),
                    self::item('admin/activity-log', 'سجل العمليات', 'bi-shield-check', '', 'activity.view'),
                ),
            ),
            array(
                'key'   => 'content',
                'label' => 'المحتوى',
                'icon'  => 'bi-journal-richtext',
                'items' => array(
                    self::item('admin/articles', 'إدارة المقالات', 'bi-journal-richtext', '', 'articles.view'),
                    self::item('admin/categories', 'الأقسام والتصنيفات', 'bi-tags', '', 'categories.manage'),
                    self::item('admin/pages', 'الصفحات الثابتة', 'bi-file-earmark-text', '', 'pages.manage'),
                    self::item('admin/menus', 'القوائم والروابط', 'bi-list-nested', '', 'menus.manage'),
                    self::item('admin/tutorials', 'الشروحات والدروس', 'bi-journal-code', 'text-info', 'tutorials.manage'),
                    self::item('admin/polls', 'استطلاعات الرأي', 'bi-bar-chart-line-fill', 'text-warning', 'polls.manage'),
                    self::item('admin/ads', 'المساحات الإعلانية', 'bi-badge-ad', '', 'ads.manage'),
                    self::item('admin/media', 'مكتبة الوسائط', 'bi-images', '', 'media.view'),
                ),
            ),
            array(
                'key'   => 'publishing',
                'label' => 'النشر والأتمتة',
                'icon'  => 'bi-lightning-charge-fill',
                'items' => array(
                    self::item('admin/news-feeds', 'استيراد ونشر الأخبار (RSS)', 'bi-lightning-charge-fill', 'text-warning', 'feeds.view'),
                    self::item('admin/rss-sources', 'مصادر RSS', 'bi-rss-fill', 'text-warning', 'sources.manage'),
                    self::item('admin/cron', 'النشر التلقائي (Cron)', 'bi-clock-history', 'text-success', 'cron.view'),
                    self::item('admin/live-blog', 'التغطيات الحية', 'bi-broadcast', 'text-danger', 'liveblog.manage'),
                    self::item('admin/newsletter', 'النشرة البريدية', 'bi-envelope-paper', '', 'newsletter.view'),
                ),
            ),
            array(
                'key'   => 'interaction',
                'label' => 'التفاعل والمجتمع',
                'icon'  => 'bi-chat-dots',
                'items' => array(
                    self::item('admin/comments', 'التعليقات والمراجعة', 'bi-chat-dots', '', 'comments.moderate', '', array('badge' => 'messages')),
                    self::item('admin/messages', 'الرسائل والاتصالات', 'bi-inbox', 'text-info', 'messages.view', '', array('badge' => 'unread')),
                ),
            ),
            array(
                'key'   => 'ai',
                'label' => 'الذكاء الاصطناعي',
                'icon'  => 'bi-robot',
                'items' => array(
                    self::item('admin/classifier-rules', 'مصطلحات التصنيف الذكي', 'bi-diagram-3-fill', 'text-primary', 'classifier.manage'),
                    self::item('admin/translation-logs', 'سجلات الترجمة', 'bi-translate', 'text-success', 'translation.view'),
                    self::item('admin/ai-logs', 'محادثات المرشد والحصص', 'bi-robot', 'text-primary', 'ai.logs'),
                ),
            ),
            array(
                'key'   => 'system',
                'label' => 'النظام والإعدادات',
                'icon'  => 'bi-gear',
                'items' => array(
                    self::item('admin/users', 'المستخدمون', 'bi-people', '', 'users.view'),
                    self::item('admin/roles', 'الأدوار والصلاحيات', 'bi-shield-lock', '', 'roles.manage'),
                    self::item('admin/settings', 'الإعدادات الشاملة', 'bi-sliders2', '', 'settings.view'),
                    self::item('admin/api-keys', 'مفاتيح API', 'bi-key-fill', 'text-info', 'apikeys.manage'),
                    self::item('admin/security-alerts', 'تنبيهات الأمان', 'bi-shield-exclamation', 'text-danger', 'security.view'),
                    self::item('admin/backup', 'النسخ الاحتياطي', 'bi-database-down', 'text-warning', 'backup.export'),
                    self::item('admin/diagnostics', 'مركز تشخيص النظام', 'bi-heart-pulse-fill', '', 'diagnostics.view', 'highlight'),
                ),
            ),
        );
    }

    private static function item($active, $label, $icon, $color, $permission, $style = '', array $extra = array()): array
    {
        return array(
            'active'  => $active,
            'label'   => $label,
            'icon'    => $icon,
            'color'   => $color,
            'perm'    => $permission,
            'style'   => $style,
            'badge'   => $extra['badge'] ?? '',
        );
    }

    /**
     * Flat list of every item (used by tests and diagnostics).
     */
    public static function items(): array
    {
        $all = array();
        foreach (self::sections() as $section) {
            foreach ($section['items'] as $item) {
                $item['group'] = $section['key'];
                $all[] = $item;
            }
        }
        return $all;
    }
}