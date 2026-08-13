<?php
declare(strict_types=1);

namespace phpbbseo\framework\Metadata;

use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbb\user;
use phpbb\db\driver\driver_interface;

/**
 * Authoritative metadata resolution engine for Home, Forum, Topic, and Member Profile.
 */
class MetadataResolver
{
    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly MetadataPatternRenderer $patternRenderer,
        private readonly PlainTextNormalizer $normalizer,
        private readonly user $user,
        private readonly ?driver_interface $db = null
    ) {}

    /**
     * Resolves metadata for the given context.
     *
     * @param MetadataContext $context
     * @return MetadataResult
     */
    public function resolve(MetadataContext $context): MetadataResult
    {
        $maxDescLen = (int) $this->configProvider->get('seo_meta_desc_max_len', '155');
        if ($maxDescLen <= 0) {
            $maxDescLen = 155;
        }

        $pageLabel = '';
        if ($context->pageNumber > 1) {
            if (isset($this->user->lang['PAGE_NUMBER'])) {
                $pageLabel = sprintf($this->user->lang['PAGE_NUMBER'], $context->pageNumber);
            } elseif (isset($this->user->lang['PAGE'])) {
                $pageLabel = $this->user->lang['PAGE'] . ' ' . $context->pageNumber;
            } else {
                $pageLabel = 'Page ' . $context->pageNumber;
            }
        }

        $globalTokens = [
            'board_name' => $context->boardName,
            'site_desc'  => $context->siteDesc,
        ];

        return match ($context->resourceType) {
            'home'   => $this->resolveHome($context, $globalTokens, $pageLabel, $maxDescLen),
            'forum'  => $this->resolveForum($context, $globalTokens, $pageLabel, $maxDescLen),
            'topic'  => $this->resolveTopic($context, $globalTokens, $pageLabel, $maxDescLen),
            'member' => $this->resolveMember($context, $globalTokens, $pageLabel, $maxDescLen),
            default  => new MetadataResult($context->boardName, ''),
        };
    }

    private function resolveHome(MetadataContext $context, array $globalTokens, string $pageLabel, int $maxDescLen): MetadataResult
    {
        $titlePattern = (string) $this->configProvider->get('seo_meta_home_title', '{board_name}');
        $descPattern  = (string) $this->configProvider->get('seo_meta_home_desc', '{site_desc}');

        $title = $this->patternRenderer->render($titlePattern, $globalTokens, $context->pageNumber, $pageLabel);
        $descRaw = $this->patternRenderer->render($descPattern, $globalTokens, 1, '');
        $desc = $this->normalizer->normalize($descRaw, $maxDescLen);

        return new MetadataResult($title, $desc);
    }

    private function resolveForum(MetadataContext $context, array $globalTokens, string $pageLabel, int $maxDescLen): MetadataResult
    {
        $titlePattern = (string) $this->configProvider->get('seo_meta_forum_title', '{forum_name} - {board_name}');
        $forumTokens = array_merge($globalTokens, [
            'forum_name' => $context->entityData['forum_name'] ?? '',
            'forum_id'   => $context->resourceId,
        ]);

        $title = $this->patternRenderer->render($titlePattern, $forumTokens, $context->pageNumber, $pageLabel);

        $forumDesc = (string) ($context->entityData['forum_desc'] ?? '');
        $desc = $this->normalizer->normalize($forumDesc, $maxDescLen);

        return new MetadataResult($title, $desc);
    }

    private function resolveTopic(MetadataContext $context, array $globalTokens, string $pageLabel, int $maxDescLen): MetadataResult
    {
        $titlePattern = (string) $this->configProvider->get('seo_meta_topic_title', '{topic_title} - {board_name}');
        $topicTokens = array_merge($globalTokens, [
            'topic_title' => $context->entityData['topic_title'] ?? '',
            'topic_id'    => $context->resourceId,
            'forum_name'  => $context->entityData['forum_name'] ?? '',
        ]);

        $title = $this->patternRenderer->render($titlePattern, $topicTokens, $context->pageNumber, $pageLabel);

        // Fetch first-post content: Prefer data already present in context
        $postText = (string) ($context->entityData['post_text'] ?? '');
        if ($postText === '' && $context->resourceId > 0 && $this->db !== null) {
            $firstPostId = (int) ($context->entityData['topic_first_post_id'] ?? 0);
            if ($firstPostId > 0) {
                $sql = 'SELECT post_text FROM ' . POSTS_TABLE . ' WHERE post_id = ' . $firstPostId;
            } else {
                $sql = 'SELECT post_text FROM ' . POSTS_TABLE . ' WHERE topic_id = ' . (int) $context->resourceId . ' ORDER BY post_id ASC';
            }
            $result = $this->db->sql_query_limit($sql, 1);
            $row = $this->db->sql_fetchrow($result);
            $this->db->sql_freeresult($result);

            if ($row && !empty($row['post_text'])) {
                $postText = (string) $row['post_text'];
            }
        }

        $desc = $this->normalizer->normalize($postText, $maxDescLen);

        return new MetadataResult($title, $desc);
    }

    private function resolveMember(MetadataContext $context, array $globalTokens, string $pageLabel, int $maxDescLen): MetadataResult
    {
        $titlePattern = (string) $this->configProvider->get('seo_meta_member_title', '{username} - {board_name}');
        $memberTokens = array_merge($globalTokens, [
            'username' => $context->entityData['username'] ?? '',
            'user_id'  => $context->resourceId,
        ]);

        $title = $this->patternRenderer->render($titlePattern, $memberTokens, $context->pageNumber, $pageLabel);

        // Member description policy: Only output if meaningful safe public information is available
        $descRaw = (string) ($context->entityData['user_sig'] ?? '');
        $desc = $this->normalizer->normalize($descRaw, $maxDescLen);

        return new MetadataResult($title, $desc);
    }
}
