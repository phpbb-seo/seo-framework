<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Integration;

use PHPUnit\Framework\TestCase;
use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbbseo\framework\Backfill\SlugBackfillManager;
use phpbbseo\framework\Rewrite\ResourceType;
use phpbbseo\framework\Sitemap\SitemapRepository;
use phpbbseo\framework\Url\DefaultSlugGenerator;

/**
 * Synthetic large-board verification test evaluating memory stability,
 * keyset zero-offset pagination, and batch insert efficiency on 100,000 topics.
 */
class LargeBoardBackfillTest extends TestCase
{
    public function testSynthetic100kBoardScalabilityAndBoundedMemory(): void
    {
        $totalTopics = 100000;
        $batchSize = 1000;
        $expectedBatches = (int) ceil($totalTopics / $batchSize);

        $multiInsertCalls = 0;
        $totalRowsInserted = 0;
        $offsetQueryCount = 0;
        $keysetQueries = 0;

        $config = new config(['seo_slug_rebuild_lock' => '0']);
        $slugGen = new DefaultSlugGenerator();
        $sitemapRepo = $this->createMock(SitemapRepository::class);

        $dbMock = $this->createMock(driver_interface::class);

        $dbMock->method('sql_query_limit')->willReturnCallback(function ($sql, $total, $offset = 0) use (&$offsetQueryCount, &$keysetQueries, $totalTopics) {
            if ($offset > 0) {
                $offsetQueryCount++;
            }

            preg_match('/t\.topic_id > (\d+)/', $sql, $m);
            $afterId = isset($m[1]) ? (int) $m[1] : 0;
            $keysetQueries++;

            // Generate synthetic batch on the fly without storing 100k items in memory
            $rows = [];
            $limit = min($total, $totalTopics - $afterId);
            for ($i = 1; $i <= $limit; $i++) {
                $tid = $afterId + $i;
                $rows[] = [
                    'topic_id'    => $tid,
                    'topic_title' => "Synthetic Topic {$tid} Title Test",
                    'topic_time'  => 1600000000 + $tid,
                ];
            }

            return new class($rows) {
                public int $idx = 0;
                public function __construct(public array $rows) {}
            };
        });

        $dbMock->method('sql_fetchrow')->willReturnCallback(function ($res) {
            if (isset($res->rows[$res->idx])) {
                return $res->rows[$res->idx++];
            }
            return false;
        });

        $dbMock->method('sql_freeresult')->willReturn(true);
        $dbMock->method('sql_transaction')->willReturn(true);

        $dbMock->method('sql_multi_insert')->willReturnCallback(function ($table, $rows) use (&$multiInsertCalls, &$totalRowsInserted) {
            $multiInsertCalls++;
            $totalRowsInserted += count($rows);
            return true;
        });

        $lastQRes = null;
        $dbMock->method('sql_query')->willReturnCallback(function ($sql) use (&$lastQRes, $totalTopics) {
            $res = new \stdClass();
            if (str_starts_with($sql, 'SELECT COUNT')) {
                preg_match('/t\.topic_id > (\d+)/', $sql, $m);
                $afterId = isset($m[1]) ? (int) $m[1] : 0;
                $res->count = max(0, $totalTopics - $afterId);
            } else {
                $res->count = 0;
            }
            $lastQRes = $res;
            return $res;
        });
        $dbMock->method('sql_fetchfield')->willReturnCallback(function ($field, $row = false, $queryId = false) use (&$lastQRes) {
            $target = $queryId ?: $lastQRes;
            return (string) ($target->count ?? 0);
        });


        $manager = new SlugBackfillManager(
            $dbMock,
            $config,
            $slugGen,
            $sitemapRepo,
            'phpbb_'
        );

        $memStart = memory_get_usage(true);
        $lastId = 0;
        $batchesRun = 0;

        do {
            $batchesRun++;
            $result = $manager->backfillBatch(
                resourceType: 'topic',
                lastId: $lastId,
                batchSize: $batchSize,
                onlyMissing: true
            );

            $lastId = $result->lastId;
        } while (!$result->completed);

        $memEnd = memory_get_usage(true);
        $memDiffMb = ($memEnd - $memStart) / (1024 * 1024);

        // Assertions verifying architectural invariants
        $this->assertSame($expectedBatches, $batchesRun, "Should execute exactly {$expectedBatches} batches");
        $this->assertSame($totalTopics, $totalRowsInserted, "Should insert exactly {$totalTopics} topics");
        $this->assertSame($expectedBatches, $multiInsertCalls, "Should execute 1 multi-insert per batch (zero N+1 writes)");
        $this->assertSame(0, $offsetQueryCount, "Zero queries may use OFFSET");
        $this->assertSame($expectedBatches, $keysetQueries, "Every batch must use keyset pagination");
        $this->assertSame($totalTopics, $lastId, "Cursor must end precisely at highest topic ID");
        $this->assertLessThan(10.0, $memDiffMb, "Memory growth across 100,000 topics must remain strictly bounded (< 10MB)");
    }
}
