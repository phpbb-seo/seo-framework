<?php
declare(strict_types=1);

namespace phpbbseo\framework;

class ext extends \phpbb\extension\base
{
    public function enable_step($old_state)
    {
        switch ($old_state) {
            case false:
                $container = $this->container;
                $config = $container->get('config');
                $config->set('phpbbseo_framework_enable', '1');

                $storeDir = $this->extension_path . 'store/';
                if (!is_dir($storeDir)) {
                    @mkdir($storeDir, 0755, true);
                }

                // Default routes fallback to ensure immediate routing availability
                $defaultRoutes = [
                    ['regex' => '#^/topic/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page/(?P<page>[0-9]+)/?$#u', 'resource' => 'topic', 'is_page' => true],
                    ['regex' => '#^/topic/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page-(?P<page>[0-9]+)/?$#u', 'resource' => 'topic', 'is_page' => true],
                    ['regex' => '#^/topic/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'topic', 'is_page' => false],
                    ['regex' => '#^/forum/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page/(?P<page>[0-9]+)/?$#u', 'resource' => 'forum', 'is_page' => true],
                    ['regex' => '#^/forum/(?P<slug>[^/]+)-(?P<id>[0-9]+)/page-(?P<page>[0-9]+)/?$#u', 'resource' => 'forum', 'is_page' => true],
                    ['regex' => '#^/forum/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'forum', 'is_page' => false],
                    ['regex' => '#^/member/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'member', 'is_page' => false],
                    ['regex' => '#^/group/(?P<slug>[^/]+)-(?P<id>[0-9]+)/?$#u', 'resource' => 'group', 'is_page' => false],
                ];

                @file_put_contents($storeDir . 'compiled_routes.php', "<?php\n// Auto-generated route cache.\ndeclare(strict_types=1);\n\nreturn " . var_export($defaultRoutes, true) . ";\n");

                // If container services are available, compile exact configured patterns
                try {
                    if ($container->has('phpbbseo.framework.rewrite.permalink_configuration') && $container->has('phpbbseo.framework.rewrite.route_cache_compiler')) {
                        /** @var \phpbbseo\framework\Rewrite\PermalinkConfiguration $permalinkConfig */
                        $permalinkConfig = $container->get('phpbbseo.framework.rewrite.permalink_configuration');
                        /** @var \phpbbseo\framework\Rewrite\RouteCacheCompiler $routeCompiler */
                        $routeCompiler = $container->get('phpbbseo.framework.rewrite.route_cache_compiler');

                        $patterns = [
                            'forum'      => $permalinkConfig->getPattern('forum'),
                            'forum_page' => $permalinkConfig->getPattern('forum_page'),
                            'topic'      => $permalinkConfig->getPattern('topic'),
                            'topic_page' => $permalinkConfig->getPattern('topic_page'),
                            'member'     => $permalinkConfig->getPattern('member'),
                            'group'      => $permalinkConfig->getPattern('group'),
                        ];

                        $routeCompiler->compileAndDump($patterns);
                    }
                } catch (\Throwable) {
                    // Safe core defaults are already written above
                }

                return 'all';

            default:
                return parent::enable_step($old_state);
        }
    }

    public function disable_step($old_state)
    {
        switch ($old_state) {
            case false:
                $container = $this->container;
                $config = $container->get('config');
                $config->set('phpbbseo_framework_enable', '0');

                // Write disabled marker so rewrite.php safely bypasses SEO interception without loops
                $storeDir = $this->extension_path . 'store/';
                if (!is_dir($storeDir)) {
                    @mkdir($storeDir, 0755, true);
                }
                $cacheFile = $storeDir . 'compiled_routes.php';
                @file_put_contents($cacheFile, "<?php\n// Disabled extension route cache marker.\ndeclare(strict_types=1);\n\nreturn ['__disabled' => true];\n");

                return 'all';

            default:
                return parent::disable_step($old_state);
        }
    }

    public function purge_step($old_state)
    {
        switch ($old_state) {
            case false:
                $cacheFile = $this->extension_path . 'store/compiled_routes.php';
                if (file_exists($cacheFile)) {
                    @unlink($cacheFile);
                }

                return 'all';

            default:
                return parent::purge_step($old_state);
        }
    }
}
