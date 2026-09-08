<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Rewrite;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Rewrite\InboundRouteResolver;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbbseo\framework\Rewrite\PublicResourceUrlResolver;
use phpbbseo\framework\Rewrite\ResourceDetector;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Url\SlugOptions;

class PublicResourceUrlResolverTest extends TestCase
{
    private EntitySeoContext $entityContext;
    private PublicResourceUrlResolver $resolver;
    private InboundRouteResolver $inboundResolver;

    protected function setUp(): void
    {
        $config = $this->createMock(ConfigurationProvider::class);
        $config->method('isRewriteEnabled')->willReturn(true);
        $config->method('get')->willReturnMap([
            ['seo_permalink_preset', 'modern', 'modern'],
            ['posts_per_page', '20', '20'],
            ['topics_per_page', '25', '25'],
        ]);

        $this->entityContext = new EntitySeoContext();
        $this->entityContext->setTopics([1715 => 'Ruben van Bommel']);
        $this->entityContext->setForums([2 => 'Algemeen']);
        $this->entityContext->setMembers([42 => 'TestUser']);

        $paginator = new PaginationResolver();
        $permalinkConfig = new PermalinkConfiguration($config);
        $compiler = new UrlPatternCompiler();
        $slugGenerator = new DefaultSlugGenerator(new SlugOptions());

        $profile = new PermalinkRewriteProfile(
            $permalinkConfig,
            $compiler,
            $this->entityContext,
            $slugGenerator,
            $paginator,
            $config
        );

        $detector = new ResourceDetector($config);
        $this->resolver = new PublicResourceUrlResolver($detector, $profile, $config);
        $this->inboundResolver = new InboundRouteResolver($profile);
    }

    /**
     * B3.a) view=unread (the reported case)
     * Must introduce leftover parameter with '?', not '&'.
     */
    public function testViewUnreadParameterGetsQuestionMark(): void
    {
        $base = $this->resolver->getBoardPath();
        $url = $this->resolver->resolve('viewtopic.php', 't=1715&view=unread');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread', $url);
    }

    /**
     * B3.b) Leftover parameter combined with routing parameters that get stripped.
     * Confirm t and start are converted to the SEO path AND view=unread is appended with '?', not '&'.
     */
    public function testCombinedRoutingAndLeftoverParameter(): void
    {
        $base = $this->resolver->getBoardPath();
        $url = $this->resolver->resolve('viewtopic.php', 't=1715&start=20&view=unread');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/page/2/?view=unread', $url);
    }

    /**
     * B3.c) Two or more leftover non-routing parameters together.
     * Confirm the first gets '?' and subsequent ones get '&amp;' (or '&').
     */
    public function testMultipleLeftoverParameters(): void
    {
        $base = $this->resolver->getBoardPath();
        $urlAmp = $this->resolver->resolve('viewtopic.php', 't=1715&view=unread&highlight=test', true);
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread&amp;highlight=test', $urlAmp);

        $urlNoAmp = $this->resolver->resolve('viewtopic.php', 't=1715&view=unread&highlight=test', false);
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread&highlight=test', $urlNoAmp);
    }

    /**
     * B3.d) Leftover parameter combined with URL that ALSO has a fragment anchor.
     * Confirm ordering: path, then '?' + query params, then '#' + fragment.
     */
    public function testLeftoverParameterWithAnchorFragment(): void
    {
        $base = $this->resolver->getBoardPath();
        $url = $this->resolver->resolve('viewtopic.php', 't=1715&view=unread#unread');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread#unread', $url);
    }

    /**
     * B3.e) Normal case with NO leftover parameters at all.
     * Confirm clean URL with no stray '?' or '&'.
     */
    public function testPlainUrlWithoutLeftoverParameters(): void
    {
        $base = $this->resolver->getBoardPath();
        $topicUrl = $this->resolver->resolve('viewtopic.php', 't=1715');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/', $topicUrl);

        $forumUrl = $this->resolver->resolve('viewforum.php', 'f=2');
        $this->assertSame($base . 'forum/algemeen-2/', $forumUrl);

        $memberUrl = $this->resolver->resolve('memberlist.php', 'mode=viewprofile&u=42');
        $this->assertSame($base . 'member/testuser-42/', $memberUrl);
    }

    /**
     * Test healing of malformed input URLs where '&' or '&amp;' was naively appended.
     */
    public function testMalformedQuerySeparatorHealing(): void
    {
        $base = $this->resolver->getBoardPath();
        $healedAmp = $this->resolver->resolve('/topic/ruben-van-bommel-1715/&amp;view=unread#unread');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread#unread', $healedAmp);

        $healedDirect = $this->resolver->resolve('/topic/ruben-van-bommel-1715/&view=unread#unread');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread#unread', $healedDirect);

        $healedParam = $this->resolver->resolve('/topic/ruben-van-bommel-1715/', 'view=unread#unread');
        $this->assertSame($base . 'topic/ruben-van-bommel-1715/?view=unread#unread', $healedParam);
    }

    /**
     * Test inbound route resolution matches gracefully even if URL contains malformed /&view=unread.
     */
    public function testInboundRouteResolutionWithMalformedAmpersand(): void
    {
        $route = $this->inboundResolver->resolve('/topic/ruben-van-bommel-1715/&view=unread');
        $this->assertNotNull($route);
        $this->assertSame('topic', $route->resource);
        $this->assertSame(1715, $route->id);

        $routeAmp = $this->inboundResolver->resolve('/topic/ruben-van-bommel-1715/&amp;view=unread');
        $this->assertNotNull($routeAmp);
        $this->assertSame('topic', $routeAmp->resource);
        $this->assertSame(1715, $routeAmp->id);
    }
}
