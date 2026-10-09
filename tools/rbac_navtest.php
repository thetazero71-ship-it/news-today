<?php
/**
 * Phase 3 check: does the sidebar degrade gracefully for a scoped role?
 * Renders the nav for a fake "author" and asserts which items survive.
 *
 * Run: php tools/rbac_navtest.php
 */
if (php_sapi_name() !== 'cli') {
    exit("CLI only\n");
}

$root = dirname(__DIR__);
define('RBAC_ENFORCE', false);
define('RBAC_SUPER_ROLE', 'admin');
require_once $root . '/core/Permissions.php';

function navItemsFor(array $user)
{
    $perms = array(
        'admin'               => 'dashboard.view',
        'admin/analytics'     => 'analytics.view',
        'admin/articles'      => 'articles.view',
        'admin/tutorials'     => 'tutorials.manage',
        'admin/news-feeds'    => 'feeds.view',
        'admin/rss-sources'   => 'sources.manage',
        'admin/cron'          => 'cron.view',
        'admin/categories'    => 'categories.manage',
        'admin/classifier-rules' => 'classifier.manage',
        'admin/comments'      => 'comments.moderate',
        'admin/messages'      => 'messages.view',
        'admin/live-blog'     => 'liveblog.manage',
        'admin/polls'         => 'polls.manage',
        'admin/users'         => 'users.view',
        'admin/settings'      => 'settings.view',
        'admin/api-keys'      => 'apikeys.manage',
        'admin/translation-logs' => 'translation.view',
        'admin/ai-logs'       => 'ai.logs',
        'admin/media'         => 'media.view',
        'admin/pages'         => 'pages.manage',
        'admin/menus'         => 'menus.manage',
        'admin/ads'           => 'ads.manage',
        'admin/newsletter'    => 'newsletter.view',
        'admin/activity-log'  => 'activity.view',
        'admin/traffic-radar' => 'traffic.view',
        'admin/security-alerts' => 'security.view',
        'admin/backup'        => 'backup.export',
        'admin/diagnostics'   => 'diagnostics.view',
    );

    $visible = array();
    foreach ($perms as $target => $perm) {
        if (Permissions::can($user, $perm)) {
            $visible[] = $target;
        }
    }
    return $visible;
}

$author = array('role_name' => 'author', 'permissions' => '{"articles":["view","create","edit_own","publish_own"],"media":["view","upload"],"comments":["create"],"dashboard":["view"],"profile":["manage"]}');
$editor = array('role_name' => 'editor', 'permissions' => '{"all":true}');
$reader = array('role_name' => 'reader', 'permissions' => '{"comments":["create"]}');

$a = navItemsFor($author);
$e = navItemsFor($editor);
$r = navItemsFor($reader);

echo "author sees " . count($a) . " items:\n  " . implode(', ', $a) . "\n\n";
echo "editor sees " . count($e) . " items\n\n";
echo "reader sees " . count($r) . " items: " . implode(', ', $r) . "\n\n";

$fail = 0;
function expect($label, $cond)
{
    global $fail;
    echo ($cond ? 'PASS ' : 'FAIL ') . $label . "\n";
    if (!$cond) {
        $fail++;
    }
}

expect('author keeps articles', in_array('admin/articles', $a, true));
expect('author keeps media', in_array('admin/media', $a, true));
expect('author keeps dashboard', in_array('admin', $a, true));
expect('author cannot see backup', !in_array('admin/backup', $a, true));
expect('author cannot see users', !in_array('admin/users', $a, true));
expect('author cannot see settings', !in_array('admin/settings', $a, true));
expect('author cannot see api-keys', !in_array('admin/api-keys', $a, true));
expect('author cannot moderate comments', !in_array('admin/comments', $a, true));

expect('editor sees everything (owner decision)', count($e) === 28);
expect('editor sees backup', in_array('admin/backup', $e, true));
expect('editor sees users', in_array('admin/users', $e, true));

expect('reader sees nothing in admin nav', count($r) === 0);

echo "\n" . ($fail === 0 ? "ALL PASS" : "FAILURES: $fail") . "\n";
exit($fail === 0 ? 0 : 1);