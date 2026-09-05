<?php
declare(strict_types=1);

namespace phpbbseo\framework\Backfill;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\lock\db as db_lock;
use phpbbseo\framework\Backfill\Exception\BackfillLockException;
use phpbbseo\framework\Rewrite\ResourceType;
use phpbbseo\framework\Sitemap\SitemapRepository;
use phpbbseo\framework\Url\SlugGeneratorInterface;

/**
 * Unified, high-performance backfill engine for persistent SEO slugs.
 *
 * Implements zero-offset keyset pagination, multi-row DBAL insertion, missing-only
 * filtering, and concurrency mutex locking via phpBB's core db lock mechanism.
 */
class SlugBackfillManager
{
    public const LOCK_NAME = 'seo_slug_rebuild_lock';
    public const DEFAULT_BATCH_SIZE = 500;
    public const MAX_BATCH_SIZE = 1000;

    private readonly string $topicsTable;
    private readonly string $slugsTable;
    private ?db_lock $lockInstance = null;

    public function __construct(
        private readonly driver_interface $db,
        private readonly config $config,
        private readonly SlugGeneratorInterface $slugGenerator,
        private readonly SitemapRepository $sitemapRepository,
        string $tablePrefix
    ) {
        $this->topicsTable = $tablePrefix . 'topics';
        $this->slugsTable  = $tablePrefix . 'seo_slugs';
    }

    /**
     * Get or create the underlying phpBB DB lock instance.
     */
    private function getLock(): db_lock
    {
        if ($this->lockInstance === null) {
            $this->lockInstance = new db_lock(self::LOCK_NAME, $this->config, $this->db);
        }
        return $this->lockInstance;
    }

    /**
     * Attempt to acquire the global backfill concurrency lock.
     */
    public function acquireLock(): bool
    {
        return $this->getLock()->acquire();
    }

    /**
     * Release the global backfill concurrency lock if owned by this process.
     */
    public function releaseLock(): void
    {
        $this->getLock()->release();
    }

    /**
     * Check if this process owns the backfill lock.
     */
    public function ownsLock(): bool
    {
        return $this->getLock()->owns_lock();
    }

    /**
     * Process a bounded batch of resources and persist their slugs.
     *
     * @param string $resourceType Resource type (e.g. 'topic')
     * @param int $lastId The highest resource ID processed in the previous batch (0 for start)
     * @param int $batchSize Number of records to process (clamped between 1 and 1000)
     * @param bool $onlyMissing When true, only targets entities without an existing slug
     * @return BackfillBatchResult
     * @throws BackfillLockException When another rebuild process holds the lock
     * @throws \InvalidArgumentException When an unsupported resource type is specified
     */
    public function backfillBatch(
        string $resourceType = 'topic',
        int $lastId = 0,
        int $batchSize = self::DEFAULT_BATCH_SIZE,
        bool $onlyMissing = true
    ): BackfillBatchResult {
        if ($resourceType !== 'topic') {
            throw new \InvalidArgumentException("Unsupported backfill resource type: '{$resourceType}'. Lite 1.1.0 supports 'topic'.");
        }

        $startTime = microtime(true);
        $batchSize = min(max($batchSize, 1), self::MAX_BATCH_SIZE);
        $lastId = max($lastId, 0);

        // Concurrency Guard: Ensure mutex lock is held
        $acquiredByBatch = false;
        if (!$this->ownsLock()) {
            if (!$this->acquireLock()) {
                throw new BackfillLockException('A slug rebuild process is already running.');
            }
            $acquiredByBatch = true;
        }

        try {
            if ($onlyMissing) {
                // Keyset Pagination targeting exclusively missing slugs: ZERO OFFSET
                $sql = 'SELECT t.topic_id, t.topic_title, t.topic_time
                    FROM ' . $this->topicsTable . ' t
                    LEFT JOIN ' . $this->slugsTable . ' s
                        ON s.resource_type = ' . ResourceType::TOPIC . ' AND s.resource_id = t.topic_id
                    WHERE s.resource_id IS NULL
                      AND t.topic_id > ' . (int) $lastId . '
                    ORDER BY t.topic_id ASC';
            } else {
                // Complete rebuild keyset traversal: ZERO OFFSET
                $sql = 'SELECT t.topic_id, t.topic_title, t.topic_time
                    FROM ' . $this->topicsTable . ' t
                    WHERE t.topic_id > ' . (int) $lastId . '
                    ORDER BY t.topic_id ASC';
            }

            $result = $this->db->sql_query_limit($sql, $batchSize);
            $insertRows = [];
            $maxId = $lastId;
            $rowCount = 0;

            while ($row = $this->db->sql_fetchrow($result)) {
                $rowCount++;
                $topicId = (int) $row['topic_id'];
                if ($topicId > $maxId) {
                    $maxId = $topicId;
                }

                $title = (string) $row['topic_title'];
                $slug = $this->slugGenerator->generate($title);
                $updatedAt = isset($row['topic_time']) ? (int) $row['topic_time'] : 0;

                $insertRows[] = [
                    'resource_type' => ResourceType::TOPIC,
                    'resource_id'   => $topicId,
                    'slug'          => $slug,
                    'updated_at'    => $updatedAt,
                ];
            }
            $this->db->sql_freeresult($result);

            // Multi-row atomic insertion
            if (!empty($insertRows)) {
                $this->db->sql_transaction('begin');
                try {
                    if (!$onlyMissing) {
                        // When performing complete rebuild, delete conflicting IDs for this batch first
                        $batchIds = array_column($insertRows, 'resource_id');
                        $sqlDelete = 'DELETE FROM ' . $this->slugsTable . '
                            WHERE resource_type = ' . ResourceType::TOPIC . '
                              AND ' . $this->db->sql_in_set('resource_id', $batchIds);
                        $this->db->sql_query($sqlDelete);
                    }

                    $this->db->sql_multi_insert($this->slugsTable, $insertRows);
                    $this->db->sql_transaction('commit');
                } catch (\Throwable $e) {
                    $this->db->sql_transaction('rollback');
                    throw $e;
                }
            }

            // Completion determination
            $completed = ($rowCount < $batchSize);
            $remaining = 0;

            if ($completed) {
                // Invalidate sitemap cache immediately upon total completion
                $this->sitemapRepository->purgeStatsCache();
            } else {
                $remaining = $this->getRemainingCount($resourceType, $maxId, $onlyMissing);
                if ($remaining === 0) {
                    $completed = true;
                    $this->sitemapRepository->purgeStatsCache();
                }
            }

            $elapsed = round(microtime(true) - $startTime, 4);

            return new BackfillBatchResult(
                processed: $rowCount,
                lastId: $maxId,
                remaining: $remaining,
                completed: $completed,
                failed: 0,
                elapsed: $elapsed
            );
        } finally {
            if ($acquiredByBatch) {
                $this->releaseLock();
            }
        }
    }

    /**
     * Efficiently count remaining topics after a given cursor ID.
     */
    public function getRemainingCount(string $resourceType = 'topic', int $afterId = 0, bool $onlyMissing = true): int
    {
        if ($resourceType !== 'topic') {
            return 0;
        }

        if ($onlyMissing) {
            $sql = 'SELECT COUNT(t.topic_id) AS total
                FROM ' . $this->topicsTable . ' t
                LEFT JOIN ' . $this->slugsTable . ' s
                    ON s.resource_type = ' . ResourceType::TOPIC . ' AND s.resource_id = t.topic_id
                WHERE s.resource_id IS NULL';
            if ($afterId > 0) {
                $sql .= ' AND t.topic_id > ' . (int) $afterId;
            }
        } else {
            $sql = 'SELECT COUNT(t.topic_id) AS total
                FROM ' . $this->topicsTable . ' t';
            if ($afterId > 0) {
                $sql .= ' WHERE t.topic_id > ' . (int) $afterId;
            }
        }

        $result = $this->db->sql_query($sql);
        $count = (int) $this->db->sql_fetchfield('total');
        $this->db->sql_freeresult($result);

        return $count;
    }

    /**
     * Count total missing slugs for a resource type across the entire board.
     */
    public function getTotalMissingCount(string $resourceType = 'topic'): int
    {
        return $this->getRemainingCount($resourceType, 0, true);
    }
}
