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
        if (!isset($this->topicTitles[$topicId])) {
            $fetched = $this->slugRepository->fetchSlugsBatch('topic', [$topicId]);
            if (isset($fetched[$topicId])) {
                $this->topicTitles[$topicId] = $fetched[$topicId];
            }
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
        if (!isset($this->forumNames[$forumId])) {
            $fetched = $this->slugRepository->fetchSlugsBatch('forum', [$forumId]);
            if (isset($fetched[$forumId])) {
                $this->forumNames[$forumId] = $fetched[$forumId];
            }
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
        if (!isset($this->usernames[$userId])) {
            $fetched = $this->slugRepository->fetchSlugsBatch('member', [$userId]);
            if (isset($fetched[$userId])) {
                $this->usernames[$userId] = $fetched[$userId];
            }
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
        if (!isset($this->groupNames[$groupId])) {
            $fetched = $this->slugRepository->fetchSlugsBatch('group', [$groupId]);
            if (isset($fetched[$groupId])) {
                $this->groupNames[$groupId] = $fetched[$groupId];
            }
        }
        return $this->groupNames[$groupId] ?? null;
    }
}
