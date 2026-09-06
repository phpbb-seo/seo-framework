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
    private function createValidator(string $serverName = 'example.com'): UrlSafetyValidator
    {
        $config = new \phpbb\config\config(['server_name' => $serverName]);
        $provider = new \phpbbseo\framework\Configuration\ConfigurationProvider($config);
        return new UrlSafetyValidator($provider);
    }

    public function testRedirectLoopPrevention(): void
    {
        $resolver = new RedirectResolver();
        $validator = $this->createValidator('example.com');
        
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
        $validator = $this->createValidator('example.com');
        
        $context = new RequestContext('http', 'example.com', '/path', '', 'route', null, null);
        
        $canonical = 'https://example.com/path';
        
        $decision = $resolver->resolve($context, $canonical, $validator);
        
        $this->assertNotNull($decision);
        $this->assertSame($canonical, $decision->targetUrl);
        $this->assertSame(301, $decision->statusCode);
        $this->assertSame(RedirectReason::CANONICAL_MISMATCH, $decision->reason);
    }

    public function testStripEntityEncodedRoutingParameters(): void
    {
        $resolver = new RedirectResolver();
        $validator = $this->createValidator('example.com');

        // Test with doubly entity-encoded start offset
        $contextDouble = new RequestContext('http', 'example.com', '/viewtopic.php', 't=100&amp;amp;start=20', 'viewtopic', 100, 3);
        $canonical = 'https://example.com/topic/slug-100/page/3/';
        $decisionDouble = $resolver->resolve($contextDouble, $canonical, $validator);

        $this->assertNotNull($decisionDouble);
        $this->assertSame('https://example.com/topic/slug-100/page/3/', $decisionDouble->targetUrl);

        // Test with single entity-encoded start offset
        $contextSingle = new RequestContext('http', 'example.com', '/viewtopic.php', 't=100&amp;start=20', 'viewtopic', 100, 3);
        $decisionSingle = $resolver->resolve($contextSingle, $canonical, $validator);

        $this->assertNotNull($decisionSingle);
        $this->assertSame('https://example.com/topic/slug-100/page/3/', $decisionSingle->targetUrl);
    }

    public function testPreserveNonRoutingTrackingParametersWithEntityEncodedRouting(): void
    {
        $resolver = new RedirectResolver();
        $validator = $this->createValidator('example.com');

        $context = new RequestContext('http', 'example.com', '/viewtopic.php', 't=100&amp;amp;start=20&utm_source=test&gclid=xyz123', 'viewtopic', 100, 3);
        $canonical = 'https://example.com/topic/slug-100/page/3/';
        $decision = $resolver->resolve($context, $canonical, $validator);

        $this->assertNotNull($decision);
        $this->assertSame('https://example.com/topic/slug-100/page/3/?utm_source=test&gclid=xyz123', $decision->targetUrl);
    }

    public function testPreserveFragmentWithQueryParameters(): void
    {
        $resolver = new RedirectResolver();
        $validator = $this->createValidator('example.com');

        $context = new RequestContext('http', 'example.com', '/viewtopic.php', 't=100&amp;start=20&utm_source=test', 'viewtopic', 100, 3);
        $canonical = 'https://example.com/topic/slug-100/page/3/#p124';
        $decision = $resolver->resolve($context, $canonical, $validator);

        $this->assertNotNull($decision);
        $this->assertSame('https://example.com/topic/slug-100/page/3/?utm_source=test#p124', $decision->targetUrl);
    }
}
