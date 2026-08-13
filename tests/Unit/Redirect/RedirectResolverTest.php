<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Redirect;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Redirect\RedirectResolver;
use phpbbseo\framework\Redirect\UrlSafetyValidator;
use phpbbseo\framework\Context\RequestContext;
use phpbbseo\framework\Redirect\RedirectDecision;
use phpbbseo\framework\Redirect\RedirectReason;

class RedirectResolverTest extends TestCase
{
    public function testRedirectLoopPrevention(): void
    {
        $resolver = new RedirectResolver();
        $validator = new UrlSafetyValidator('example.com');
        
        $context = new RequestContext('https', 'example.com', '/path', '', 'route', null, null);
        
        // Current normalized: https://example.com/path
        // Canonical given: HTTP://EXAMPLE.com:443/path/../path (if normalizer handles .. we would, but our basic normalizer just handles case)
        
        $canonical = 'HTTPS://EXAMPLE.COM/path';
        
        $decision = $resolver->resolve($context, $canonical, $validator);
        
        $this->assertNull($decision, 'Should not redirect if normalized URLs match (loop prevention)');
    }

    public function testValidCanonicalMismatchRedirect(): void
    {
        $resolver = new RedirectResolver();
        $validator = new UrlSafetyValidator('example.com');
        
        $context = new RequestContext('http', 'example.com', '/path', '', 'route', null, null);
        
        $canonical = 'https://example.com/path';
        
        $decision = $resolver->resolve($context, $canonical, $validator);
        
        $this->assertNotNull($decision);
        $this->assertSame($canonical, $decision->targetUrl);
        $this->assertSame(301, $decision->statusCode);
        $this->assertSame(RedirectReason::CANONICAL_MISMATCH, $decision->reason);
    }
}
