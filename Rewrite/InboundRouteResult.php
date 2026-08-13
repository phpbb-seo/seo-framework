<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

/**
 * Holds the result of an inbound route match.
 */
class InboundRouteResult
{
    public function __construct(
        public readonly string $resource,     // 'topic', 'forum', 'member'
        public readonly int    $id,
        public readonly string $slug,
        public readonly ?int   $page = null   // null means page 1
    ) {}
}
