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

// Enable phpBB native board-root URL asset resolution for clean SEO paths
if (!defined('PHPBB_USE_BOARD_URL_PATH')) {
    define('PHPBB_USE_BOARD_URL_PATH', true);
}

$cacheFile = __DIR__ . '/store/compiled_routes.php';

// Safe hardcoded target script map (Security rule: user input NEVER reaches include path)
$targetScripts = [
    'topic'  => 'viewtopic.php',
    'forum'  => 'viewforum.php',
    'member' => 'memberlist.php',
    'group'  => 'memberlist.php',
];

if (file_exists($cacheFile)) {
    $routes = include $cacheFile;
    if (is_array($routes)) {
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

                    // 1. Preserve original public SEO URI for Framework RequestContextFactory & canonical checks
                    $_SERVER['SEO_PUBLIC_REQUEST_URI'] = $_SERVER['REQUEST_URI'];

                    // 2. Build internal query string
                    $internalQuery = http_build_query($_GET);

                    // 3. Normalize internal phpBB execution context so path_helper calculates web_root_path as "./"
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
}

// Unmatched requests (e.g. extension routes, /sitemap.xml, /app.php/help/faq) pass to app.php
$appScript = ($boardDir ?? '') !== '' ? $boardDir . '/app.php' : '/app.php';
$_SERVER['SCRIPT_NAME']     = $appScript;
$_SERVER['PHP_SELF']        = $appScript;
$_SERVER['SCRIPT_FILENAME'] = $phpbbRootPath . 'app.php';

require $phpbbRootPath . 'app.php';
exit;
