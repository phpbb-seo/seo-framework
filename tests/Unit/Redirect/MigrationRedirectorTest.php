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

class MigrationRedirectorTest extends TestCase
{
    private function createRedirector(
        array $requestParams = [],
        array $serverVars = [],
        array $configVars = [],
        ?array $migrationRows = []
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
            'server_name'     => 'example.com',
            'script_path'     => '/',
            'posts_per_page'  => '20',
            'topics_per_page' => '50',
            'seo_rewrite_enabled' => '0',
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
            'php'
        );
        $redirector->setTesting(true);

        return $redirector;
    }

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

    public function testDetectVBulletinPost(): void
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

    public function testDetectMyBBSEFPostForumUser(): void
    {
        // Post
        $redPost = $this->createRedirector([], ['REQUEST_URI' => '/post-456.html']);
        $lPost = $redPost->detectLegacyRequest();
        $this->assertSame('post', $lPost['type']);
        $this->assertSame(456, $lPost['id']);

        // Forum
        $redForum = $this->createRedirector([], ['REQUEST_URI' => '/forum-22-page-2.html']);
        $lForum = $redForum->detectLegacyRequest();
        $this->assertSame('forum', $lForum['type']);
        $this->assertSame(22, $lForum['id']);
        $this->assertSame(2, $lForum['page']);

        // User
        $redUser = $this->createRedirector([], ['REQUEST_URI' => '/user-88.html']);
        $lUser = $redUser->detectLegacyRequest();
        $this->assertSame('user', $lUser['type']);
        $this->assertSame(88, $lUser['id']);
    }

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

    public function testPreserveIdsFallbackWhenNotInMigrationMap(): void
    {
        // No migration table rows, but topic exists natively
        $nativeTopicRow = [
            'eid' => 500,
        ];

        $redirector = $this->createRedirector([
            'vb_thread_id' => 500,
        ], [
            'REQUEST_URI' => '/index.php?vb_thread_id=500',
            'SCRIPT_NAME' => '/index.php',
            'HTTP_HOST'   => 'example.com',
        ], [], [$nativeTopicRow]);

        $redirector->onCommon(new \stdClass());

        $this->assertSame(301, $redirector->getLastStatusCode());
        $this->assertNotNull($redirector->getLastRedirectUrl());
        $this->assertStringContainsString('t=500', $redirector->getLastRedirectUrl());
    }
}
