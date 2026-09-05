<?php
/**
 * phpBB SEO Framework - English ACP Strings
 */
if (!defined('IN_PHPBB')) {
    exit;
}

if (empty($lang) || !is_array($lang)) {
    $lang = [];
}

$lang = array_merge($lang, [
    // Header & Edition
    'SEO_APP_TITLE'             => 'SEO Framework',
    'SEO_EDITION_LITE'          => 'Lite Edition',
    'SEO_EDITION_PRO'           => 'Pro Edition',
    'SEO_STATUS_ACTIVE'         => 'Active',
    'SEO_STATUS_INACTIVE'       => 'Inactive',

    // Navigation Groups
    'SEO_NAV_CORE'              => 'Core Modules',
    'SEO_NAV_LITE'              => 'Lite Modules',
    'SEO_NAV_PRO'               => 'Pro Features',
    'SEO_NAV_ROADMAP'           => 'Roadmap & Pro',
    'SEO_NAV_DASHBOARD'         => 'Dashboard',
    'SEO_NAV_PERMALINKS'        => 'Permalinks',
    'SEO_NAV_TITLES'            => 'Titles & Meta',
    'SEO_NAV_SITEMAP'           => 'XML Sitemap',
    'SEO_NAV_SOCIAL'            => 'Social & OpenGraph',
    'SEO_NAV_SCHEMA'            => 'Schema & Rich Data',
    'SEO_NAV_REDIRECTS'         => 'Redirect Manager',
    'SEO_NAV_ANALYZER'          => 'SEO Analyzer',
    'SEO_NAV_ROBOTS'            => 'Robots & Indexing',
    'SEO_NAV_INDEXNOW'          => 'IndexNow Protocol',
    'SEO_PRO_DISABLED_NOTICE'   => 'phpBB SEO Pro extension is disabled. Enable it in Customise > Manage extensions to access this feature.',

    // Dashboard View
    'SEO_DASHBOARD_TITLE'            => 'SEO Framework Dashboard',
    'SEO_GENERAL_SETTINGS'           => 'General Settings',
    'SEO_FOOTER_ATTRIBUTION'         => 'Footer Attribution',
    'SEO_FOOTER_ATTRIBUTION_EXPLAIN' => 'Display a clean, minimal "Powered by phpBB SEO" link in the board footer.',
    'SEO_DASHBOARD_SAVED'            => 'Dashboard settings have been updated successfully.',
    'SEO_POWERED_BY'                 => 'Powered by %s',
    'SEO_STATUS_OVERVIEW'            => 'System Status',
    'SEO_STATUS_FRAMEWORK'      => 'SEO Framework',
    'SEO_STATUS_REWRITE'        => 'URL Rewriting',
    'SEO_STATUS_CANONICAL'      => 'Canonical Engine',
    'SEO_STATUS_SLUG_INDEX'     => 'Slug Index',
    'SEO_ACTIVE_PRESET'         => 'Active Permalink Preset',
    'SEO_CURRENT_PATTERNS'      => 'Active URL Patterns',
    'SEO_MANAGE_PERMALINKS'     => 'Configure Permalinks',
    'SEO_ENABLED'               => 'Enabled',
    'SEO_DISABLED'              => 'Disabled',
    'SEO_ACTIVE'                => 'Active',
    'SEO_INACTIVE'              => 'Inactive',
    'SEO_AVAILABLE'             => 'Available',

    // Permalinks View
    'SEO_PERMALINKS_TITLE'      => 'Permalink Settings',
    'SEO_PERMALINKS_EXPLAIN'    => 'Configure SEO-friendly URL patterns for public resources. Custom permalink structures are immediately compiled and activated without manual cache purge.',
    'SEO_HISTORICAL_WARNING'    => 'Warning: Changing URL patterns alters public URLs. The Shared Core provides automatic legacy 301 redirection from immediately previous patterns, but broad structural shifts should be executed carefully.',
    // Card Titles
    'SEO_CARD_STATUS'           => 'System Status',
    'SEO_CARD_HOME'             => 'Home & Board Index',
    'SEO_CARD_FORUMS'           => 'Forum URLs',
    'SEO_CARD_TOPICS'           => 'Topic URLs',
    'SEO_CARD_MEMBERS'          => 'Member Profile URLs',
    'SEO_CARD_GROUPS'           => 'Group URLs',

    // Titles & Meta Settings
    'ACP_PHPBBSEO_TITLES_META'    => 'Titles & Meta',
    'SEO_META_ENABLE'             => 'Enable Titles & Meta Engine',
    'SEO_META_ENABLE_EXPLAIN'     => 'Generate dynamic, optimized page titles and meta descriptions for indexable resources.',
    'SEO_PATTERN_HOME_TITLE'      => 'Home Title Pattern',
    'SEO_HOME_DESC'               => 'Home Meta Description',
    'SEO_PATTERN_FORUM_TITLE'     => 'Forum Title Pattern',
    'SEO_PATTERN_TOPIC_TITLE'     => 'Topic Title Pattern',
    'SEO_PATTERN_MEMBER_TITLE'    => 'Member Profile Title Pattern',
    'SEO_DESC_MAX_LEN'            => 'Meta Description Maximum Length (chars)',
    'SEO_TITLES_META_SAVED'       => 'Titles & Meta configuration has been updated successfully.',
    'SEO_PATTERN_FORUM'         => 'Forum Pattern',
    'SEO_PATTERN_TOPIC'         => 'Topic Pattern',
    'SEO_PATTERN_MEMBER'        => 'Member Profile Pattern',
    'SEO_PATTERN_GROUP'         => 'Group Pattern',
    'SEO_VARIABLES'             => 'Available Tokens',
    'SEO_LIVE_PREVIEW'          => 'Preview',
    'SEO_ACTION_READY'          => 'Configuration is saved and active.',
    'SEO_UNSAVED_CHANGES'       => 'Unsaved changes',
    'SEO_PERMALINKS_SAVED'      => 'Permalink settings and route cache have been successfully updated.',
    'SEO_ERR_MISSING_ID'        => 'The %s pattern must contain the required {id} placeholder.',
    'SEO_RES_FORUM'             => 'Forum',
    'SEO_RES_TOPIC'             => 'Topic',
    'SEO_RES_MEMBER'            => 'Member',
    'SEO_RES_GROUP'             => 'Group',

    // XML Sitemap
    'ACP_PHPBBSEO_SITEMAP'         => 'XML Sitemap',
    'SEO_SITEMAP_STATUS_TITLE'     => 'Sitemap Status & Index',
    'SEO_SITEMAP_INDEX_URL'        => 'Sitemap Index URL',
    'SEO_OPEN_SITEMAP'             => 'Open Sitemap',
    'SEO_ROBOTS_RECOMMENDATION'    => 'Recommended robots.txt directive',
    'SEO_SITEMAP_STATISTICS'       => 'Live Sitemap Statistics',
    'SEO_STAT_FORUMS'              => 'Public Forums',
    'SEO_STAT_TOPICS'              => 'Public Topics',
    'SEO_STAT_TOPIC_FILES'         => 'Topic Sitemap Files',
    'SEO_STAT_MISSING_SLUGS'       => 'Missing Slugs',
    'SEO_SITEMAP_CONFIG'           => 'Sitemap Configuration',
    'SEO_SITEMAP_ENABLE'           => 'Enable XML Sitemap',
    'SEO_SITEMAP_ENABLE_EXPLAIN'   => 'Generate and serve dynamic, standards-compliant XML sitemaps for search engines.',
    'SEO_URLS_PER_FILE'            => 'URLs per Sitemap File',
    'SEO_URLS_PER_FILE_EXPLAIN'    => 'Specify maximum number of URLs per topic sitemap file (allowed range: 100 - 50,000).',
    'SEO_SITEMAP_SAVED'            => 'XML Sitemap configuration has been updated successfully.',
    'SEO_ERR_SITEMAP_CHUNK_SIZE'   => 'URLs per sitemap file must be an integer between 100 and 50,000.',

    // Persistent Slug Backfill
    'SEO_MISSING_NOTICE_TITLE'     => 'topics do not yet have persistent SEO slugs.',
    'SEO_MISSING_NOTICE_DESC'      => 'Active topics will continue to be indexed automatically as they are visited. To index all existing topics immediately for search engines and sitemaps:',
    'SEO_REBUILD_MISSING_SLUGS'    => 'Rebuild Missing Slugs',
    'SEO_REBUILDING_SLUGS'         => 'Rebuilding SEO slugs...',
    'SEO_TOPICS_PROCESSED'         => 'topics processed',
    'SEO_REBUILD_COMPLETE'         => 'Slug rebuild completed successfully! All topics are now indexed.',
    'SEO_REBUILD_PAUSED'           => 'Slug rebuild paused. The previous batches were completed successfully. You can resume the rebuild.',
    'SEO_RESUME_REBUILD'           => 'Resume Rebuild',
    'SEO_REBUILD_LOCKED'           => 'A slug rebuild process is already running in another session or CLI.',


    // Version & Updates
    'SEO_VERSION_TITLE'            => 'Version & Updates',
    'SEO_VERSION_LATEST_NOTICE'    => 'You are running the latest version of phpBB SEO Framework.',
    'SEO_VERSION_UPDATE_AVAILABLE' => 'phpBB SEO Framework %s is available',
    'SEO_VERSION_UPDATE_EXPLAIN'   => 'You are currently running version %1$s. A newer version of phpBB SEO Framework Lite (%2$s) is available for download.',
    'SEO_VERSION_AHEAD_NOTICE'     => 'You are running a development version (%1$s) ahead of the latest official release (%2$s).',
    'SEO_VERSION_UNAVAILABLE'      => 'Update status is currently unavailable.',
    'SEO_CHECK_FOR_UPDATES'        => 'Check for updates',
    'SEO_VIEW_RELEASE'             => 'View Release',
    'SEO_DOWNLOAD_UPDATE'          => 'Download Update',
    'SEO_UPDATES_CHECKED_SUCCESS'  => 'Update check completed successfully.',

    // Safe Uninstall
    'SEO_NAV_SAFE_UNINSTALL'               => 'Safe Uninstall',
    'ACP_PHPBBSEO_SAFE_UNINSTALL'          => 'Safe Uninstall',
    'SEO_SAFE_UNINSTALL_TITLE'             => 'Safe Uninstall Protection',
    'SEO_SAFE_UNINSTALL_STATUS_PREPARED'   => 'Protected & Ready for Uninstall',
    'SEO_SAFE_UNINSTALL_STATUS_NORMAL'     => 'Normal Lite Routing Active',
    'SEO_SAFE_UNINSTALL_DESC'              => 'Safe Uninstall prepares standalone web server rewrite rules that permanently 301-redirect all existing SEO URLs to native phpBB scripts (viewtopic.php, viewforum.php). This ensures search engines and inbound links never break even if the extension is disabled or physically deleted from your server.',
    'SEO_SAFE_UNINSTALL_INSPECTION_TITLE'  => 'Pre-Flight System Inspection',
    'SEO_SAFE_UNINSTALL_PRESET_CHECK'      => 'Active Permalink Preset',
    'SEO_SAFE_UNINSTALL_REVERSIBLE_CHECK'  => 'URL Reversibility',
    'SEO_CHECK_PASS'                       => 'PASS',
    'SEO_CHECK_WARN'                       => 'WARNING',
    'SEO_FAMILIES_REVERSIBLE'              => 'families reversible',
    'SEO_SAFE_UNINSTALL_HTACCESS_CHECK'    => 'Root .htaccess File',
    'SEO_FOUND'                            => 'Found',
    'SEO_NOT_FOUND'                        => 'Not Found',
    'SEO_WRITABLE'                         => 'Writable',
    'SEO_READONLY'                         => 'Read-Only',
    'SEO_SAFE_UNINSTALL_BOARD_PATH'        => 'Board Rewrite Base',
    'SEO_SAFE_UNINSTALL_READY_HEAD'        => 'Safe Uninstall is Prepared',
    'SEO_SAFE_UNINSTALL_READY_BODY'        => 'Standalone HTTP 301 fallback rules are active in your .htaccess file. You may now safely disable and remove the extension in Manage Extensions.',
    'SEO_BTN_PREPARE_SAFE_UNINSTALL'       => 'Prepare Safe Uninstall',
    'SEO_BTN_RESTORE_LITE'                 => 'Revert to Normal Lite Routing',
    'SEO_SAFE_UNINSTALL_PREPARED_SUCCESS'  => 'Safe Uninstall rules have been successfully injected into .htaccess. All SEO URLs are now protected with standalone 301 redirects.',
    'SEO_SAFE_UNINSTALL_RESTORED_SUCCESS'  => 'Normal Lite routing has been restored. Fallback rules have been removed from .htaccess.',
    'SEO_SAFE_UNINSTALL_RULES_HEADER'      => 'Standalone Fallback Rules Preview',
    'SEO_SAFE_UNINSTALL_RULES_EXPLAIN'     => 'These rewrite rules are independent of the extension and will continue redirecting your indexed SEO URLs to native phpBB even after the extension files are deleted.',
    'SEO_HTACCESS_NOT_WRITABLE_TOOLTIP'    => 'The root .htaccess file is not writable by PHP. Please copy and paste the rules below manually.',

    // Legacy Migration (USU Compatibility)
    'SEO_LEGACY_MIGRATION_TITLE'       => 'Legacy Migration Compatibility (USU / phpBB SEO)',
    'SEO_LEGACY_USU_ENABLE'            => 'Enable Ultimate SEO URLs (USU) 301 Redirects',
    'SEO_LEGACY_USU_ENABLE_EXPLAIN'    => 'Automatically detects legacy Ultimate SEO URL (USU) links (*-t{id}.html, *-f{id}.html, member{id}.html, post{id}.html) and issues single-hop HTTP 301 permanent redirects to active Lite canonical URLs. Requires zero database mapping and zero legacy USU files.',
    'SEO_LEGACY_USU_WARNING'           => 'Only enable this if this site previously used Ultimate SEO URLs (USU). If this site never used USU, leave this OFF — enabling it unnecessarily will cause the board to 301-redirect unrelated URLs that happen to match legacy patterns (e.g. anything ending in -f{number} or -t{number}), which is not appropriate for a site without USU history.',
]);

