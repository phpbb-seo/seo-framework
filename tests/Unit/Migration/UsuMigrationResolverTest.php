<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use phpbb\config\config;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Migration\UsuMigrationResolver;
use phpbbseo\framework\Url\PaginationResolver;

class UsuMigrationResolverTest extends TestCase
{
    private ConfigurationProvider $configProvider;
    private PaginationResolver $paginationResolver;
    private config $config;

    protected function setUp(): void
    {
        $this->config = new config([
            'phpbbseo_framework_enable'   => '1',
            'seo_rewrite_enabled'         => '1',
            'phpbbseo_legacy_usu_enabled' => '1',
            'posts_per_page'              => '20',
            'topics_per_page'             => '50',
        ]);
        $this->configProvider = new ConfigurationProvider($this->config);
        $this->paginationResolver = new PaginationResolver();
    }

    private function createResolver(): UsuMigrationResolver
    {
        return new UsuMigrationResolver(
            $this->paginationResolver,
            $this->configProvider
        );
    }

    public function testStandardTopicResolution(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('my-first-topic-t123.html');
        $this->assertNotNull($result);
        $this->assertSame('topic', $result->resource);
        $this->assertSame(123, $result->id);
        $this->assertSame('my-first-topic', $result->slug);
        $this->assertNull($result->page);
    }

    public function testSimpleTopicFormats(): void
    {
        $resolver = $this->createResolver();

        $tResult = $resolver->resolve('t456.html');
        $this->assertNotNull($tResult);
        $this->assertSame('topic', $tResult->resource);
        $this->assertSame(456, $tResult->id);

        $topicResult = $resolver->resolve('topic789.html');
        $this->assertNotNull($topicResult);
        $this->assertSame('topic', $topicResult->resource);
        $this->assertSame(789, $topicResult->id);
    }

    public function testNestedDirectoryTopicResolution(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('category/sub-forum/deep-discussion-t999.html');
        $this->assertNotNull($result);
        $this->assertSame('topic', $result->resource);
        $this->assertSame(999, $result->id);
        $this->assertSame('deep-discussion', $result->slug);
    }

    public function testPaginatedTopicResolution(): void
    {
        $resolver = $this->createResolver();

        // Start 20 with 20 posts per page = Page 2
        $result1 = $resolver->resolve('my-discussion-t123-20.html');
        $this->assertNotNull($result1);
        $this->assertSame('topic', $result1->resource);
        $this->assertSame(123, $result1->id);
        $this->assertSame(2, $result1->page);

        // Start with -s20 prefix
        $result2 = $resolver->resolve('my-discussion-t123-s20.html');
        $this->assertNotNull($result2);
        $this->assertSame('topic', $result2->resource);
        $this->assertSame(123, $result2->id);
        $this->assertSame(2, $result2->page);

        // Start 40 with 20 posts per page = Page 3
        $result3 = $resolver->resolve('topic123-40.html');
        $this->assertNotNull($result3);
        $this->assertSame(3, $result3->page);
    }

    public function testStandardForumResolution(): void
    {
        $resolver = $this->createResolver();

        $result = $resolver->resolve('general-discussion-f10.html');
        $this->assertNotNull($result);
        $this->assertSame('forum', $result->resource);
        $this->assertSame(10, $result->id);
        $this->assertSame('general-discussion', $result->slug);
        $this->assertNull($result->page);
    }

    public function testSimpleForumFormats(): void
    {
        $resolver = $this->createResolver();

        $fResult = $resolver->resolve('f2.html');
        $this->assertNotNull($fResult);
        $this->assertSame('forum', $fResult->resource);
        $this->assertSame(2, $fResult->id);

        $forumResult = $resolver->resolve('forum5.html');
        $this->assertNotNull($forumResult);
        $this->assertSame('forum', $forumResult->resource);
        $this->assertSame(5, $forumResult->id);
    }

    public function testPaginatedForumResolution(): void
    {
        $resolver = $this->createResolver();

        // Start 50 with 50 topics per page = Page 2
        $result = $resolver->resolve('general-discussion-f10-50.html');
        $this->assertNotNull($result);
        $this->assertSame('forum', $result->resource);
        $this->assertSame(10, $result->id);
        $this->assertSame(2, $result->page);

        // Start 100 with 50 topics per page = Page 3 (-s prefix)
        $result2 = $resolver->resolve('category/forum-f10-s100.html');
        $this->assertNotNull($result2);
        $this->assertSame(3, $result2->page);
    }

    public function testMemberResolutions(): void
    {
        $resolver = $this->createResolver();

        $uResult = $resolver->resolve('u2.html');
        $this->assertNotNull($uResult);
        $this->assertSame('member', $uResult->resource);
        $this->assertSame(2, $uResult->id);

        $userResult = $resolver->resolve('user15.html');
        $this->assertNotNull($userResult);
        $this->assertSame('member', $userResult->resource);
        $this->assertSame(15, $userResult->id);

        $memberResult = $resolver->resolve('member88.html');
        $this->assertNotNull($memberResult);
        $this->assertSame('member', $memberResult->resource);
        $this->assertSame(88, $memberResult->id);

        $slugUserResult = $resolver->resolve('super-admin-u1.html');
        $this->assertNotNull($slugUserResult);
        $this->assertSame('member', $slugUserResult->resource);
        $this->assertSame(1, $slugUserResult->id);
    }

    public function testPostResolutions(): void
    {
        $resolver = $this->createResolver();

        $pResult = $resolver->resolve('p555.html');
        $this->assertNotNull($pResult);
        $this->assertSame('post', $pResult->resource);
        $this->assertSame(555, $pResult->id);

        $postResult = $resolver->resolve('post777.html');
        $this->assertNotNull($postResult);
        $this->assertSame('post', $postResult->resource);
        $this->assertSame(777, $postResult->id);
    }

    public function testDisabledSettingReturnsNull(): void
    {
        $this->config['phpbbseo_legacy_usu_enabled'] = '0';
        $resolver = $this->createResolver();

        $this->assertFalse($resolver->isEnabled());
        $this->assertNull($resolver->resolve('topic-t123.html'));
        $this->assertNull($resolver->resolve('forum-f10.html'));
        $this->assertNull($resolver->resolve('user2.html'));
        $this->assertNull($resolver->resolve('post55.html'));
    }

    public function testDefaultSettingIsOffWhenConfigKeyAbsent(): void
    {
        // Simulate a fresh install / migration where phpbbseo_legacy_usu_enabled is not yet written to DB
        $freshConfig = new config([
            'phpbbseo_framework_enable' => '1',
            'seo_rewrite_enabled'       => '1',
            // phpbbseo_legacy_usu_enabled is absent
        ]);
        $configProvider = new ConfigurationProvider($freshConfig);
        $resolver = new UsuMigrationResolver($this->paginationResolver, $configProvider);

        $this->assertFalse($resolver->isEnabled());
        $this->assertNull($resolver->resolve('some-topic-t123.html'));
        $this->assertNull($resolver->resolve('some-forum-f45.html'));
        $this->assertNull($resolver->resolve('member12.html'));
        $this->assertNull($resolver->resolve('post789.html'));
    }

    public function testNonUsuUrlsIgnored(): void
    {
        $resolver = $this->createResolver();

        $this->assertNull($resolver->resolve('index.php'));
        $this->assertNull($resolver->resolve('viewtopic.php?t=123'));
        $this->assertNull($resolver->resolve('random-page.html'));
        $this->assertNull($resolver->resolve('styles/prosilver/theme/stylesheet.css'));
        $this->assertNull($resolver->resolve('topic-t0.html'));
        $this->assertNull($resolver->resolve('forum-f0.html'));
    }

    public function testInboundRouteResolverIntegration(): void
    {
        $usuResolver = $this->createResolver();

        $patternCompiler = new \phpbbseo\framework\Rewrite\UrlPatternCompiler();
        $permalinkConfig = new \phpbbseo\framework\Rewrite\PermalinkConfiguration($this->configProvider);
        $slugGen = new \phpbbseo\framework\Url\DefaultSlugGenerator();
        
        // Mock / anonymous EntitySeoContext
        $slugRepo = new class extends \phpbbseo\framework\Rewrite\SlugRepository {
            public function __construct() {}
            public function fetchSlugsBatch(string $resource, array $ids): array { return []; }
            public function fetchPostToTopicBatch(array $postIds): array { return []; }
        };
        $entityContext = new \phpbbseo\framework\Context\EntitySeoContext($slugRepo);

        $profile = new \phpbbseo\framework\Rewrite\PermalinkRewriteProfile(
            $permalinkConfig,
            $patternCompiler,
            $entityContext,
            $slugGen,
            $this->paginationResolver,
            $this->configProvider
        );

        $inboundResolver = new \phpbbseo\framework\Rewrite\InboundRouteResolver(
            $profile,
            $usuResolver
        );

        // Standard Lite URL should match
        $liteResult = $inboundResolver->resolve('/topic/my-slug-123/');
        $this->assertNotNull($liteResult);
        $this->assertSame(123, $liteResult->id);
        $this->assertSame('topic', $liteResult->resource);

        // Legacy USU URL should match via UsuMigrationResolver
        $usuResult = $inboundResolver->resolve('/my-old-topic-t456.html');
        $this->assertNotNull($usuResult);
        $this->assertSame(456, $usuResult->id);
        $this->assertSame('topic', $usuResult->resource);
        $this->assertSame('my-old-topic', $usuResult->slug);

        // Forum legacy USU URL
        $usuForum = $inboundResolver->resolve('/my-old-forum-f12.html');
        $this->assertNotNull($usuForum);
        $this->assertSame(12, $usuForum->id);
        $this->assertSame('forum', $usuForum->resource);
    }

    public function testHtmExtensionSupport(): void
    {
        $resolver = $this->createResolver();

        // Topic .htm
        $topic = $resolver->resolve('some-title-t1.htm');
        $this->assertNotNull($topic);
        $this->assertSame('topic', $topic->resource);
        $this->assertSame(1, $topic->id);
        $this->assertSame('some-title', $topic->slug);

        // Paginated topic .htm
        $topicPage = $resolver->resolve('some-title-t1-s20.htm');
        $this->assertNotNull($topicPage);
        $this->assertSame(2, $topicPage->page);

        // Forum .htm
        $forum = $resolver->resolve('some-forum-f2.htm');
        $this->assertNotNull($forum);
        $this->assertSame('forum', $forum->resource);
        $this->assertSame(2, $forum->id);

        // Member .htm
        $member = $resolver->resolve('member2.htm');
        $this->assertNotNull($member);
        $this->assertSame('member', $member->resource);
        $this->assertSame(2, $member->id);

        // Post .htm
        $post = $resolver->resolve('post1.htm');
        $this->assertNotNull($post);
        $this->assertSame('post', $post->resource);
        $this->assertSame(1, $post->id);
    }

    public function testNoExtensionSupport(): void
    {
        $resolver = $this->createResolver();

        // Topic without extension
        $topic = $resolver->resolve('some-title-t1');
        $this->assertNotNull($topic);
        $this->assertSame('topic', $topic->resource);
        $this->assertSame(1, $topic->id);
        $this->assertSame('some-title', $topic->slug);

        // Topic with trailing slash
        $topicSlash = $resolver->resolve('some-title-t1/');
        $this->assertNotNull($topicSlash);
        $this->assertSame('topic', $topicSlash->resource);
        $this->assertSame(1, $topicSlash->id);

        // Forum without extension and with trailing slash
        $forum = $resolver->resolve('some-forum-f2');
        $this->assertNotNull($forum);
        $this->assertSame('forum', $forum->resource);
        $this->assertSame(2, $forum->id);

        $forumSlash = $resolver->resolve('some-forum-f2/');
        $this->assertNotNull($forumSlash);
        $this->assertSame(2, $forumSlash->id);

        // Paginated topic without extension
        $topicPage = $resolver->resolve('some-title-t1-20');
        $this->assertNotNull($topicPage);
        $this->assertSame(2, $topicPage->page);

        // Member and Post without extension
        $member = $resolver->resolve('member2');
        $this->assertNotNull($member);
        $this->assertSame(2, $member->id);

        $post = $resolver->resolve('post1');
        $this->assertNotNull($post);
        $this->assertSame(1, $post->id);
    }

    public function testUnderscoreDelimiterSupport(): void
    {
        $resolver = $this->createResolver();

        // Topic with underscore before t marker
        $topic = $resolver->resolve('some_title_t1.html');
        $this->assertNotNull($topic);
        $this->assertSame('topic', $topic->resource);
        $this->assertSame(1, $topic->id);
        $this->assertSame('some_title', $topic->slug);

        // Underscore with .htm
        $topicHtm = $resolver->resolve('some_title_t1.htm');
        $this->assertNotNull($topicHtm);
        $this->assertSame(1, $topicHtm->id);

        // Underscore without extension
        $topicNoExt = $resolver->resolve('some_title_t1');
        $this->assertNotNull($topicNoExt);
        $this->assertSame(1, $topicNoExt->id);

        // Forum with underscore before f marker
        $forum = $resolver->resolve('some_forum_f2.html');
        $this->assertNotNull($forum);
        $this->assertSame('forum', $forum->resource);
        $this->assertSame(2, $forum->id);
        $this->assertSame('some_forum', $forum->slug);

        // Underscore in pagination: _s20
        $paginatedTopic = $resolver->resolve('some_title_t1_s20.html');
        $this->assertNotNull($paginatedTopic);
        $this->assertSame(1, $paginatedTopic->id);
        $this->assertSame(2, $paginatedTopic->page);

        // Member with underscore before u marker
        $member = $resolver->resolve('some_user_u5.html');
        $this->assertNotNull($member);
        $this->assertSame('member', $member->resource);
        $this->assertSame(5, $member->id);
        $this->assertSame('some_user', $member->slug);
    }

    public function testApproachARouteCollisionAvoidance(): void
    {
        // 1. Resolver WITHOUT router (simulating behavior before Approach A)
        $resolverWithoutRouter = new UsuMigrationResolver(
            $this->paginationResolver,
            $this->configProvider,
            null
        );

        // Without router, an ambiguous path like /report-f2 or /extension-t5 is claimed by USU:
        $claimedForum = $resolverWithoutRouter->resolve('report-f2');
        $this->assertNotNull($claimedForum);
        $this->assertSame('forum', $claimedForum->resource);
        $this->assertSame(2, $claimedForum->id);

        $claimedTopic = $resolverWithoutRouter->resolve('extension-t5');
        $this->assertNotNull($claimedTopic);
        $this->assertSame('topic', $claimedTopic->resource);
        $this->assertSame(5, $claimedTopic->id);

        // 2. Resolver WITH router (Approach A active)
        $mockRouter = new class implements \Symfony\Component\Routing\RouterInterface {
            public function setContext(\Symfony\Component\Routing\RequestContext $context) {}
            public function getContext() { return new \Symfony\Component\Routing\RequestContext(); }
            public function getRouteCollection() { return new \Symfony\Component\Routing\RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                if ($pathinfo === '/report-f2') {
                    return ['_controller' => 'my_ext.controller:report', '_route' => 'my_ext_report'];
                }
                if ($pathinfo === '/extension-t5') {
                    return ['_controller' => 'other_ext.controller:index', '_route' => 'other_ext_topic'];
                }
                if ($pathinfo === '/post-only-f3') {
                    throw new \Symfony\Component\Routing\Exception\MethodNotAllowedException(['POST']);
                }
                throw new \Symfony\Component\Routing\Exception\ResourceNotFoundException();
            }
        };

        $resolverWithRouter = new UsuMigrationResolver(
            $this->paginationResolver,
            $this->configProvider,
            $mockRouter
        );

        // Colliding routes MUST yield (return null), letting Symfony handle them
        $this->assertNull($resolverWithRouter->resolve('report-f2'));
        $this->assertNull($resolverWithRouter->resolve('/report-f2'));
        $this->assertNull($resolverWithRouter->resolve('extension-t5'));
        $this->assertNull($resolverWithRouter->resolve('/post-only-f3'));

        // Legitimate legacy paths must STILL be properly resolved
        $legitTopic = $resolverWithRouter->resolve('my-topic-t123.html');
        $this->assertNotNull($legitTopic);
        $this->assertSame('topic', $legitTopic->resource);
        $this->assertSame(123, $legitTopic->id);

        $legitForum = $resolverWithRouter->resolve('some-forum-f2.html');
        $this->assertNotNull($legitForum);
        $this->assertSame('forum', $legitForum->resource);
        $this->assertSame(2, $legitForum->id);
    }

    public function testFailClosedOnGenericRouterException(): void
    {
        $errorRouter = new class implements \Symfony\Component\Routing\RouterInterface {
            public function setContext(\Symfony\Component\Routing\RequestContext $context) {}
            public function getContext() { return new \Symfony\Component\Routing\RequestContext(); }
            public function getRouteCollection() { return new \Symfony\Component\Routing\RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                if ($pathinfo === '/weird-edge-case-f9') {
                    throw new \RuntimeException('Database connection failure during dynamic route lookup');
                }
                if ($pathinfo === '/broken-matcher-t7') {
                    throw new \LogicException('Unexpected circular route definition encountered');
                }
                throw new \Symfony\Component\Routing\Exception\ResourceNotFoundException();
            }
        };

        $resolver = new UsuMigrationResolver(
            $this->paginationResolver,
            $this->configProvider,
            $errorRouter
        );

        // Fail-safe direction: on generic / unexpected \Throwable, the router check MUST fail-closed
        // toward NOT redirecting (returns null), preventing accidental route hijacking.
        $this->assertNull($resolver->resolve('weird-edge-case-f9'));
        $this->assertNull($resolver->resolve('/weird-edge-case-f9'));
        $this->assertNull($resolver->resolve('broken-matcher-t7'));

        // Paths where router cleanly throws ResourceNotFoundException continue to be resolved normally
        $cleanLegacy = $resolver->resolve('normal-topic-t100.html');
        $this->assertNotNull($cleanLegacy);
        $this->assertSame('topic', $cleanLegacy->resource);
        $this->assertSame(100, $cleanLegacy->id);
    }
}
