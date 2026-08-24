<?php
declare(strict_types=1);

namespace phpbbseo\framework\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Metadata\MetadataResolver;
use phpbbseo\framework\Metadata\MetadataContext;
use phpbbseo\framework\Canonical\CanonicalResolver;
use phpbbseo\framework\Context\RequestContextFactory;
use phpbb\template\template;
use phpbb\config\config;
use phpbb\request\request_interface;

/**
 * Event listener that intercepts phpBB page header generation and injects
 * structured, branded SEO titles, meta descriptions, and canonical links.
 */
class MetadataListener implements EventSubscriberInterface
{
    private array $entityContextData = [];

    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly MetadataResolver $resolver,
        private readonly template $template,
        private readonly config $config,
        private readonly request_interface $request,
        private readonly ?CanonicalResolver $canonicalResolver = null,
        private readonly ?RequestContextFactory $contextFactory = null
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            'core.viewtopic_modify_page_title'                  => 'onViewtopicModifyTitle',
            'core.viewforum_modify_page_title'                  => 'onViewforumModifyTitle',
            'core.memberlist_view_profile'                      => 'onMemberlistViewProfile',
            'core.memberlist_modify_view_profile_template_vars' => 'onMemberlistViewProfileVars',
            'core.page_header'                                  => 'onPageHeader',
            'core.page_header_after'                            => 'onPageHeaderAfter',
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
                'seo_title'           => (string) ($event['seo_title'] ?? ($topicData['seo_title'] ?? '')),
                'meta_description'    => (string) ($event['meta_description'] ?? ($topicData['meta_description'] ?? '')),
            ];
        }
    }

    public function onViewforumModifyTitle(\phpbb\event\data $event): void
    {
        $forumData = $event['forum_data'] ?? [];
        if (!empty($forumData)) {
            $this->entityContextData = [
                'type'             => 'forum',
                'id'               => (int) ($forumData['forum_id'] ?? 0),
                'forum_name'       => (string) ($forumData['forum_name'] ?? ''),
                'forum_desc'       => (string) ($forumData['forum_desc'] ?? ''),
                'seo_title'        => (string) ($event['seo_title'] ?? ($forumData['seo_title'] ?? '')),
                'meta_description' => (string) ($event['meta_description'] ?? ($forumData['meta_description'] ?? '')),
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

    public function onMemberlistViewProfileVars(\phpbb\event\data $event): void
    {
        $userId = (int) ($event['user_id'] ?? 0);
        if ($userId > 0) {
            $this->entityContextData = [
                'type'     => 'member',
                'id'       => $userId,
                'username' => '',
                'user_sig' => '',
            ];
        }
    }

    public function onPageHeader(\phpbb\event\data $event): void
    {
        if (defined('IN_ADMIN') || defined('ADMIN_START') || str_contains($this->request->server('SCRIPT_NAME', ''), '/adm/')) {
            return;
        }

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

            // Assign template variables
            $this->template->assign_vars([
                'PAGE_TITLE'             => $result->title,
                'S_SEO_META_DESCRIPTION' => $result->hasDescription(),
                'SEO_META_DESCRIPTION'   => htmlspecialchars($result->description, ENT_QUOTES, 'UTF-8'),
            ]);

            // Resolve canonical URL for branding block
            $canonicalUrl = null;
            $pageData = $event['page_data'] ?? [];
            if (!empty($pageData['U_CANONICAL'])) {
                $canonicalUrl = (string) $pageData['U_CANONICAL'];
            } elseif ($this->canonicalResolver !== null && $this->contextFactory !== null && $this->configProvider->isRewriteEnabled()) {
                $reqContext = $this->contextFactory->createFromPhpbbRequest($this->request);
                $canonicalUrl = $this->canonicalResolver->resolve($reqContext);
            }

            $finalEscapedTitle = htmlspecialchars($result->title, ENT_QUOTES, 'UTF-8');
            $finalEscapedDesc = $result->hasDescription() ? htmlspecialchars($result->description, ENT_QUOTES, 'UTF-8') : null;
            $finalEscapedCanonical = ($canonicalUrl !== null && $canonicalUrl !== '') ? htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') : null;

            // Register clean output buffer filter to structure branded SEO metadata in <head>
            ob_start(function ($buffer) use ($finalEscapedTitle, $finalEscapedDesc, $finalEscapedCanonical) {
                // Extract any application/ld+json script block if present (e.g. from Pro Schema)
                $jsonLdBlock = null;
                if (preg_match('#\s*(<script\s+type=["\']application/ld\+json["\']>.*?</script>)\s*#si', $buffer, $jsonMatches)) {
                    $jsonLdBlock = trim($jsonMatches[1]);
                    // Remove the standalone script block from the original location
                    $buffer = str_replace($jsonMatches[0], "\n", $buffer);
                }

                // Extract any Social SEO tags block if present (e.g. from Pro Social)
                $socialBlock = null;
                if (preg_match('#\s*(<!-- Social SEO by phpBB SEO Pro -->.*?<!-- /Social SEO by phpBB SEO Pro -->)\s*#si', $buffer, $socialMatches)) {
                    $socialBlock = trim($socialMatches[1]);
                    $buffer = str_replace($socialMatches[0], "\n", $buffer);
                }

                return preg_replace_callback('#<title>(.*?)</title>#si', function ($matches) use ($finalEscapedTitle, $finalEscapedDesc, $finalEscapedCanonical, $jsonLdBlock, $socialBlock) {
                    $prefix = '';
                    if (preg_match('#^(\(\d+\)\s*)#u', trim($matches[1]), $pMatch)) {
                        $prefix = $pMatch[1];
                    }

                    $lines = [];
                    $lines[] = '<!-- Search Engine Optimization by phpBB SEO Framework - https://www.phpbbseo.com/ -->';
                    $lines[] = '';
                    $lines[] = '<title>' . $prefix . $finalEscapedTitle . '</title>';
                    if ($finalEscapedDesc !== null && $finalEscapedDesc !== '') {
                        $lines[] = '<meta name="description" content="' . $finalEscapedDesc . '" />';
                    }
                    if ($finalEscapedCanonical !== null && $finalEscapedCanonical !== '') {
                        $lines[] = '<link rel="canonical" href="' . $finalEscapedCanonical . '" />';
                    }
                    if ($socialBlock !== null && $socialBlock !== '') {
                        $lines[] = '';
                        $lines[] = $socialBlock;
                    }
                    if ($jsonLdBlock !== null && $jsonLdBlock !== '') {
                        $lines[] = '';
                        $lines[] = $jsonLdBlock;
                    }
                    $lines[] = '';
                    $lines[] = '<!-- /phpBB SEO Framework -->';

                    return implode("\n", $lines);
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

        // Check if member profile page
        $mode = (string) $this->request->variable('mode', '', false, request_interface::GET);
        if ($mode === 'viewprofile') {
            $userId = (int) $this->request->variable('u', 0, false, request_interface::GET);
            if ($userId > 0) {
                return new MetadataContext('member', $userId, ['user_id' => $userId], $pageNumber, $boardName, $siteDesc);
            }
        }

        // Check if group page
        if ($mode === 'group') {
            $groupId = (int) $this->request->variable('g', 0, false, request_interface::GET);
            if ($groupId > 0) {
                return new MetadataContext('group', $groupId, ['group_id' => $groupId], $pageNumber, $boardName, $siteDesc);
            }
        }

        // Determine if Board Index / Home
        $scriptName = basename($this->request->server('SCRIPT_NAME', ''));
        if ($scriptName === 'index.php' || $this->request->is_set('index')) {
            return new MetadataContext('home', 0, [], 1, $boardName, $siteDesc);
        }

        return null;
    }
}
