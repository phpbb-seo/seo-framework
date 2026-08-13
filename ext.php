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

                // Thin lifecycle adapter: Delegate route compilation to RouteCacheCompiler
                try {
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
                } catch (\Throwable) {
                    // Fallback to safe core defaults if services not yet initialized
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

                // Remove compiled route cache so rewrite.php halts inbound SEO interception
                $cacheFile = $this->extension_path . 'store/compiled_routes.php';
                if (file_exists($cacheFile)) {
                    @unlink($cacheFile);
                }

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
