<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use phpbbseo\framework\Configuration\ConfigurationProvider;

/**
 * Centrally resolves public resource scripts and parameter targets into friendly SEO URLs.
 * Orchestrates pattern mapping, slug resolution, parameter filtering, and board base path prepending.
 */
class PublicResourceUrlResolver
{
    private ?string $boardPath = null;

    public function __construct(
        private readonly ResourceDetector $detector,
        private readonly PermalinkRewriteProfile $permalinkProfile,
        private readonly ConfigurationProvider $configProvider
    ) {}

    /**
     * Resolves a public script and parameters to an SEO URL.
     * Returns null if target cannot be resolved or is not a public resource.
     *
     * @param string $url The target base script or URL (e.g. 'viewtopic.php')
     * @param array<string, mixed>|string $params The script query parameters
     * @param bool $isAmp Whether to format query string with HTML entity separator (&amp;)
     * @return string|null The friendly SEO URL, or null if native representation should be kept
     */
    public function resolve(string $url, $params, bool $isAmp = true): ?string
    {
        if (!$this->configProvider->isRewriteEnabled()) {
            return null;
        }

        // 1. Parse anchor from the url
        $anchor = '';
        $hashPos = strpos($url, '#');
        if ($hashPos !== false) {
            $anchor = substr($url, $hashPos);
            $url = substr($url, 0, $hashPos);
        }

        // 2. Parse query parameters to an array
        $parsedParams = [];
        if (is_string($params)) {
            $hashPosParam = strpos($params, '#');
            if ($hashPosParam !== false) {
                $anchor = substr($params, $hashPosParam);
                $params = substr($params, 0, $hashPosParam);
            }
            $cleanParamsStr = str_replace('&amp;', '&', ltrim($params, '?&'));
            parse_str($cleanParamsStr, $parsedParams);
        } elseif (is_array($params)) {
            $parsedParams = $params;
        }

        // Normalize anchor
        if ($anchor !== '') {
            $anchor = '#' . ltrim($anchor, '#');
            if (preg_match('/#p(\d+)/', $anchor, $m)) {
                $anchor = '#p' . $m[1];
            }
        }

        // Merge query parameters that were embedded inside the base URL
        $queryStr = parse_url($url, PHP_URL_QUERY);
        if ($queryStr !== null && $queryStr !== '') {
            $cleanQueryStr = str_replace('&amp;', '&', $queryStr);
            parse_str($cleanQueryStr, $urlParams);
            $parsedParams = array_merge($urlParams, $parsedParams);
        }

        $script = basename(parse_url($url, PHP_URL_PATH) ?? '');
        $allowedScripts = ['viewtopic.php', 'viewforum.php', 'memberlist.php'];

        // Idempotency: if URL is not a native public script, it might be an already-SEO URL
        if (!in_array($script, $allowedScripts, true)) {
            if (isset($parsedParams['start'])) {
                $boardPath = $this->getBoardPath();
                $rawPath = parse_url($url, PHP_URL_PATH) ?? '';
                $cleanPath = '/' . ltrim(ltrim($rawPath, '.'), '/');
                $boardPrefix = rtrim($boardPath, '/');
                if ($boardPrefix !== '' && str_starts_with($cleanPath, $boardPrefix . '/')) {
                    $cleanPath = substr($cleanPath, strlen($boardPrefix));
                }
                $path = '/' . ltrim($cleanPath, '/');

                // Check if it matches an already SEO-formatted topic URL
                $match = $this->permalinkProfile->matchTopic($path);
                if ($match !== null) {
                    $id = (int) $match['id'];
                    $start = (int) $parsedParams['start'];
                    $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');

                    $seoPath = $this->permalinkProfile->generateTopicPageUrl($id, $start, $postsPerPage);
                    if ($seoPath !== null) {
                        $excludeKeys = ['start'];
                        $queryString = $this->buildQueryString($parsedParams, $excludeKeys, $isAmp);
                        $finalUrl = $boardPath . ltrim($seoPath, '/') . $queryString . $anchor;
                        return $this->normalizeDuplicateFragments($finalUrl);
                    }
                }

                // Check if it matches an already SEO-formatted forum URL
                $matchForum = $this->permalinkProfile->matchForum($path);
                if ($matchForum !== null) {
                    $id = (int) $matchForum['id'];
                    $start = (int) $parsedParams['start'];
                    $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');

                    $seoPath = $this->permalinkProfile->generateForumPageUrl($id, $start, $topicsPerPage);
                    if ($seoPath !== null) {
                        $excludeKeys = ['start'];
                        $queryString = $this->buildQueryString($parsedParams, $excludeKeys, $isAmp);
                        $finalUrl = $boardPath . ltrim($seoPath, '/') . $queryString . $anchor;
                        return $this->normalizeDuplicateFragments($finalUrl);
                    }
                }
            }

            return null;
        }

        // 3. Detect target resource
        $target = $this->detector->detect($script, $parsedParams);
        if ($target === null) {
            return null;
        }

        $seoPath = null;
        $id = $target->getId();
        $excludeKeys = [];

        switch ($target->getType()) {
            case 'forum':
                $pagination = $target->getPaginationParams();
                $start = $pagination['start'] ?? 0;
                if ($start > 0) {
                    $topicsPerPage = (int) $this->configProvider->get('topics_per_page', '50');
                    $seoPath = $this->permalinkProfile->generateForumPageUrl($id, $start, $topicsPerPage);
                } else {
                    $seoPath = $this->permalinkProfile->generateForumUrl($id);
                }
                $excludeKeys = ['f', 'start'];
                break;

            case 'topic':
                $pagination = $target->getPaginationParams();
                $start = $pagination['start'] ?? 0;
                if ($start > 0) {
                    $postsPerPage = (int) $this->configProvider->get('posts_per_page', '20');
                    $seoPath = $this->permalinkProfile->generateTopicPageUrl($id, $start, $postsPerPage);
                } else {
                    $seoPath = $this->permalinkProfile->generateTopicUrl($id);
                }
                $excludeKeys = ['t', 'start', 'p'];
                // If this topic target was passed with a post_id and anchor is empty, set #p{id}
                $postId = isset($parsedParams['p']) ? (int) $parsedParams['p'] : ($pagination['post_id'] ?? 0);
                if ($anchor === '' && $postId > 0) {
                    $anchor = '#p' . $postId;
                }
                break;

            case 'post':
                // Resolve post to its owning topic mapping
                $topicId = $this->permalinkProfile->getEntityContext()->getTopicIdForPost($id);
                if ($topicId !== null) {
                    $seoPath = $this->permalinkProfile->generateTopicUrl($topicId);
                    $excludeKeys = ['p'];
                    if ($anchor === '') {
                        $anchor = '#p' . $id;
                    }
                }
                break;

            case 'member':
                $seoPath = $this->permalinkProfile->generateMemberUrl($id);
                $excludeKeys = ['u', 'mode'];
                break;

            case 'group':
                $seoPath = $this->permalinkProfile->generateGroupUrl($id);
                $excludeKeys = ['g', 'mode'];
                break;
        }

        if ($seoPath === null) {
            return null;
        }

        // 4. Build extra query string (preserving tracking parameters, sid, etc.)
        $queryString = $this->buildQueryString($parsedParams, $excludeKeys, $isAmp);

        // 5. Prepend board root prefix (e.g. "/phpbb/") for root-relative link safety across all URL depths
        $base = $this->getBoardPath();
        $seoUrl = $base . ltrim($seoPath, '/');

        $finalUrl = $seoUrl . $queryString . $anchor;
        return $this->normalizeDuplicateFragments($finalUrl);
    }

    /**
     * Normalize the final URL to ensure we only have a single #p{id} fragment.
     */
    private function normalizeDuplicateFragments(string $url): string
    {
        if (preg_match('/(#p\d+)/', $url, $m)) {
            $basePart = preg_replace('/#.*/', '', $url);
            return $basePart . $m[1];
        }
        return $url;
    }

    /**
     * Rebuilds the query string for non-routing parameters.
     */
    private function buildQueryString(array $params, array $excludeKeys, bool $isAmp): string
    {
        $filtered = [];
        foreach ($params as $key => $value) {
            if (!in_array($key, $excludeKeys, true)) {
                $filtered[$key] = $value;
            }
        }
        if (empty($filtered)) {
            return '';
        }

        $queryString = http_build_query($filtered, '', '&');
        if ($isAmp) {
            $queryString = str_replace('&', '&amp;', $queryString);
        }
        return '?' . $queryString;
    }

    /**
     * Resolves the dynamically parsed board base path (e.g. "/phpbb/" or "/").
     */
    public function getBoardPath(): string
    {
        if ($this->boardPath === null) {
            $path = (string) parse_url(generate_board_url(), PHP_URL_PATH);
            $this->boardPath = rtrim($path, '/') . '/';
        }
        return $this->boardPath;
    }
}
