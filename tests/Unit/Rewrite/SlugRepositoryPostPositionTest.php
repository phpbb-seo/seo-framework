<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Rewrite;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Rewrite\SlugRepository;
use phpbbseo\framework\Url\SlugGeneratorInterface;
use phpbb\db\driver\driver_interface;

if (!defined('POSTS_TABLE')) {
    define('POSTS_TABLE', 'phpbb_posts');
}
if (!defined('TOPICS_TABLE')) {
    define('TOPICS_TABLE', 'phpbb_topics');
}

class SlugRepositoryPostPositionTest extends TestCase
{
    public function testFastPathForTopicLastPostBypassesCountQuery(): void
    {
        $mockDb = new MockDatabaseDriver([
            'post_id'               => 100,
            'topic_id'              => 5,
            'forum_id'              => 2,
            'post_time'             => 1234567890,
            'post_visibility'       => 1,
            'topic_first_post_id'   => 1,
            'topic_last_post_id'    => 100,
            'topic_posts_approved'  => 45,
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SpySlugRepository($mockDb, $mockGenerator, 'phpbb_');

        $pos = $repo->fetchPostPosition(100);

        $this->assertNotNull($pos);
        $this->assertSame(5, $pos['topic_id']);
        $this->assertSame(2, $pos['forum_id']);
        // 45 approved posts in topic -> last post has 44 preceding posts
        $this->assertSame(44, $pos['prev_posts']);

        // Assert that the slow-path COUNT query method was NEVER called
        $this->assertSame(0, $repo->countCalls, 'Slow-path countPrecedingPosts must NOT be called for topic last post');
    }

    public function testFastPathForTopicFirstPostBypassesCountQuery(): void
    {
        $mockDb = new MockDatabaseDriver([
            'post_id'               => 1,
            'topic_id'              => 5,
            'forum_id'              => 2,
            'post_time'             => 1000000000,
            'post_visibility'       => 1,
            'topic_first_post_id'   => 1,
            'topic_last_post_id'    => 100,
            'topic_posts_approved'  => 45,
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SpySlugRepository($mockDb, $mockGenerator, 'phpbb_');

        $pos = $repo->fetchPostPosition(1);

        $this->assertNotNull($pos);
        $this->assertSame(5, $pos['topic_id']);
        $this->assertSame(2, $pos['forum_id']);
        $this->assertSame(0, $pos['prev_posts']);

        // Assert that the slow-path COUNT query method was NEVER called
        $this->assertSame(0, $repo->countCalls, 'Slow-path countPrecedingPosts must NOT be called for topic first post');
    }

    public function testSlowPathForHistoricalIntermediatePostCallsCount(): void
    {
        $mockDb = new MockDatabaseDriver([
            'post_id'               => 50,
            'topic_id'              => 5,
            'forum_id'              => 2,
            'post_time'             => 1100000000,
            'post_visibility'       => 1,
            'topic_first_post_id'   => 1,
            'topic_last_post_id'    => 100,
            'topic_posts_approved'  => 45,
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SpySlugRepository($mockDb, $mockGenerator, 'phpbb_');

        $pos = $repo->fetchPostPosition(50);

        $this->assertNotNull($pos);
        $this->assertSame(5, $pos['topic_id']);
        $this->assertSame(2, $pos['forum_id']);
        // Assert that the slow-path COUNT query method WAS called exactly once
        $this->assertSame(1, $repo->countCalls, 'Slow-path countPrecedingPosts MUST be called for intermediate posts');
        $this->assertSame(24, $pos['prev_posts']);
    }

    public function testBatchPreloadExtractsFirstAndLastPostPositionsInOneQuery(): void
    {
        $mockDb = new MockBatchDatabaseDriver([
            [
                'post_id'              => 10,
                'topic_id'             => 1,
                'forum_id'             => 2,
                'topic_first_post_id'  => 1,
                'topic_last_post_id'   => 10,
                'topic_posts_approved' => 10,
            ],
            [
                'post_id'              => 20,
                'topic_id'             => 2,
                'forum_id'             => 2,
                'topic_first_post_id'  => 20,
                'topic_last_post_id'   => 25,
                'topic_posts_approved' => 6,
            ],
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SlugRepository($mockDb, $mockGenerator, 'phpbb_');

        $batch = $repo->fetchPostTopicDataBatch([10, 20]);

        $this->assertSame([10 => 1, 20 => 2], $batch['mappings']);
        $this->assertArrayHasKey(10, $batch['positions']);
        $this->assertArrayHasKey(20, $batch['positions']);

        // Post 10 is last post of topic 1 (10 posts) -> prev_posts = 9
        $this->assertSame(9, $batch['positions'][10]['prev_posts']);
        // Post 20 is first post of topic 2 -> prev_posts = 0
        $this->assertSame(0, $batch['positions'][20]['prev_posts']);
    }

    public function testCountPrecedingPostsExecutesTwoUnambiguousQueries(): void
    {
        $mockDb = new MockSequenceDatabaseDriver([
            // Query 1: strictly earlier posts
            ['prev_posts' => 15],
            // Query 2: tiebreaker for exact timestamp (includes target post itself)
            ['prev_posts' => 1],
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SlugRepository($mockDb, $mockGenerator, 'phpbb_');

        // Target post is approved
        $count = $repo->countPrecedingPosts(5, 2, 1200000000, 50, true);

        // 15 earlier + 1 tie - 1 (since target is approved) = 15 preceding posts
        $this->assertSame(15, $count);
        $this->assertSame(2, count($mockDb->queriesExecuted));

        // Verify Query 1 structure: strictly earlier (<) without OR
        $this->assertTrue(str_contains($mockDb->queriesExecuted[0], 'AND p.post_time < 1200000000'));
        $this->assertFalse(str_contains($mockDb->queriesExecuted[0], 'OR'));

        // Verify Query 2 structure: exact tiebreaker (= and <=) without OR
        $this->assertTrue(str_contains($mockDb->queriesExecuted[1], 'AND p.post_time = 1200000000'));
        $this->assertTrue(str_contains($mockDb->queriesExecuted[1], 'AND p.post_id <= 50'));
        $this->assertFalse(str_contains($mockDb->queriesExecuted[1], 'OR'));
    }

    public function testCountPrecedingPostsHandlesTiebreakerWithMultiplePosts(): void
    {
        $mockDb = new MockSequenceDatabaseDriver([
            // Query 1: 10 strictly earlier posts
            ['prev_posts' => 10],
            // Query 2: 3 posts at exact same timestamp with post_id <= target
            ['prev_posts' => 3],
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SlugRepository($mockDb, $mockGenerator, 'phpbb_');

        // Target post is approved
        $count = $repo->countPrecedingPosts(5, 2, 1200000000, 75, true);

        // 10 earlier + 3 tie - 1 = 12 preceding posts
        $this->assertSame(12, $count);
    }

    public function testCountPrecedingPostsHandlesUnapprovedTargetPost(): void
    {
        $mockDb = new MockSequenceDatabaseDriver([
            // Query 1: 8 strictly earlier posts
            ['prev_posts' => 8],
            // Query 2: 0 posts with exact same timestamp and post_id <= target (target is unapproved, so not counted)
            ['prev_posts' => 0],
        ]);

        $mockGenerator = new MockSlugGenerator();
        $repo = new SlugRepository($mockDb, $mockGenerator, 'phpbb_');

        // Target post is unapproved (isApproved = false, so no -1 subtraction)
        $count = $repo->countPrecedingPosts(5, 2, 1200000000, 75, false);

        $this->assertSame(8, $count);
    }
}

class MockSlugGenerator implements SlugGeneratorInterface
{
    public function generate(string $text, string $type = 'topic'): string
    {
        return 'test-slug';
    }
}

class SpySlugRepository extends SlugRepository
{
    public int $countCalls = 0;

    public function countPrecedingPosts(int $topicId, int $forumId, int $postTime, int $postId, bool $isApproved): int
    {
        $this->countCalls++;
        return 24;
    }
}

class MockDatabaseDriver extends \phpbb\db\driver\mysqli
{
    public function __construct(protected ?array $row = null) {}

    public function sql_query($query = '', $cache_ttl = 0) { return true; }
    public function sql_fetchrow($query_id = false) { return $this->row; }
    public function sql_freeresult($query_id = false) { return true; }
    public function sql_in_set($field, $array, $negate = false, $allow_null = false)
    {
        return $field . ' IN (' . implode(',', (array)$array) . ')';
    }
}

class MockBatchDatabaseDriver extends MockDatabaseDriver
{
    private int $index = 0;
    public function __construct(private readonly array $rows) { parent::__construct(null); }
    public function sql_fetchrow($query_id = false)
    {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return false;
    }
}

class MockSequenceDatabaseDriver extends MockDatabaseDriver
{
    public array $queriesExecuted = [];
    private int $index = 0;

    public function __construct(private readonly array $rows)
    {
        parent::__construct(null);
    }

    public function sql_query($query = '', $cache_ttl = 0)
    {
        $this->queriesExecuted[] = $query;
        return true;
    }

    public function sql_fetchrow($query_id = false)
    {
        if ($this->index < count($this->rows)) {
            return $this->rows[$this->index++];
        }
        return false;
    }
}

