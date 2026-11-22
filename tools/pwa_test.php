<?php
/**
 * PWA install chain: manifest credentials, worker hardening, install buttons.
 *
 * Bug: the manifest was fetched without credentials on InfinityFree, so the
 * host answered with an HTML security page and the browser reported a manifest
 * syntax error. The worker also intercepted cross-origin requests and could
 * respond with nothing at all.
 *
 * Run: php tools/pwa_test.php
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
$header = (string) file_get_contents($root . '/views/partials/header.php');
$footer = (string) file_get_contents($root . '/views/partials/footer.php');
$sw = (string) file_get_contents($root . '/sw.js');
$js = (string) file_get_contents($root . '/assets/js/tech-platform.js');
$manifest = json_decode((string) file_get_contents($root . '/manifest.json'), true);

echo "--- the manifest is requested with cookies ---\n";
t('the link carries crossorigin=use-credentials', strpos($header, 'rel="manifest"') !== false
    && strpos($header, 'crossorigin="use-credentials"') !== false);
t('the manifest is valid JSON', is_array($manifest));
t('it advertises 192px and 512px icons', is_array($manifest)
    && strpos(json_encode($manifest), '192') !== false
    && strpos(json_encode($manifest), '512') !== false);
t('it starts in standalone mode', is_array($manifest) && ($manifest['display'] ?? '') === 'standalone');

echo "\n--- the worker never breaks on foreign traffic ---\n";
t('the cache is versioned to v3', strpos($sw, "tech-news-pwa-v3") !== false);
t('only same-origin requests are handled', strpos($sw, 'url.origin !== self.location.origin') !== false);
t('non-GET is ignored', strpos($sw, "if (req.method !== 'GET') return;") !== false);
t('admin and api are skipped', strpos($sw, "url.pathname.startsWith('/admin')") !== false
    && strpos($sw, "url.pathname.startsWith('/api/')") !== false);
t('every failure resolves to a Response', strpos($sw, 'function offlineResponse()') !== false
    && substr_count($sw, 'offlineResponse()') >= 3);
t('the old undefined-returning catch is gone', strpos($sw, ".catch(() => caches.match(req))") === false);
t('pre-cache failures are isolated per asset', strpos($sw, 'PRECACHE_ASSETS.map') !== false
    && strpos($sw, '[PWA SW] Skipped asset:') !== false);

echo "\n--- install buttons exist in both places ---\n";
t('a header install button exists', strpos($header, 'pwa-install-trigger') !== false);
t('a footer install button exists', strpos($footer, 'pwa-install-trigger') !== false);
t('both call the shared entry point', substr_count($header, 'openPwaGuide') >= 1
    && substr_count($footer, 'openPwaGuide') >= 1);
t('triggers are hidden when already installed', strpos($js, 'hideInstallTriggers()') !== false
    && strpos($js, 'if (isStandaloneApp)') !== false);

echo "\n--- the guide covers every platform ---\n";
t('window.installPwa is exposed', strpos($js, 'window.installPwa = async function') !== false);
t('window.openPwaGuide is exposed', strpos($js, 'window.openPwaGuide = function') !== false);
t('it uses the deferred prompt first', strpos($js, 'if (deferredPrompt) {') !== false
    && strpos($js, 'deferredPrompt.prompt();') !== false);
t('iOS steps mention Safari and Add to Home Screen', strpos($js, 'Safari') !== false
    && strpos($js, 'الشاشة الرئيسية') !== false);
t('Android steps are present', strpos($js, 'isAndroid') !== false);
t('desktop steps are present', strpos($js, 'Chrome') !== false && strpos($js, 'Edge') !== false);
t('the dialog closes with Escape and backdrop', strpos($js, "e.target === modal") !== false
    && strpos($js, "e.key === 'Escape'") !== false);
t('it stays RTL and accessible', strpos($js, 'pwa-guide-modal') !== false
    && strpos($js, 'aria-modal') !== false);

echo "\npassed: $pass  failed: $fail\n";
exit($fail === 0 ? 0 : 1);
