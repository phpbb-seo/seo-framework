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
                return $this->permalinkProfile->generateTopicUrl($route->id);

            case 'forum':
                if ($route->page !== null && $route->page > 1) {
                    $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                    $start = $this->paginationResolver->pageToStart($route->page, $topicsPerPage);
                    return $this->permalinkProfile->generateForumPageUrl($route->id, $start, $topicsPerPage);
                }
                return $this->permalinkProfile->generateForumUrl($route->id);

            case 'member':
                return $this->permalinkProfile->generateMemberUrl($route->id);

            case 'group':
                return $this->permalinkProfile->generateGroupUrl($route->id);

            case 'post':
                $pos = $this->permalinkProfile->getEntityContext()->getPostPosition($route->id);
                $topicId = $pos['topic_id'] ?? $this->permalinkProfile->getEntityContext()->getTopicIdForPost($route->id);
                if ($topicId !== null && $topicId > 0) {
                    $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');
                    $start = isset($pos['prev_posts']) ? (int) (floor($pos['prev_posts'] / $postsPerPage) * $postsPerPage) : 0;
                    $topicUrl = ($start > 0)
                        ? $this->permalinkProfile->generateTopicPageUrl($topicId, $start, $postsPerPage)
                        : $this->permalinkProfile->generateTopicUrl($topicId);
                    if ($topicUrl !== null) {
                        return $topicUrl . '#p' . $route->id;
                    }
                }
                return null;
        }

        return null;
    }

    private function generateCanonicalForLegacy(string $script, RequestContext $context): ?string
    {
        // Parse query from context (robust against single and recursive entity encoding)
        $query = [];
        if ($context->query !== '') {
            $cleanQueryStr = $context->query;
            while (str_contains($cleanQueryStr, '&amp;')) {
                $cleanQueryStr = str_replace('&amp;', '&', $cleanQueryStr);
            }
            parse_str($cleanQueryStr, $query);
        }

        switch ($script) {
            case 'viewtopic.php':
                $pVal = $query['p'] ?? ($query['amp;p'] ?? null);
                $postId = null;
                if ($pVal !== null && is_numeric($pVal) && (int) $pVal > 0) {
                    $postId = (int) $pVal;
                } elseif (!empty($GLOBALS['post_id']) && (int) $GLOBALS['post_id'] > 0) {
                    $postId = (int) $GLOBALS['post_id'];
                }

                $pos = ($postId !== null)
                    ? $this->permalinkProfile->getEntityContext()->getPostPosition($postId)
                    : null;

                $tVal = $query['t'] ?? ($query['amp;t'] ?? null);
                $topicId = null;
                if ($tVal !== null && is_numeric($tVal) && (int) $tVal > 0) {
                    $topicId = (int) $tVal;
                } elseif (!empty($GLOBALS['topic_id']) && (int) $GLOBALS['topic_id'] > 0) {
                    $topicId = (int) $GLOBALS['topic_id'];
                } elseif ($pos !== null) {
                    $topicId = $pos['topic_id'];
                } elseif ($postId !== null) {
                    $topicId = $this->permalinkProfile->getEntityContext()->getTopicIdForPost($postId);
                }

                if ($topicId === null || $topicId <= 0) {
                    return null;
                }

                $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');

                // Determine start offset with strict precedence:
                // 1. Post position offset within topic if a resolvable post ID is present
                //    (the page containing the post must be loaded so the #p{id} anchor is reachable)
                // 2. Explicit query parameter 'start' (authoritative for pure topic pagination)
                // 3. Global $start if positive
                // 4. Default to 0
                if ($pos !== null && isset($pos['prev_posts'])) {
                    $start = (int) (floor($pos['prev_posts'] / $postsPerPage) * $postsPerPage);
                } elseif (($startVal = $query['start'] ?? ($query['amp;start'] ?? null)) !== null && is_numeric($startVal)) {
                    $start = max(0, (int) $startVal);
                } elseif (!empty($GLOBALS['start']) && (int) $GLOBALS['start'] > 0) {
                    $start = (int) $GLOBALS['start'];
                } else {
                    $start = 0;
                }

                if ($start > 0) {
                    $seoUrl = $this->permalinkProfile->generateTopicPageUrl($topicId, $start, $postsPerPage);
                } else {
                    $seoUrl = $this->permalinkProfile->generateTopicUrl($topicId);
                }

                if ($seoUrl === null) {
                    return null;
                }

                if ($postId !== null) {
                    $seoUrl .= '#p' . $postId;
                }

                return $seoUrl;

            case 'viewforum.php':
                $fVal = $query['f'] ?? ($query['amp;f'] ?? null);
                $forumId = ($fVal !== null && is_numeric($fVal)) ? (int) $fVal : null;
                if ($forumId === null || $forumId <= 0) {
                    return null;
                }
                $startVal = $query['start'] ?? ($query['amp;start'] ?? null);
                $start = ($startVal !== null && is_numeric($startVal)) ? max(0, (int) $startVal) : 0;
                $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                if ($start > 0) {
                    return $this->permalinkProfile->generateForumPageUrl($forumId, $start, $topicsPerPage);
                }
                return $this->permalinkProfile->generateForumUrl($forumId);

            case 'memberlist.php':
                $uVal = $query['u'] ?? ($query['amp;u'] ?? null);
                $userId = ($uVal !== null && is_numeric($uVal)) ? (int) $uVal : null;
                $mode   = $query['mode'] ?? ($query['amp;mode'] ?? '');
                if ($userId !== null && $userId > 0 && $mode === 'viewprofile') {
                    return $this->permalinkProfile->generateMemberUrl($userId);
                }

                $gVal = $query['g'] ?? ($query['amp;g'] ?? null);
                $groupId = ($gVal !== null && is_numeric($gVal)) ? (int) $gVal : null;
                if ($groupId !== null && $groupId > 0 && $mode === 'group') {
                    return $this->permalinkProfile->generateGroupUrl($groupId);
                }
                return null;
        }

        return null;
    }

    private function buildAbsoluteUrl(string $seoPath, RequestContext $context): string
    {
        $boardUrl = rtrim(generate_board_url(), '/');
        if (preg_match('#^https?://#i', $boardUrl, $schemeMatch)) {
            $boardUrl = $schemeMatch[0] . preg_replace('#^(?:https?://)+#i', '', $boardUrl);
        }
        $scriptPath = (string) parse_url($boardUrl, PHP_URL_PATH);
        $boardPath = '/' . trim($scriptPath, '/');

        $cleanSeoPath = '/' . ltrim($seoPath, '/');

        if ($boardPath !== '/' && $boardPath !== '') {
            if (str_starts_with($cleanSeoPath, $boardPath . '/')) {
                $cleanSeoPath = substr($cleanSeoPath, strlen($boardPath));
            }
        }

        return rtrim($boardUrl, '/') . '/' . ltrim($cleanSeoPath, '/');
    }
}
