<?php
declare(strict_types=1);

namespace phpbbseo\framework\Sitemap;

use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbb\config\config;

/**
 * Single authoritative absolute URL generator for sitemap resources.
 * Consumes the frozen Shared Core PermalinkRewriteProfile without duplication.
 */
class SitemapUrlGenerator
{
    private string $boardUrl;

    public function __construct(
        private readonly PermalinkRewriteProfile $rewriteProfile,
        private readonly config $config,
        private readonly string $phpbbRootPath
    ) {
        $this->boardUrl = rtrim(generate_board_url(), '/');
    }

    public function getBoardUrl(): string
    {
        return $this->boardUrl . '/';
    }

    public function generateForumUrl(int $forumId, string $slug): string
    {
        $path = $this->rewriteProfile->generateForumUrlWithSlug($forumId, $slug);
        return $this->boardUrl . '/' . ltrim($path, '/');
    }

    public function generateTopicUrl(int $topicId, string $slug): string
    {
        $path = $this->rewriteProfile->generateTopicUrlWithSlug($topicId, $slug);
        return $this->boardUrl . '/' . ltrim($path, '/');
    }

    public function getXslUrl(): string
    {
        return $this->boardUrl . '/sitemap.xsl';
    }
}
