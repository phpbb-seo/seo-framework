<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use phpbb\db\driver\driver_interface;
use phpbbseo\framework\Url\SlugGeneratorInterface;

/**
 * Handles database operations for the framework-owned rebuildable slug read-model table.
 */
class SlugRepository
{
    /**
     * Cache flag indicating whether FORCE INDEX is supported and valid on the active database.
     * If forcing tid_post_time fails (e.g. custom schema missing the index), this flips to false
     * to avoid repeating failed index hints and immediately use the plain query.
     */
    private static bool $useForceIndex = true;

    public static function resetForceIndexState(): void
    {
        self::$useForceIndex = true;
    }

    public function __construct(
        private readonly driver_interface $db,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly string $tablePrefix
    ) {}

    public function localizeGroupName(string $name): string
    {
        if (!str_starts_with($name, 'G_')) {
            return $name;
        }

        global $user;
        if ($user !== null && isset($user->lang) && is_object($user) && method_exists($user, 'lang')) {
            $translated = $user->lang($name);
            if (is_string($translated) && $translated !== '' && $translated !== $name) {
                return $translated;
            }
        }

        $defaultGroupMap = [
            'G_ADMINISTRATORS'       => 'Administrators',
            'G_GLOBAL_MODERATORS'    => 'Global Moderators',
            'G_REGISTERED'          => 'Registered Users',
            'G_REGISTERED_COPPA'    => 'Registered COPPA Users',
            'G_GUESTS'              => 'Guests',
            'G_BOTS'                => 'Bots',
            'G_NEWLY_REGISTERED'    => 'Newly Registered Users',
        ];

        return $defaultGroupMap[$name] ?? (substr($name, 0, 2) === 'G_' ? substr($name, 2) : $name);
    }

    /**
     * Store a slug in the database.
     */
    public function saveSlug(string $type, int $id, string $name, int $updatedAt = 0): void
    {
        $numericType = ResourceType::fromString($type);
        if ($numericType === 0) {
            return;
        }

        if ($type === 'group') {
            $name = $this->localizeGroupName($name);
        }

        $slug = $this->slugGenerator->generate($name);

        $sql = 'REPLACE INTO ' . $this->tablePrefix . 'seo_slugs ' .
            $this->db->sql_build_array('INSERT', [
                'resource_type' => $numericType,
                'resource_id'   => $id,
                'slug'          => $slug,
                'updated_at'    => $updatedAt,
            ]);

        $this->db->sql_query($sql);
    }

    /**
     * Delete a slug from the database.
     */
    public function deleteSlug(string $type, int $id): void
    {
        $numericType = ResourceType::fromString($type);
        if ($numericType === 0) {
            return;
        }

        $sql = 'DELETE FROM ' . $this->tablePrefix . 'seo_slugs
            WHERE resource_type = ' . (int) $numericType . '
                AND resource_id = ' . (int) $id;

        $this->db->sql_query($sql);
    }

    /**
     * Batch fetch slugs from the persistent database table.
     * Returns an array of [id => slug].
     *
     * @param string $type The resource type ('forum', 'topic', 'member', 'group')
     * @param int[] $ids List of resource identifiers
     * @return array<int, string>
     */
    public function fetchSlugsBatch(string $type, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $numericType = ResourceType::fromString($type);
        if ($numericType === 0) {
            return [];
        }

        // De-duplicate and cast to integers
        $ids = array_unique(array_map('intval', $ids));

        $sql = 'SELECT resource_id, slug
            FROM ' . $this->tablePrefix . 'seo_slugs
            WHERE resource_type = ' . (int) $numericType . '
                AND ' . $this->db->sql_in_set('resource_id', $ids);

        $result = $this->db->sql_query($sql);
        $slugs = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $slugs[(int) $row['resource_id']] = $this->slugGenerator->generate((string) $row['slug']);
        }
        $this->db->sql_freeresult($result);

        // Auto-recover any missing entity slugs from core phpBB tables
        $missingIds = array_diff($ids, array_keys($slugs));
        if (!empty($missingIds)) {
            $recovered = $this->resolveAndSaveMissingSlugs($type, $missingIds);
            foreach ($recovered as $mId => $mSlug) {
                $slugs[$mId] = $mSlug;
            }
        }

        return $slugs;
    }

    /**
     * Fetch entity names from core phpBB tables for missing IDs, save their slugs, and return [id => slug].
     *
     * @param string $type
     * @param int[] $ids
     * @return array<int, string>
     */
    private function resolveAndSaveMissingSlugs(string $type, array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $recovered = [];
        switch ($type) {
            case 'forum':
                $sql = 'SELECT forum_id AS id, forum_name AS name FROM ' . FORUMS_TABLE . '
                    WHERE ' . $this->db->sql_in_set('forum_id', $ids);
                break;

            case 'topic':
                $sql = 'SELECT topic_id AS id, topic_title AS name, topic_time FROM ' . TOPICS_TABLE . '
                    WHERE ' . $this->db->sql_in_set('topic_id', $ids);
                break;

            case 'member':
                $sql = 'SELECT user_id AS id, username AS name FROM ' . USERS_TABLE . '
                    WHERE user_type IN (0, 3) AND ' . $this->db->sql_in_set('user_id', $ids);
                break;

            case 'group':
                $sql = 'SELECT group_id AS id, group_name AS name FROM ' . GROUPS_TABLE . '
                    WHERE ' . $this->db->sql_in_set('group_id', $ids);
                break;

            default:
                return [];
        }

        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $id = (int) $row['id'];
            $name = (string) $row['name'];
            $updatedAt = isset($row['topic_time']) ? (int) $row['topic_time'] : 0;

            if ($type === 'group' && str_starts_with($name, 'G_')) {
                global $user;
                if ($user !== null) {
                    $name = $user->lang($name);
                }
            }

            $this->saveSlug($type, $id, $name, $updatedAt);
            $recovered[$id] = $this->slugGenerator->generate($name);
        }
        $this->db->sql_freeresult($result);

        return $recovered;
    }

    /**
     * Fetch all slugs of a given type from the database.
     * Used for warming static lists (forums/groups) during request initialization.
     *
     * @return array<int, string>
     */
    public function fetchAllSlugs(string $type): array
    {
        $numericType = ResourceType::fromString($type);
        if ($numericType === 0) {
            return [];
        }

        $sql = 'SELECT resource_id, slug
            FROM ' . $this->tablePrefix . 'seo_slugs
            WHERE resource_type = ' . (int) $numericType;

        $result = $this->db->sql_query($sql);
        $slugs = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $slugs[(int) $row['resource_id']] = $this->slugGenerator->generate((string) $row['slug']);
        }
        $this->db->sql_freeresult($result);

        return $slugs;
    }

    /**
     * Rebuild slugs for all public entities of a given type.
     * Uses the DefaultSlugGenerator PHP service.
     */
    public function rebuildSlugs(string $type): void
    {
        switch ($type) {
            case 'forum':
                $this->rebuildForums();
                break;
            case 'topic':
                $this->rebuildTopics();
                break;
            case 'member':
                $this->rebuildMembers();
                break;
            case 'group':
                $this->rebuildGroups();
                break;
        }
    }

    private function rebuildForums(): void
    {
        $sql = 'SELECT forum_id, forum_name FROM ' . FORUMS_TABLE;
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $this->saveSlug('forum', (int) $row['forum_id'], (string) $row['forum_name']);
        }
        $this->db->sql_freeresult($result);
    }

    private function rebuildTopics(): void
    {
        // Batch query to prevent memory limits
        $start = 0;
        $limit = 1000;
        do {
            $sql = 'SELECT topic_id, topic_title, topic_time FROM ' . TOPICS_TABLE . ' ORDER BY topic_id ASC';
            $result = $this->db->sql_query_limit($sql, $limit, $start);
            $count = 0;
            while ($row = $this->db->sql_fetchrow($result)) {
                $count++;
                $this->saveSlug('topic', (int) $row['topic_id'], (string) $row['topic_title'], (int) $row['topic_time']);
            }
            $this->db->sql_freeresult($result);
            $start += $limit;
        } while ($count === $limit);
    }

    private function rebuildMembers(): void
    {
        $start = 0;
        $limit = 1000;
        do {
            $sql = 'SELECT user_id, username FROM ' . USERS_TABLE . ' ORDER BY user_id ASC';
            $result = $this->db->sql_query_limit($sql, $limit, $start);
            $count = 0;
            while ($row = $this->db->sql_fetchrow($result)) {
                $count++;
                // Skip anonymous
                $userId = (int) $row['user_id'];
                if ($userId > 1) {
                    $this->saveSlug('member', $userId, (string) $row['username']);
                }
            }
            $this->db->sql_freeresult($result);
            $start += $limit;
        } while ($count === $limit);
    }

    private function rebuildGroups(): void
    {
        global $user; // Inject or access the user translation context

        $sql = 'SELECT group_id, group_name FROM ' . GROUPS_TABLE;
        $result = $this->db->sql_query($sql);
        while ($row = $this->db->sql_fetchrow($result)) {
            $groupName = (string) $row['group_name'];
            $groupId = (int) $row['group_id'];

            // Handle localized default groups deterministically using board default language label
            // In phpBB, standard groups are prefixed with "G_" (e.g. G_ADMINISTRATORS)
            if (str_starts_with($groupName, 'G_') && $user !== null) {
                $groupName = $user->lang($groupName);
            }

            $this->saveSlug('group', $groupId, $groupName);
        }
        $this->db->sql_freeresult($result);
    }

    /**
     * Update a user's slug by lookup on their new username.
     */
    public function updateUserSlug(string $newName): void
    {
        $sql = 'SELECT user_id FROM ' . USERS_TABLE . "
            WHERE username = '" . $this->db->sql_escape($newName) . "'";
        $result = $this->db->sql_query($sql);
        $row = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if ($row) {
            $userId = (int) $row['user_id'];
            if ($userId > 1) {
                $this->saveSlug('member', $userId, $newName);
            }
        }
    }

    /**
     * Batch fetch post-to-topic mappings.
     *
     * @param array<int> $postIds
     * @return array<int, int>
     */
    public function fetchPostToTopicBatch(array $postIds): array
    {
        return $this->fetchPostTopicDataBatch($postIds)['mappings'];
    }

    /**
     * Batch fetch post-to-topic mappings and post positions for first/last posts in 1 query.
     *
     * @param array<int> $postIds
     * @return array{mappings: array<int, int>, positions: array<int, array{topic_id: int, forum_id: int, prev_posts: int}>}
     */
    public function fetchPostTopicDataBatch(array $postIds): array
    {
        if (empty($postIds)) {
            return ['mappings' => [], 'positions' => []];
        }

        $postIds = array_unique(array_map('intval', $postIds));

        $sql = 'SELECT p.post_id, p.topic_id, p.forum_id, t.topic_first_post_id, t.topic_last_post_id, t.topic_posts_approved
            FROM ' . POSTS_TABLE . ' p
            LEFT JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = p.topic_id
            WHERE ' . $this->db->sql_in_set('p.post_id', $postIds);

        $result = $this->db->sql_query($sql);
        $mappings = [];
        $positions = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $postId = (int) $row['post_id'];
            $topicId = (int) $row['topic_id'];
            $forumId = (int) $row['forum_id'];
            $mappings[$postId] = $topicId;

            $lastPostId = isset($row['topic_last_post_id']) ? (int) $row['topic_last_post_id'] : 0;
            $firstPostId = isset($row['topic_first_post_id']) ? (int) $row['topic_first_post_id'] : 0;
            $postsApproved = isset($row['topic_posts_approved']) ? (int) $row['topic_posts_approved'] : 0;

            if ($lastPostId > 0 && $postId === $lastPostId && $postsApproved > 0) {
                $positions[$postId] = [
                    'topic_id'   => $topicId,
                    'forum_id'   => $forumId,
                    'prev_posts' => max(0, $postsApproved - 1),
                ];
            } elseif ($firstPostId > 0 && $postId === $firstPostId) {
                $positions[$postId] = [
                    'topic_id'   => $topicId,
                    'forum_id'   => $forumId,
                    'prev_posts' => 0,
                ];
            }
        }
        $this->db->sql_freeresult($result);

        return [
            'mappings'  => $mappings,
            'positions' => $positions,
        ];
    }

    /**
     * Fetch post position metadata (topic_id, forum_id, prev_posts) for canonical pagination.
     * Uses fast-path for first and last posts without running COUNT queries.
     *
     * @param int $postId
     * @return array{topic_id: int, forum_id: int, prev_posts: int}|null
     */
    public function fetchPostPosition(int $postId): ?array
    {
        if ($postId <= 0) {
            return null;
        }

        $sql = 'SELECT p.post_id, p.topic_id, p.forum_id, p.post_time, p.post_visibility,
                       t.topic_first_post_id, t.topic_last_post_id, t.topic_posts_approved
            FROM ' . POSTS_TABLE . ' p
            LEFT JOIN ' . TOPICS_TABLE . ' t ON t.topic_id = p.topic_id
            WHERE p.post_id = ' . (int) $postId;
        $result = $this->db->sql_query($sql);
        $post = $this->db->sql_fetchrow($result);
        $this->db->sql_freeresult($result);

        if (!$post) {
            return null;
        }

        $topicId = (int) $post['topic_id'];
        $forumId = (int) $post['forum_id'];
        $firstPostId = isset($post['topic_first_post_id']) ? (int) $post['topic_first_post_id'] : 0;
        $lastPostId = isset($post['topic_last_post_id']) ? (int) $post['topic_last_post_id'] : 0;
        $postsApproved = isset($post['topic_posts_approved']) ? (int) $post['topic_posts_approved'] : 0;

        // Fast path 1: First post in topic is always on page 1 (prev_posts = 0)
        if ($firstPostId > 0 && $postId === $firstPostId) {
            return [
                'topic_id'   => $topicId,
                'forum_id'   => $forumId,
                'prev_posts' => 0,
            ];
        }

        // Fast path 2: Last post in topic has exactly (topic_posts_approved - 1) preceding posts
        if ($lastPostId > 0 && $postId === $lastPostId && $postsApproved > 0) {
            return [
                'topic_id'   => $topicId,
                'forum_id'   => $forumId,
                'prev_posts' => max(0, $postsApproved - 1),
            ];
        }

        // Slow path: Arbitrary historical post — count approved posts prior to this post
        $prevPosts = $this->countPrecedingPosts(
            $topicId,
            $forumId,
            (int) $post['post_time'],
            (int) $postId,
            (int) $post['post_visibility'] === 1
        );

        return [
            'topic_id'   => $topicId,
            'forum_id'   => $forumId,
            'prev_posts' => $prevPosts,
        ];
    }

    /**
     * Slow-path fallback: Count approved posts preceding an arbitrary historical post.
     *
     * Restructured into two unambiguous indexed queries to prevent MySQL query-plan
     * ambiguity (avoiding index_merge on large tables with composite tid_post_time index):
     *   1. Posts with post_time strictly less than target post.
     *   2. Posts with identical post_time and post_id <= target post (tiebreaker).
     */
    public function countPrecedingPosts(int $topicId, int $forumId, int $postTime, int $postId, bool $isApproved): int
    {
        $isMysql = (stripos($this->db->get_sql_layer(), 'mysql') !== false);
        $shouldForceIndex = $isMysql && self::$useForceIndex;

        $count1 = 0;
        $query1Succeeded = false;

        // 1. Attempt Query 1 with FORCE INDEX on MySQL/MariaDB to prevent index_merge
        if ($shouldForceIndex) {
            $sql1Forced = 'SELECT COUNT(p.post_id) AS prev_posts
                FROM ' . POSTS_TABLE . ' p FORCE INDEX (tid_post_time)
                WHERE p.topic_id = ' . (int) $topicId . '
                    AND p.post_visibility = 1
                    AND p.post_time < ' . (int) $postTime;

            $this->db->sql_return_on_error(true);
            try {
                $result1 = $this->db->sql_query($sql1Forced);
                if ($result1 !== false) {
                    $countRow1 = $this->db->sql_fetchrow($result1);
                    $this->db->sql_freeresult($result1);
                    $count1 = (int) ($countRow1['prev_posts'] ?? 0);
                    $query1Succeeded = true;
                } else {
                    // Forced index query failed (e.g. index tid_post_time does not exist)
                    self::$useForceIndex = false;
                }
            } catch (\Throwable) {
                // Caught under PHP 8.1+ mysqli error reporting mode
                self::$useForceIndex = false;
            } finally {
                $this->db->sql_return_on_error(false);
            }
        }

        // Fallback: Run plain query without index hint if FORCE INDEX was not attempted or failed
        if (!$query1Succeeded) {
            $sql1Plain = 'SELECT COUNT(p.post_id) AS prev_posts
                FROM ' . POSTS_TABLE . ' p
                WHERE p.topic_id = ' . (int) $topicId . '
                    AND p.post_visibility = 1
                    AND p.post_time < ' . (int) $postTime;
            $result1 = $this->db->sql_query($sql1Plain);
            $countRow1 = $this->db->sql_fetchrow($result1);
            $this->db->sql_freeresult($result1);
            $count1 = (int) ($countRow1['prev_posts'] ?? 0);
        }

        // 2. Count approved posts at the exact same timestamp with post_id <= target
        $sql2 = 'SELECT COUNT(p.post_id) AS prev_posts
            FROM ' . POSTS_TABLE . ' p
            WHERE p.topic_id = ' . (int) $topicId . '
                AND p.post_visibility = 1
                AND p.post_time = ' . (int) $postTime . '
                AND p.post_id <= ' . (int) $postId;
        $result2 = $this->db->sql_query($sql2);
        $countRow2 = $this->db->sql_fetchrow($result2);
        $this->db->sql_freeresult($result2);
        $count2 = (int) ($countRow2['prev_posts'] ?? 0);

        $totalCount = $count1 + $count2;
        return $isApproved ? max(0, $totalCount - 1) : $totalCount;
    }
}
