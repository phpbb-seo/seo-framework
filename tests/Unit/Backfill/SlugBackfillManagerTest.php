<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Backfill;

use PHPUnit\Framework\TestCase;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbbseo\framework\Backfill\Exception\BackfillLockException;
use phpbbseo\framework\Backfill\SlugBackfillManager;
use phpbbseo\framework\Rewrite\ResourceType;
use phpbbseo\framework\Sitemap\SitemapRepository;
use phpbbseo\framework\Url\DefaultSlugGenerator;

/**
 * Comprehensive test suite for SlugBackfillManager covering keyset pagination,
 * multi-row insertion, missing-only mode, cursor progression, idempotency,
 * transactions, concurrency locks, and cache invalidation.
 */
class SlugBackfillManagerTest extends TestCase
{
    private array $dbTopics = [];
    private array $dbSlugs = [];
    private array $executedQueries = [];
    private array $multiInserts = [];
    private array $transactionLog = [];
    private int $cachePurgeCount = 0;
    private config $config;
    private DefaultSlugGenerator $slugGenerator;
    private SitemapRepository $sitemapRepo;
    private driver_interface $dbMock;

    protected function setUp(): void
    {
        $this->dbTopics = [];
        $this->dbSlugs = [];
        $this->executedQueries = [];
        $this->multiInserts = [];
        $this->transactionLog = [];
        $this->cachePurgeCount = 0;

        $this->config = new config([
            'seo_slug_rebuild_lock' => '0',
        ]);
        $this->slugGenerator = new DefaultSlugGenerator();

        // SitemapRepository Mock
        $this->sitemapRepo = $this->createMock(SitemapRepository::class);
        $this->sitemapRepo->method('purgeStatsCache')->willReturnCallback(function () {
            $this->cachePurgeCount++;
        });

        // Driver Interface Mock
        $this->dbMock = $this->createMock(driver_interface::class);

        $this->dbMock->method('sql_query_limit')->willReturnCallback(function ($sql, $total, $offset = 0) {
            $this->executedQueries[] = ['sql' => $sql, 'limit' => $total, 'offset' => $offset];

            // Keyset condition parser
            preg_match('/t\.topic_id > (\d+)/', $sql, $mLastId);
            $afterId = isset($mLastId[1]) ? (int) $mLastId[1] : 0;
            $onlyMissing = str_contains($sql, 's.resource_id IS NULL');

            $rows = [];
            foreach ($this->dbTopics as $topic) {
                if ($topic['topic_id'] <= $afterId) {
                    continue;
                }
                if ($onlyMissing && isset($this->dbSlugs[ResourceType::TOPIC][$topic['topic_id']])) {
                    continue;
                }
                $rows[] = $topic;
                if (count($rows) >= $total) {
                    break;
                }
            }

            $idx = 0;
            $resultResource = new class($rows) {
                public int $idx = 0;
                public function __construct(public array $rows) {}
            };

            return $resultResource;
        });

        $this->dbMock->method('sql_fetchrow')->willReturnCallback(function ($res) {
            if ($res instanceof \stdClass || is_object($res)) {
                if (isset($res->rows[$res->idx])) {
                    return $res->rows[$res->idx++];
                }
            }
            return false;
        });

        $this->dbMock->method('sql_freeresult')->willReturn(true);

        $this->dbMock->method('sql_transaction')->willReturnCallback(function ($status = 'begin') {
            $this->transactionLog[] = $status;
            return true;
        });

        $this->dbMock->method('sql_multi_insert')->willReturnCallback(function ($table, $rows) {
            $this->multiInserts[] = ['table' => $table, 'rows' => $rows];
            foreach ($rows as $row) {
                $type = $row['resource_type'];
                $id = $row['resource_id'];
                $this->dbSlugs[$type][$id] = $row['slug'];
            }
            return true;
        });

        $this->dbMock->method('sql_in_set')->willReturnCallback(function ($field, $array) {
            return $field . ' IN (' . implode(',', array_map('intval', (array) $array)) . ')';
        });

        $lastResult = null;
        $this->dbMock->method('sql_query')->willReturnCallback(function ($sql) use (&$lastResult) {
            $this->executedQueries[] = ['sql' => $sql];
            if (str_starts_with($sql, 'SELECT COUNT')) {
                // Determine remaining count based on sql
                preg_match('/t\.topic_id > (\d+)/', $sql, $mLastId);
                $afterId = isset($mLastId[1]) ? (int) $mLastId[1] : 0;
                $onlyMissing = str_contains($sql, 's.resource_id IS NULL');

                $count = 0;
                foreach ($this->dbTopics as $topic) {
                    if ($topic['topic_id'] <= $afterId) {
                        continue;
                    }
                    if ($onlyMissing && isset($this->dbSlugs[ResourceType::TOPIC][$topic['topic_id']])) {
                        continue;
                    }
                    $count++;
                }

                $res = new \stdClass();
                $res->count = $count;
                $lastResult = $res;
                return $res;
            }
            return true;
        });

        $this->dbMock->method('sql_fetchfield')->willReturnCallback(function ($field, $rowData = false, $queryId = false) use (&$lastResult) {
            $target = $queryId ?: $lastResult;
            if (is_object($target) && isset($target->count)) {
                return (string) $target->count;
            }
            return '0';
        });

    }

    private function createManager(): SlugBackfillManager
    {
        return new SlugBackfillManager(
            $this->dbMock,
            $this->config,
            $this->slugGenerator,
            $this->sitemapRepo,
            'phpbb_'
        );
    }

    public function testEmptyBoardReturnsZeroProcessedAndCompleted(): void
    {
        $manager = $this->createManager();
        $result = $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame(0, $result->processed);
        $this->assertSame(0, $result->lastId);
        $this->assertSame(0, $result->remaining);
        $this->assertTrue($result->completed);
        $this->assertSame(1, $this->cachePurgeCount);
    }

    public function testSingleTopicIsBackfilledAndPurgesCache(): void
    {
        $this->dbTopics = [
            ['topic_id' => 1, 'topic_title' => 'First Topic', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $result = $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame(1, $result->processed);
        $this->assertSame(1, $result->lastId);
        $this->assertSame(0, $result->remaining);
        $this->assertTrue($result->completed);
        $this->assertSame('first-topic', $this->dbSlugs[ResourceType::TOPIC][1]);
        $this->assertSame(1, $this->cachePurgeCount);
    }

    public function testBatchSizeExactly500TopicsCompletesWhenNoMoreRemain(): void
    {
        for ($i = 1; $i <= 500; $i++) {
            $this->dbTopics[] = ['topic_id' => $i, 'topic_title' => "Topic {$i}", 'topic_time' => 1600000000 + $i];
        }

        $manager = $this->createManager();
        $result = $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame(500, $result->processed);
        $this->assertSame(500, $result->lastId);
        $this->assertSame(0, $result->remaining);
        $this->assertTrue($result->completed);
        $this->assertCount(500, $this->dbSlugs[ResourceType::TOPIC]);
        $this->assertSame(1, $this->cachePurgeCount);
    }

    public function test501TopicsRequiresTwoBatchesWithoutPrematureCachePurge(): void
    {
        for ($i = 1; $i <= 501; $i++) {
            $this->dbTopics[] = ['topic_id' => $i, 'topic_title' => "Topic {$i}", 'topic_time' => 1600000000 + $i];
        }

        $manager = $this->createManager();

        // Batch 1 (500 topics)
        $batch1 = $manager->backfillBatch('topic', 0, 500, true);
        $this->assertSame(500, $batch1->processed);
        $this->assertSame(500, $batch1->lastId);
        $this->assertSame(1, $batch1->remaining);
        $this->assertFalse($batch1->completed);
        $this->assertSame(0, $this->cachePurgeCount, 'Cache must NOT be purged before complete');

        // Batch 2 (1 remaining topic)
        $batch2 = $manager->backfillBatch('topic', $batch1->lastId, 500, true);
        $this->assertSame(1, $batch2->processed);
        $this->assertSame(501, $batch2->lastId);
        $this->assertSame(0, $batch2->remaining);
        $this->assertTrue($batch2->completed);
        $this->assertSame(1, $this->cachePurgeCount, 'Cache must be purged upon final batch completion');
    }

    public function test1200TopicsProcessedInThreeBatches(): void
    {
        for ($i = 1; $i <= 1200; $i++) {
            $this->dbTopics[] = ['topic_id' => $i, 'topic_title' => "Topic {$i}", 'topic_time' => 1600000000 + $i];
        }

        $manager = $this->createManager();
        $lastId = 0;
        $totalProcessed = 0;
        $batches = 0;

        do {
            $batches++;
            $res = $manager->backfillBatch('topic', $lastId, 500, true);
            $totalProcessed += $res->processed;
            $lastId = $res->lastId;
        } while (!$res->completed);

        $this->assertSame(3, $batches);
        $this->assertSame(1200, $totalProcessed);
        $this->assertCount(1200, $this->dbSlugs[ResourceType::TOPIC]);
        $this->assertSame(1, $this->cachePurgeCount);
    }

    public function testMissingOnlyModeSkipsAlreadyIndexedTopics(): void
    {
        // 10 topics exist; 8 already have slugs
        for ($i = 1; $i <= 10; $i++) {
            $this->dbTopics[] = ['topic_id' => $i, 'topic_title' => "Topic {$i}", 'topic_time' => 1600000000 + $i];
            if ($i <= 8) {
                $this->dbSlugs[ResourceType::TOPIC][$i] = "custom-slug-{$i}";
            }
        }

        $manager = $this->createManager();
        $result = $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame(2, $result->processed);
        $this->assertSame(10, $result->lastId);
        $this->assertTrue($result->completed);

        // Pre-existing slugs must not be overwritten
        $this->assertSame('custom-slug-1', $this->dbSlugs[ResourceType::TOPIC][1]);
        $this->assertSame('custom-slug-8', $this->dbSlugs[ResourceType::TOPIC][8]);
        // Missing slugs populated
        $this->assertSame('topic-9', $this->dbSlugs[ResourceType::TOPIC][9]);
        $this->assertSame('topic-10', $this->dbSlugs[ResourceType::TOPIC][10]);
    }

    public function testAllTopicsIndexedReturnsImmediately(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->dbTopics[] = ['topic_id' => $i, 'topic_title' => "Topic {$i}", 'topic_time' => 1600000000];
            $this->dbSlugs[ResourceType::TOPIC][$i] = "topic-{$i}";
        }

        $manager = $this->createManager();
        $result = $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame(0, $result->processed);
        $this->assertSame(0, $result->remaining);
        $this->assertTrue($result->completed);
    }

    public function testDuplicateTitlesGenerateDeterministicSlugsWithoutCollision(): void
    {
        $this->dbTopics = [
            ['topic_id' => 10, 'topic_title' => 'Welcome to phpBB', 'topic_time' => 1600000000],
            ['topic_id' => 20, 'topic_title' => 'Welcome to phpBB', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $res = $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame(2, $res->processed);
        $this->assertSame('welcome-to-phpbb', $this->dbSlugs[ResourceType::TOPIC][10]);
        $this->assertSame('welcome-to-phpbb', $this->dbSlugs[ResourceType::TOPIC][20]);
    }

    public function testTopicIdGapsHandledCorrectlyByKeysetCursor(): void
    {
        $this->dbTopics = [
            ['topic_id' => 1, 'topic_title' => 'Topic 1', 'topic_time' => 1600000000],
            ['topic_id' => 50, 'topic_title' => 'Topic 50', 'topic_time' => 1600000000],
            ['topic_id' => 9999, 'topic_title' => 'Topic 9999', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $res = $manager->backfillBatch('topic', 0, 2, true);

        $this->assertSame(2, $res->processed);
        $this->assertSame(50, $res->lastId);
        $this->assertFalse($res->completed);

        $res2 = $manager->backfillBatch('topic', 50, 2, true);
        $this->assertSame(1, $res2->processed);
        $this->assertSame(9999, $res2->lastId);
        $this->assertTrue($res2->completed);
    }

    public function testKeysetPaginationUsesZeroOffset(): void
    {
        $this->dbTopics = [
            ['topic_id' => 10, 'topic_title' => 'T10', 'topic_time' => 1600000000],
            ['topic_id' => 20, 'topic_title' => 'T20', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $manager->backfillBatch('topic', 5, 500, true);

        $limitQuery = null;
        foreach ($this->executedQueries as $q) {
            if (isset($q['offset'])) {
                $limitQuery = $q;
                break;
            }
        }

        $this->assertNotNull($limitQuery);
        $this->assertSame(0, $limitQuery['offset'], 'OFFSET must always be zero in keyset pagination');
        $this->assertStringContainsString('t.topic_id > 5', $limitQuery['sql']);
        $this->assertStringContainsString('ORDER BY t.topic_id ASC', $limitQuery['sql']);
    }

    public function testBatchSizeClamping(): void
    {
        $this->dbTopics = [
            ['topic_id' => 1, 'topic_title' => 'T1', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();

        // Test clamping of excessive batch size (> 1000)
        $manager->backfillBatch('topic', 0, 5000, true);
        $limitQueries = array_values(array_filter($this->executedQueries, fn($q) => isset($q['limit'])));
        $lastQ = end($limitQueries);
        $this->assertSame(1000, $lastQ['limit']);

        // Test clamping of zero/negative batch size (< 1)
        $this->dbSlugs = [];
        $manager->backfillBatch('topic', 0, -10, true);
        $limitQueries = array_values(array_filter($this->executedQueries, fn($q) => isset($q['limit'])));
        $lastQ = end($limitQueries);
        $this->assertSame(1, $lastQ['limit']);
    }

    public function testNegativeCursorClampedToZero(): void
    {
        $this->dbTopics = [
            ['topic_id' => 1, 'topic_title' => 'T1', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $res = $manager->backfillBatch('topic', -99, 500, true);

        $this->assertSame(1, $res->processed);
        $this->assertSame(1, $res->lastId);
    }

    public function testUtf8AndPersianTopicTitles(): void
    {
        $this->dbTopics = [
            ['topic_id' => 101, 'topic_title' => 'آموزش سئو برای phpBB ۳.۳', 'topic_time' => 1600000000],
            ['topic_id' => 102, 'topic_title' => 'Café & Théâtre Français', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame('آموزش-سئو-برای-phpbb-۳-۳', $this->dbSlugs[ResourceType::TOPIC][101]);
        $this->assertSame('café-théâtre-français', $this->dbSlugs[ResourceType::TOPIC][102]);
    }


    public function testHtmlEntitiesDecodedRepeatedly(): void
    {
        $this->dbTopics = [
            ['topic_id' => 201, 'topic_title' => 'News &amp; Updates &quot;2026&quot;', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $manager->backfillBatch('topic', 0, 500, true);

        $this->assertSame('news-updates-2026', $this->dbSlugs[ResourceType::TOPIC][201]);
    }

    public function testMultiRowInsertAndTransactionWrapping(): void
    {
        $this->dbTopics = [
            ['topic_id' => 1, 'topic_title' => 'Topic 1', 'topic_time' => 1600000000],
            ['topic_id' => 2, 'topic_title' => 'Topic 2', 'topic_time' => 1600000000],
        ];

        $manager = $this->createManager();
        $manager->backfillBatch('topic', 0, 500, true);

        $this->assertCount(1, $this->multiInserts);
        $this->assertCount(2, $this->multiInserts[0]['rows']);
        $this->assertSame(['begin', 'commit'], $this->transactionLog);
    }

    public function testTransactionRollbackOnDbException(): void
    {
        $this->dbTopics = [
            ['topic_id' => 1, 'topic_title' => 'Topic 1', 'topic_time' => 1600000000],
        ];

        $this->dbMock->method('sql_multi_insert')->willThrowException(new \RuntimeException('Disk full'));

        $manager = $this->createManager();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Disk full');

        try {
            $manager->backfillBatch('topic', 0, 500, true);
        } finally {
            $this->assertContains('rollback', $this->transactionLog);
        }
    }


    public function testConcurrencyLockPreventsSimultaneousRebuild(): void
    {
        // Simulate active lock owned by another process
        $this->config->set('seo_slug_rebuild_lock', (time() + 1800) . ' active_token', false);

        $manager = $this->createManager();

        $this->expectException(BackfillLockException::class);
        $this->expectExceptionMessage('A slug rebuild process is already running.');

        $manager->backfillBatch('topic', 0, 500, true);
    }

    public function testUnsupportedResourceTypeThrowsException(): void
    {
        $manager = $this->createManager();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Unsupported backfill resource type: 'member'");

        $manager->backfillBatch('member', 0, 500, true);
    }
}
