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

class SeoListenerSitemapInvalidationTest extends TestCase
{
    private function createListener(SpySitemapRepository $spySitemap, SpySlugRepositoryForListener $spySlug): SeoListener
    {
        $mockContextFactory = new class extends RequestContextFactory {
            public function __construct() {}
        };
        $mockEntityContext = new EntitySeoContext();
        $mockInbound = new class extends InboundRouteResolver {
            public function __construct() {}
        };
        $mockUrlResolver = new class extends PublicResourceUrlResolver {
            public function __construct() {}
        };
        $mockConfig = new class extends ConfigurationProvider {
            public function __construct() {}
        };
        $mockCanonical = new class extends CanonicalResolver {
            public function __construct() {}
        };
        $mockRedirect = new class extends RedirectResolver {
            public function __construct() {}
        };
        $mockSafety = new class extends UrlSafetyValidator {
            public function __construct() {}
        };
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
        $mockPagination = new class extends PaginationResolver {
            public function __construct() {}
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
        $mockUser = new class extends \phpbb\user {
            public function __construct() { $this->data = ['user_id' => 2]; }
        };

        return new SeoListener(
            $mockContextFactory,
            $mockEntityContext,
            $mockInbound,
            $mockUrlResolver,
            $mockConfig,
            $mockCanonical,
            $mockRedirect,
            $mockSafety,
            $mockRequest,
            $spySlug,
            $mockPagination,
            $mockTemplate,
            $mockUser,
            $spySitemap
        );
    }

    public function testSubmitPostEndCallsPurgeStatsCacheOnNewTopicFirstPost(): void
    {
        $spySitemap = new SpySitemapRepository();
        $spySlug = new SpySlugRepositoryForListener();
        $listener = $this->createListener($spySitemap, $spySlug);

        $event = [
            'mode'    => 'post',
            'subject' => 'My New Public Topic',
            'data'    => [
                'topic_id'            => 10,
                'post_id'             => 1,
                'topic_first_post_id' => 1,
            ],
        ];

        $listener->onSubmitPostEnd($event);

        $this->assertSame(1, $spySlug->saveCalls);
        $this->assertSame([['topic', 10, 'My New Public Topic']], $spySlug->savedSlugs);
        $this->assertSame(1, $spySitemap->purgeCalls, 'purgeStatsCache must be called exactly once when creating a new topic');
    }

    public function testSubmitPostEndDoesNotCallPurgeOnReply(): void
    {
        $spySitemap = new SpySitemapRepository();
        $spySlug = new SpySlugRepositoryForListener();
        $listener = $this->createListener($spySitemap, $spySlug);

        $event = [
            'mode'    => 'reply',
            'subject' => 'Re: My New Public Topic',
            'data'    => [
                'topic_id'            => 10,
                'post_id'             => 2,
                'topic_first_post_id' => 1,
            ],
        ];

        $listener->onSubmitPostEnd($event);

        $this->assertSame(0, $spySlug->saveCalls);
        $this->assertSame(0, $spySitemap->purgeCalls, 'purgeStatsCache must NOT be called on reply');
    }

    public function testDeleteTopicsAfterCallsPurgeStatsCacheOnce(): void
    {
        $spySitemap = new SpySitemapRepository();
        $spySlug = new SpySlugRepositoryForListener();
        $listener = $this->createListener($spySitemap, $spySlug);

        $event = [
            'topic_ids' => [10, 11, 12],
        ];

        $listener->onDeleteTopicsAfter($event);

        $this->assertSame(3, $spySlug->deleteCalls);
        $this->assertSame(1, $spySitemap->purgeCalls, 'purgeStatsCache must be called exactly once on topic batch deletion');
    }

    public function testApproveTopicsAfterPersistsSlugAndPurgesCache(): void
    {
        $spySitemap = new SpySitemapRepository();
        $spySlug = new SpySlugRepositoryForListener();
        $listener = $this->createListener($spySitemap, $spySlug);

        $event = [
            'action'     => 'approve',
            'topic_info' => [
                ['topic_id' => 50, 'topic_title' => 'Moderated Topic 50'],
                ['topic_id' => 51, 'topic_title' => 'Moderated Topic 51'],
            ],
        ];

        $listener->onApproveTopicsAfter($event);

        $this->assertSame(2, $spySlug->saveCalls);
        $this->assertSame([
            ['topic', 50, 'Moderated Topic 50'],
            ['topic', 51, 'Moderated Topic 51'],
        ], $spySlug->savedSlugs);
        $this->assertSame(1, $spySitemap->purgeCalls, 'purgeStatsCache must be called exactly once when approving topics');
    }

    public function testApprovePostsAfterPurgesCacheWhenTopicIsApproved(): void
    {
        $spySitemap = new SpySitemapRepository();
        $spySlug = new SpySlugRepositoryForListener();
        $listener = $this->createListener($spySitemap, $spySlug);

        $event = [
            'action'     => 'approve',
            'num_topics' => 1,
            'topic_info' => [
                ['topic_id' => 70, 'topic_title' => 'Topic From First Post Approval'],
            ],
        ];

        $listener->onApprovePostsAfter($event);

        $this->assertSame(1, $spySlug->saveCalls);
        $this->assertSame(1, $spySitemap->purgeCalls, 'purgeStatsCache must be called exactly once on post approval creating topic');
    }
}

class SpySitemapRepository extends SitemapRepository
{
    public int $purgeCalls = 0;

    public function __construct() {}

    public function purgeStatsCache(?int $chunkSize = null): void
    {
        $this->purgeCalls++;
    }
}

class SpySlugRepositoryForListener extends SlugRepository
{
    public int $saveCalls = 0;
    public int $deleteCalls = 0;
    public array $savedSlugs = [];

    public function __construct() {}

    public function saveSlug(string $type, int $id, string $name, int $updatedAt = 0): void
    {
        $this->saveCalls++;
        $this->savedSlugs[] = [$type, $id, $name];
    }

    public function deleteSlug(string $type, int $id): void
    {
        $this->deleteCalls++;
    }
}
