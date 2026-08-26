<?php
/**
 * phpBB SEO Framework — Lightweight Pre-Bootstrap Inbound Front Controller.
 *
 * Runs BEFORE phpBB common.php boots.
 * Matches inbound SEO URLs using ultra-fast compiled route cache.
 * Preserves public SEO REQUEST_URI for canonical checks while normalizing internal
 * execution server context so phpBB calculates root asset paths as "./".
 *
 * Resides exclusively at: ext/phpbbseo/framework/rewrite.php
 * Deterministically resolves phpBB root from __DIR__ without requiring root file copies.
 */

// 1. Deterministically resolve phpBB installation root filesystem path from __DIR__
$rawRoot = realpath(__DIR__ . '/../../../');
if ($rawRoot === false || !is_file($rawRoot . '/common.php') || !is_file($rawRoot . '/app.php')) {
    http_response_code(500);
    echo 'Error: Unable to locate phpBB installation root from SEO rewrite handler.';
    exit;
}

$phpbbRootPath = rtrim(str_replace('\\', '/', $rawRoot), '/') . '/';

// 2. Ensure current working directory is the phpBB installation root
@chdir($phpbbRootPath);

$phpbb_root_path = './';
$phpEx = 'php';



$cacheFile = __DIR__ . '/store/compiled_routes.php';

// Safe hardcoded target script map (Security rule: user input NEVER reaches include path)
$targetScripts = [
    'topic'  => 'viewtopic.php',
    'forum'  => 'viewforum.php',
    'member' => 'memberlist.php',
    'group'  => 'memberlist.php',
];

$routes = null;
if (file_exists($cacheFile)) {
    $routes = include $cacheFile;
    if (is_array($routes) && isset($routes['__disabled']) && $routes['__disabled'] === true) {
        $routes = []; // Extension is disabled: bypass SEO rewrite
    }
}

if ($routes === null || (!is_array($routes) && !file_exists($cacheFile))) {
    // Fail-safe: Use default routes if cache file is absent
    $routes = [
        ['regex' => '#^/topic/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page/(?P<page>[0-9]+)/?$#u', 'resource' => 'topic', 'is_page' => true],
        ['regex' => '#^/topic/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page-(?P<page>[0-9]+)/?$#u', 'resource' => 'topic', 'is_page' => true],
        ['regex' => '#^/topic/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'topic', 'is_page' => false],
        ['regex' => '#^/forum/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page/(?P<page>[0-9]+)/?$#u', 'resource' => 'forum', 'is_page' => true],
        ['regex' => '#^/forum/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page-(?P<page>[0-9]+)/?$#u', 'resource' => 'forum', 'is_page' => true],
        ['regex' => '#^/forum/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'forum', 'is_page' => false],
        ['regex' => '#^/member/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'member', 'is_page' => false],
        ['regex' => '#^/group/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'group', 'is_page' => false],
    ];

    $storeDir = __DIR__ . '/store/';
    if (!is_dir($storeDir)) {
        @mkdir($storeDir, 0755, true);
    }
    @file_put_contents($cacheFile, "<?php\n// Auto-generated route cache.\ndeclare(strict_types=1);\n\nreturn " . var_export($routes, true) . ";\n");
}

$rawUri = $_SERVER['REQUEST_URI'] ?? '';
$qPos = strpos($rawUri, '?');
$path = ($qPos !== false) ? substr($rawUri, 0, $qPos) : $rawUri;
$path = rawurldecode($path);

// Determine board web path prefix (e.g. "/phpbb/ext/..." -> "/phpbb", "/ext/..." -> "")
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
$extSubPath = '/ext/phpbbseo/framework/rewrite.php';
$pos = strrpos($scriptName, $extSubPath);
$boardDir = ($pos !== false) ? substr($scriptName, 0, $pos) : '';
$boardDir = rtrim($boardDir, '/');

if ($boardDir !== '' && str_starts_with($path, $boardDir . '/')) {
    $path = substr($path, strlen($boardDir));
}

// 1. Intercept and route native operational endpoints (e.g. relative resolution from deep SEO URLs)
$nativeScriptMap = [
    'adm/index.php'     => 'adm/index.php',
    'download/file.php' => 'download/file.php',
    'file.php'          => 'download/file.php',
    'posting.php'       => 'posting.php',
    'mcp.php'           => 'mcp.php',
    'ucp.php'           => 'ucp.php',
    'report.php'        => 'report.php',
    'cron.php'          => 'cron.php',
    'viewtopic.php'     => 'viewtopic.php',
    'viewforum.php'     => 'viewforum.php',
    'memberlist.php'    => 'memberlist.php',
];

if (preg_match('#(?:^|/)(adm/index\.php|download/file\.php|file\.php|posting\.php|mcp\.php|ucp\.php|report\.php|cron\.php|viewtopic\.php|viewforum\.php|memberlist\.php)$#i', $path, $nativeMatch)) {
    $matchedKey = strtolower($nativeMatch[1]);
    if (isset($nativeScriptMap[$matchedKey])) {
        $targetScript = $nativeScriptMap[$matchedKey];
        if (is_file($phpbbRootPath . $targetScript)) {
            $internalScript = ($boardDir !== '') ? $boardDir . '/' . $targetScript : '/' . $targetScript;
            $internalQuery = http_build_query($_GET);
            $_SERVER['SCRIPT_NAME']     = $internalScript;
            $_SERVER['PHP_SELF']        = $internalScript;
            $_SERVER['SCRIPT_FILENAME'] = $phpbbRootPath . $targetScript;
            $_SERVER['REQUEST_URI']     = $internalScript . ($internalQuery !== '' ? '?' . $internalQuery : '');
            $_SERVER['PATH_INFO']       = '';

            if ($targetScript === 'download/file.php') {
                @chdir($phpbbRootPath . 'download');
            } elseif ($targetScript === 'adm/index.php') {
                @chdir($phpbbRootPath . 'adm');
            }

            require $phpbbRootPath . $targetScript;
            exit;
        }
    }
}

// 2. Intercept and redirect relative static asset requests from deep SEO URL paths
if (preg_match('#(?:^|/)(styles|assets|images|ext)/(.*)$#i', $path, $assetMatch)) {
    $assetRelPath = $assetMatch[1] . '/' . $assetMatch[2];
    $fullAssetPath = $phpbbRootPath . $assetRelPath;
    if (is_file($fullAssetPath)) {
        $targetAssetUrl = ($boardDir !== '') ? $boardDir . '/' . $assetRelPath : '/' . $assetRelPath;
        header('Location: ' . $targetAssetUrl, true, 301);
        exit;
    }
}

// 2. Inbound SEO URL Route Matching
if (is_array($routes) && !empty($routes)) {
    foreach ($routes as $route) {
        if (preg_match($route['regex'], $path, $matches)) {
            $resource = $route['resource'];
            $id = isset($matches['id']) ? (int) $matches['id'] : 0;
            $page = isset($matches['page']) ? (int) $matches['page'] : 0;

            if ($id > 0 && isset($targetScripts[$resource])) {
                $targetScript = $targetScripts[$resource];

                // Prepare native request environment
                switch ($resource) {
                    case 'topic':
                        $_GET['t'] = (string) $id;
                        $_REQUEST['t'] = (string) $id;
                        break;

                    case 'forum':
                        $_GET['f'] = (string) $id;
                        $_REQUEST['f'] = (string) $id;
                        break;

                    case 'member':
                        $_GET['mode'] = 'viewprofile';
                        $_GET['u'] = (string) $id;
                        $_REQUEST['mode'] = 'viewprofile';
                        $_REQUEST['u'] = (string) $id;
                        break;

                    case 'group':
                        $_GET['mode'] = 'group';
                        $_GET['g'] = (string) $id;
                        $_REQUEST['mode'] = 'group';
                        $_REQUEST['g'] = (string) $id;
                        break;
                }

                // Dynamically pass page number without hardcoding pagination offsets
                if ($page > 1) {
                    $_GET['seo_page'] = (string) $page;
                    $_REQUEST['seo_page'] = (string) $page;
                }

                // Preserve original public SEO URI for Framework RequestContextFactory & canonical checks
                $_SERVER['SEO_PUBLIC_REQUEST_URI'] = $_SERVER['REQUEST_URI'];

                // Build internal query string
                $internalQuery = http_build_query($_GET);

                // Normalize internal phpBB execution context
                $internalScript = ($boardDir !== '') ? $boardDir . '/' . $targetScript : '/' . $targetScript;
                $_SERVER['SCRIPT_NAME']     = $internalScript;
                $_SERVER['PHP_SELF']        = $internalScript;
                $_SERVER['SCRIPT_FILENAME'] = $phpbbRootPath . $targetScript;
                $_SERVER['REQUEST_URI']     = $internalScript . ($internalQuery !== '' ? '?' . $internalQuery : '');
                $_SERVER['PATH_INFO']       = '';

                require $phpbbRootPath . $targetScript;
                exit;
            }
        }
    }
}

// 3. Controller Routes (e.g. extension routes, /sitemap.xml, /app.php/demo, /app.php/help/faq)
$appPath = $path;
if (preg_match('#(?:^|/)app\.php(/.*)?$#i', $path, $appMatch)) {
    $appPath = $appMatch[1] ?? '';
}

$appScript = ($boardDir !== '') ? $boardDir . '/app.php' : '/app.php';
$_SERVER['SCRIPT_NAME']     = $appScript;
$_SERVER['PHP_SELF']        = $appScript . ($appPath !== '' ? $appPath : '');
$_SERVER['SCRIPT_FILENAME'] = $phpbbRootPath . 'app.php';
$_SERVER['PATH_INFO']       = $appPath !== '' ? $appPath : '';

require $phpbbRootPath . 'app.php';
exit;
