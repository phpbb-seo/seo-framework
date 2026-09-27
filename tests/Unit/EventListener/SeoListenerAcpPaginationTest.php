<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\EventListener;

use ArrayObject;
use PHPUnit\Framework\TestCase;
use phpbbseo\framework\EventListener\SeoListener;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Context\RequestContextFactory;
use phpbbseo\framework\Canonical\CanonicalResolver;
use phpbbseo\framework\Redirect\RedirectResolver;
use phpbbseo\framework\Redirect\UrlSafetyValidator;
use phpbbseo\framework\Rewrite\InboundRouteResolver;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbbseo\framework\Rewrite\PublicResourceUrlResolver;
use phpbbseo\framework\Rewrite\ResourceDetector;
use phpbbseo\framework\Rewrite\SlugRepository;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;
use phpbbseo\framework\Sitemap\SitemapRepository;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Url\SlugOptions;
use phpbb\request\request_interface;

class SeoListenerAcpPaginationTest extends TestCase
{
    private string $currentScriptName = '/index.php';
    private SeoListener $listener;
    private EntitySeoContext $entityContext;

    protected function setUp(): void
    {
        $this->currentScriptName = '/index.php';

        $config = $this->createMock(ConfigurationProvider::class);
        $config->method('isRewriteEnabled')->willReturn(true);
        $config->method('get')->willReturnMap([
            ['seo_permalink_preset', 'modern', 'modern'],
            ['posts_per_page', '20', '20'],
            ['topics_per_page', '25', '25'],
        ]);

        $this->entityContext = new EntitySeoContext();
        $this->entityContext->setTopics([100 => 'my-test-topic']);

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
        $urlResolver = new PublicResourceUrlResolver($detector, $profile, $config);

        $mockRequest = new class($this) implements request_interface {
            public function __construct(private SeoListenerAcpPaginationTest $test) {}
            public function is_set($var, $super_global = \phpbb\request\request_interface::REQUEST) { return false; }
            public function is_set_post($var) { return false; }
            public function variable($var_name, $default, $multibyte = false, $super_global = \phpbb\request\request_interface::REQUEST) { return $default; }
            public function raw_variable($var_name, $default = 0, $super_global = \phpbb\request\request_interface::REQUEST) { return $default; }
            public function file($form_name) { return []; }
            public function server($var_name, $default = '') {
                if ($var_name === 'SCRIPT_NAME') {
                    return $this->test->getScriptName();
                }
                return $default;
            }
            public function header($header_name, $default = '') { return $default; }
            public function get_super_global($super_global = \phpbb\request\request_interface::REQUEST) { return []; }
            public function overwrite($var_name, $value, $super_global = \phpbb\request\request_interface::REQUEST) {}
            public function enable_super_globals() {}
            public function disable_super_globals() {}
            public function is_enable_super_globals() { return false; }
            public function get_names($super_global = \phpbb\request\request_interface::REQUEST) { return []; }
            public function escape($value, $multibyte = false) { return $value; }
            public function is_ajax() { return false; }
            public function is_secure() { return false; }
            public function variable_names($super_global = \phpbb\request\request_interface::REQUEST) { return []; }
        };

        $mockTemplate = $this->createMock(\phpbb\template\template::class);

        $mockContextFactory = new class extends RequestContextFactory { public function __construct() {} };
        $mockInbound = new class extends InboundRouteResolver { public function __construct() {} };
        $mockCanonical = new class extends CanonicalResolver { public function __construct() {} };
        $mockRedirect = new class extends RedirectResolver { public function __construct() {} };
        $mockSafety = new class extends UrlSafetyValidator { public function __construct() {} };

        $db = $this->createMock(\phpbb\db\driver\driver_interface::class);
        $slugRepo = new SlugRepository($db, $slugGenerator, 'phpbb_');
        $sitemapRepo = $this->createMock(\phpbbseo\framework\Sitemap\SitemapRepository::class);

        $userMock = $this->createMock(\phpbb\user::class);

        $this->listener = new SeoListener(
            $mockContextFactory,
            $this->entityContext,
            $mockInbound,
            $urlResolver,
            $config,
            $mockCanonical,
            $mockRedirect,
            $mockSafety,
            $mockRequest,
            $slugRepo,
            $paginator,
            $mockTemplate,
            $userMock,
            $sitemapRepo
        );
    }

    public function getScriptName(): string
    {
        return $this->currentScriptName;
    }

    public function testAcpPaginationLinkIsNotRewritten(): void
    {
        $this->currentScriptName = '/phpbb/adm/index.php';

        $event = new ArrayObject([
            'base_url' => './index.php?i=acp_bots&mode=bots',
            'on_page'  => 2,
            'per_page' => 25,
        ]);

        $this->listener->onPaginationGeneratePageLink($event);

        $this->assertArrayNotHasKey('generate_page_link_override', $event, 'ACP pagination must never be overridden/rewritten');
    }

    public function testAcpDirectBaseUrlPaginationIsNotRewritten(): void
    {
        $this->currentScriptName = '/phpbb/index.php';

        $event = new ArrayObject([
            'base_url' => 'adm/index.php?i=acp_logs',
            'on_page'  => 3,
            'per_page' => 50,
        ]);

        $this->listener->onPaginationGeneratePageLink($event);

        $this->assertArrayNotHasKey('generate_page_link_override', $event, 'adm/ base_url must never be rewritten');
    }

    public function testMcpPaginationIsNotRewritten(): void
    {
        $this->currentScriptName = '/phpbb/mcp.php';

        $event = new ArrayObject([
            'base_url' => './mcp.php?i=reports&mode=reports',
            'on_page'  => 2,
            'per_page' => 10,
        ]);

        $this->listener->onPaginationGeneratePageLink($event);

        $this->assertArrayNotHasKey('generate_page_link_override', $event, 'MCP pagination must never be rewritten');
    }

    public function testUcpPaginationIsNotRewritten(): void
    {
        $this->currentScriptName = '/phpbb/ucp.php';

        $event = new ArrayObject([
            'base_url' => './ucp.php?i=ucp_pm&mode=view',
            'on_page'  => 2,
            'per_page' => 25,
        ]);

        $this->listener->onPaginationGeneratePageLink($event);

        $this->assertArrayNotHasKey('generate_page_link_override', $event, 'UCP pagination must never be rewritten');
    }

    public function testFrontendTopicPaginationIsRewritten(): void
    {
        $this->currentScriptName = '/phpbb/viewtopic.php';

        $event = new ArrayObject([
            'base_url' => 'viewtopic.php?t=100',
            'on_page'  => 2,
            'per_page' => 20,
        ]);

        $this->listener->onPaginationGeneratePageLink($event);

        $this->assertArrayHasKey('generate_page_link_override', $event, 'Frontend topic pagination MUST be rewritten');
        $this->assertStringContainsKeyOrVal('/topic/my-test-topic-100/page/2/', (string) $event['generate_page_link_override']);
    }

    public function testAppendSidBypassesAcpAndMcpAndUcp(): void
    {
        // ACP
        $this->currentScriptName = '/phpbb/adm/index.php';
        $eventAcp = new ArrayObject([
            'url'    => './index.php?i=acp_bots&mode=bots',
            'params' => '',
            'is_amp' => true,
        ]);
        $this->listener->onAppendSid($eventAcp);
        $this->assertArrayNotHasKey('append_sid_overwrite', $eventAcp);

        // MCP
        $this->currentScriptName = '/phpbb/mcp.php';
        $eventMcp = new ArrayObject([
            'url'    => 'mcp.php?i=main&mode=front',
            'params' => '',
            'is_amp' => true,
        ]);
        $this->listener->onAppendSid($eventMcp);
        $this->assertArrayNotHasKey('append_sid_overwrite', $eventMcp);

        // UCP
        $this->currentScriptName = '/phpbb/ucp.php';
        $eventUcp = new ArrayObject([
            'url'    => 'ucp.php?i=ucp_pm&mode=view',
            'params' => '',
            'is_amp' => true,
        ]);
        $this->listener->onAppendSid($eventUcp);
        $this->assertArrayNotHasKey('append_sid_overwrite', $eventUcp);

        // Frontend
        $this->currentScriptName = '/phpbb/viewtopic.php';
        $eventFrontend = new ArrayObject([
            'url'    => 'viewtopic.php?t=100',
            'params' => '',
            'is_amp' => true,
        ]);
        $this->listener->onAppendSid($eventFrontend);
        $this->assertArrayHasKey('append_sid_overwrite', $eventFrontend);
        $this->assertStringContainsKeyOrVal('/topic/my-test-topic-100/', (string) $eventFrontend['append_sid_overwrite']);
    }

    public function testUcpBookmarksShowSeoTopicLinks(): void
    {
        // When user is on UCP Bookmarks page
        $this->currentScriptName = '/phpbb/ucp.php';
        $event = new ArrayObject([
            'url'    => 'viewtopic.php?t=100',
            'params' => '',
            'is_amp' => true,
        ]);
        $this->listener->onAppendSid($event);

        $this->assertArrayHasKey('append_sid_overwrite', $event, 'UCP page MUST still rewrite topic links to SEO URLs');
        $this->assertStringContainsKeyOrVal('/topic/my-test-topic-100/', (string) $event['append_sid_overwrite']);
    }

    public function testMcpQueueShowSeoTopicLinks(): void
    {
        // When moderator is on MCP Queue page
        $this->currentScriptName = '/phpbb/mcp.php';
        $event = new ArrayObject([
            'url'    => 'viewtopic.php?t=100',
            'params' => '',
            'is_amp' => true,
        ]);
        $this->listener->onAppendSid($event);

        $this->assertArrayHasKey('append_sid_overwrite', $event, 'MCP page MUST still rewrite topic links to SEO URLs');
        $this->assertStringContainsKeyOrVal('/topic/my-test-topic-100/', (string) $event['append_sid_overwrite']);
    }

    private function assertStringContainsKeyOrVal(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "Failed asserting that '$haystack' contains '$needle'");
    }
}
