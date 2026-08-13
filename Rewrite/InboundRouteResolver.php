<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

/**
 * Resolves inbound pretty-URL paths to Framework route results.
 * Matched by ID only — slug is descriptive, not identity.
 */
class InboundRouteResolver
{
    public function __construct(
        private readonly PermalinkRewriteProfile $profile
    ) {}

    public function resolve(string $path): ?InboundRouteResult
    {
        // Decode percent-encoding once for Unicode slugs (e.g. Persian/Arabic/Chinese)
        $path = rawurldecode($path);

        // Normalize board path prefix (e.g. "/phpbb/group/..." -> "/group/...")
        $scriptPath = (string) parse_url(generate_board_url(), PHP_URL_PATH);
        $boardPath = '/' . trim($scriptPath, '/');
        if ($boardPath !== '/' && $boardPath !== '' && str_starts_with($path, $boardPath)) {
            $path = '/' . ltrim(substr($path, strlen($boardPath)), '/');
        }

        // Try topic (paginated first, then base)
        $match = $this->profile->matchTopic($path);
        if ($match !== null) {
            return new InboundRouteResult(
                resource: 'topic',
                id:       $match['id'],
                slug:     $match['slug'],
                page:     $match['page'] ?? null
            );
        }

        // Try forum (paginated first, then base)
        $match = $this->profile->matchForum($path);
        if ($match !== null) {
            return new InboundRouteResult(
                resource: 'forum',
                id:       $match['id'],
                slug:     $match['slug'],
                page:     $match['page'] ?? null
            );
        }

        // Try member
        $match = $this->profile->matchMember($path);
        if ($match !== null) {
            return new InboundRouteResult(
                resource: 'member',
                id:       $match['id'],
                slug:     $match['slug'],
                page:     null
            );
        }

        // Try group
        $match = $this->profile->matchGroup($path);
        if ($match !== null) {
            return new InboundRouteResult(
                resource: 'group',
                id:       $match['id'],
                slug:     $match['slug'],
                page:     null
            );
        }

        return null;
    }
}
