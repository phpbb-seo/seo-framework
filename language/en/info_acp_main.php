<?php
/**
 * phpBB SEO Framework - English ACP Module Info
 */
if (!defined('IN_PHPBB')) {
    exit;
}

if (empty($lang) || !is_array($lang)) {
    $lang = [];
}

$lang = array_merge($lang, [
    'ACP_PHPBBSEO_TITLE'       => 'SEO Framework',
    'ACP_PHPBBSEO_DASHBOARD'   => 'Dashboard',
    'ACP_PHPBBSEO_PERMALINKS'  => 'Permalinks',
    'ACP_PHPBBSEO_TITLES_META' => 'Titles & Meta',
    'ACP_PHPBBSEO_SITEMAP'     => 'XML Sitemap',
    'ACP_PHPBBSEO_SAFE_UNINSTALL' => 'Safe Uninstall',
]);
