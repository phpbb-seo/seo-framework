<?php
declare(strict_types=1);

namespace phpbbseo\framework\Context;

/**
 * Immutable request context representing SEO-relevant request information.
 */
class RequestContext
{
    public function __construct(
        public readonly string $scheme,
        public readonly string $host,
        public readonly string $path,
        public readonly string $query,
        public readonly string $route,
        public readonly ?int $entityId,
        public readonly ?int $pagination
    ) {}
}
