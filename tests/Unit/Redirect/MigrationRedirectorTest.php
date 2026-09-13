<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Redirect;

use PHPUnit\Framework\TestCase;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\request\request_interface;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Redirect\MigrationRedirector;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbbseo\framework\Rewrite\PublicResourceUrlResolver;
use phpbbseo\framework\Rewrite\ResourceDetector;
use phpbbseo\framework\Rewrite\SlugRepository;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Url\SlugOptions;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

class MigrationRedirectorTest extends TestCase
{
    private function createRedirector(
        array $requestParams = [],
        array $serverVars = [],
        array $configVars = [],
        ?array $migrationRows = [],
        ?RouterInterface $router = null
    ): MigrationRedirector {
        $db = $this->createMock(driver_interface::class);

        if ($migrationRows !== null) {
            $db->method('sql_escape')->willReturnCallback(fn($str) => addslashes((string) $str));
            $db->method('sql_query_limit')->willReturnCallback(function ($sql) use ($migrationRows) {
                // Find matching row
                foreach ($migrationRows as $row) {
                    if (isset($row['content_type']) && str_contains($sql, "content_type = '{$row['content_type']}'")) {
                        if (isset($row['source_id']) && str_contains($sql, "source_id = {$row['source_id']}")) {
                            $res = new \ArrayIterator([$row]);
                            return $res;
                        }
                    }
                    if (isset($row['eid']) && str_contains($sql, " = {$row['eid']}")) {
                        $res = new \ArrayIterator([$row]);
                        return $res;
                    }
                }
                return new \ArrayIterator([]);
            });
            $db->method('sql_fetchrow')->willReturnCallback(function ($res) {
                if ($res instanceof \ArrayIterator) {
                    $current = $res->current();
                    $res->next();
                    return $current ?: false;
                }
                return false;
            });
        }

        $request = $this->createMock(request_interface::class);
        $request->method('is_set')->willReturnCallback(fn($var) => array_key_exists($var, $requestParams));
        $request->method('variable')->willReturnCallback(function ($var, $default) use ($requestParams) {
            if (!array_key_exists($var, $requestParams)) {
                return $default;
            }
            $val = $requestParams[$var];
            return is_int($default) ? (int) $val : (string) $val;
        });
        $request->method('server')->willReturnCallback(function ($var, $default = '') use ($serverVars) {
            return $serverVars[$var] ?? $default;
        });

        $config = new config(array_merge([
            'server_name'                    => 'example.com',
            'script_path'                    => '/',
            'posts_per_page'                 => '20',
            'topics_per_page'                => '50',
            'phpbbseo_framework_enable'      => '1',
            'seo_rewrite_enabled'            => '0',
            'seo_migration_redirect_enabled' => '1',
            'seo_migration_preserve_ids'     => '0',
            'seo_migration_platforms'        => 'xenforo,vbulletin,mybb,smf',
        ], $configVars));

        $configProvider = new ConfigurationProvider($config);
        $slugGenerator = new DefaultSlugGenerator(new SlugOptions());
        $slugRepo = new SlugRepository($db, $slugGenerator, 'phpbb_');
        $entityContext = new EntitySeoContext($slugRepo);
        $paginationResolver = new PaginationResolver();
        $permalinkConfig = new PermalinkConfiguration($configProvider);
        $compiler = new UrlPatternCompiler();
        $permalinkProfile = new PermalinkRewriteProfile(
            $permalinkConfig,
            $compiler,
            $entityContext,
            $slugGenerator,
            $paginationResolver,
            $configProvider
        );
        $detector = new ResourceDetector();
        $urlResolver = new PublicResourceUrlResolver($detector, $permalinkProfile, $configProvider);

        $redirector = new MigrationRedirector(
            $db,
            $request,
            $config,
            $configProvider,
            $urlResolver,
            $slugRepo,
            $entityContext,
            'phpbb_',
            './',
            'php',
            $router
        );
        $redirector->setTesting(true);

        return $redirector;
    }

    // =========================================================================
    // SECTION 1: Default-Off State & Admin Gating
    // =========================================================================

    public function testDefaultOffStateReturnsNull(): void
    {
        // When seo_migration_redirect_enabled is '0' (default for non-migrated sites)
        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/threads/my-topic.123/',
        ], [
            'seo_migration_redirect_enabled' => '0',
        ]);

        $this->assertFalse($redirector->isEnabled());
        $this->assertNull($redirector->detectLegacyRequest());

        $redirector->onCommon(new \stdClass());
        $this->assertNull($redirector->getLastRedirectUrl());
    }

    public function testDefaultOffWhenConfigKeyAbsent(): void
    {
        // When the key doesn't exist in config at all
        $config = new config([
            'server_name' => 'example.com',
            'script_path' => '/',
        ]);
        $configProvider = new ConfigurationProvider($config);
        $this->assertFalse($configProvider->isMigrationRedirectEnabled());
    }

    public function testPlatformSpecificGating(): void
    {
        // Only MyBB enabled
        $mybbOnly = $this->createRedirector([], [
            'REQUEST_URI' => '/threads/xenforo-thread.123/',
        ], [
            'seo_migration_redirect_enabled' => '1',
            'seo_migration_platforms'        => 'mybb',
        ]);

        $this->assertTrue($mybbOnly->isPlatformEnabled('mybb'));
        $this->assertFalse($mybbOnly->isPlatformEnabled('xenforo'));
        $this->assertFalse($mybbOnly->isPlatformEnabled('vbulletin'));
        $this->assertFalse($mybbOnly->isPlatformEnabled('smf'));

        // XenForo URL must NOT be detected when only MyBB is enabled
        $this->assertNull($mybbOnly->detectLegacyRequest());

        // But MyBB URL must be detected
        $mybbReq = $this->createRedirector([], [
            'REQUEST_URI' => '/thread-123.html',
        ], [
            'seo_migration_redirect_enabled' => '1',
            'seo_migration_platforms'        => 'mybb',
        ]);
        $legacy = $mybbReq->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('mybb', $legacy['system']);
        $this->assertSame(123, $legacy['id']);
    }

    // =========================================================================
    // SECTION 2: Route Collision Protection (Symfony Router Verification)
    // =========================================================================

    public function testCollisionAvoidanceHighestRiskXenForoMembers(): void
    {
        // Simulating an extension that registered a route at /members/{id}
        $mockRouter = new class implements RouterInterface {
            public function setContext(RequestContext $context) {}
            public function getContext() { return new RequestContext(); }
            public function getRouteCollection() { return new RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                if (str_starts_with($pathinfo, '/members/')) {
                    return ['_controller' => 'some_ext.controller:member_directory', '_route' => 'some_ext_members'];
                }
                throw new ResourceNotFoundException();
            }
        };

        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/members/admin.1/',
        ], [], [], $mockRouter);

        // Router collision detected -> MUST yield (return null)
        $this->assertNull($redirector->detectLegacyRequest());

        $redirector->onCommon(new \stdClass());
        $this->assertNull($redirector->getLastRedirectUrl());
    }

    public function testCollisionAvoidanceHighestRiskVBulletinForum(): void
    {
        // Simulating an extension or custom permalink route at /forum/{id}
        $mockRouter = new class implements RouterInterface {
            public function setContext(RequestContext $context) {}
            public function getContext() { return new RequestContext(); }
            public function getRouteCollection() { return new RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                if (preg_match('#^/forum/\d+#', $pathinfo)) {
                    return ['_controller' => 'portal.controller:forum', '_route' => 'portal_forum'];
                }
                throw new ResourceNotFoundException();
            }
        };

        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/forum/12-announcements',
        ], [], [], $mockRouter);

        // Collision detected -> MUST yield
        $this->assertNull($redirector->detectLegacyRequest());
    }

    public function testCollisionAvoidanceMyBBThread(): void
    {
        // Simulating another extension registering /thread-123.html
        $mockRouter = new class implements RouterInterface {
            public function setContext(RequestContext $context) {}
            public function getContext() { return new RequestContext(); }
            public function getRouteCollection() { return new RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                if ($pathinfo === '/thread-123.html') {
                    return ['_controller' => 'archive.controller:thread', '_route' => 'archive_thread'];
                }
                throw new ResourceNotFoundException();
            }
        };

        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/thread-123.html',
        ], [], [], $mockRouter);

        $this->assertNull($redirector->detectLegacyRequest());
    }

    public function testCollisionAvoidanceMethodNotAllowedYields(): void
    {
        // Route exists but method does not match -> MethodNotAllowedException
        $mockRouter = new class implements RouterInterface {
            public function setContext(RequestContext $context) {}
            public function getContext() { return new RequestContext(); }
            public function getRouteCollection() { return new RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                if ($pathinfo === '/posts/999') {
                    throw new MethodNotAllowedException(['POST']);
                }
                throw new ResourceNotFoundException();
            }
        };

        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/posts/999',
        ], [], [], $mockRouter);

        // Registered route exists (method not allowed) -> MUST yield
        $this->assertNull($redirector->detectLegacyRequest());
    }

    public function testCollisionAvoidanceFailClosedOnGenericException(): void
    {
        // Unexpected router runtime error -> Fail closed (assume collision, yield)
        $mockRouter = new class implements RouterInterface {
            public function setContext(RequestContext $context) {}
            public function getContext() { return new RequestContext(); }
            public function getRouteCollection() { return new RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                throw new \RuntimeException('Database failure in router');
            }
        };

        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/threads/topic.100/',
        ], [], [], $mockRouter);

        // Fail closed -> yield (do NOT hijack)
        $this->assertNull($redirector->detectLegacyRequest());
    }

    public function testCleanLegacyRequestProceedsWhenNoCollision(): void
    {
        // Normal router where legacy path throws ResourceNotFoundException
        $mockRouter = new class implements RouterInterface {
            public function setContext(RequestContext $context) {}
            public function getContext() { return new RequestContext(); }
            public function getRouteCollection() { return new RouteCollection(); }
            public function generate($name, $parameters = [], $referenceType = self::ABSOLUTE_PATH) { return ''; }
            public function match($pathinfo)
            {
                throw new ResourceNotFoundException();
            }
        };

        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/threads/legit-thread.777/',
        ], [], [], $mockRouter);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame(777, $legacy['id']);
        $this->assertSame('topic', $legacy['type']);
    }

    // =========================================================================
    // SECTION 3: Safe 404 vs Preserve IDs Fallback Matrix
    // =========================================================================

    public function testMissingTableWithoutPreserveIdsFailsClosedToSafe404(): void
    {
        // No migration table (null), preserve_ids = 0 (default)
        // Even if native topic exists, DO NOT guess!
        $redirector = $this->createRedirector([], [], [
            'seo_migration_preserve_ids' => '0',
        ], null);

        $targetId = $redirector->resolveTargetId('topic', 500);
        $this->assertNull($targetId, 'Must return null (404) when mapping is absent and preserve_ids is off');
    }

    public function testMissingTableWithPreserveIdsResolvesIfNativeEntityExists(): void
    {
        // No migration table, but admin explicitly opted into preserve_ids
        $nativeTopicRow = ['eid' => 500];
        $redirector = $this->createRedirector([], [], [
            'seo_migration_preserve_ids' => '1',
        ], [$nativeTopicRow]);

        $targetId = $redirector->resolveTargetId('topic', 500);
        $this->assertSame(500, $targetId);
    }

    public function testUnmappedSourceIdWithoutPreserveIdsReturnsNull(): void
    {
        // Migration table exists, but ID 9999 has no mapping row
        $migrationRows = [
            ['content_type' => 'topic', 'source_id' => 10, 'target_id' => 20],
            ['eid' => 9999], // Native entity happens to exist with same ID
        ];

        $redirector = $this->createRedirector([], [], [
            'seo_migration_preserve_ids' => '0',
        ], $migrationRows);

        $targetId = $redirector->resolveTargetId('topic', 9999);
        $this->assertNull($targetId, 'Unmapped entity must return null instead of redirecting to wrong native topic');
    }

    public function testUnmappedSourceIdWithPreserveIdsResolvesIfNativeEntityExists(): void
    {
        $migrationRows = [
            ['content_type' => 'topic', 'source_id' => 10, 'target_id' => 20],
            ['eid' => 9999],
        ];

        $redirector = $this->createRedirector([], [], [
            'seo_migration_preserve_ids' => '1',
        ], $migrationRows);

        $targetId = $redirector->resolveTargetId('topic', 9999);
        $this->assertSame(9999, $targetId);
    }

    public function testUnmappedSourceIdWithPreserveIdsReturnsNullIfNativeEntityDoesNotExist(): void
    {
        // Preserve IDs is enabled, but entity doesn't exist natively either
        $migrationRows = [
            ['content_type' => 'topic', 'source_id' => 10, 'target_id' => 20],
        ];

        $redirector = $this->createRedirector([], [], [
            'seo_migration_preserve_ids' => '1',
        ], $migrationRows);

        $targetId = $redirector->resolveTargetId('topic', 8888);
        $this->assertNull($targetId);
    }

    // =========================================================================
    // SECTION 4: XenForo Pattern Coverage
    // =========================================================================

    public function testDetectXenForoFriendlyUrl(): void
    {
        $redirector = $this->createRedirector([], [
            'REQUEST_URI'  => '/threads/my-awesome-thread.1234/page-3',
            'SCRIPT_NAME'  => '/index.php',
            'QUERY_STRING' => '',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('topic', $legacy['type']);
        $this->assertSame(1234, $legacy['id']);
        $this->assertSame(3, $legacy['page']);
        $this->assertSame('xenforo', $legacy['system']);
    }

    public function testDetectXenForoFriendlyUrlWithoutSlug(): void
    {
        $redirector = $this->createRedirector([], [
            'REQUEST_URI' => '/threads/567/',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('topic', $legacy['type']);
        $this->assertSame(567, $legacy['id']);
        $this->assertSame(1, $legacy['page']);
        $this->assertSame('xenforo', $legacy['system']);
    }

    public function testDetectXenForoQueryParams(): void
    {
        $redirector = $this->createRedirector([
            'threads' => '999',
            'page'    => 2,
        ], [
            'REQUEST_URI' => '/index.php?threads=999&page=2',
            'SCRIPT_NAME' => '/index.php',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('topic', $legacy['type']);
        $this->assertSame(999, $legacy['id']);
        $this->assertSame(2, $legacy['page']);
        $this->assertSame('xenforo', $legacy['system']);
    }

    public function testDetectXenForoPostsForumsMembers(): void
    {
        // Posts
        $postRed = $this->createRedirector([], ['REQUEST_URI' => '/posts/345/']);
        $lPost = $postRed->detectLegacyRequest();
        $this->assertNotNull($lPost);
        $this->assertSame('post', $lPost['type']);
        $this->assertSame(345, $lPost['id']);

        // Forums
        $forumRed = $this->createRedirector([], ['REQUEST_URI' => '/forums/general.10/page-2']);
        $lForum = $forumRed->detectLegacyRequest();
        $this->assertNotNull($lForum);
        $this->assertSame('forum', $lForum['type']);
        $this->assertSame(10, $lForum['id']);
        $this->assertSame(2, $lForum['page']);

        // Members
        $userRed = $this->createRedirector([], ['REQUEST_URI' => '/members/cool-user.88/']);
        $lUser = $userRed->detectLegacyRequest();
        $this->assertNotNull($lUser);
        $this->assertSame('user', $lUser['type']);
        $this->assertSame(88, $lUser['id']);
    }

    // =========================================================================
    // SECTION 5: vBulletin Pattern Coverage
    // =========================================================================

    public function testDetectVBulletinShowthread(): void
    {
        $redirector = $this->createRedirector([
            't'    => 456,
            'page' => 2,
        ], [
            'REQUEST_URI'  => '/showthread.php?t=456&page=2',
            'SCRIPT_NAME'  => '/showthread.php',
            'QUERY_STRING' => 't=456&page=2',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('topic', $legacy['type']);
        $this->assertSame(456, $legacy['id']);
        $this->assertSame(2, $legacy['page']);
        $this->assertSame('vbulletin', $legacy['system']);
    }

    public function testDetectVBulletinShowpost(): void
    {
        $redirector = $this->createRedirector([
            'p' => 789,
        ], [
            'REQUEST_URI'  => '/showpost.php?p=789',
            'SCRIPT_NAME'  => '/showpost.php',
            'QUERY_STRING' => 'p=789',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('post', $legacy['type']);
        $this->assertSame(789, $legacy['id']);
        $this->assertSame('vbulletin', $legacy['system']);
    }

    public function testDetectVBulletinForumAndMember(): void
    {
        // Forum display
        $redirector = $this->createRedirector([
            'f' => 12,
        ], [
            'REQUEST_URI'  => '/forumdisplay.php?f=12',
            'SCRIPT_NAME'  => '/forumdisplay.php',
            'QUERY_STRING' => 'f=12',
        ]);
        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('forum', $legacy['type']);
        $this->assertSame(12, $legacy['id']);
        $this->assertSame('vbulletin', $legacy['system']);

        // Member profile
        $redirectorUser = $this->createRedirector([
            'u' => 78,
        ], [
            'REQUEST_URI'  => '/member.php?u=78',
            'SCRIPT_NAME'  => '/member.php',
            'QUERY_STRING' => 'u=78',
        ]);
        $legacyUser = $redirectorUser->detectLegacyRequest();
        $this->assertNotNull($legacyUser);
        $this->assertSame('user', $legacyUser['type']);
        $this->assertSame(78, $legacyUser['id']);
        $this->assertSame('vbulletin', $legacyUser['system']);
    }

    public function testDetectVBulletin4SEFUrls(): void
    {
        // vB4 topic: /threads/123-My-Topic-Title
        $topicRed = $this->createRedirector([], ['REQUEST_URI' => '/threads/123-My-Topic-Title']);
        $lTopic = $topicRed->detectLegacyRequest();
        $this->assertNotNull($lTopic);
        $this->assertSame('topic', $lTopic['type']);
        $this->assertSame(123, $lTopic['id']);
        $this->assertSame('vbulletin', $lTopic['system']);

        // vB4 forum: /forum/45-Main-Forum
        $forumRed = $this->createRedirector([], ['REQUEST_URI' => '/forum/45-Main-Forum']);
        $lForum = $forumRed->detectLegacyRequest();
        $this->assertNotNull($lForum);
        $this->assertSame('forum', $lForum['type']);
        $this->assertSame(45, $lForum['id']);
        $this->assertSame('vbulletin', $lForum['system']);
    }

    // =========================================================================
    // SECTION 6: MyBB Pattern Coverage
    // =========================================================================

    public function testDetectMyBBSEFUrl(): void
    {
        $redirector = $this->createRedirector([], [
            'REQUEST_URI'  => '/thread-321-page-4.html',
            'SCRIPT_NAME'  => '/index.php',
            'QUERY_STRING' => '',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('topic', $legacy['type']);
        $this->assertSame(321, $legacy['id']);
        $this->assertSame(4, $legacy['page']);
        $this->assertSame('mybb', $legacy['system']);
    }

    public function testDetectMyBBSEFPostForumUser(): void
    {
        // Post
        $redPost = $this->createRedirector([], ['REQUEST_URI' => '/post-456.html']);
        $lPost = $redPost->detectLegacyRequest();
        $this->assertNotNull($lPost);
        $this->assertSame('post', $lPost['type']);
        $this->assertSame(456, $lPost['id']);

        // Forum
        $redForum = $this->createRedirector([], ['REQUEST_URI' => '/forum-22-page-2.html']);
        $lForum = $redForum->detectLegacyRequest();
        $this->assertNotNull($lForum);
        $this->assertSame('forum', $lForum['type']);
        $this->assertSame(22, $lForum['id']);
        $this->assertSame(2, $lForum['page']);

        // User
        $redUser = $this->createRedirector([], ['REQUEST_URI' => '/user-88.html']);
        $lUser = $redUser->detectLegacyRequest();
        $this->assertNotNull($lUser);
        $this->assertSame('user', $lUser['type']);
        $this->assertSame(88, $lUser['id']);
    }

    // =========================================================================
    // SECTION 7: SMF Pattern Coverage
    // =========================================================================

    public function testDetectSMFTopicWithOffset(): void
    {
        $redirector = $this->createRedirector([], [
            'REQUEST_URI'  => '/index.php?topic=55.30',
            'SCRIPT_NAME'  => '/index.php',
            'QUERY_STRING' => 'topic=55.30',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('topic', $legacy['type']);
        $this->assertSame(55, $legacy['id']);
        $this->assertSame(30, $legacy['offset']);
        $this->assertSame('smf', $legacy['system']);
    }

    public function testDetectSMFPostJump(): void
    {
        $redirector = $this->createRedirector([], [
            'REQUEST_URI'  => '/index.php?topic=55.msg999',
            'SCRIPT_NAME'  => '/index.php',
            'QUERY_STRING' => 'topic=55.msg999',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('post', $legacy['type']);
        $this->assertSame(999, $legacy['id']);
        $this->assertSame('smf', $legacy['system']);
    }

    public function testDetectSMFBoardAndProfile(): void
    {
        // SMF board
        $redirector = $this->createRedirector([], [
            'REQUEST_URI'  => '/index.php?board=5.0',
            'SCRIPT_NAME'  => '/index.php',
            'QUERY_STRING' => 'board=5.0',
        ]);
        $legacy = $redirector->detectLegacyRequest();
        $this->assertNotNull($legacy);
        $this->assertSame('forum', $legacy['type']);
        $this->assertSame(5, $legacy['id']);
        $this->assertSame('smf', $legacy['system']);

        // SMF profile
        $redirectorUser = $this->createRedirector([], [
            'REQUEST_URI'  => '/index.php?action=profile;u=99',
            'SCRIPT_NAME'  => '/index.php',
            'QUERY_STRING' => 'action=profile;u=99',
        ]);
        $legacyUser = $redirectorUser->detectLegacyRequest();
        $this->assertNotNull($legacyUser);
        $this->assertSame('user', $legacyUser['type']);
        $this->assertSame(99, $legacyUser['id']);
        $this->assertSame('smf', $legacyUser['system']);
    }

    // =========================================================================
    // SECTION 8: End-to-End Successful Redirect Execution
    // =========================================================================

    public function testSuccessfulMigrationRedirect(): void
    {
        $migrationRows = [
            [
                'content_type'  => 'topic',
                'source_id'     => 1234,
                'target_id'     => 5678,
                'source_system' => 'xenforo',
            ],
        ];

        $redirector = $this->createRedirector([
            'threads' => '1234',
            'page'    => 3,
        ], [
            'REQUEST_URI' => '/index.php?threads=1234&page=3',
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST'   => 'example.com',
            'HTTPS'       => 'on',
        ], [
            'posts_per_page' => '20',
        ], $migrationRows);

        $redirector->onCommon(new \stdClass());

        $this->assertSame(301, $redirector->getLastStatusCode());
        $this->assertNotNull($redirector->getLastRedirectUrl());
        // Target topic 5678, page 3 offset = (3 - 1) * 20 = 40
        $this->assertStringContainsString('t=5678', $redirector->getLastRedirectUrl());
        $this->assertStringContainsString('start=40', $redirector->getLastRedirectUrl());
    }

    public function testPreserveIdsFallbackWhenExplicitlyEnabled(): void
    {
        $nativeTopicRow = [
            'eid' => 500,
        ];

        $redirector = $this->createRedirector([
            'vb_thread_id' => 500,
        ], [
            'REQUEST_URI' => '/index.php?vb_thread_id=500',
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST'   => 'example.com',
        ], [
            'seo_migration_preserve_ids' => '1',
        ], [$nativeTopicRow]);

        $redirector->onCommon(new \stdClass());

        $this->assertSame(301, $redirector->getLastStatusCode());
        $this->assertNotNull($redirector->getLastRedirectUrl());
        $this->assertStringContainsString('t=500', $redirector->getLastRedirectUrl());
    }

    // =========================================================================
    // SECTION 9: Edge Cases & Safety Bailouts
    // =========================================================================

    public function testNormalPhpBBTrafficDoesNotTriggerRedirect(): void
    {
        $redirector = $this->createRedirector([], [
            'REQUEST_URI'  => '/viewtopic.php?t=123',
            'SCRIPT_NAME'  => '/viewtopic.php',
            'QUERY_STRING' => 't=123',
        ]);

        $legacy = $redirector->detectLegacyRequest();
        $this->assertNull($legacy, 'Standard phpBB viewtopic.php should not be flagged as legacy');
    }

    public function testAcpPageBailout(): void
    {
        $redirector = $this->createRedirector([
            'threads' => '123',
        ], [
            'REQUEST_URI'  => '/adm/index.php?threads=123',
            'SCRIPT_NAME'  => '/adm/index.php',
            'QUERY_STRING' => 'threads=123',
        ]);

        $redirector->onCommon(new \stdClass());
        $this->assertNull($redirector->getLastRedirectUrl(), 'ACP pages must never be redirected');
    }

    public function testZeroOrNegativeIdsBailoutSafely(): void
    {
        $zeroTopic = $this->createRedirector([], ['REQUEST_URI' => '/threads/0/']);
        $this->assertNull($zeroTopic->detectLegacyRequest());

        $zeroVb = $this->createRedirector(['t' => 0], [
            'REQUEST_URI' => '/showthread.php?t=0',
            'SCRIPT_NAME' => '/showthread.php',
        ]);
        $this->assertNull($zeroVb->detectLegacyRequest());

        $zeroMybb = $this->createRedirector([], ['REQUEST_URI' => '/thread-0.html']);
        $this->assertNull($zeroMybb->detectLegacyRequest());
    }
}
