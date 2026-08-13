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
            $slugs[(int) $row['resource_id']] = (string) $row['slug'];
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
            $slugs[(int) $row['resource_id']] = (string) $row['slug'];
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
        if (empty($postIds)) {
            return [];
        }

        $postIds = array_unique(array_map('intval', $postIds));

        $sql = 'SELECT post_id, topic_id
            FROM ' . POSTS_TABLE . '
            WHERE ' . $this->db->sql_in_set('post_id', $postIds);

        $result = $this->db->sql_query($sql);
        $mappings = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $mappings[(int) $row['post_id']] = (int) $row['topic_id'];
        }
        $this->db->sql_freeresult($result);

        return $mappings;
    }
}
