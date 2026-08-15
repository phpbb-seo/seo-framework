<?php
declare(strict_types=1);

namespace phpbbseo\framework\Canonical;

use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\RequestContext;
use phpbbseo\framework\Rewrite\InboundRouteResolver;
use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbbseo\framework\Url\PaginationResolver;

/**
 * Determines the single canonical URL for the current request.
 *
 * Canonical URL is built from:
 *  1. EntitySeoContext (via PermalinkRewriteProfile) for the correct current slug.
 *  2. ConfigurationProvider for trusted scheme/host.
 *
 * Does NOT read phpBB global variables directly.
 * Entity data must be populated into EntitySeoContext via SeoListener events
 * before CanonicalResolver is invoked.
 */
class CanonicalResolver
{
    public function __construct(
        private readonly PermalinkRewriteProfile $permalinkProfile,
        private readonly InboundRouteResolver $inboundResolver,
        private readonly ConfigurationProvider $configProvider,
        private readonly PaginationResolver $paginationResolver
    ) {}

    /**
     * Resolve canonical URL for this request.
     * Returns null if no canonical can be determined (e.g. non-SEO page).
     */
    public function resolve(RequestContext $context): ?string
    {
        $path = $context->path;

        // Try to match current path as a SEO URL
        $route = $this->inboundResolver->resolve($path);
        if ($route !== null) {
            $seoUrl = $this->generateCanonicalForRoute($route, $context);
            if ($seoUrl !== null) {
                return $this->buildAbsoluteUrl($seoUrl, $context);
            }
        }

        // Try legacy phpBB URLs
        $script = basename($path);
        $seoUrl = $this->generateCanonicalForLegacy($script, $context);
        if ($seoUrl !== null) {
            return $this->buildAbsoluteUrl($seoUrl, $context);
        }

        return null;
    }

    /**
     * @param \phpbbseo\framework\Rewrite\InboundRouteResult $route
     */
    private function generateCanonicalForRoute(object $route, RequestContext $context): ?string
    {
        switch ($route->resource) {
            case 'topic':
                if ($route->page !== null && $route->page > 1) {
                    $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');
                    $start = $this->paginationResolver->pageToStart($route->page, $postsPerPage);
                    return $this->permalinkProfile->generateTopicPageUrl($route->id, $start, $postsPerPage);
                }
                $staleUrl = $this->permalinkProfile->detectStaleTopic($route->id, $route->slug);
                return $staleUrl ?? $this->permalinkProfile->generateTopicUrl($route->id);

            case 'forum':
                if ($route->page !== null && $route->page > 1) {
                    $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                    $start = $this->paginationResolver->pageToStart($route->page, $topicsPerPage);
                    return $this->permalinkProfile->generateForumPageUrl($route->id, $start, $topicsPerPage);
                }
                $staleUrl = $this->permalinkProfile->detectStaleForum($route->id, $route->slug);
                return $staleUrl ?? $this->permalinkProfile->generateForumUrl($route->id);

            case 'member':
                $staleUrl = $this->permalinkProfile->detectStaleMember($route->id, $route->slug);
                return $staleUrl ?? $this->permalinkProfile->generateMemberUrl($route->id);

            case 'group':
                $staleUrl = $this->permalinkProfile->detectStaleGroup($route->id, $route->slug);
                return $staleUrl ?? $this->permalinkProfile->generateGroupUrl($route->id);
        }

        return null;
    }

    private function generateCanonicalForLegacy(string $script, RequestContext $context): ?string
    {
        // Parse query from context
        $query = [];
        if ($context->query !== '') {
            $cleanQueryStr = str_replace('&amp;', '&', $context->query);
            parse_str($cleanQueryStr, $query);
        }

        switch ($script) {
            case 'viewtopic.php':
                $topicId = $GLOBALS['topic_id'] ?? (isset($query['t']) ? (int) $query['t'] : null);
                if ($topicId === null) {
                    $postId = isset($query['p']) ? (int) $query['p'] : null;
                    if ($postId !== null) {
                        $topicId = $this->permalinkProfile->getEntityContext()->getTopicIdForPost($postId);
                    }
                }

                if ($topicId === null) {
                    return null;
                }

                $start = $GLOBALS['start'] ?? (isset($query['start']) ? (int) $query['start'] : 0);
                $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');
                
                if ($start > 0) {
                    $seoUrl = $this->permalinkProfile->generateTopicPageUrl($topicId, $start, $postsPerPage);
                } else {
                    $seoUrl = $this->permalinkProfile->generateTopicUrl($topicId);
                }

                if ($seoUrl === null) {
                    return null;
                }

                $postId = isset($query['p']) ? (int) $query['p'] : null;
                if ($postId !== null) {
                    $seoUrl .= '#p' . $postId;
                }

                return $seoUrl;

            case 'viewforum.php':
                $forumId = isset($query['f']) ? (int) $query['f'] : null;
                if ($forumId === null) {
                    return null;
                }
                $start = isset($query['start']) ? (int) $query['start'] : 0;
                $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                if ($start > 0) {
                    return $this->permalinkProfile->generateForumPageUrl($forumId, $start, $topicsPerPage);
                }
                return $this->permalinkProfile->generateForumUrl($forumId);

            case 'memberlist.php':
                $userId = isset($query['u']) ? (int) $query['u'] : null;
                $mode   = $query['mode'] ?? '';
                if ($userId !== null && $mode === 'viewprofile') {
                    return $this->permalinkProfile->generateMemberUrl($userId);
                }

                $groupId = isset($query['g']) ? (int) $query['g'] : null;
                if ($groupId !== null && $mode === 'group') {
                    return $this->permalinkProfile->generateGroupUrl($groupId);
                }
                return null;
        }

        return null;
    }

    private function buildAbsoluteUrl(string $seoPath, RequestContext $context): string
    {
        $boardUrl = rtrim(generate_board_url(), '/');
        $scriptPath = (string) parse_url($boardUrl, PHP_URL_PATH);
        $boardPath = '/' . trim($scriptPath, '/');

        $cleanSeoPath = '/' . ltrim($seoPath, '/');

        if ($boardPath !== '/' && $boardPath !== '') {
            if (str_starts_with($cleanSeoPath, $boardPath . '/')) {
                $cleanSeoPath = substr($cleanSeoPath, strlen($boardPath));
            }
        }

        return $boardUrl . $cleanSeoPath;
    }
}
