<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Url\SlugGeneratorInterface;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Configuration\ConfigurationProvider;

/**
 * Composes pattern-based URL generation and inbound matching for all supported resource types.
 * Driven entirely by PermalinkConfiguration and UrlPatternCompiler — no hardcoded URL structures.
 * All permalink presets (modern, compact, classic, custom) use this same class.
 */
class PermalinkRewriteProfile
{
    private CompiledUrlPattern $forumPattern;
    private CompiledUrlPattern $forumPagePattern;
    private CompiledUrlPattern $forumPageAltPattern;
    private CompiledUrlPattern $topicPattern;
    private CompiledUrlPattern $topicPagePattern;
    private CompiledUrlPattern $topicPageAltPattern;
    private CompiledUrlPattern $memberPattern;
    private CompiledUrlPattern $groupPattern;

    public function __construct(
        private readonly PermalinkConfiguration $permalinkConfig,
        private readonly UrlPatternCompiler $compiler,
        private readonly EntitySeoContext $entityContext,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly PaginationResolver $paginationResolver,
        private readonly ConfigurationProvider $configProvider
    ) {
        $this->compilePatterns();
    }

    private function compilePatterns(): void
    {
        $this->forumPattern     = $this->compiler->compile($this->permalinkConfig->getPattern('forum'), ['id']);
        $this->topicPattern     = $this->compiler->compile($this->permalinkConfig->getPattern('topic'), ['id']);
        $this->memberPattern    = $this->compiler->compile($this->permalinkConfig->getPattern('member'), ['id']);
        $this->groupPattern     = $this->compiler->compile($this->permalinkConfig->getPattern('group'), ['id']);

        $forumPageRaw = $this->permalinkConfig->getPattern('forum_page');
        $this->forumPagePattern = $this->compiler->compile($forumPageRaw, ['id', 'page']);
        $forumAltRaw = str_contains($forumPageRaw, 'page/{page}')
            ? str_replace('page/{page}', 'page-{page}', $forumPageRaw)
            : str_replace('page-{page}', 'page/{page}', $forumPageRaw);
        $this->forumPageAltPattern = $this->compiler->compile($forumAltRaw, ['id', 'page']);

        $topicPageRaw = $this->permalinkConfig->getPattern('topic_page');
        $this->topicPagePattern = $this->compiler->compile($topicPageRaw, ['id', 'page']);
        $topicAltRaw = str_contains($topicPageRaw, 'page/{page}')
            ? str_replace('page/{page}', 'page-{page}', $topicPageRaw)
            : str_replace('page-{page}', 'page/{page}', $topicPageRaw);
        $this->topicPageAltPattern = $this->compiler->compile($topicAltRaw, ['id', 'page']);
    }

    /**
     * Generate a forum URL, or return null if entity data is unavailable.
     */
    public function generateForumUrl(int $forumId): ?string
    {
        $name = $this->entityContext->getForumName($forumId);
        if ($name === null) {
            return null; // No placeholder slug — return null, caller keeps native URL
        }
        return $this->forumPattern->generate([
            'id'   => $forumId,
            'slug' => $this->slugGenerator->generate($name),
        ]);
    }

    /**
     * Generate a forum URL using an authoritative slug directly.
     */
    public function generateForumUrlWithSlug(int $forumId, string $slug): string
    {
        return $this->forumPattern->generate([
            'id'   => $forumId,
            'slug' => $this->slugGenerator->generate($slug),
        ]);
    }

    /**
     * Generate a topic URL using an authoritative slug directly.
     */
    public function generateTopicUrlWithSlug(int $topicId, string $slug): string
    {
        return $this->topicPattern->generate([
            'id'   => $topicId,
            'slug' => $this->slugGenerator->generate($slug),
        ]);
    }

    /**
     * Generate a forum URL with pagination, or null if entity data is unavailable.
     * Page 1 returns the base forum URL (no page segment).
     */
    public function generateForumPageUrl(int $forumId, int $start, int $topicsPerPage): ?string
    {
        $name = $this->entityContext->getForumName($forumId);
        if ($name === null) {
            return null;
        }

        $page = $this->paginationResolver->startToPage($start, $topicsPerPage);
        $slug = $this->slugGenerator->generate($name);

        // Page 1 → canonical base URL
        if ($page === null) {
            return $this->forumPattern->generate(['id' => $forumId, 'slug' => $slug]);
        }

        return $this->forumPagePattern->generate([
            'id'   => $forumId,
            'slug' => $slug,
            'page' => $page,
        ]);
    }

    /**
     * Generate a topic URL (no pagination), or null if entity data is unavailable.
     */
    public function generateTopicUrl(int $topicId): ?string
    {
        $title = $this->entityContext->getTopicTitle($topicId);
        if ($title === null) {
            return null;
        }
        return $this->topicPattern->generate([
            'id'   => $topicId,
            'slug' => $this->slugGenerator->generate($title),
        ]);
    }

    /**
     * Generate a topic URL with pagination, or null if entity data is unavailable.
     * Page 1 returns the base topic URL (no page segment).
     */
    public function generateTopicPageUrl(int $topicId, int $start, int $postsPerPage): ?string
    {
        $title = $this->entityContext->getTopicTitle($topicId);
        if ($title === null) {
            return null;
        }

        $page = $this->paginationResolver->startToPage($start, $postsPerPage);
        $slug = $this->slugGenerator->generate($title);

        // Page 1 → canonical base URL
        if ($page === null) {
            return $this->topicPattern->generate(['id' => $topicId, 'slug' => $slug]);
        }

        return $this->topicPagePattern->generate([
            'id'   => $topicId,
            'slug' => $slug,
            'page' => $page,
        ]);
    }

    public function generateMemberUrl(int $userId): ?string
    {
        $username = $this->entityContext->getUsername($userId);
        if ($username === null) {
            return null;
        }
        return $this->memberPattern->generate([
            'id'   => $userId,
            'slug' => $this->slugGenerator->generate($username),
        ]);
    }

    public function generateGroupUrl(int $groupId): ?string
    {
        $groupName = $this->entityContext->getGroupName($groupId);
        if ($groupName === null) {
            return null;
        }
        return $this->groupPattern->generate([
            'id'   => $groupId,
            'slug' => $this->slugGenerator->generate($groupName),
        ]);
    }

    /**
     * Getter for entity context (used by central resolver).
     */
    public function getEntityContext(): EntitySeoContext
    {
        return $this->entityContext;
    }

    /**
     * Attempt to match an inbound path to a topic.
     * Returns ['id' => int, 'slug' => string, 'page' => ?int] or null.
     *
     * @return array<string, mixed>|null
     */
    public function matchTopic(string $path): ?array
    {
        // Try paginated first (primary & alternate styles)
        $result = $this->topicPagePattern->match($path) ?? $this->topicPageAltPattern->match($path);
        if ($result !== null) {
            return [
                'id'   => (int) $result['id'],
                'slug' => $result['slug'] ?? '',
                'page' => (int) $result['page'],
            ];
        }

        $result = $this->topicPattern->match($path);
        if ($result !== null) {
            return [
                'id'   => (int) $result['id'],
                'slug' => $result['slug'] ?? '',
                'page' => null,
            ];
        }

        return null;
    }

    public function matchForum(string $path): ?array
    {
        // Try paginated first (primary & alternate styles)
        $result = $this->forumPagePattern->match($path) ?? $this->forumPageAltPattern->match($path);
        if ($result !== null) {
            return [
                'id'   => (int) $result['id'],
                'slug' => $result['slug'] ?? '',
                'page' => (int) $result['page'],
            ];
        }

        $result = $this->forumPattern->match($path);
        if ($result !== null) {
            return [
                'id'   => (int) $result['id'],
                'slug' => $result['slug'] ?? '',
                'page' => null,
            ];
        }
        return null;
    }

    /**
     * Attempt to match an inbound path to a member profile.
     *
     * @return array<string, mixed>|null
     */
    public function matchMember(string $path): ?array
    {
        $result = $this->memberPattern->match($path);
        if ($result !== null) {
            return [
                'id'   => (int) $result['id'],
                'slug' => $result['slug'] ?? '',
            ];
        }
        return null;
    }

    /**
     * Attempt to match an inbound path to a group profile.
     *
     * @return array<string, mixed>|null
     */
    public function matchGroup(string $path): ?array
    {
        $result = $this->groupPattern->match($path);
        if ($result !== null) {
            return [
                'id'   => (int) $result['id'],
                'slug' => $result['slug'] ?? '',
            ];
        }
        return null;
    }

    /**
     * Detect if the slug in an inbound URL is stale compared to the current entity title.
     * Returns the canonical (current) URL if stale, null if still fresh.
     */
    public function detectStaleTopic(int $topicId, string $inboundSlug): ?string
    {
        $title = $this->entityContext->getTopicTitle($topicId);
        if ($title === null) {
            return null; // Can't determine staleness without entity data
        }

        $canonicalSlug = $this->slugGenerator->generate($title);
        if ($canonicalSlug === $inboundSlug) {
            return null; // Slug is fresh
        }

        // Slug is stale — return canonical URL for redirect
        return $this->topicPattern->generate(['id' => $topicId, 'slug' => $canonicalSlug]);
    }

    public function detectStaleForum(int $forumId, string $inboundSlug): ?string
    {
        $name = $this->entityContext->getForumName($forumId);
        if ($name === null) {
            return null;
        }
        $canonicalSlug = $this->slugGenerator->generate($name);
        if ($canonicalSlug === $inboundSlug) {
            return null;
        }
        return $this->forumPattern->generate(['id' => $forumId, 'slug' => $canonicalSlug]);
    }

    public function detectStaleMember(int $userId, string $inboundSlug): ?string
    {
        $username = $this->entityContext->getUsername($userId);
        if ($username === null) {
            return null;
        }
        $canonicalSlug = $this->slugGenerator->generate($username);
        if ($canonicalSlug === $inboundSlug) {
            return null;
        }
        return $this->memberPattern->generate(['id' => $userId, 'slug' => $canonicalSlug]);
    }

    public function detectStaleGroup(int $groupId, string $inboundSlug): ?string
    {
        $name = $this->entityContext->getGroupName($groupId);
        if ($name === null) {
            return null;
        }
        $canonicalSlug = $this->slugGenerator->generate($name);
        if ($canonicalSlug === $inboundSlug) {
            return null;
        }
        return $this->groupPattern->generate(['id' => $groupId, 'slug' => $canonicalSlug]);
    }

    /** Access compiled patterns for conflict detection. */
    public function getCompiledForumPattern(): CompiledUrlPattern { return $this->forumPattern; }
    public function getCompiledForumPagePattern(): CompiledUrlPattern { return $this->forumPagePattern; }
    public function getCompiledTopicPattern(): CompiledUrlPattern { return $this->topicPattern; }
    public function getCompiledTopicPagePattern(): CompiledUrlPattern { return $this->topicPagePattern; }
    public function getCompiledMemberPattern(): CompiledUrlPattern { return $this->memberPattern; }
    public function getCompiledGroupPattern(): CompiledUrlPattern { return $this->groupPattern; }
}
