<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Canonical;

use PHPUnit\Framework\TestCase;
use phpbb\config\config;
use phpbbseo\framework\Canonical\CanonicalResolver;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Context\RequestContext;
use phpbbseo\framework\Migration\UsuMigrationResolver;
use phpbbseo\framework\Rewrite\InboundRouteResolver;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Url\SlugOptions;

class CanonicalResolverTest extends TestCase
{
    private ConfigurationProvider $configProvider;
    private EntitySeoContext $entityContext;
    private PermalinkRewriteProfile $permalinkProfile;
    private InboundRouteResolver $inboundResolver;
    private PaginationResolver $paginationResolver;
    private CanonicalResolver $resolver;

    protected function setUp(): void
    {
        unset($GLOBALS['topic_id'], $GLOBALS['start'], $GLOBALS['post_id']);

        $config = new config([
            'phpbbseo_framework_enable'   => '1',
            'seo_rewrite_enabled'         => '1',
            'phpbbseo_legacy_usu_enabled' => '1',
            'seo_permalink_preset'        => 'modern',
            'posts_per_page'              => '20',
            'topics_per_page'             => '50',
        ]);
        $this->configProvider = new ConfigurationProvider($config);
        $this->paginationResolver = new PaginationResolver();
        $this->entityContext = new EntitySeoContext();

        $permalinkConfig = new PermalinkConfiguration($this->configProvider);
        $compiler = new UrlPatternCompiler();
        $slugGenerator = new DefaultSlugGenerator(new SlugOptions());

        $this->permalinkProfile = new PermalinkRewriteProfile(
            $permalinkConfig,
            $compiler,
            $this->entityContext,
            $slugGenerator,
            $this->paginationResolver,
            $this->configProvider
        );

        $usuResolver = new UsuMigrationResolver(
            $this->paginationResolver,
            $this->configProvider
        );

        $this->inboundResolver = new InboundRouteResolver(
            $this->permalinkProfile,
            $usuResolver
        );

        $this->resolver = new CanonicalResolver(
            $this->permalinkProfile,
            $this->inboundResolver,
            $this->configProvider,
            $this->paginationResolver
        );

        // Preload standard topic
        $this->entityContext->setTopicTitle(100, 'multi-page-test-topic');
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['topic_id'], $GLOBALS['start'], $GLOBALS['post_id']);
    }

    /**
     * BUG 1: Safe start resolution with entity-encoded query string and $GLOBALS['start'] = 0.
     */
    public function testSafeStartOffsetEntityEncodedQueryString(): void
    {
        $GLOBALS['start'] = 0;
        $GLOBALS['topic_id'] = 0;

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            't=100&amp;start=20',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/page/2/', $canonicalUrl);
    }

    /**
     * BUG 1: Global start fallback when query string omits start.
     */
    public function testGlobalsStartFallbackWhenQueryStringMissing(): void
    {
        $GLOBALS['start'] = 20;
        $GLOBALS['topic_id'] = 100;

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            't=100',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/page/2/', $canonicalUrl);
    }

    /**
     * BUG 2: $GLOBALS['topic_id'] = 0 does not block query string 't'.
     */
    public function testTopicIdZeroInGlobalsFallsBackToQueryString(): void
    {
        $GLOBALS['topic_id'] = 0;

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            't=100',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/', $canonicalUrl);
    }

    /**
     * BUG 2: Post ID resolves to topic ID and calculates correct page from prev_posts.
     */
    public function testPostIdResolvesToTopicAndCalculatesCorrectPage(): void
    {
        $GLOBALS['topic_id'] = 0;
        $GLOBALS['start'] = 0;

        // Post 147 is on page 2 (prev_posts = 25, posts_per_page = 20 -> start = 20 -> page 2)
        $this->entityContext->setPostPosition(147, 100, 25);

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            'p=147',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/page/2/#p147', $canonicalUrl);
    }

    /**
     * BUG 2: Post on page 1 resolves to base topic URL (no /page/1/) with #p anchor.
     */
    public function testPostIdOnFirstPageEmitsBaseTopicUrlWithAnchor(): void
    {
        $GLOBALS['topic_id'] = 0;
        $GLOBALS['start'] = 0;

        // Post 137 is on page 1 (prev_posts = 0)
        $this->entityContext->setPostPosition(137, 100, 0);

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            'p=137',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/#p137', $canonicalUrl);
    }

    /**
     * BUG 2: Non-existent post ID returns null, never emits fabricated '-0/' URL.
     */
    public function testNonExistentPostIdReturnsNullNeverEmitsZero(): void
    {
        $GLOBALS['topic_id'] = 0;
        $GLOBALS['start'] = 0;

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            'p=999999',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNull($canonicalUrl);
    }

    /**
     * Post position takes precedence over explicit start when post ID is resolvable.
     * Prevents landing on the wrong page where anchor #p{id} cannot be found.
     */
    public function testPostPositionOverridesExplicitStartWhenPostResolvable(): void
    {
        $this->entityContext->setPostPosition(147, 100, 25);

        // Post 147 is on page 2 (prev_posts = 25, posts_per_page = 20 -> offset 20)
        // Request passes a conflicting start=40 (which would be page 3)
        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            't=100&p=147&start=40',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        // Must resolve to page 2 where post 147 is located, NOT page 3
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/page/2/#p147', $canonicalUrl);
    }

    /**
     * Conflicting start parameter is ignored in favor of the post's actual page.
     */
    public function testConflictingStartIgnoredInFavorOfPostPage(): void
    {
        // Post 157 is on page 1 (prev_posts = 5, posts_per_page = 20 -> offset 0)
        $this->entityContext->setPostPosition(157, 100, 5);

        // Stale start=60 in query string
        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            'p=157&start=60',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        // Lands on page 1 (base topic URL) with anchor #p157
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/#p157', $canonicalUrl);
    }

    /**
     * Pure topic pagination without post ID still honors explicit start parameter.
     */
    public function testPureTopicPaginationHonorsExplicitStart(): void
    {
        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/viewtopic.php',
            't=100&start=40',
            'viewtopic'
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/page/3/', $canonicalUrl);
    }

    /**
     * Inbound legacy USU post route resolves with correct pagination.
     */
    public function testInboundLegacyUsuPostResolvesWithPagination(): void
    {
        $this->entityContext->setPostPosition(147, 100, 25);

        $context = new RequestContext(
            'https',
            'localhost',
            '/phpbb/post147.html',
            '',
            ''
        );

        $canonicalUrl = $this->resolver->resolve($context);
        $this->assertNotNull($canonicalUrl);
        $this->assertSame('https://localhost/phpbb/topic/multi-page-test-topic-100/page/2/#p147', $canonicalUrl);
    }
}
