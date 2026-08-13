<?php
declare(strict_types=1);

namespace phpbbseo\framework\Redirect;

use phpbbseo\framework\Context\RequestContext;

/**
 * Determines if the current request should be redirected.
 */
class RedirectResolver
{
    public function resolve(RequestContext $context, ?string $canonicalUrl, UrlSafetyValidator $validator): ?RedirectDecision
    {
        if ($canonicalUrl === null) {
            return null;
        }

        if (!$validator->isSafe($canonicalUrl)) {
            return null; // Do not redirect to an unsafe canonical URL
        }

        $currentUrl = sprintf(
            '%s://%s/%s%s',
            $context->scheme,
            $context->host,
            ltrim($context->path, '/'),
            empty($context->query) ? '' : '?' . $context->query
        );

        $normalizedCurrent = $validator->normalizeUrl($currentUrl);
        $normalizedCanonical = $validator->normalizeUrl($canonicalUrl);

        if ($normalizedCurrent !== $normalizedCanonical) {
            return new RedirectDecision($canonicalUrl, 301, RedirectReason::CANONICAL_MISMATCH);
        }

        return null;
    }
}
