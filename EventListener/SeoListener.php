<?php
declare(strict_types=1);

namespace phpbbseo\framework\EventListener;

use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Context\RequestContextFactory;
use phpbbseo\framework\Canonical\CanonicalResolver;
use phpbbseo\framework\Redirect\RedirectResolver;
use phpbbseo\framework\Redirect\UrlSafetyValidator;
use phpbbseo\framework\Rewrite\InboundRouteResolver;
use phpbbseo\framework\Rewrite\PublicResourceUrlResolver;
use phpbbseo\framework\Rewrite\SlugRepository;
use phpbbseo\framework\Url\PaginationResolver;
use phpbb\template\template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbb\request\request_interface;

/**
 * phpBB event listener: bridges phpBB lifecycle events into the SEO framework.
 */
class SeoListener implements EventSubscriberInterface
{
    public function __construct(
        private readonly RequestContextFactory $contextFactory,
        private readonly EntitySeoContext $entityContext,
        private readonly InboundRouteResolver $inboundResolver,
        private readonly PublicResourceUrlResolver $urlResolver,
        private readonly ConfigurationProvider $configProvider,
        private readonly CanonicalResolver $canonicalResolver,
        private readonly RedirectResolver $redirectResolver,
        private readonly UrlSafetyValidator $urlSafetyValidator,
        private readonly request_interface $request,
        private readonly SlugRepository $slugRepository,
        private readonly PaginationResolver $paginationResolver,
        private readonly template $template,
        private readonly \phpbb\user $user
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Early inbound pagination start resolution (before viewforum/viewtopic reads $start)
            'core.common'                               => ['onCommon', 1000],
            // Early inbound rewriting & global preloading
            'core.user_setup'                           => ['onUserSetup', 1000],
            // Board Index forum list preloading
            'core.display_forums_before'                => 'onDisplayForums',
            // Entity context batch preloading (1 batch query per list page)
            'core.viewforum_modify_topics_data'         => 'onViewForumTopics',
            'core.viewtopic_modify_forum_id'            => 'onViewTopicForum',
            'core.viewtopic_modify_post_data'           => 'onViewTopicPosts',
            'core.memberlist_prepare_profile_data'      => 'onMemberProfile',
            // Member links custom presentation-preserving adapter
            'core.modify_username_string'               => 'onModifyUsernameString',
            'core.modify_group_name_string'             => 'onModifyGroupNameString',
            // Canonical + legacy redirect
            'core.page_header'                          => 'onPageHeader',
            // Outbound URL rewriting hook
            'core.append_sid'                           => 'onAppendSid',
            // Native phpBB template pagination rewriting
            'core.pagination_generate_page_link'        => 'onPaginationGeneratePageLink',
            // Rename & delete database synchronization (write operations)
            'core.submit_post_end'                      => 'onSubmitPostEnd',
            'core.delete_topics_after_query'            => 'onDeleteTopicsAfter',
            'core.delete_user_after'                    => 'onDeleteUserAfter',
            'core.user_add_after'                       => 'onUserAddAfter',
            'core.ucp_register_register_after'          => 'onUserAddAfter',
            'core.update_username'                      => 'onUpdateUsername',
            'core.acp_manage_forums_update_data_after'  => 'onForumUpdateAfter',
            'core.delete_forum_content_before_query'    => 'onForumDeleteBefore',
            'core.acp_manage_group_request_data'        => 'onGroupUpdateAfter',
            'core.delete_group_after'                   => 'onGroupDeleteAfter',
        ];
    }

    // -------------------------------------------------------------------------
    // INBOUND: Intercept pretty URLs early in phpBB lifecycle & preload static resources
    // -------------------------------------------------------------------------

    public function onCommon($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        // Establish board-root URL context early so operational endpoints (e.g. mcp.php, posting.php)
        // generate absolute/board-rooted URLs rather than relative paths that break under nested SEO URLs.
        if (!defined('PHPBB_USE_BOARD_URL_PATH') && !defined('IN_ADMIN') && !defined('IN_INSTALL') && !defined('IN_CRON')) {
            define('PHPBB_USE_BOARD_URL_PATH', true);
        }

        $seoPage = (int) $this->request->variable('seo_page', 0, false, request_interface::GET);
        if ($seoPage > 1) {
            $topicId = (int) $this->request->variable('t', 0, false, request_interface::GET);
            $forumId = (int) $this->request->variable('f', 0, false, request_interface::GET);

            if ($topicId > 0) {
                $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');
                $start = $this->paginationResolver->pageToStart($seoPage, $postsPerPage);
                if ($start > 0) {
                    $this->request->overwrite('start', $start, request_interface::GET);
                    $this->request->overwrite('start', $start, request_interface::REQUEST);
                }
            } elseif ($forumId > 0) {
                $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                $start = $this->paginationResolver->pageToStart($seoPage, $topicsPerPage);
                if ($start > 0) {
                    $this->request->overwrite('start', $start, request_interface::GET);
                    $this->request->overwrite('start', $start, request_interface::REQUEST);
                }
            }
        }
    }

    public function onUserSetup($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        // Process dynamic inbound pagination if passed from pre-bootstrap rewrite.php (fallback)
        $seoPage = (int) $this->request->variable('seo_page', 0, false, request_interface::GET);
        if ($seoPage > 1) {
            $topicId = (int) $this->request->variable('t', 0, false, request_interface::GET);
            $forumId = (int) $this->request->variable('f', 0, false, request_interface::GET);

            if ($topicId > 0) {
                $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');
                $start = $this->paginationResolver->pageToStart($seoPage, $postsPerPage);
                if ($start > 0) {
                    $this->request->overwrite('start', $start, request_interface::GET);
                    $this->request->overwrite('start', $start, request_interface::REQUEST);
                }
            } elseif ($forumId > 0) {
                $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                $start = $this->paginationResolver->pageToStart($seoPage, $topicsPerPage);
                if ($start > 0) {
                    $this->request->overwrite('start', $start, request_interface::GET);
                    $this->request->overwrite('start', $start, request_interface::REQUEST);
                }
            }
        }

        // 2. Preload all forum and group slugs into memory context (1 batch database query)
        $forums = $this->slugRepository->fetchAllSlugs('forum');
        $groups = $this->slugRepository->fetchAllSlugs('group');

        $this->entityContext->setForums($forums);
        $this->entityContext->setGroups($groups);

        // 3. Early canonical warming and 301 legacy redirect evaluation
        $postId = (int) $this->request->variable('p', 0, false, request_interface::GET);
        if ($postId > 0) {
            $topicMap = $this->slugRepository->fetchPostToTopicBatch([$postId]);
            if (!empty($topicMap)) {
                $this->entityContext->setPostToTopic($topicMap);
                $topicId = $topicMap[$postId];
                $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
                $this->entityContext->setTopics($topicSlugs);
            }
        }

        $topicId = (int) $this->request->variable('t', 0, false, request_interface::GET);
        if ($topicId > 0) {
            $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
            $this->entityContext->setTopics($topicSlugs);
        }

        $forumId = (int) $this->request->variable('f', 0, false, request_interface::GET);
        if ($forumId > 0) {
            $forumSlugs = $this->slugRepository->fetchSlugsBatch('forum', [$forumId]);
            $this->entityContext->setForums($forumSlugs);
        }

        $userId = (int) $this->request->variable('u', 0, false, request_interface::GET);
        if ($userId > 0) {
            $memberSlugs = $this->slugRepository->fetchSlugsBatch('member', [$userId]);
            $this->entityContext->setMembers($memberSlugs);
        }

        $groupId = (int) $this->request->variable('g', 0, false, request_interface::GET);
        if ($groupId > 0) {
            $groupSlugs = $this->slugRepository->fetchSlugsBatch('group', [$groupId]);
            $this->entityContext->setGroups($groupSlugs);
        }

        $context = $this->contextFactory->createFromPhpbbRequest($this->request);
        $canonicalUrl = $this->canonicalResolver->resolve($context);
        $decision = $this->redirectResolver->resolve($context, $canonicalUrl, $this->urlSafetyValidator);

        if ($decision !== null && !empty($decision->targetUrl)) {
            header('Location: ' . $decision->targetUrl, true, $decision->statusCode);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // ENTITY CONTEXT: Batch preload from database read-model (prevent N+1 queries)
    // -------------------------------------------------------------------------

    public function onDisplayForums($event): void
    {
        $forumRows = $event['forum_rows'] ?? [];
        $postIds = [];
        foreach ($forumRows as $row) {
            if (isset($row['forum_last_post_id']) && $row['forum_last_post_id'] > 0) {
                $postIds[] = (int) $row['forum_last_post_id'];
            }
        }

        if (!empty($postIds)) {
            // Batch load post-to-topic mappings in 1 query
            $mappings = $this->slugRepository->fetchPostToTopicBatch($postIds);
            $this->entityContext->setPostToTopic($mappings);

            // Batch load topic slugs for those topic IDs in 1 query
            $topicIds = array_values($mappings);
            if (!empty($topicIds)) {
                $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', $topicIds);
                $this->entityContext->setTopics($topicSlugs);
            }
        }
    }

    public function onViewForumTopics($event): void
    {
        $topicIds = $event['topic_list'] ?? [];
        $rowset = $event['rowset'] ?? [];

        // Collect poster user IDs and last post IDs on the page
        $userIds = [];
        $postIds = [];
        foreach ($rowset as $row) {
            if (isset($row['topic_poster'])) {
                $userIds[] = (int) $row['topic_poster'];
            }
            if (isset($row['topic_last_poster_id'])) {
                $userIds[] = (int) $row['topic_last_poster_id'];
            }
            if (isset($row['topic_last_post_id']) && $row['topic_last_post_id'] > 0) {
                $postIds[] = (int) $row['topic_last_post_id'];
            }
        }

        // Batch fetch topic slugs (1 query)
        if (!empty($topicIds)) {
            $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', $topicIds);
            $this->entityContext->setTopics($topicSlugs);
        }

        // Batch fetch member slugs (1 query)
        if (!empty($userIds)) {
            $memberSlugs = $this->slugRepository->fetchSlugsBatch('member', $userIds);
            $this->entityContext->setMembers($memberSlugs);
        }

        // Batch fetch post-to-topic mappings (1 query)
        if (!empty($postIds)) {
            $mappings = $this->slugRepository->fetchPostToTopicBatch($postIds);
            $this->entityContext->setPostToTopic($mappings);
        }
    }

    public function onViewTopicForum($event): void
    {
        $topicData = $event['topic_data'] ?? [];
        if (isset($topicData['topic_id'])) {
            $topicId = (int) $topicData['topic_id'];
            $slugs = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
            $this->entityContext->setTopics($slugs);
        }
    }

    public function onViewTopicPosts($event): void
    {
        $topicData = $event['topic_data'] ?? [];
        $rowset = $event['rowset'] ?? [];

        if (isset($topicData['topic_id'])) {
            $topicId = (int) $topicData['topic_id'];
            $this->entityContext->setTopics([
                $topicId => (string) ($topicData['topic_title'] ?? '')
            ]);

            // Map all post IDs on this page to the parent topic ID
            $postToTopic = [];
            foreach ($rowset as $row) {
                if (isset($row['post_id'])) {
                    $postToTopic[(int) $row['post_id']] = $topicId;
                }
            }
            if (!empty($postToTopic)) {
                $this->entityContext->setPostToTopic($postToTopic);
            }
        }

        // Collect post authors
        $userIds = [];
        foreach ($rowset as $row) {
            if (isset($row['poster_id'])) {
                $userIds[] = (int) $row['poster_id'];
            }
        }

        // Batch fetch member slugs (1 query)
        if (!empty($userIds)) {
            $memberSlugs = $this->slugRepository->fetchSlugsBatch('member', $userIds);
            $this->entityContext->setMembers($memberSlugs);
        }
    }

    public function onMemberProfile($event): void
    {
        $member = $event['data'] ?? [];
        if (isset($member['user_id'], $member['username'])) {
            $this->entityContext->setMembers([
                (int) $member['user_id'] => (string) $member['username']
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // PRESENTATION-PRESERVING MEMBER URL SWAP
    // -------------------------------------------------------------------------

    public function onModifyUsernameString($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        $userId = (int) ($event['user_id'] ?? 0);
        $username = (string) ($event['username'] ?? '');
        $mode = $event['mode'] ?? '';

        // Only rewrite if we have a valid registered user and it's a mode that generates a profile link
        if ($userId <= 1 || $username === '' || !in_array($mode, ['profile', 'full'], true)) {
            return;
        }

        // Prime the context for this user so that the resolver can resolve it without database query
        $this->entityContext->setMembers([$userId => $username]);

        $seoUrl = $this->urlResolver->resolve('memberlist.php', [
            'mode' => 'viewprofile',
            'u'    => $userId,
        ]);

        if ($seoUrl === null) {
            return;
        }

        if ($mode === 'profile') {
            $event['username_string'] = $seoUrl;
        } elseif ($mode === 'full') {
            // Surgically replace only the href attribute to keep phpBB's styles, class, colors, and accessibility tags intact
            $event['username_string'] = preg_replace(
                '/href="[^"]*"/',
                'href="' . htmlspecialchars($seoUrl, ENT_COMPAT) . '"',
                $event['username_string'],
                1
            );
        }
    }

    public function onModifyGroupNameString($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        $groupId = (int) ($event['group_id'] ?? 0);
        $groupNameString = (string) ($event['group_name_string'] ?? '');

        if ($groupId > 0 && $groupNameString !== '') {
            $groupSlugs = $this->slugRepository->fetchSlugsBatch('group', [$groupId]);
            $this->entityContext->setGroups($groupSlugs);

            $seoUrl = $this->urlResolver->resolve('memberlist.php', ['mode' => 'group', 'g' => $groupId]);
            if ($seoUrl !== null) {
                $pattern = '#href="[^"]*memberlist\.[a-z]+\?[^"]*g=' . $groupId . '(?=[&"\#])[^"]*"#i';
                $replacement = 'href="' . htmlspecialchars($seoUrl, ENT_COMPAT) . '"';
                $event['group_name_string'] = preg_replace($pattern, $replacement, $groupNameString);
            }
        }
    }

    // -------------------------------------------------------------------------
    // CANONICAL + LEGACY REDIRECT
    // -------------------------------------------------------------------------

    public function onPageHeader($event): void
    {
        try {
            $this->user->add_lang_ext('phpbbseo/framework', 'acp_seo');
            $poweredByFormat = $this->user->lang('SEO_POWERED_BY');
            if (empty($poweredByFormat) || !str_contains($poweredByFormat, '%s')) {
                $poweredByFormat = 'Powered by %s';
            }
            $brandLink = '<a href="https://www.phpbbseo.com/" rel="nofollow">phpBB SEO</a>';
            $this->template->assign_vars([
                'S_SEO_FOOTER_ATTRIBUTION' => true,
                'L_SEO_POWERED_BY'         => sprintf($poweredByFormat, $brandLink),
            ]);
        } catch (\Throwable) {
            $this->template->assign_vars([
                'S_SEO_FOOTER_ATTRIBUTION' => true,
                'L_SEO_POWERED_BY'         => 'Powered by <a href="https://www.phpbbseo.com/" rel="nofollow">phpBB SEO</a>',
            ]);
        }

        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        // Warm EntitySeoContext from incoming request query parameters for legacy inbound redirects
        $postId = (int) $this->request->variable('p', 0, false, request_interface::GET);
        if ($postId > 0) {
            $topicMap = $this->slugRepository->fetchPostToTopicBatch([$postId]);
            if (!empty($topicMap)) {
                $this->entityContext->setPostToTopic($topicMap);
                $topicId = $topicMap[$postId];
                $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
                $this->entityContext->setTopics($topicSlugs);
            }
        }

        $topicId = (int) $this->request->variable('t', 0, false, request_interface::GET);
        if ($topicId > 0) {
            $topicSlugs = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
            $this->entityContext->setTopics($topicSlugs);
        }

        $forumId = (int) $this->request->variable('f', 0, false, request_interface::GET);
        if ($forumId > 0) {
            $forumSlugs = $this->slugRepository->fetchSlugsBatch('forum', [$forumId]);
            $this->entityContext->setForums($forumSlugs);
        }

        $userId = (int) $this->request->variable('u', 0, false, request_interface::GET);
        if ($userId > 0) {
            $memberSlugs = $this->slugRepository->fetchSlugsBatch('member', [$userId]);
            $this->entityContext->setMembers($memberSlugs);
        }

        $groupId = (int) $this->request->variable('g', 0, false, request_interface::GET);
        if ($groupId > 0) {
            $groupSlugs = $this->slugRepository->fetchSlugsBatch('group', [$groupId]);
            $this->entityContext->setGroups($groupSlugs);
        }

        // Native loaded globals fallback
        if (isset($GLOBALS['topic_id'], $GLOBALS['topic_data']['topic_title'])) {
            $this->entityContext->setTopics([
                (int) $GLOBALS['topic_id'] => (string) $GLOBALS['topic_data']['topic_title']
            ]);
        }
        if (isset($GLOBALS['forum_id'], $GLOBALS['forum_data']['forum_name'])) {
            $this->entityContext->setForums([
                (int) $GLOBALS['forum_id'] => (string) $GLOBALS['forum_data']['forum_name']
            ]);
        }

        $context = $this->contextFactory->createFromPhpbbRequest($this->request);
        $canonicalUrl = $this->canonicalResolver->resolve($context);

        if ($canonicalUrl !== null) {
            $this->template->assign_var('U_CANONICAL', $canonicalUrl);
            $pageData = $event['page_data'] ?? [];
            $pageData['U_CANONICAL'] = $canonicalUrl;
            $event['page_data'] = $pageData;
        }

        $decision = $this->redirectResolver->resolve($context, $canonicalUrl, $this->urlSafetyValidator);

        if ($decision !== null && !empty($decision->targetUrl)) {
            header('Location: ' . $decision->targetUrl, true, $decision->statusCode);
            exit;
        }
    }

    // -------------------------------------------------------------------------
    // OUTBOUND: Rewrite phpBB-generated links to SEO URLs
    // -------------------------------------------------------------------------

    public function onAppendSid($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        if (!isset($event['url'], $event['params'])) {
            return;
        }

        // Respect existing overrides by other extensions
        if (isset($event['append_sid_overwrite']) && $event['append_sid_overwrite'] !== false) {
            return;
        }

        $page = (string) ($event['url'] ?? '');
        $params = $event['params'] ?? [];
        $isAmp = (bool) ($event['is_amp'] ?? true);

        $seoUrl = $this->urlResolver->resolve($page, $params, $isAmp);

        if ($seoUrl !== null) {
            $event['append_sid_overwrite'] = $seoUrl;
        }
    }

    public function onPaginationGeneratePageLink($event): void
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return;
        }

        $baseUrl = $event['base_url'] ?? '';
        $onPage = (int) ($event['on_page'] ?? 1);
        $perPage = (int) ($event['per_page'] ?? 0);

        if (!is_string($baseUrl) || $perPage <= 0) {
            return;
        }

        $start = ($onPage > 1) ? ($onPage - 1) * $perPage : 0;
        $params = ($start > 0) ? "start=$start" : '';

        $resolved = $this->urlResolver->resolve($baseUrl, $params);
        if ($resolved !== null) {
            $event['generate_page_link_override'] = $resolved;
        }
    }

    // -------------------------------------------------------------------------
    // DATABASE SYNCHRONIZATION (RENAME/DELETE UPDATE HOOKS)
    // -------------------------------------------------------------------------

    public function onSubmitPostEnd($event): void
    {
        $mode = $event['mode'] ?? '';
        $subject = (string) ($event['subject'] ?? '');
        $data = $event['data'] ?? [];
        $topicId = (int) ($data['topic_id'] ?? ($event['topic_id'] ?? 0));
        $postId = (int) ($data['post_id'] ?? ($event['post_id'] ?? 0));
        $firstPostId = (int) ($data['topic_first_post_id'] ?? 0);

        $isFirstPost = ($mode === 'post') || 
            ($mode === 'edit' && ($firstPostId === 0 || $postId === $firstPostId));

        if ($topicId > 0 && $isFirstPost && $subject !== '') {
            $this->slugRepository->saveSlug('topic', $topicId, $subject, (int) time());
        }
    }

    public function onDeleteTopicsAfter($event): void
    {
        $topicIds = $event['topic_ids'] ?? [];
        foreach ($topicIds as $topicId) {
            $this->slugRepository->deleteSlug('topic', (int) $topicId);
        }
    }

    public function onDeleteUserAfter($event): void
    {
        $userIds = $event['user_ids'] ?? [];
        foreach ($userIds as $userId) {
            $this->slugRepository->deleteSlug('member', (int) $userId);
        }
    }

    public function onUpdateUsername($event): void
    {
        $newName = $event['new_name'] ?? '';
        if ($newName !== '') {
            $this->slugRepository->updateUserSlug($newName);
        }
    }

    public function onForumUpdateAfter($event): void
    {
        $forumData = $event['forum_data'] ?? [];
        if (isset($forumData['forum_id'], $forumData['forum_name'])) {
            $this->slugRepository->saveSlug('forum', (int) $forumData['forum_id'], (string) $forumData['forum_name']);
        }
    }

    public function onForumDeleteBefore($event): void
    {
        $forumId = (int) ($event['forum_id'] ?? 0);
        if ($forumId > 0) {
            $this->slugRepository->deleteSlug('forum', $forumId);
        }
    }

    public function onUserAddAfter($event): void
    {
        $userId = (int) ($event['user_id'] ?? 0);
        $userRow = $event['user_row'] ?? ($event['data'] ?? []);
        $username = (string) ($userRow['username'] ?? '');

        if ($userId > 1 && $username !== '') {
            $this->slugRepository->saveSlug('member', $userId, $username);
        }
    }

    public function onGroupUpdateAfter($event): void
    {
        $groupId = (int) ($event['group_id'] ?? 0);
        $groupName = (string) ($event['group_name'] ?? '');
        if (empty($groupName) && isset($event['submit_ary']['group_name'])) {
            $groupName = (string) $event['submit_ary']['group_name'];
        }

        if ($groupId > 0 && $groupName !== '') {
            $this->slugRepository->saveSlug('group', $groupId, $groupName);
        }
    }

    public function onGroupDeleteAfter($event): void
    {
        $groupId = (int) ($event['group_id'] ?? 0);
        if ($groupId > 0) {
            $this->slugRepository->deleteSlug('group', $groupId);
        }
    }
}
