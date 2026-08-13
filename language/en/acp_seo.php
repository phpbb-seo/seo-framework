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

    // Dashboard View
    'SEO_DASHBOARD_TITLE'       => 'SEO Framework Dashboard',
    'SEO_STATUS_OVERVIEW'       => 'System Status',
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
]);
