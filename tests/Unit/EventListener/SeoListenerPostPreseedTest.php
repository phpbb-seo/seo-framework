<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\EventListener;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\EventListener\SeoListener;
use phpbbseo\framework\Rewrite\SlugRepository;
use phpbbseo\framework\Sitemap\SitemapRepository;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Context\RequestContextFactory;
use phpbbseo\framework\Canonical\CanonicalResolver;
use phpbbseo\framework\Redirect\RedirectResolver;
use phpbbseo\framework\Redirect\UrlSafetyValidator;
use phpbbseo\framework\Rewrite\InboundRouteResolver;
use phpbbseo\framework\Rewrite\PublicResourceUrlResolver;
use phpbbseo\framework\Url\PaginationResolver;
use phpbb\template\template;
use phpbb\request\request_interface;

class SeoListenerPostPreseedTest extends TestCase
{
    private EntitySeoContext $entityContext;
    private SeoListener $listener;

    protected function setUp(): void
    {
        $this->entityContext = new EntitySeoContext();

        $mockContextFactory = new class extends RequestContextFactory { public function __construct() {} };
        $mockInbound = new class extends InboundRouteResolver { public function __construct() {} };
        $mockUrlResolver = new class extends PublicResourceUrlResolver { public function __construct() {} };
        $mockConfig = new class extends ConfigurationProvider {
            public function __construct() {}
            public function isRewriteEnabled(): bool { return true; }
        };
        $mockCanonical = new class extends CanonicalResolver { public function __construct() {} };
        $mockRedirect = new class extends RedirectResolver { public function __construct() {} };
        $mockSafety = new class extends UrlSafetyValidator { public function __construct() {} };
        $mockRequest = new class implements request_interface {
            public function is_set($var, $super_global = \phpbb\request\request_interface::REQUEST) { return false; }
            public function is_set_post($var) { return false; }
            public function variable($var_name, $default, $multibyte = false, $super_global = \phpbb\request\request_interface::REQUEST) { return $default; }
            public function raw_variable($var_name, $default = 0, $super_global = \phpbb\request\request_interface::REQUEST) { return $default; }
            public function file($form_name) { return []; }
            public function server($var_name, $default = '') { return $default; }
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
        $mockTemplate = new class extends \phpbb\template\base {
            public function __construct() {
                $this->context = new \phpbb\template\context();
            }
            public function clear_cache() { return $this; }
            public function get_user_style() { return []; }
            public function set_style($style_directories = array('styles')) { return $this; }
            public function set_custom_style($names, $paths) { return $this; }
            public function display($handle) { return $this; }
            public function assign_display($handle, $template_var = '', $return_content = true) { return $this; }
            public function get_source_file_for_handle($handle) { return ''; }
        };
        $mockSlug = new class extends SlugRepository { public function __construct() {} };
        $mockSitemap = new class extends SitemapRepository { public function __construct() {} };
        $mockPaginator = new PaginationResolver();

        $this->listener = new SeoListener(
            $mockContextFactory,
            $this->entityContext,
            $mockInbound,
            $mockUrlResolver,
            $mockConfig,
            $mockCanonical,
            $mockRedirect,
            $mockSafety,
            $mockRequest,
            $mockSlug,
            $mockPaginator,
            $mockTemplate,
            new class extends \phpbb\user { public function __construct() { $this->data = ['user_id' => 2]; } },
            $mockSitemap
        );
    }

    public function testSafePreseedingChronologicalAsc(): void
    {
        $event = new \ArrayObject([
            'topic_data' => [
                'topic_id'                 => 100,
                'forum_id'                 => 5,
                'topic_posts_unapproved'   => 0,
                'topic_posts_softdeleted'  => 0,
            ],
            'sort_dir'   => 'a',
            'sort_key'   => 't',
            'start'      => 40,
            'rowset'     => [
                ['post_id' => 501, 'topic_id' => 100, 'post_visibility' => 1],
                ['post_id' => 502, 'topic_id' => 100, 'post_visibility' => 1],
                ['post_id' => 503, 'topic_id' => 100, 'post_visibility' => 1],
            ],
        ]);

        $this->listener->onViewTopicPosts($event);

        $pos1 = $this->entityContext->getPostPosition(501);
        $pos2 = $this->entityContext->getPostPosition(502);
        $pos3 = $this->entityContext->getPostPosition(503);

        $this->assertNotNull($pos1);
        $this->assertSame(40, $pos1['prev_posts']);
        $this->assertSame(100, $pos1['topic_id']);

        $this->assertNotNull($pos2);
        $this->assertSame(41, $pos2['prev_posts']);

        $this->assertNotNull($pos3);
        $this->assertSame(42, $pos3['prev_posts']);
    }

    public function testSkipPreseedingOnDescSort(): void
    {
        $event = new \ArrayObject([
            'topic_data' => ['topic_id' => 100, 'forum_id' => 5, 'topic_posts_unapproved' => 0, 'topic_posts_softdeleted' => 0],
            'sort_dir'   => 'd',
            'sort_key'   => 't',
            'start'      => 0,
            'rowset'     => [['post_id' => 501, 'topic_id' => 100, 'post_visibility' => 1]],
        ]);

        $this->listener->onViewTopicPosts($event);
        $this->assertNull($this->entityContext->getPostPosition(501));
    }

    public function testSkipPreseedingOnUnapprovedPosts(): void
    {
        $event = new \ArrayObject([
            'topic_data' => ['topic_id' => 100, 'forum_id' => 5, 'topic_posts_unapproved' => 1, 'topic_posts_softdeleted' => 0],
            'sort_dir'   => 'a',
            'sort_key'   => 't',
            'start'      => 0,
            'rowset'     => [['post_id' => 501, 'topic_id' => 100, 'post_visibility' => 1]],
        ]);

        $this->listener->onViewTopicPosts($event);
        $this->assertNull($this->entityContext->getPostPosition(501));
    }

    public function testSkipPreseedingOnSoftDeletedPosts(): void
    {
        $event = new \ArrayObject([
            'topic_data' => ['topic_id' => 100, 'forum_id' => 5, 'topic_posts_unapproved' => 0, 'topic_posts_softdeleted' => 2],
            'sort_dir'   => 'a',
            'sort_key'   => 't',
            'start'      => 0,
            'rowset'     => [['post_id' => 501, 'topic_id' => 100, 'post_visibility' => 1]],
        ]);

        $this->listener->onViewTopicPosts($event);
        $this->assertNull($this->entityContext->getPostPosition(501));
    }
}