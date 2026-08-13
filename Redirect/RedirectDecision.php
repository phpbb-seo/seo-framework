<?php
declare(strict_types=1);

namespace phpbbseo\framework\Redirect;

/**
 * Represents the final decision on whether and how to redirect.
 */
class RedirectDecision
{
    public function __construct(
        public readonly string $targetUrl,
        public readonly int $statusCode = 301,
        public readonly RedirectReason $reason = RedirectReason::CANONICAL_MISMATCH
    ) {}
}
