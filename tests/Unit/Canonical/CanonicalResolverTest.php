<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Canonical;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Canonical\CanonicalResolver;
use phpbbseo\framework\Context\RequestContext;
use phpbbseo\framework\Url\RouteResolver;
use phpbbseo\framework\Url\UrlResult;

class CanonicalResolverTest extends TestCase
{
    public function testCanonicalResolutionConstructsAbsoluteUrl(): void
    {
        $routeResolver = $this->createMock(RouteResolver::class);
        $routeResolver->method('resolve')->willReturn(new UrlResult('/topic/5-test', ['page' => 2]));

        $resolver = new CanonicalResolver($routeResolver);

        $context = new RequestContext(
            'https',
            'example.com',
            '/viewtopic.php',
            't=5&start=10',
            'viewtopic',
            5,
            10
        );

        $canonicalUrl = $resolver->resolve($context);

        $this->assertSame('https://example.com/topic/5-test?page=2', $canonicalUrl);
    }
}
