<?php
/**
 * phpBB SEO Framework — Lightweight Pre-Bootstrap Inbound Front Controller.
 *
 * Runs BEFORE phpBB common.php boots.
 * Matches inbound SEO URLs using ultra-fast compiled route cache.
 * Preserves public SEO REQUEST_URI for canonical checks while normalizing internal
 * execution server context so phpBB calculates root asset paths as "./".
 */

$phpbb_root_path = (defined('PHPBB_ROOT_PATH')) ? PHPBB_ROOT_PATH : './';
$phpEx = 'php';

$storeDir = $phpbb_root_path . 'ext/phpbbseo/framework/store/';
$cacheFile = $storeDir . 'compiled_routes.php';

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

        // Normalize board path prefix (e.g. "/phpbb/topic/..." -> "/topic/...")
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $boardDir = rtrim(dirname($scriptName), '/\\');
        if ($boardDir === '\\') {
            $boardDir = '';
        }
        if ($boardDir !== '' && $boardDir !== '/' && str_starts_with($path, $boardDir)) {
            $path = '/' . ltrim(substr($path, strlen($boardDir)), '/');
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
                    $internalScript = ($boardDir !== '' && $boardDir !== '/') ? $boardDir . '/' . $targetScript : '/' . $targetScript;
                    $_SERVER['SCRIPT_NAME']     = $internalScript;
                    $_SERVER['PHP_SELF']        = $internalScript;
                    $_SERVER['SCRIPT_FILENAME'] = $phpbb_root_path . $targetScript;
                    $_SERVER['REQUEST_URI']     = $internalScript . ($internalQuery !== '' ? '?' . $internalQuery : '');
                    $_SERVER['PATH_INFO']       = '';

                    require $phpbb_root_path . $targetScript;
                    exit;
                }
            }
        }
    }
}

// Unmatched requests (e.g. extension routes, app.php/help/faq) pass to app.php ONCE
require $phpbb_root_path . 'app.php';
exit;
