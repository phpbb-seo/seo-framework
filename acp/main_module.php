<?php
declare(strict_types=1);

namespace phpbbseo\framework\acp;

use phpbbseo\framework\Rewrite\RouteCacheCompiler;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;
use phpbbseo\framework\Rewrite\PatternConflictDetector;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Version\Version;
use phpbbseo\framework\Update\UpdateChecker;
use phpbbseo\framework\Update\UpdateResult;

/**
 * ACP Controller for phpBB SEO Framework (Dashboard & Permalinks).
 */
class main_module
{
    public string $u_action = '';
    public string $tpl_name = '';
    public string $page_title = '';

    public function main(string $id, string $mode): void
    {
        global $phpbb_container, $user, $template, $request, $config;

        $user->add_lang_ext('phpbbseo/framework', ['acp_seo', 'info_acp_main']);

        /** @var ConfigurationProvider $configProvider */
        $configProvider = $phpbb_container->get('phpbbseo.framework.configuration.provider');

        // Extract clean board base URL for real-time preview computation
        $boardUrl = generate_board_url();

        // Base clean URL for sidebar navigation links across modules
        $baseAction = $this->u_action;
        $cleanAction = preg_replace('/&amp;mode=[a-z0-9_]+/i', '', $baseAction);
        $cleanAction = preg_replace('/&mode=[a-z0-9_]+/i', '', $cleanAction);
        $cleanActionNoModule = preg_replace('/&amp;i=[^&]+/i', '', $cleanAction);
        $cleanActionNoModule = preg_replace('/&i=[^&]+/i', '', $cleanActionNoModule);

        /** @var \phpbb\extension\manager $extManager */
        $extManager = $phpbb_container->get('ext.manager');
        $isProEnabled = $extManager->is_enabled('phpbbseo/pro');

        $template->assign_vars([
            'PSEO_ACTIVE_MODE'         => $mode,
            'PSEO_BASE_URL'            => $boardUrl,
            'PSEO_VERSION'             => Version::getVersion(),
            'PSEO_EDITION'             => $isProEnabled ? 'Pro' : 'Lite',
            'PSEO_ASSET_VERSION'       => Version::getVersion() . '.' . time(),
            'PSEO_FULL_VERSION'        => 'v' . Version::getVersion() . ' • ' . ($isProEnabled ? 'Pro' : 'Lite') . ' Edition',
            'U_ACTION_DASHBOARD'       => $cleanAction . '&amp;mode=dashboard',
            'U_ACTION_PERMALINKS'      => $cleanAction . '&amp;mode=permalinks',
            'U_ACTION_TITLES_META'     => $cleanAction . '&amp;mode=titles_meta',
            'U_ACTION_SITEMAP'         => $cleanAction . '&amp;mode=sitemap',
            'U_ACTION_SAFE_UNINSTALL'  => $cleanAction . '&amp;mode=safe_uninstall',
            'U_ACTION_PRO_OVERVIEW'    => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=overview',
            'U_ACTION_PRO_ANALYZER'    => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=analyzer',
            'U_ACTION_PRO_TITLES_META' => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=titles_meta',
            'U_ACTION_PRO_SOCIAL'      => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=social',
            'U_ACTION_PRO_SCHEMA'      => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=schema',
            'U_ACTION_PRO_GSC'         => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=gsc',
            'U_ACTION_PRO_MONITOR_404' => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=monitor_404',
            'U_ACTION_PRO_REDIRECTS'   => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=redirects',
            'U_ACTION_PRO_ROBOTS'      => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=robots',
            'U_ACTION_PRO_INDEXNOW'    => $cleanActionNoModule . '&amp;i=\\phpbbseo\\pro\\acp\\pro_module&amp;mode=indexnow',
            'S_SEO_ENABLED'            => $configProvider->isEnabled(),
            'S_REWRITE_ENABLED'        => $configProvider->isRewriteEnabled(),
            'S_PRO_ENABLED'            => $isProEnabled,
        ]);

        switch ($mode) {
            case 'dashboard':
                $this->handleDashboard($phpbb_container, $user, $template, $request, $configProvider);
                break;

            case 'permalinks':
                $this->handlePermalinks($phpbb_container, $user, $template, $request, $config);
                break;

            case 'titles_meta':
                $this->handleTitlesMeta($phpbb_container, $user, $template, $request, $config);
                break;

            case 'sitemap':
                $this->handleSitemap($phpbb_container, $user, $template, $request, $config);
                break;

            case 'safe_uninstall':
                $this->handleSafeUninstall($phpbb_container, $user, $template, $request, $config);
                break;

            default:
                trigger_error('NO_MODE', E_USER_ERROR);
        }
    }

    private function handleDashboard($container, $user, $template, $request, $configProvider): void
    {
        $this->tpl_name = '@phpbbseo_framework/acp_seo_dashboard';
        $this->page_title = $user->lang('ACP_PHPBBSEO_DASHBOARD');

        /** @var PermalinkConfiguration $permalinkConfig */
        $permalinkConfig = $container->get('phpbbseo.framework.rewrite.permalink_configuration');

        /** @var UpdateChecker $updateChecker */
        $updateChecker = $container->get('phpbbseo.framework.update.checker');

        $isManualCheck = (bool) $request->variable('check_updates', 0);
        $updateResult = $updateChecker->check($isManualCheck);

        $template->assign_vars([
            'SEO_PRESET'              => $permalinkConfig->getActivePreset(),
            'PATTERN_FORUM'           => $permalinkConfig->getPattern('forum'),
            'PATTERN_TOPIC'           => $permalinkConfig->getPattern('topic'),
            'PATTERN_MEMBER'          => $permalinkConfig->getPattern('member'),
            'PATTERN_GROUP'           => $permalinkConfig->getPattern('group'),
            'PSEO_UPDATE_STATUS'      => $updateResult->getStatus(),
            'PSEO_UPDATE_AVAILABLE'   => $updateResult->isUpdateAvailable(),
            'PSEO_UPDATE_AHEAD'       => $updateResult->isAhead(),
            'PSEO_UPDATE_CURRENT'     => ($updateResult->getStatus() === UpdateResult::STATUS_UP_TO_DATE),
            'PSEO_UPDATE_UNAVAILABLE' => ($updateResult->getStatus() === UpdateResult::STATUS_UNAVAILABLE),
            'PSEO_LATEST_VERSION'     => $updateResult->getLatestVersion(),
            'PSEO_RELEASE_URL'        => $updateResult->getReleaseUrl(),
            'PSEO_DOWNLOAD_URL'       => $updateResult->getDownloadUrl(),
            'PSEO_CHECKED_AT'         => $updateResult->getCheckedAt() > 0 ? $user->format_date($updateResult->getCheckedAt()) : '',
            'U_CHECK_UPDATES'         => $this->u_action . '&amp;mode=dashboard&amp;check_updates=1',
            'S_MANUAL_CHECKED'        => $isManualCheck,
        ]);
    }

    private function handlePermalinks($container, $user, $template, $request, $config): void
    {
        $this->tpl_name = '@phpbbseo_framework/acp_seo_permalinks';
        $this->page_title = $user->lang('ACP_PHPBBSEO_PERMALINKS');

        /** @var PermalinkConfiguration $permalinkConfig */
        $permalinkConfig = $container->get('phpbbseo.framework.rewrite.permalink_configuration');
        /** @var UrlPatternCompiler $patternCompiler */
        $patternCompiler = $container->get('phpbbseo.framework.rewrite.url_pattern_compiler');
        /** @var RouteCacheCompiler $routeCompiler */
        $routeCompiler = $container->get('phpbbseo.framework.rewrite.route_cache_compiler');

        $errors = [];
        add_form_key('acp_seo_permalinks');

        // Form submission
        if ($request->is_set_post('submit')) {
            if (!check_form_key('acp_seo_permalinks')) {
                $errors[] = $user->lang('FORM_INVALID');
            } else {
                $patternForum  = trim($request->variable('pattern_forum', '', true));
                $patternTopic  = trim($request->variable('pattern_topic', '', true));
                $patternMember = trim($request->variable('pattern_member', '', true));
                $patternGroup  = trim($request->variable('pattern_group', '', true));

                // Normalization: Ensure leading slash
                $patternForum  = '/' . ltrim($patternForum, '/');
                $patternTopic  = '/' . ltrim($patternTopic, '/');
                $patternMember = '/' . ltrim($patternMember, '/');
                $patternGroup  = '/' . ltrim($patternGroup, '/');
                $legacyUsu     = $request->variable('legacy_usu_enabled', 0) ? 1 : 0;

                // Derive pagination patterns consistently
                $patternForumPage = rtrim($patternForum, '/') . '/page-{page}/';
                $patternTopicPage = rtrim($patternTopic, '/') . '/page-{page}/';

                $submittedPatterns = [
                    'forum'      => $patternForum,
                    'forum_page' => $patternForumPage,
                    'topic'      => $patternTopic,
                    'topic_page' => $patternTopicPage,
                    'member'     => $patternMember,
                    'group'      => $patternGroup,
                ];

                // 1. Validate {id} placeholder requirement
                foreach (['forum', 'topic', 'member', 'group'] as $res) {
                    if (!str_contains($submittedPatterns[$res], '{id}')) {
                        $errors[] = sprintf($user->lang('SEO_ERR_MISSING_ID'), $user->lang('SEO_RES_' . strtoupper($res)));
                    }
                }

                // 2. Validate complete pattern set & conflict detection via RouteCacheCompiler
                if (empty($errors)) {
                    global $phpbb_root_path;

                    // Snapshot old config values for complete rollback safety
                    $oldConfigValues = [
                        'seo_permalink_preset'        => (string) ($config['seo_permalink_preset'] ?? 'modern'),
                        'phpbbseo_legacy_usu_enabled' => (string) ($config['phpbbseo_legacy_usu_enabled'] ?? '0'),
                        'seo_pattern_forum'           => (string) ($config['seo_pattern_forum'] ?? '/forum/{slug}-{id}/'),
                        'seo_pattern_forum_page'      => (string) ($config['seo_pattern_forum_page'] ?? '/forum/{slug}-{id}/page-{page}/'),
                        'seo_pattern_topic'           => (string) ($config['seo_pattern_topic'] ?? '/topic/{slug}-{id}/'),
                        'seo_pattern_topic_page'      => (string) ($config['seo_pattern_topic_page'] ?? '/topic/{slug}-{id}/page-{page}/'),
                        'seo_pattern_member'          => (string) ($config['seo_pattern_member'] ?? '/member/{slug}-{id}/'),
                        'seo_pattern_group'           => (string) ($config['seo_pattern_group'] ?? '/group/{slug}-{id}/'),
                        'seo_prev_pattern_forum'      => (string) ($config['seo_prev_pattern_forum'] ?? '/forum/{slug}-{id}/'),
                        'seo_prev_pattern_topic'      => (string) ($config['seo_prev_pattern_topic'] ?? '/topic/{slug}-{id}/'),
                        'seo_prev_pattern_member'     => (string) ($config['seo_prev_pattern_member'] ?? '/member/{slug}-{id}/'),
                        'seo_prev_pattern_group'      => (string) ($config['seo_prev_pattern_group'] ?? '/group/{slug}-{id}/'),
                    ];

                    $prevPatterns = [
                        'forum'  => $oldConfigValues['seo_pattern_forum'],
                        'topic'  => $oldConfigValues['seo_pattern_topic'],
                        'member' => $oldConfigValues['seo_pattern_member'],
                        'group'  => $oldConfigValues['seo_pattern_group'],
                    ];

                    // Snapshot current route cache file content
                    $storeDir = ($phpbb_root_path ?? './') . 'ext/phpbbseo/framework/store/';
                    $targetRouteFile = $storeDir . 'compiled_routes.php';
                    $oldRouteFileContent = file_exists($targetRouteFile) ? @file_get_contents($targetRouteFile) : null;

                    try {
                        // 1. Stage & compile new routes to verified artifact, atomically replace compiled_routes.php
                        $routeCompiler->compileAndDump($submittedPatterns, $prevPatterns, (bool) $legacyUsu);

                        // 2. Persist previously active patterns and new configuration to phpBB DB
                        foreach ($prevPatterns as $res => $prevVal) {
                            $config->set('seo_prev_pattern_' . $res, $prevVal);
                        }

                        $config->set('seo_permalink_preset', 'custom');
                        $config->set('phpbbseo_legacy_usu_enabled', (string) $legacyUsu);
                        $config->set('seo_pattern_forum', $patternForum);
                        $config->set('seo_pattern_forum_page', $patternForumPage);
                        $config->set('seo_pattern_topic', $patternTopic);
                        $config->set('seo_pattern_topic_page', $patternTopicPage);
                        $config->set('seo_pattern_member', $patternMember);
                        $config->set('seo_pattern_group', $patternGroup);

                        trigger_error($user->lang('SEO_PERMALINKS_SAVED') . adm_back_link($this->u_action));
                    } catch (\Throwable $e) {
                        // Complete bidirectional rollback: restore old config & restore old route cache
                        foreach ($oldConfigValues as $k => $v) {
                            $config->set($k, $v);
                        }

                        if ($oldRouteFileContent !== null) {
                            @file_put_contents($targetRouteFile, $oldRouteFileContent);
                        }

                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Current values
        $curForum  = $permalinkConfig->getPattern('forum');
        $curTopic  = $permalinkConfig->getPattern('topic');
        $curMember = $permalinkConfig->getPattern('member');
        $curGroup  = $permalinkConfig->getPattern('group');

        // Compile live previews
        $previewForum  = $this->generatePreview($patternCompiler, $curForum, 'example-forum', 12);
        $previewTopic  = $this->generatePreview($patternCompiler, $curTopic, 'example-topic', 345);
        $previewMember = $this->generatePreview($patternCompiler, $curMember, 'example-user', 27);
        $previewGroup  = $this->generatePreview($patternCompiler, $curGroup, 'example-group', 5);

        $template->assign_vars([
            'TOKEN_SLUG'      => '{slug}',
            'TOKEN_ID'        => '{id}',
            'PATTERN_FORUM'   => $curForum,
            'PATTERN_TOPIC'   => $curTopic,
            'PATTERN_MEMBER'  => $curMember,
            'PATTERN_GROUP'   => $curGroup,
            'PREVIEW_FORUM'   => $previewForum,
            'PREVIEW_TOPIC'        => $previewTopic,
            'PREVIEW_MEMBER'       => $previewMember,
            'PREVIEW_GROUP'        => $previewGroup,
            'S_LEGACY_USU_ENABLED' => (bool) ($config['phpbbseo_legacy_usu_enabled'] ?? false),
            'S_ERROR'              => !empty($errors),
            'ERROR_MSG'            => implode('<br>', $errors),
            'U_ACTION'             => $this->u_action,
        ]);
    }

    private function handleTitlesMeta($container, $user, $template, $request, $config): void
    {
        $this->tpl_name = '@phpbbseo_framework/acp_seo_titles_meta';
        $this->page_title = $user->lang('ACP_PHPBBSEO_TITLES_META');

        /** @var \phpbbseo\framework\Metadata\MetadataPatternRenderer $patternRenderer */
        $patternRenderer = $container->get('phpbbseo.framework.metadata.pattern_renderer');

        $errors = [];
        add_form_key('acp_seo_titles_meta');

        if ($request->is_set_post('submit')) {
            if (!check_form_key('acp_seo_titles_meta')) {
                $errors[] = $user->lang('FORM_INVALID');
            } else {
                $metaEnable  = $request->variable('meta_enable', 1) ? '1' : '0';
                $homeTitle   = trim($request->variable('home_title', '', true));
                $homeDesc    = trim($request->variable('home_desc', '', true));
                $forumTitle  = trim($request->variable('forum_title', '', true));
                $topicTitle  = trim($request->variable('topic_title', '', true));
                $memberTitle = trim($request->variable('member_title', '', true));
                $descMaxLen  = $request->variable('desc_max_len', 155);

                if ($descMaxLen <= 0 || $descMaxLen > 500) {
                    $descMaxLen = 155;
                }

                // Basic validation: patterns must not contain raw script tags or control characters
                foreach (['home_title' => $homeTitle, 'forum_title' => $forumTitle, 'topic_title' => $topicTitle, 'member_title' => $memberTitle] as $field => $val) {
                    if (str_contains($val, '<script') || str_contains($val, '</script>')) {
                        $errors[] = sprintf($user->lang('SEO_ERR_INVALID_CHARS'), $field);
                    }
                }

                if (empty($errors)) {
                    // Snapshot old config values for complete rollback safety
                    $oldConfig = [
                        'seo_meta_enable'       => (string) ($config['seo_meta_enable'] ?? '1'),
                        'seo_meta_home_title'   => (string) ($config['seo_meta_home_title'] ?? '{board_name}'),
                        'seo_meta_home_desc'    => (string) ($config['seo_meta_home_desc'] ?? '{site_desc}'),
                        'seo_meta_forum_title'  => (string) ($config['seo_meta_forum_title'] ?? '{forum_name} - {board_name}'),
                        'seo_meta_topic_title'  => (string) ($config['seo_meta_topic_title'] ?? '{topic_title} - {board_name}'),
                        'seo_meta_member_title' => (string) ($config['seo_meta_member_title'] ?? '{username} - {board_name}'),
                        'seo_meta_desc_max_len' => (string) ($config['seo_meta_desc_max_len'] ?? '155'),
                    ];

                    try {
                        $config->set('seo_meta_enable', $metaEnable);
                        $config->set('seo_meta_home_title', $homeTitle !== '' ? $homeTitle : '{board_name}');
                        $config->set('seo_meta_home_desc', $homeDesc);
                        $config->set('seo_meta_forum_title', $forumTitle !== '' ? $forumTitle : '{forum_name} - {board_name}');
                        $config->set('seo_meta_topic_title', $topicTitle !== '' ? $topicTitle : '{topic_title} - {board_name}');
                        $config->set('seo_meta_member_title', $memberTitle !== '' ? $memberTitle : '{username} - {board_name}');
                        $config->set('seo_meta_desc_max_len', (string) $descMaxLen);

                        trigger_error($user->lang('SEO_TITLES_META_SAVED') . adm_back_link($this->u_action));
                    } catch (\Throwable $e) {
                        // Rollback config
                        foreach ($oldConfig as $k => $v) {
                            $config->set($k, $v);
                        }
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        // Current values
        $curMetaEnable  = (string) ($config['seo_meta_enable'] ?? '1') === '1';
        $curHomeTitle   = (string) ($config['seo_meta_home_title'] ?? '{board_name}');
        $curHomeDesc    = (string) ($config['seo_meta_home_desc'] ?? '{site_desc}');
        $curForumTitle  = (string) ($config['seo_meta_forum_title'] ?? '{forum_name} - {board_name}');
        $curTopicTitle  = (string) ($config['seo_meta_topic_title'] ?? '{topic_title} - {board_name}');
        $curMemberTitle = (string) ($config['seo_meta_member_title'] ?? '{username} - {board_name}');
        $curDescMaxLen  = (int) ($config['seo_meta_desc_max_len'] ?? 155);

        $boardName = (string) ($config['sitename'] ?? 'My Board');
        $siteDesc  = (string) ($config['site_desc'] ?? 'A vibrant community forum');

        // Authoritative Server-side Previews using MetadataPatternRenderer
        $previewHomeTitle   = $patternRenderer->render($curHomeTitle, ['board_name' => $boardName, 'site_desc' => $siteDesc], 1, '');
        $previewHomeDesc    = $patternRenderer->render($curHomeDesc, ['board_name' => $boardName, 'site_desc' => $siteDesc], 1, '');
        $previewForumTitle  = $patternRenderer->render($curForumTitle, ['board_name' => $boardName, 'forum_name' => 'General Discussion', 'forum_id' => 2], 1, '');
        $previewTopicTitle  = $patternRenderer->render($curTopicTitle, ['board_name' => $boardName, 'topic_title' => 'Sample Topic Title', 'topic_id' => 4, 'forum_name' => 'General Discussion'], 1, '');
        $previewMemberTitle = $patternRenderer->render($curMemberTitle, ['board_name' => $boardName, 'username' => 'admin', 'user_id' => 2], 1, '');

        $template->assign_vars([
            'S_META_ENABLE'        => $curMetaEnable,
            'HOME_TITLE'           => $curHomeTitle,
            'HOME_DESC'            => $curHomeDesc,
            'FORUM_TITLE'          => $curForumTitle,
            'TOPIC_TITLE'          => $curTopicTitle,
            'MEMBER_TITLE'         => $curMemberTitle,
            'DESC_MAX_LEN'         => $curDescMaxLen,
            'PREVIEW_HOME_TITLE'   => $previewHomeTitle,
            'PREVIEW_HOME_DESC'    => $previewHomeDesc,
            'PREVIEW_FORUM_TITLE'  => $previewForumTitle,
            'PREVIEW_TOPIC_TITLE'  => $previewTopicTitle,
            'PREVIEW_MEMBER_TITLE' => $previewMemberTitle,
            'TOKEN_BOARD_NAME'     => '{board_name}',
            'TOKEN_SITE_DESC'      => '{site_desc}',
            'TOKEN_PAGE'           => '{page}',
            'TOKEN_FORUM_NAME'     => '{forum_name}',
            'TOKEN_FORUM_ID'       => '{forum_id}',
            'TOKEN_TOPIC_TITLE'    => '{topic_title}',
            'TOKEN_TOPIC_ID'       => '{topic_id}',
            'TOKEN_USERNAME'       => '{username}',
            'TOKEN_USER_ID'        => '{user_id}',
            'S_ERROR'              => !empty($errors),
            'ERROR_MSG'            => implode('<br>', $errors),
            'U_ACTION'             => $this->u_action,
        ]);
    }

    private function handleSitemap($container, $user, $template, $request, $config): void
    {
        $this->tpl_name = '@phpbbseo_framework/acp_seo_sitemap';
        $this->page_title = $user->lang('ACP_PHPBBSEO_SITEMAP');

        /** @var \phpbbseo\framework\Sitemap\SitemapRepository $repository */
        $repository = $container->get('phpbbseo.framework.sitemap.repository');
        /** @var \phpbbseo\framework\Sitemap\SitemapUrlGenerator $urlGenerator */
        $urlGenerator = $container->get('phpbbseo.framework.sitemap.url_generator');

        $errors = [];

        if ($request->is_set_post('submit')) {
            if (!check_form_key('pseo_sitemap_form')) {
                $errors[] = $user->lang('FORM_INVALID');
            } else {
                $sitemapEnable = $request->variable('sitemap_enable', 0) ? '1' : '0';
                $urlsPerFile   = $request->variable('urls_per_file', 50000);

                if ($urlsPerFile < 100 || $urlsPerFile > 50000) {
                    $errors[] = $user->lang('SEO_ERR_SITEMAP_CHUNK_SIZE');
                }

                if (empty($errors)) {
                    $oldConfig = [
                        'seo_sitemap_enable'        => (string) ($config['seo_sitemap_enable'] ?? '1'),
                        'seo_sitemap_urls_per_file' => (string) ($config['seo_sitemap_urls_per_file'] ?? '50000'),
                    ];

                    try {
                        $config->set('seo_sitemap_enable', $sitemapEnable);
                        $config->set('seo_sitemap_urls_per_file', (string) $urlsPerFile);

                        $repository->purgeStatsCache();

                        trigger_error($user->lang('SEO_SITEMAP_SAVED') . adm_back_link($this->u_action));
                    } catch (\Throwable $e) {
                        foreach ($oldConfig as $k => $v) {
                            $config->set($k, $v);
                        }
                        $errors[] = $e->getMessage();
                    }
                }
            }
        }

        if ($request->is_set_post('action_backfill_step')) {
            if (!check_form_key('pseo_sitemap_rebuild')) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $user->lang('FORM_INVALID')]);
                exit;
            }

            /** @var \phpbbseo\framework\Backfill\SlugBackfillManager $backfillManager */
            $backfillManager = $container->get('phpbbseo.framework.backfill.manager');
            $lastId = max(0, (int) $request->variable('last_id', 0));
            $batchSize = min(max((int) $request->variable('batch_size', 500), 1), 1000);

            try {
                $res = $backfillManager->backfillBatch('topic', $lastId, $batchSize, true);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success'   => true,
                    'processed' => $res->processed,
                    'last_id'   => $res->lastId,
                    'remaining' => $res->remaining,
                    'completed' => $res->completed,
                    'elapsed'   => $res->elapsed,
                ]);
                exit;
            } catch (\phpbbseo\framework\Backfill\Exception\BackfillLockException $e) {
                http_response_code(409);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $e->getMessage(), 'locked' => true]);
                exit;
            } catch (\Throwable $e) {
                http_response_code(500);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }

        $curSitemapEnable = (string) ($config['seo_sitemap_enable'] ?? '1') === '1';
        $curUrlsPerFile   = (int) ($config['seo_sitemap_urls_per_file'] ?? 50000);

        $stats = $repository->getSitemapStats($curUrlsPerFile);
        $sitemapUrl = $urlGenerator->getBoardUrl() . 'sitemap.xml';

        $backfillEndpoint = '';
        if ($container->has('controller.helper')) {
            try {
                /** @var \phpbb\controller\helper $controllerHelper */
                $controllerHelper = $container->get('controller.helper');
                $backfillEndpoint = $controllerHelper->route('phpbbseo_framework_acp_backfill', [], true, $user->session_id);
            } catch (\Throwable $e) {
                $backfillEndpoint = $this->u_action;
            }
        } else {
            $backfillEndpoint = $this->u_action;
        }

        $template->assign_vars([
            'S_SITEMAP_ENABLE'    => $curSitemapEnable,
            'URLS_PER_FILE'       => $curUrlsPerFile,
            'SITEMAP_URL'         => $sitemapUrl,
            'STAT_FORUMS'         => number_format($stats['public_forums']),
            'STAT_TOPICS'         => number_format($stats['public_topics']),
            'STAT_FILES'          => number_format($stats['topic_files']),
            'STAT_MISSING'        => number_format($stats['missing_slugs']),
            'NUM_MISSING_RAW'     => (int) $stats['missing_slugs'],
            'S_HAS_MISSING_SLUGS' => ((int) $stats['missing_slugs'] > 0),
            'U_BACKFILL_ENDPOINT' => $backfillEndpoint,
            'U_BACKFILL_ACTION'   => $this->u_action,
            'S_ERROR'             => !empty($errors),
            'ERROR_MSG'           => implode('<br>', $errors),
            'U_ACTION'            => $this->u_action,
        ]);

        add_form_key('pseo_sitemap_form');
        add_form_key('pseo_sitemap_rebuild', '_REBUILD');


    }

    private function handleSafeUninstall($container, $user, $template, $request, $config): void
    {
        $this->tpl_name = '@phpbbseo_framework/acp_seo_safe_uninstall';
        $this->page_title = $user->lang('ACP_PHPBBSEO_SAFE_UNINSTALL');

        /** @var \phpbbseo\framework\SafeUninstall\SafeUninstallManager $safeUninstall */
        $safeUninstall = $container->get('phpbbseo.framework.safe_uninstall.manager');

        $successMsg = '';
        $errorMsg = '';

        if ($request->is_set_post('action_prepare')) {
            if (!check_form_key('acp_seo_safe_uninstall')) {
                $errorMsg = $user->lang('FORM_INVALID');
            } else {
                try {
                    $safeUninstall->prepare();
                    $successMsg = $user->lang('SEO_SAFE_UNINSTALL_PREPARED_SUCCESS');
                } catch (\Throwable $e) {
                    $errorMsg = $e->getMessage();
                }
            }
        } elseif ($request->is_set_post('action_restore')) {
            if (!check_form_key('acp_seo_safe_uninstall')) {
                $errorMsg = $user->lang('FORM_INVALID');
            } else {
                try {
                    $safeUninstall->restore();
                    $successMsg = $user->lang('SEO_SAFE_UNINSTALL_RESTORED_SUCCESS');
                } catch (\Throwable $e) {
                    $errorMsg = $e->getMessage();
                }
            }
        }

        $analysis = $safeUninstall->analyze();

        $template->assign_vars([
            'ACTIVE_PRESET'        => $analysis['active_preset'],
            'S_IS_REVERSIBLE'      => $analysis['is_reversible'],
            'REVERSIBLE_COUNT'     => $analysis['reversible_count'],
            'TOTAL_FAMILIES'       => $analysis['total_families'],
            'BOARD_BASE_PATH'      => $analysis['board_base_path'],
            'S_HTACCESS_EXISTS'    => $analysis['htaccess_exists'],
            'S_HTACCESS_WRITABLE'  => $analysis['htaccess_writable'],
            'S_IS_PREPARED'        => $analysis['is_prepared'],
            'HTACCESS_RULES'       => htmlspecialchars($safeUninstall->generateHtaccessRules()),
            'NGINX_RULES'          => htmlspecialchars($safeUninstall->generateNginxRules()),
            'S_SUCCESS'            => !empty($successMsg),
            'SUCCESS_MSG'          => $successMsg,
            'S_ERROR'              => !empty($errorMsg),
            'ERROR_MSG'            => $errorMsg,
            'U_ACTION'             => $this->u_action,
        ]);

        add_form_key('acp_seo_safe_uninstall');
    }

    private function generatePreview(UrlPatternCompiler $compiler, string $pattern, string $slug, int $id): string
    {
        try {
            $compiled = $compiler->compile($pattern, ['id']);
            return $compiled->generate(['id' => $id, 'slug' => $slug]);
        } catch (\Throwable) {
            return $pattern;
        }
    }
}
