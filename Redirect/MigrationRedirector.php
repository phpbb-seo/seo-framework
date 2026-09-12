<?php
declare(strict_types=1);

namespace phpbbseo\framework\Redirect;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Rewrite\PublicResourceUrlResolver;
use phpbbseo\framework\Rewrite\SlugRepository;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Migration 301 Permanent Redirector
 *
 * Automatically intercepts incoming legacy URLs from XenForo, vBulletin,
 * SMF, and MyBB, queries the phpbb_migration_id_map translation table,
 * and performs sub-millisecond HTTP 301 permanent redirects to phpBB SEO canonical URLs.
 */
class MigrationRedirector implements EventSubscriberInterface
{
    private ?bool $hasMigrationTable = null;
    private bool $isTesting = false;
    private ?string $lastRedirectUrl = null;
    private ?int $lastStatusCode = null;

    public function __construct(
        private readonly driver_interface $db,
        private readonly request_interface $request,
        private readonly config $config,
        private readonly ConfigurationProvider $configProvider,
        private readonly PublicResourceUrlResolver $urlResolver,
        private readonly SlugRepository $slugRepository,
        private readonly EntitySeoContext $entityContext,
        private readonly string $tablePrefix,
        private readonly string $rootPath,
        private readonly string $phpExt
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'core.common' => ['onCommon', 25],
        ];
    }

    /**
     * Intercept request early in phpBB lifecycle before heavy rendering
     */
    public function onCommon($event): void
    {
        if (defined('ADMIN_START') || defined('IN_ADMIN')) {
            return;
        }

        $scriptName = (string) $this->request->server('SCRIPT_NAME', '');
        if (str_contains($scriptName, '/adm/')) {
            return;
        }

        $legacyRequest = $this->detectLegacyRequest();
        if ($legacyRequest === null) {
            return;
        }

        $targetUrl = $this->resolveRedirectUrl($legacyRequest);
        if ($targetUrl !== null && $targetUrl !== '') {
            $this->redirect($targetUrl);
        }
    }

    /**
     * Detect whether the incoming HTTP request is a legacy URL
     *
     * @return array{type: string, id: int, page?: int, offset?: int, system?: string}|null
     */
    public function detectLegacyRequest(): ?array
    {
        $rawUri = (string) $this->request->server('REQUEST_URI', '');
        $rawQuery = (string) $this->request->server('QUERY_STRING', '');
        $scriptName = strtolower(basename((string) $this->request->server('SCRIPT_NAME', '')));

        // ---------------------------------------------------------------------
        // 1. Explicit Webserver Query Parameters (from .htaccess / Nginx rewrite)
        // ---------------------------------------------------------------------

        // XenForo query params
        if ($this->request->is_set('threads')) {
            $val = (string) $this->request->variable('threads', '');
            $parsed = $this->parseIdAndPage($val);
            if ($parsed !== null) {
                $page = max(1, (int) $this->request->variable('page', 1));
                if ($page > 1 && $parsed['page'] === 1) {
                    $parsed['page'] = $page;
                }
                return ['type' => 'topic', 'id' => $parsed['id'], 'page' => $parsed['page'], 'system' => 'xenforo'];
            }
        }
        if ($this->request->is_set('posts')) {
            $val = (int) $this->request->variable('posts', 0);
            if ($val > 0) {
                return ['type' => 'post', 'id' => $val, 'system' => 'xenforo'];
            }
        }
        if ($this->request->is_set('forums')) {
            $val = (string) $this->request->variable('forums', '');
            $parsed = $this->parseIdAndPage($val);
            if ($parsed !== null) {
                $page = max(1, (int) $this->request->variable('page', 1));
                if ($page > 1 && $parsed['page'] === 1) {
                    $parsed['page'] = $page;
                }
                return ['type' => 'forum', 'id' => $parsed['id'], 'page' => $parsed['page'], 'system' => 'xenforo'];
            }
        }
        if ($this->request->is_set('members')) {
            $val = (string) $this->request->variable('members', '');
            $parsed = $this->parseIdAndPage($val);
            if ($parsed !== null) {
                return ['type' => 'user', 'id' => $parsed['id'], 'system' => 'xenforo'];
            }
        }

        // vBulletin webserver query params
        if ($this->request->is_set('vb_thread_id')) {
            $id = (int) $this->request->variable('vb_thread_id', 0);
            if ($id > 0) {
                $page = (int) $this->request->variable('page', 1);
                return ['type' => 'topic', 'id' => $id, 'page' => max(1, $page), 'system' => 'vbulletin'];
            }
        }
        if ($this->request->is_set('vb_post_id')) {
            $id = (int) $this->request->variable('vb_post_id', 0);
            if ($id > 0) {
                return ['type' => 'post', 'id' => $id, 'system' => 'vbulletin'];
            }
        }
        if ($this->request->is_set('vb_forum_id')) {
            $id = (int) $this->request->variable('vb_forum_id', 0);
            if ($id > 0) {
                $page = (int) $this->request->variable('page', 1);
                return ['type' => 'forum', 'id' => $id, 'page' => max(1, $page), 'system' => 'vbulletin'];
            }
        }
        if ($this->request->is_set('vb_user_id')) {
            $id = (int) $this->request->variable('vb_user_id', 0);
            if ($id > 0) {
                return ['type' => 'user', 'id' => $id, 'system' => 'vbulletin'];
            }
        }

        // MyBB webserver query params
        if ($this->request->is_set('mybb_tid')) {
            $id = (int) $this->request->variable('mybb_tid', 0);
            if ($id > 0) {
                $page = (int) $this->request->variable('page', 1);
                return ['type' => 'topic', 'id' => $id, 'page' => max(1, $page), 'system' => 'mybb'];
            }
        }
        if ($this->request->is_set('mybb_pid')) {
            $id = (int) $this->request->variable('mybb_pid', 0);
            if ($id > 0) {
                return ['type' => 'post', 'id' => $id, 'system' => 'mybb'];
            }
        }
        if ($this->request->is_set('mybb_fid')) {
            $id = (int) $this->request->variable('mybb_fid', 0);
            if ($id > 0) {
                $page = (int) $this->request->variable('page', 1);
                return ['type' => 'forum', 'id' => $id, 'page' => max(1, $page), 'system' => 'mybb'];
            }
        }
        if ($this->request->is_set('mybb_uid')) {
            $id = (int) $this->request->variable('mybb_uid', 0);
            if ($id > 0) {
                return ['type' => 'user', 'id' => $id, 'system' => 'mybb'];
            }
        }

        // SMF webserver query params
        if ($this->request->is_set('smf_topic')) {
            $val = (string) $this->request->variable('smf_topic', '');
            if (preg_match('/^(\d+)\.msg(\d+)$/i', $val, $m)) {
                return ['type' => 'post', 'id' => (int) $m[2], 'system' => 'smf'];
            }
            if (preg_match('/^(\d+)(?:\.(\d+))?$/', $val, $m)) {
                return ['type' => 'topic', 'id' => (int) $m[1], 'offset' => (int) ($m[2] ?? 0), 'system' => 'smf'];
            }
        }
        if ($this->request->is_set('smf_board')) {
            $val = (string) $this->request->variable('smf_board', '');
            if (preg_match('/^(\d+)(?:\.(\d+))?$/', $val, $m)) {
                return ['type' => 'forum', 'id' => (int) $m[1], 'offset' => (int) ($m[2] ?? 0), 'system' => 'smf'];
            }
        }
        if ($this->request->is_set('smf_user')) {
            $id = (int) $this->request->variable('smf_user', 0);
            if ($id > 0) {
                return ['type' => 'user', 'id' => $id, 'system' => 'smf'];
            }
        }

        // ---------------------------------------------------------------------
        // 2. Direct Legacy Script Files (e.g. showthread.php, forumdisplay.php)
        // ---------------------------------------------------------------------

        if ($scriptName === 'showthread.php' || str_contains($rawUri, 'showthread.php')) {
            $page = max(1, (int) $this->request->variable('page', 1));
            // Post ID in showthread
            $pid = (int) ($this->request->variable('p', 0) ?: $this->request->variable('postid', 0) ?: $this->request->variable('pid', 0));
            if ($pid > 0) {
                return ['type' => 'post', 'id' => $pid, 'system' => 'vbulletin'];
            }
            // Topic ID in showthread
            $tid = (int) ($this->request->variable('t', 0) ?: $this->request->variable('threadid', 0) ?: $this->request->variable('tid', 0));
            if ($tid > 0) {
                return ['type' => 'topic', 'id' => $tid, 'page' => $page, 'system' => 'vbulletin'];
            }
            // vB format: showthread.php?12345-Thread-Title
            if (preg_match('/^(\d+)(?:-[^&]*)?/i', $rawQuery, $m)) {
                return ['type' => 'topic', 'id' => (int) $m[1], 'page' => $page, 'system' => 'vbulletin'];
            }
        }

        if ($scriptName === 'showpost.php' || str_contains($rawUri, 'showpost.php')) {
            $pid = (int) ($this->request->variable('p', 0) ?: $this->request->variable('postid', 0) ?: $this->request->variable('pid', 0));
            if ($pid > 0) {
                return ['type' => 'post', 'id' => $pid, 'system' => 'vbulletin'];
            }
            if (preg_match('/^(\d+)/i', $rawQuery, $m)) {
                return ['type' => 'post', 'id' => (int) $m[1], 'system' => 'vbulletin'];
            }
        }

        if ($scriptName === 'forumdisplay.php' || str_contains($rawUri, 'forumdisplay.php')) {
            $page = max(1, (int) $this->request->variable('page', 1));
            $fid = (int) ($this->request->variable('f', 0) ?: $this->request->variable('forumid', 0) ?: $this->request->variable('fid', 0));
            if ($fid > 0) {
                return ['type' => 'forum', 'id' => $fid, 'page' => $page, 'system' => 'vbulletin'];
            }
            // vB format: forumdisplay.php?12-Forum-Title
            if (preg_match('/^(\d+)(?:-[^&]*)?/i', $rawQuery, $m)) {
                return ['type' => 'forum', 'id' => (int) $m[1], 'page' => $page, 'system' => 'vbulletin'];
            }
        }

        if ($scriptName === 'member.php' || str_contains($rawUri, 'member.php')) {
            $uid = (int) ($this->request->variable('u', 0) ?: $this->request->variable('userid', 0) ?: $this->request->variable('uid', 0));
            if ($uid > 0) {
                return ['type' => 'user', 'id' => $uid, 'system' => 'vbulletin'];
            }
            if (preg_match('/^(\d+)/i', $rawQuery, $m)) {
                return ['type' => 'user', 'id' => (int) $m[1], 'system' => 'vbulletin'];
            }
        }

        // ---------------------------------------------------------------------
        // 3. Raw Path / REQUEST_URI inspection (XenForo & MyBB SEF paths)
        // ---------------------------------------------------------------------

        // MyBB SEF friendly URLs
        if (preg_match('#thread-(\d+)(?:-page-(\d+))?\.html#i', $rawUri, $m)) {
            return ['type' => 'topic', 'id' => (int) $m[1], 'page' => (int) ($m[2] ?? 1), 'system' => 'mybb'];
        }
        if (preg_match('#post-(\d+)\.html#i', $rawUri, $m)) {
            return ['type' => 'post', 'id' => (int) $m[1], 'system' => 'mybb'];
        }
        if (preg_match('#forum-(\d+)(?:-page-(\d+))?\.html#i', $rawUri, $m)) {
            return ['type' => 'forum', 'id' => (int) $m[1], 'page' => (int) ($m[2] ?? 1), 'system' => 'mybb'];
        }
        if (preg_match('#user-(\d+)\.html#i', $rawUri, $m)) {
            return ['type' => 'user', 'id' => (int) $m[1], 'system' => 'mybb'];
        }

        // XenForo friendly URL paths: /threads/slug.123/ or /threads/123/
        if (preg_match('#/(?:index\.php\?)?threads/(?:[a-zA-Z0-9_\-\.%]+\.)?(\d+)(?:/(?:page-(\d+))?)?#i', $rawUri, $m)) {
            return ['type' => 'topic', 'id' => (int) $m[1], 'page' => (int) ($m[2] ?? 1), 'system' => 'xenforo'];
        }
        if (preg_match('#/(?:index\.php\?)?posts/(\d+)#i', $rawUri, $m)) {
            return ['type' => 'post', 'id' => (int) $m[1], 'system' => 'xenforo'];
        }
        if (preg_match('#/(?:index\.php\?)?forums/(?:[a-zA-Z0-9_\-\.%]+\.)?(\d+)(?:/(?:page-(\d+))?)?#i', $rawUri, $m)) {
            return ['type' => 'forum', 'id' => (int) $m[1], 'page' => (int) ($m[2] ?? 1), 'system' => 'xenforo'];
        }
        if (preg_match('#/(?:index\.php\?)?members/(?:[a-zA-Z0-9_\-\.%]+\.)?(\d+)#i', $rawUri, $m)) {
            return ['type' => 'user', 'id' => (int) $m[1], 'system' => 'xenforo'];
        }

        // vBulletin 4.x / vBSEO friendly URL paths
        if (preg_match('#/threads/(\d+)(?:-[^/]+)?#i', $rawUri, $m)) {
            return ['type' => 'topic', 'id' => (int) $m[1], 'page' => 1, 'system' => 'vbulletin'];
        }
        if (preg_match('#/forum/(\d+)(?:-[^/]+)?#i', $rawUri, $m)) {
            return ['type' => 'forum', 'id' => (int) $m[1], 'page' => 1, 'system' => 'vbulletin'];
        }

        // ---------------------------------------------------------------------
        // 4. SMF Query String in index.php (e.g. ?topic=123.0 or ;u=78)
        // ---------------------------------------------------------------------

        if ($scriptName === 'index.php' || $scriptName === '') {
            // SMF Post jump
            if (preg_match('/(?:^|[;&?])topic=\d+\.msg(\d+)/i', $rawQuery, $m)) {
                return ['type' => 'post', 'id' => (int) $m[1], 'system' => 'smf'];
            }
            // SMF Topic
            if (preg_match('/(?:^|[;&?])topic=(\d+)(?:\.(\d+))?/i', $rawQuery, $m)) {
                return ['type' => 'topic', 'id' => (int) $m[1], 'offset' => (int) ($m[2] ?? 0), 'system' => 'smf'];
            }
            // SMF Board
            if (preg_match('/(?:^|[;&?])board=(\d+)(?:\.(\d+))?/i', $rawQuery, $m)) {
                return ['type' => 'forum', 'id' => (int) $m[1], 'offset' => (int) ($m[2] ?? 0), 'system' => 'smf'];
            }
            // SMF User profile: index.php?action=profile;u=12
            if (preg_match('/action=profile.*?[;?&](?:u|user)=(\d+)/i', $rawQuery, $m)) {
                return ['type' => 'user', 'id' => (int) $m[1], 'system' => 'smf'];
            }
        }

        return null;
    }

    /**
     * Resolves the target redirect URL for the detected legacy request
     */
    public function resolveRedirectUrl(array $legacy): ?string
    {
        $contentType = $legacy['type'] ?? '';
        $sourceId = (int) ($legacy['id'] ?? 0);
        $sourceSystem = $legacy['system'] ?? null;

        if ($contentType === '' || $sourceId <= 0) {
            return null;
        }

        $targetId = $this->resolveTargetId($contentType, $sourceId, $sourceSystem);
        if ($targetId === null || $targetId <= 0) {
            return null;
        }

        $page = max(1, (int) ($legacy['page'] ?? 1));
        $offset = isset($legacy['offset']) ? (int) $legacy['offset'] : null;

        return match ($contentType) {
            'topic' => $this->buildTopicUrl($targetId, $page, $offset),
            'post'  => $this->buildPostUrl($targetId),
            'forum' => $this->buildForumUrl($targetId, $page, $offset),
            'user'  => $this->buildUserUrl($targetId),
            default => null,
        };
    }

    /**
     * Query phpbb_migration_id_map with fallback to native phpBB tables
     */
    public function resolveTargetId(string $contentType, int $sourceId, ?string $sourceSystem = null): ?int
    {
        if ($sourceId <= 0) {
            return null;
        }

        if ($this->hasMigrationTable === null) {
            try {
                $sql = 'SELECT 1 FROM ' . $this->tablePrefix . 'migration_id_map';
                $res = $this->db->sql_query_limit($sql, 1);
                $this->db->sql_freeresult($res);
                $this->hasMigrationTable = true;
            } catch (\Throwable) {
                $this->hasMigrationTable = false;
            }
        }

        if ($this->hasMigrationTable) {
            // 1. Try matching with source_system prefix if specified
            if ($sourceSystem !== null && $sourceSystem !== '') {
                $sql = 'SELECT target_id FROM ' . $this->tablePrefix . "migration_id_map
                        WHERE content_type = '" . $this->db->sql_escape($contentType) . "'
                          AND source_id = " . (int) $sourceId . "
                          AND source_system LIKE '" . $this->db->sql_escape($sourceSystem) . "%'
                        ORDER BY id DESC";
                $result = $this->db->sql_query_limit($sql, 1);
                $row = $this->db->sql_fetchrow($result);
                $this->db->sql_freeresult($result);
                if ($row && isset($row['target_id']) && (int) $row['target_id'] > 0) {
                    return (int) $row['target_id'];
                }
            }

            // 2. Generic match across all migrated runs
            $sql = 'SELECT target_id FROM ' . $this->tablePrefix . "migration_id_map
                    WHERE content_type = '" . $this->db->sql_escape($contentType) . "'
                      AND source_id = " . (int) $sourceId . "
                    ORDER BY id DESC";
            $result = $this->db->sql_query_limit($sql, 1);
            $row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);
            if ($row && isset($row['target_id']) && (int) $row['target_id'] > 0) {
                return (int) $row['target_id'];
            }
        }

        // 3. Fallback: Check if target ID exists identically in phpBB (Preserve IDs migration mode)
        return $this->checkEntityExistsNative($contentType, $sourceId);
    }

    private function checkEntityExistsNative(string $contentType, int $id): ?int
    {
        try {
            $sql = match ($contentType) {
                'topic' => 'SELECT topic_id AS eid FROM ' . $this->tablePrefix . 'topics WHERE topic_id = ' . (int) $id,
                'post'  => 'SELECT post_id AS eid FROM ' . $this->tablePrefix . 'posts WHERE post_id = ' . (int) $id,
                'forum' => 'SELECT forum_id AS eid FROM ' . $this->tablePrefix . 'forums WHERE forum_id = ' . (int) $id,
                'user'  => 'SELECT user_id AS eid FROM ' . $this->tablePrefix . 'users WHERE user_id = ' . (int) $id,
                default => null,
            };

            if ($sql === null) {
                return null;
            }

            $result = $this->db->sql_query_limit($sql, 1);
            $row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);

            return ($row && isset($row['eid'])) ? (int) $row['eid'] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function buildTopicUrl(int $targetId, int $page, ?int $offset): string
    {
        $postsPerPage = (int) ($this->config['posts_per_page'] ?? 20);
        if ($postsPerPage <= 0) {
            $postsPerPage = 20;
        }

        if ($offset !== null && $offset > 0) {
            // SMF post offset translation (SMF default is 15 posts per page)
            $smfPage = (int) floor($offset / 15);
            $start = $smfPage * $postsPerPage;
        } elseif ($page > 1) {
            $start = ($page - 1) * $postsPerPage;
        } else {
            $start = 0;
        }

        if ($this->configProvider->isRewriteEnabled()) {
            $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', [$targetId]);
            if (!empty($topicSlugs)) {
                $this->entityContext->setTopics($topicSlugs);
            }
            $seoUrl = $this->urlResolver->resolve('viewtopic.php', [
                't'     => $targetId,
                'start' => $start,
            ]);
            if ($seoUrl !== null) {
                return $this->absoluteUrl($seoUrl);
            }
        }

        $params = ['t' => $targetId];
        if ($start > 0) {
            $params['start'] = $start;
        }

        $nativeUrl = append_sid($this->rootPath . 'viewtopic.' . $this->phpExt, $params);
        return $this->absoluteUrl($nativeUrl);
    }

    private function buildPostUrl(int $targetId): string
    {
        if ($this->configProvider->isRewriteEnabled()) {
            $batchData = $this->slugRepository->fetchPostTopicDataBatch([$targetId]);
            if (!empty($batchData['mappings'])) {
                $this->entityContext->setPostToTopic($batchData['mappings']);
                $this->entityContext->setPostPositions($batchData['positions']);

                $topicId = $batchData['mappings'][$targetId];
                $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
                $this->entityContext->setTopics($topicSlugs);

                $seoUrl = $this->urlResolver->resolve('viewtopic.php', ['p' => $targetId]);
                if ($seoUrl !== null) {
                    return $this->absoluteUrl($seoUrl);
                }
            }
        }

        $nativeUrl = append_sid($this->rootPath . 'viewtopic.' . $this->phpExt, ['p' => $targetId]) . '#p' . $targetId;
        return $this->absoluteUrl($nativeUrl);
    }

    private function buildForumUrl(int $targetId, int $page, ?int $offset): string
    {
        $topicsPerPage = (int) ($this->config['topics_per_page'] ?? 50);
        if ($topicsPerPage <= 0) {
            $topicsPerPage = 50;
        }

        if ($offset !== null && $offset > 0) {
            // SMF board topic offset translation (SMF default is 20 topics per page)
            $smfPage = (int) floor($offset / 20);
            $start = $smfPage * $topicsPerPage;
        } elseif ($page > 1) {
            $start = ($page - 1) * $topicsPerPage;
        } else {
            $start = 0;
        }

        if ($this->configProvider->isRewriteEnabled()) {
            $forumSlugs = $this->slugRepository->fetchSlugsBatch('forum', [$targetId]);
            if (!empty($forumSlugs)) {
                $this->entityContext->setForums($forumSlugs);
            }
            $seoUrl = $this->urlResolver->resolve('viewforum.php', [
                'f'     => $targetId,
                'start' => $start,
            ]);
            if ($seoUrl !== null) {
                return $this->absoluteUrl($seoUrl);
            }
        }

        $params = ['f' => $targetId];
        if ($start > 0) {
            $params['start'] = $start;
        }

        $nativeUrl = append_sid($this->rootPath . 'viewforum.' . $this->phpExt, $params);
        return $this->absoluteUrl($nativeUrl);
    }

    private function buildUserUrl(int $targetId): string
    {
        if ($this->configProvider->isRewriteEnabled()) {
            $userSlugs = $this->slugRepository->fetchSlugsBatch('member', [$targetId]);
            if (!empty($userSlugs)) {
                $this->entityContext->setMembers($userSlugs);
            }
            $seoUrl = $this->urlResolver->resolve('memberlist.php', [
                'mode' => 'viewprofile',
                'u'    => $targetId,
            ]);
            if ($seoUrl !== null) {
                return $this->absoluteUrl($seoUrl);
            }
        }

        $nativeUrl = append_sid($this->rootPath . 'memberlist.' . $this->phpExt, [
            'mode' => 'viewprofile',
            'u'    => $targetId,
        ]);
        return $this->absoluteUrl($nativeUrl);
    }

    /**
     * Helper to extract numeric ID and optional page number from slug or path
     * e.g. "my-first-thread.123/page-2" -> id: 123, page: 2
     *
     * @return array{id: int, page: int}|null
     */
    private function parseIdAndPage(string $str): ?array
    {
        $str = trim($str, '/');
        if (preg_match('/(?:^|[.\/])(\d+)(?:\/page-(\d+))?$/i', $str, $m)) {
            return [
                'id'   => (int) $m[1],
                'page' => isset($m[2]) ? (int) $m[2] : 1,
            ];
        }
        if (preg_match('/(\d+)/', $str, $m)) {
            return [
                'id'   => (int) $m[1],
                'page' => 1,
            ];
        }
        return null;
    }

    /**
     * Ensure URL is absolute with board scheme and domain
     */
    private function absoluteUrl(string $url): string
    {
        if (preg_match('#^https?://#i', $url)) {
            return $url;
        }

        $boardUrl = '';
        if (function_exists('generate_board_url')) {
            $boardUrl = generate_board_url();
        }

        if (empty($boardUrl)) {
            $isHttps = ($this->request->server('HTTPS') === 'on' || (int) $this->request->server('SERVER_PORT') === 443);
            $scheme = $isHttps ? 'https' : 'http';
            $host = (string) $this->request->server('HTTP_HOST', 'localhost');
            $boardUrl = $scheme . '://' . $host;
        }

        $cleanPath = ltrim($url, '/');
        if (str_starts_with($cleanPath, './')) {
            $cleanPath = substr($cleanPath, 2);
        }

        return rtrim($boardUrl, '/') . '/' . ltrim($cleanPath, '/');
    }

    /**
     * Dispatch 301 Permanent Redirect
     */
    protected function redirect(string $targetUrl): void
    {
        $this->lastRedirectUrl = $targetUrl;
        $this->lastStatusCode = 301;

        if ($this->isTesting) {
            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('HTTP/1.1 301 Moved Permanently', true, 301);
        header('Location: ' . $targetUrl, true, 301);
        header('X-Redirect-By: phpBB-SEO-Migration-301');
        exit;
    }

    // Testing API
    public function setTesting(bool $testing): void
    {
        $this->isTesting = $testing;
    }

    public function getLastRedirectUrl(): ?string
    {
        return $this->lastRedirectUrl;
    }

    public function getLastStatusCode(): ?int
    {
        return $this->lastStatusCode;
    }
}
