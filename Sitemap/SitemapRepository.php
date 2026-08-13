<?php
declare(strict_types=1);

namespace phpbbseo\framework\Sitemap;

use phpbb\db\driver\driver_interface;
use phpbb\auth\auth;
use phpbb\cache\service as cache_service;

/**
 * Bounded, high-performance database repository for XML Sitemap generation and statistics.
 * Strictly enforces anonymous guest ACL visibility and employs a cached topic boundary map
 * built via pure forward-keyset iteration to guarantee 100% ZERO OFFSET across the entire subsystem.
 */
class SitemapRepository
{
    private const STATS_CACHE_KEY = 'seo_sitemap_stats';
    private const STATS_CACHE_TTL = 600; // 10 minutes
    private const BOUNDARY_CACHE_KEY = 'seo_sitemap_topic_boundaries_%d';
    private const BOUNDARY_CACHE_TTL = 86400; // 24 hours
    private const BOUNDARY_BATCH_SIZE = 5000;

    private readonly string $forumsTable;
    private readonly string $topicsTable;
    private readonly string $slugsTable;

    public function __construct(
        private readonly driver_interface $db,
        private readonly auth $auth,
        private readonly cache_service $cache,
        string $tablePrefix
    ) {
        $this->forumsTable = $tablePrefix . 'forums';
        $this->topicsTable = $tablePrefix . 'topics';
        $this->slugsTable  = $tablePrefix . 'seo_slugs';
    }

    /**
     * Resolve forum IDs genuinely accessible to anonymous visitors (guests).
     * Strictly isolated from the current session user without mutating global state.
     * Excludes password-protected forums.
     *
     * @return int[]
     */
    public function getGuestAllowedForumIds(): array
    {
        // 1. Compute guest ACL in an isolated cloned auth container
        $guestAuth = clone $this->auth;
        $userData = $this->auth->obtain_user_data(ANONYMOUS);
        if (empty($userData)) {
            return [];
        }
        $guestAuth->acl($userData);
        $allowedForums = array_keys($guestAuth->acl_getf('f_read', true));

        if (empty($allowedForums)) {
            return [];
        }

        // 2. Exclude password-protected forums
        $sql = 'SELECT forum_id
            FROM ' . $this->forumsTable . '
            WHERE ' . $this->db->sql_in_set('forum_id', $allowedForums) . "
              AND forum_password = ''";
        $result = $this->db->sql_query($sql);

        $filtered = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $filtered[] = (int) $row['forum_id'];
        }
        $this->db->sql_freeresult($result);

        return $filtered;
    }

    /**
     * Retrieve all crawlable public forums joined with authoritative slugs.
     *
     * @return array<int, array{forum_id: int, slug: string, lastmod: int}>
     */
    public function getPublicForums(): array
    {
        $guestForums = $this->getGuestAllowedForumIds();
        if (empty($guestForums)) {
            return [];
        }

        $sql = 'SELECT f.forum_id, f.forum_last_post_time, s.slug
            FROM ' . $this->forumsTable . ' f
            INNER JOIN ' . $this->slugsTable . ' s
                ON s.resource_type = 1 AND s.resource_id = f.forum_id
            WHERE ' . $this->db->sql_in_set('f.forum_id', $guestForums) . '
              AND f.forum_type IN (0, 1)
              AND f.forum_password = \'\'
              AND s.slug != \'\'
            ORDER BY f.left_id ASC';

        $result = $this->db->sql_query($sql);
        $forums = [];
        while ($row = $this->db->sql_fetchrow($result)) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue; // Skip entries without slug safely
            }
            $forums[] = [
                'forum_id' => (int) $row['forum_id'],
                'slug'     => $slug,
                'lastmod'  => (int) ($row['forum_last_post_time'] ?? 0),
            ];
        }
        $this->db->sql_freeresult($result);

        return $forums;
    }

    /**
     * Count total public crawlable topics (including global announcements and valid slugs).
     */
    public function getTotalPublicTopicsCount(): int
    {
        $guestForums = $this->getGuestAllowedForumIds();
        if (empty($guestForums)) {
            return 0;
        }

        $sql = 'SELECT COUNT(t.topic_id) as total_topics
            FROM ' . $this->topicsTable . ' t
            INNER JOIN ' . $this->slugsTable . ' s
                ON s.resource_type = 2 AND s.resource_id = t.topic_id
            WHERE (' . $this->db->sql_in_set('t.forum_id', $guestForums) . ' OR t.topic_type = 3)
              AND t.topic_visibility = 1
              AND s.slug != \'\'';

        $result = $this->db->sql_query($sql);
        $total = (int) $this->db->sql_fetchfield('total_topics');
        $this->db->sql_freeresult($result);

        return $total;
    }

    /**
     * Retrieve or build the compact Topic Sitemap Boundary Map [pageNumber => startTopicId].
     * Built via pure forward-keyset batch iteration with 100% ZERO OFFSET.
     *
     * @param int $chunkSize Number of URLs per sitemap file (e.g. 50000)
     * @param bool $forceRebuild Force re-traversal of topic boundaries
     * @return array<int, int> [pageNumber => startTopicId]
     */
    public function getTopicBoundaries(int $chunkSize, bool $forceRebuild = false): array
    {
        if ($chunkSize < 1) {
            $chunkSize = 50000;
        }

        $cacheKey = sprintf(self::BOUNDARY_CACHE_KEY, $chunkSize);
        if (!$forceRebuild) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false && is_array($cached) && !empty($cached)) {
                return $cached;
            }
        }

        $guestForums = $this->getGuestAllowedForumIds();
        if (empty($guestForums)) {
            return [];
        }

        $lastTopicId = 0;
        $eligibleCount = 0;
        $boundaries = [];
        $page = 1;

        // Pure forward-keyset batch iteration: ZERO OFFSET throughout rebuild
        while (true) {
            $sql = 'SELECT t.topic_id
                FROM ' . $this->topicsTable . ' t
                INNER JOIN ' . $this->slugsTable . ' s
                    ON s.resource_type = 2 AND s.resource_id = t.topic_id
                WHERE (' . $this->db->sql_in_set('t.forum_id', $guestForums) . ' OR t.topic_type = 3)
                  AND t.topic_visibility = 1
                  AND s.slug != \'\'
                  AND t.topic_id > ' . (int) $lastTopicId . '
                ORDER BY t.topic_id ASC';

            $result = $this->db->sql_query_limit($sql, self::BOUNDARY_BATCH_SIZE);
            $rowCount = 0;

            while ($row = $this->db->sql_fetchrow($result)) {
                $rowCount++;
                $topicId = (int) $row['topic_id'];
                $lastTopicId = $topicId;

                // When a new chunk boundary is reached, record start topic_id
                if ($eligibleCount % $chunkSize === 0) {
                    $boundaries[$page] = $topicId;
                    $page++;
                }

                $eligibleCount++;
            }
            $this->db->sql_freeresult($result);

            if ($rowCount < self::BOUNDARY_BATCH_SIZE) {
                break; // Finished traversing all eligible topics
            }
        }

        $this->cache->put($cacheKey, $boundaries, self::BOUNDARY_CACHE_TTL);

        return $boundaries;
    }

    /**
     * Stream a chunk of public topics directly with ZERO OFFSET.
     * Looks up the start topic ID directly from the compact boundary index.
     *
     * @param int $page 1-indexed chunk number
     * @param int $chunkSize Number of URLs per sitemap (e.g. 50000)
     * @param callable $callback function(int $topicId, string $slug, int $lastmod): void
     * @return bool True if streamed, false if page out of range / empty
     */
    public function streamTopics(int $page, int $chunkSize, callable $callback): bool
    {
        if ($page < 1 || $chunkSize < 1) {
            return false;
        }

        $boundaries = $this->getTopicBoundaries($chunkSize);
        if (!isset($boundaries[$page])) {
            return false;
        }

        $startId = $boundaries[$page];
        $guestForums = $this->getGuestAllowedForumIds();
        if (empty($guestForums)) {
            return false;
        }

        // Bounded cursor data streaming: starts directly at $startId with ZERO OFFSET
        $sql = 'SELECT t.topic_id, t.topic_last_post_time, s.slug
            FROM ' . $this->topicsTable . ' t
            INNER JOIN ' . $this->slugsTable . ' s
                ON s.resource_type = 2 AND s.resource_id = t.topic_id
            WHERE (' . $this->db->sql_in_set('t.forum_id', $guestForums) . ' OR t.topic_type = 3)
              AND t.topic_visibility = 1
              AND s.slug != \'\'
              AND t.topic_id >= ' . (int) $startId . '
            ORDER BY t.topic_id ASC';

        $result = $this->db->sql_query_limit($sql, $chunkSize);
        while ($row = $this->db->sql_fetchrow($result)) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            $callback(
                (int) $row['topic_id'],
                $slug,
                (int) ($row['topic_last_post_time'] ?? 0)
            );
        }
        $this->db->sql_freeresult($result);

        return true;
    }

    /**
     * Compute ACP statistics (cached for 10 minutes to prevent overhead).
     *
     * @return array{public_forums: int, public_topics: int, topic_files: int, missing_slugs: int}
     */
    public function getSitemapStats(int $chunkSize): array
    {
        $cached = $this->cache->get(self::STATS_CACHE_KEY);
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $guestForums = $this->getGuestAllowedForumIds();
        $publicForums = count($guestForums);
        $publicTopics = 0;
        $missingSlugs = 0;

        if ($publicForums > 0) {
            $publicTopics = $this->getTotalPublicTopicsCount();

            // Missing slug index anti-join count
            $missingSql = 'SELECT COUNT(t.topic_id) as missing_count
                FROM ' . $this->topicsTable . ' t
                LEFT JOIN ' . $this->slugsTable . ' s
                    ON s.resource_type = 2 AND s.resource_id = t.topic_id
                WHERE (' . $this->db->sql_in_set('t.forum_id', $guestForums) . ' OR t.topic_type = 3)
                  AND t.topic_visibility = 1
                  AND (s.slug IS NULL OR s.slug = \'\')';
            $mRes = $this->db->sql_query($missingSql);
            $missingSlugs = (int) $this->db->sql_fetchfield('missing_count');
            $this->db->sql_freeresult($mRes);
        }

        $boundaries = $this->getTopicBoundaries($chunkSize);
        $topicFiles = max(1, count($boundaries));

        $stats = [
            'public_forums' => $publicForums,
            'public_topics' => $publicTopics,
            'topic_files'   => $topicFiles,
            'missing_slugs' => $missingSlugs,
        ];

        $this->cache->put(self::STATS_CACHE_KEY, $stats, self::STATS_CACHE_TTL);

        return $stats;
    }

    /**
     * Purge both stats cache and topic boundary map caches.
     */
    public function purgeStatsCache(): void
    {
        $this->cache->destroy(self::STATS_CACHE_KEY);
        $this->cache->destroy(sprintf(self::BOUNDARY_CACHE_KEY, 50000));
        for ($size = 100; $size <= 50000; $size += 5000) {
            $this->cache->destroy(sprintf(self::BOUNDARY_CACHE_KEY, $size));
        }
    }
}
