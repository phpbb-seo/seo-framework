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
            $targetUrl = $canonicalUrl;
            if (!empty($context->query)) {
                $cleanQuery = str_replace('&amp;', '&', $context->query);
                parse_str($cleanQuery, $queryParams);
                $excludeParams = ['t', 'f', 'p', 'u', 'g', 'start', 'sid', 'mode', 'seo_page'];
                foreach ($excludeParams as $ep) {
                    unset($queryParams[$ep]);
                    unset($queryParams['amp;' . $ep]);
                }
                if (!empty($queryParams)) {
                    $targetUrl .= (str_contains($targetUrl, '?') ? '&' : '?') . http_build_query($queryParams);
                }
            }

            $normalizedTarget = $validator->normalizeUrl($targetUrl);
            if ($normalizedTarget === $normalizedCurrent) {
                return null;
            }

            return new RedirectDecision($targetUrl, 301, RedirectReason::CANONICAL_MISMATCH);
        }

        return null;
    }
}
