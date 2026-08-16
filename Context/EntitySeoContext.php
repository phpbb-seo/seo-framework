<?php
declare(strict_types=1);

namespace phpbbseo\framework\Context;

use phpbbseo\framework\Rewrite\SlugRepository;

/**
 * Request-scoped cache for entity metadata to prevent N+1 queries during SEO URL generation.
 * Supports transparent auto-recovery via SlugRepository for 100% resource coverage.
 */
class EntitySeoContext
{
    /** @var array<int, string> */
    private array $topicTitles = [];

    /** @var array<int, string> */
    private array $forumNames = [];

    /** @var array<int, string> */
    private array $usernames = [];

    /** @var array<int, string> */
    private array $groupNames = [];

    /** @var array<int, int> */
    private array $postToTopic = [];

    public function __construct(
        private readonly SlugRepository $slugRepository
    ) {}

    /**
     * Batch inject post_id => topic_id mappings.
     * @param array<int, int> $mappings Array of post_id => topic_id
     */
    public function setPostToTopic(array $mappings): void
    {
        foreach ($mappings as $postId => $topicId) {
            $this->postToTopic[(int) $postId] = (int) $topicId;
        }
    }

    public function getTopicIdForPost(int $postId): ?int
    {
        if (!array_key_exists($postId, $this->postToTopic)) {
            $map = $this->slugRepository->fetchPostToTopicBatch([$postId]);
            $this->postToTopic[$postId] = $map[$postId] ?? null;
        }
        return $this->postToTopic[$postId] ?? null;
    }

    /**
     * Batch inject topic titles.
     * @param array<int, string> $topics Array of topic_id => topic_title
     */
    public function setTopics(array $topics): void
    {
        foreach ($topics as $id => $title) {
            $this->topicTitles[(int) $id] = (string) $title;
        }
    }

    public function getTopicTitle(int $topicId): ?string
    {
        if (!array_key_exists($topicId, $this->topicTitles)) {
            $fetched = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
            $this->topicTitles[$topicId] = $fetched[$topicId] ?? null;
        }
        return $this->topicTitles[$topicId] ?? null;
    }

    /**
     * Batch inject forum names.
     * @param array<int, string> $forums Array of forum_id => forum_name
     */
    public function setForums(array $forums): void
    {
        foreach ($forums as $id => $name) {
            $this->forumNames[(int) $id] = (string) $name;
        }
    }

    public function getForumName(int $forumId): ?string
    {
        if (!array_key_exists($forumId, $this->forumNames)) {
            $fetched = $this->slugRepository->fetchSlugsBatch('forum', [$forumId]);
            $this->forumNames[$forumId] = $fetched[$forumId] ?? null;
        }
        return $this->forumNames[$forumId] ?? null;
    }

    /**
     * Batch inject usernames.
     * @param array<int, string> $members Array of user_id => username
     */
    public function setMembers(array $members): void
    {
        foreach ($members as $id => $username) {
            $this->usernames[(int) $id] = (string) $username;
        }
    }

    public function getUsername(int $userId): ?string
    {
        if (!array_key_exists($userId, $this->usernames)) {
            $fetched = $this->slugRepository->fetchSlugsBatch('member', [$userId]);
            $this->usernames[$userId] = $fetched[$userId] ?? null;
        }
        return $this->usernames[$userId] ?? null;
    }

    /**
     * Batch inject group names.
     * @param array<int, string> $groups Array of group_id => group_name
     */
    public function setGroups(array $groups): void
    {
        foreach ($groups as $id => $name) {
            $this->groupNames[(int) $id] = (string) $name;
        }
    }

    public function getGroupName(int $groupId): ?string
    {
        if (!array_key_exists($groupId, $this->groupNames)) {
            $fetched = $this->slugRepository->fetchSlugsBatch('group', [$groupId]);
            $this->groupNames[$groupId] = $fetched[$groupId] ?? null;
        }
        return $this->groupNames[$groupId] ?? null;
    }

    /**
     * Preload and cache a list of topic IDs in a single batch query.
     * Optional developer helper for heavy custom extensions / widgets.
     *
     * @param array<int> $topicIds
     */
    public function preloadTopics(array $topicIds): void
    {
        $missing = [];
        foreach ($topicIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !array_key_exists($id, $this->topicTitles)) {
                $missing[] = $id;
            }
        }
        if (!empty($missing)) {
            $fetched = $this->slugRepository->fetchSlugsBatch('topic', array_unique($missing));
            foreach ($missing as $id) {
                $this->topicTitles[$id] = $fetched[$id] ?? null;
            }
        }
    }

    /**
     * Preload and cache a list of post IDs to their owning topic IDs in a single batch query.
     * Optional developer helper for heavy custom extensions / widgets.
     *
     * @param array<int> $postIds
     */
    public function preloadPosts(array $postIds): void
    {
        $missing = [];
        foreach ($postIds as $id) {
            $id = (int) $id;
            if ($id > 0 && !array_key_exists($id, $this->postToTopic)) {
                $missing[] = $id;
            }
        }
        if (!empty($missing)) {
            $map = $this->slugRepository->fetchPostToTopicBatch(array_unique($missing));
            foreach ($missing as $id) {
                $this->postToTopic[$id] = $map[$id] ?? null;
            }
            // Preload the resolved topic slugs in batch to guarantee 0-SQL topic generation
            $resolvedTopicIds = array_filter(array_values($map));
            if (!empty($resolvedTopicIds)) {
                $this->preloadTopics($resolvedTopicIds);
            }
        }
    }
}
