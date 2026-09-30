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
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Url\SlugOptions;
use phpbb\request\request_interface;

class SeoListenerRootLinkNormalizationTest extends TestCase
{
    private string $currentScriptName = '/index.php';
    private SeoListener $listener;
    private array $templateVars = [];

    protected function setUp(): void
    {
        $this->currentScriptName = '/index.php';
        $this->templateVars = [];

        $config = $this->createMock(ConfigurationProvider::class);
        $config->method('isRewriteEnabled')->willReturn(true);
        $config->method('get')->willReturnMap([
            ['seo_permalink_preset', 'modern', 'modern'],
            ['posts_per_page', '20', '20'],
            ['topics_per_page', '25', '25'],
            ['assets_version', '10', '10'],
            ['allow_cdn', false, false],
        ]);

        $entityContext = new EntitySeoContext();
        $paginator = new PaginationResolver();
        $permalinkConfig = new PermalinkConfiguration($config);
        $compiler = new UrlPatternCompiler();
        $slugGenerator = new DefaultSlugGenerator(new SlugOptions());

        $profile = new PermalinkRewriteProfile(
            $permalinkConfig,
            $compiler,
            $entityContext,
            $slugGenerator,
            $paginator,
            $config
        );

        $detector = new ResourceDetector($config);
        $urlResolver = new PublicResourceUrlResolver($detector, $profile, $config);

        $mockRequest = new class($this) implements request_interface {
            public function __construct(private SeoListenerRootLinkNormalizationTest $test) {}
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

        $mockTemplate = new class($this) implements \phpbb\template\template {
            public function __construct(private SeoListenerRootLinkNormalizationTest $test) {}
            public function clear_cache() { return $this; }
            public function get_user_style() { return []; }
            public function set_filenames(array $filename_array) { return $this; }
            public function get_source_file_for_handle($handle) { return ''; }
            public function display($handle) { return true; }
            public function assign_display($handle, $template_var = '', $echo = false) { return ''; }
            public function set_style($style_directories = ['styles']) { return $this; }
            public function set_custom_style($names, $paths) { return $this; }
            public function destroy() { return $this; }
            public function destroy_block_vars($blockname) { return $this; }
            public function assign_vars(array $vararray) {
                foreach ($vararray as $k => $v) {
                    $this->test->setTemplateVar($k, $v);
                }
                return $this;
            }
            public function assign_var($varname, $varval) {
                $this->test->setTemplateVar($varname, $varval);
                return $this;
            }
            public function append_var($varname, $varval) { return $this; }
            public function retrieve_vars(array $vararray) {
                $res = [];
                foreach ($vararray as $v) {
                    $res[$v] = $this->test->getTemplateVar($v);
                }
                return $res;
            }
            public function retrieve_var($varname) {
                return $this->test->getTemplateVar($varname);
            }
            public function assign_block_vars($blockname, array $vararray) { return $this; }
            public function assign_block_vars_array($blockname, array $block_vars_array) { return $this; }
            public function retrieve_block_vars($blockname, array $vararray) { return []; }
            public function alter_block_array($blockname, array $vararray, $key = false, $mode = 'insert') { return false; }
            public function find_key_index($blockname, $key) { return false; }
        };

        $mockContextFactory = new class extends RequestContextFactory { public function __construct() {} };
        $mockInbound = new class extends InboundRouteResolver { public function __construct() {} };
        $mockCanonical = new class extends CanonicalResolver { public function __construct() {} };
        $mockRedirect = new class extends RedirectResolver { public function __construct() {} };
        $mockSafety = new class extends UrlSafetyValidator { public function __construct() {} };

        $db = $this->createMock(\phpbb\db\driver\driver_interface::class);
        $slugRepo = new SlugRepository($db, $slugGenerator, 'phpbb_');

        $userMock = $this->createMock(\phpbb\user::class);
        $userMock->style = ['style_path' => 'prosilver'];
        $userMock->lang_name = 'en';

        $this->listener = new SeoListener(
            $mockContextFactory,
            $entityContext,
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
            $userMock
        );
    }

    public function getScriptName(): string
    {
        return $this->currentScriptName;
    }

    public function setTemplateVar(string $name, mixed $val): void
    {
        $this->templateVars[$name] = $val;
    }

    public function getTemplateVar(string $name): mixed
    {
        return $this->templateVars[$name] ?? null;
    }

    public function testOnAppendSidRootsAcpLinkOnFrontend(): void
    {
        $this->currentScriptName = '/index.php';

        $event = new ArrayObject([
            'url'    => './adm/index.php',
            'params' => '',
            'is_amp' => true,
        ]);

        $this->listener->onAppendSid($event);

        $this->assertArrayNotHasKey('append_sid_overwrite', $event, 'append_sid_overwrite must not be set for ACP');
        $this->assertStringEndsWith('/adm/index.php', $event['url'], 'Relative ./adm/index.php must be rooted to adm/index.php');
        $this->assertStringStartsWith('/', $event['url'], 'Must be root-relative');
    }

    public function testOnAppendSidRootsUcpLinkOnFrontend(): void
    {
        $this->currentScriptName = '/app.php';

        $event = new ArrayObject([
            'url'    => './ucp.php',
            'params' => 'mode=login',
            'is_amp' => true,
        ]);

        $this->listener->onAppendSid($event);

        $this->assertArrayNotHasKey('append_sid_overwrite', $event);
        $this->assertStringEndsWith('/ucp.php', $event['url'], 'Relative ./ucp.php must be rooted to ucp.php');
        $this->assertStringStartsWith('/', $event['url'], 'Must be root-relative');
    }

    public function testOnAppendSidPreservesAcpInternalLinks(): void
    {
        $this->currentScriptName = '/adm/index.php';

        $event = new ArrayObject([
            'url'    => './index.php?i=acp_board&mode=settings',
            'params' => '',
            'is_amp' => true,
        ]);

        $this->listener->onAppendSid($event);

        $this->assertSame('./index.php?i=acp_board&mode=settings', $event['url'], 'Inside ACP, relative links must not be touched');
    }

    public function testOnPageHeaderAfterRootsRelativeTemplateVars(): void
    {
        $this->currentScriptName = '/index.php';

        $this->setTemplateVar('S_LOGIN_ACTION', './ucp.php?mode=login');
        $this->setTemplateVar('U_REGISTER', './ucp.php?mode=register');
        $this->setTemplateVar('U_LOGIN_LOGOUT', './ucp.php?mode=login');
        $this->setTemplateVar('U_SEARCH', './search.php');

        $this->listener->onPageHeaderAfter(new ArrayObject([]));

        $this->assertStringEndsWith('/ucp.php?mode=login', $this->getTemplateVar('S_LOGIN_ACTION'));
        $this->assertStringEndsWith('/ucp.php?mode=register', $this->getTemplateVar('U_REGISTER'));
        $this->assertStringEndsWith('/ucp.php?mode=login', $this->getTemplateVar('U_LOGIN_LOGOUT'));
        $this->assertStringEndsWith('/search.php', $this->getTemplateVar('U_SEARCH'));
        $this->assertStringStartsWith('/', $this->getTemplateVar('S_LOGIN_ACTION'));
    }

    public function testOnPageFooterAfterRootsUAcp(): void
    {
        $this->currentScriptName = '/index.php';

        $this->setTemplateVar('U_ACP', 'adm/index.php?sid=abc123xyz');

        $this->listener->onPageFooterAfter(new ArrayObject([]));

        $this->assertStringEndsWith('/adm/index.php?sid=abc123xyz', $this->getTemplateVar('U_ACP'));
        $this->assertStringStartsWith('/', $this->getTemplateVar('U_ACP'));
    }

    public function testOnPageFooterAfterLeavesEmptyUAcpUntouched(): void
    {
        $this->currentScriptName = '/index.php';

        $this->setTemplateVar('U_ACP', '');

        $this->listener->onPageFooterAfter(new ArrayObject([]));

        $this->assertSame('', $this->getTemplateVar('U_ACP'));
    }
}
