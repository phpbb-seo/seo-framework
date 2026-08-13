<?php
declare(strict_types=1);

namespace phpbbseo\framework\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Metadata\MetadataResolver;
use phpbbseo\framework\Metadata\MetadataContext;
use phpbb\template\template;
use phpbb\config\config;
use phpbb\request\request_interface;

/**
 * Event listener that intercepts phpBB page header generation and injects
 * resolved SEO titles and meta descriptions.
 */
class MetadataListener implements EventSubscriberInterface
{
    private array $entityContextData = [];

    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly MetadataResolver $resolver,
        private readonly template $template,
        private readonly config $config,
        private readonly request_interface $request
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'core.viewtopic_modify_page_title' => 'onViewtopicModifyTitle',
            'core.viewforum_modify_page_title' => 'onViewforumModifyTitle',
            'core.memberlist_view_profile'     => 'onMemberlistViewProfile',
            'core.page_header'                 => 'onPageHeader',
            'core.page_header_after'           => 'onPageHeaderAfter',
        ];
    }

    public function onViewtopicModifyTitle(\phpbb\event\data $event): void
    {
        $topicData = $event['topic_data'] ?? [];
        if (!empty($topicData)) {
            $this->entityContextData = [
                'type'                => 'topic',
                'id'                  => (int) ($topicData['topic_id'] ?? 0),
                'topic_title'         => (string) ($topicData['topic_title'] ?? ''),
                'topic_first_post_id' => (int) ($topicData['topic_first_post_id'] ?? 0),
                'forum_name'          => (string) ($topicData['forum_name'] ?? ''),
                'forum_id'            => (int) ($topicData['forum_id'] ?? 0),
                'post_text'           => (string) ($event['post_text'] ?? ''),
            ];
        }
    }

    public function onViewforumModifyTitle(\phpbb\event\data $event): void
    {
        $forumData = $event['forum_data'] ?? [];
        if (!empty($forumData)) {
            $this->entityContextData = [
                'type'       => 'forum',
                'id'         => (int) ($forumData['forum_id'] ?? 0),
                'forum_name' => (string) ($forumData['forum_name'] ?? ''),
                'forum_desc' => (string) ($forumData['forum_desc'] ?? ''),
            ];
        }
    }

    public function onMemberlistViewProfile(\phpbb\event\data $event): void
    {
        $userData = $event['data'] ?? [];
        if (!empty($userData)) {
            $this->entityContextData = [
                'type'     => 'member',
                'id'       => (int) ($userData['user_id'] ?? 0),
                'username' => (string) ($userData['username'] ?? ''),
                'user_sig' => (string) ($userData['user_sig'] ?? ''),
            ];
        }
    }

    public function onPageHeader(\phpbb\event\data $event): void
    {
        if (!$this->configProvider->isEnabled() || $this->configProvider->get('seo_meta_enable', '1') !== '1') {
            return;
        }

        try {
            $context = $this->buildMetadataContext();
            if ($context === null) {
                return;
            }

            $result = $this->resolver->resolve($context);

            // Update phpBB page_title event data
            $event['page_title'] = $result->title;

            // Assign template variables (Single HTML escaping at template boundary)
            $this->template->assign_vars([
                'PAGE_TITLE'             => $result->title,
                'S_SEO_META_DESCRIPTION' => $result->hasDescription(),
                'SEO_META_DESCRIPTION'   => htmlspecialchars($result->description, ENT_QUOTES, 'UTF-8'),
            ]);

            // Register clean output buffer filter to ensure exact title output without phpBB template duplication
            $finalEscapedTitle = htmlspecialchars($result->title, ENT_QUOTES, 'UTF-8');
            ob_start(function ($buffer) use ($finalEscapedTitle) {
                return preg_replace_callback('#<title>(.*?)</title>#si', function ($matches) use ($finalEscapedTitle) {
                    $prefix = '';
                    if (preg_match('#^(\(\d+\)\s*)#u', trim($matches[1]), $pMatch)) {
                        $prefix = $pMatch[1];
                    }
                    return '<title>' . $prefix . $finalEscapedTitle . '</title>';
                }, $buffer, 1);
            });
        } catch (\Throwable) {
            // Fail-safe: Never break page header on metadata exception
        }
    }

    public function onPageHeaderAfter(\phpbb\event\data $event): void
    {
        // Re-affirm PAGE_TITLE if assigned
    }

    private function buildMetadataContext(): ?MetadataContext
    {
        $boardName = (string) ($this->config['sitename'] ?? 'phpBB');
        $siteDesc  = (string) ($this->config['site_desc'] ?? '');

        $start = $this->request->variable('start', 0);
        $limit = (int) ($this->config['posts_per_page'] ?? 10);
        $pageNumber = ($start > 0 && $limit > 0) ? (int) floor($start / $limit) + 1 : 1;

        if (!empty($this->entityContextData)) {
            $type = $this->entityContextData['type'] ?? 'other';
            $id   = (int) ($this->entityContextData['id'] ?? 0);
            return new MetadataContext($type, $id, $this->entityContextData, $pageNumber, $boardName, $siteDesc);
        }

        // Determine if Board Index / Home
        $scriptName = basename($this->request->server('SCRIPT_NAME', ''));
        if ($scriptName === 'index.php' || $this->request->is_set('index')) {
            return new MetadataContext('home', 0, [], 1, $boardName, $siteDesc);
        }

        return null;
    }
}
