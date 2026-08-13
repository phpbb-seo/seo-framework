<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Rewrite;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Context\EntitySeoContext;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Rewrite\PermalinkRewriteProfile;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\PaginationResolver;
use phpbbseo\framework\Url\SlugOptions;

class PermalinkRewriteProfileTest extends TestCase
{
    private EntitySeoContext $entityContext;
    private PermalinkRewriteProfile $profile;
    private PaginationResolver $paginator;

    protected function setUp(): void
    {
        $config = $this->createMock(ConfigurationProvider::class);
        $config->method('get')->willReturnMap([
            ['seo_permalink_preset', 'modern', 'modern'],
            ['posts_per_page', '20', '20'],
            ['topics_per_page', '25', '25'],
        ]);

        $this->entityContext = new EntitySeoContext();
        $this->paginator = new PaginationResolver();

        $permalinkConfig = new PermalinkConfiguration($config);
        $compiler = new UrlPatternCompiler();
        $slugGenerator = new DefaultSlugGenerator(new SlugOptions());

        $this->profile = new PermalinkRewriteProfile(
            $permalinkConfig,
            $compiler,
            $this->entityContext,
            $slugGenerator,
            $this->paginator,
            $config
        );
    }

    // ── Forum ────────────────────────────────────────────────────────────────

    public function testForumUrlGeneration(): void
    {
        $this->entityContext->setForumName(12, 'General Discussion');
        $url = $this->profile->generateForumUrl(12);
        $this->assertSame('/forum/general-discussion-12/', $url);
    }

    public function testForumUrlWithoutEntityDataReturnsNull(): void
    {
        // Entity context deliberately empty — no fake slug generated
        $url = $this->profile->generateForumUrl(99);
        $this->assertNull($url, 'Must return null when entity data is unavailable, not a placeholder slug');
    }

    // ── Topic ────────────────────────────────────────────────────────────────

    public function testTopicUrlGeneration(): void
    {
        $this->entityContext->setTopicTitle(582, 'phpBB SEO Framework');
        $url = $this->profile->generateTopicUrl(582);
        $this->assertSame('/topic/phpbb-seo-framework-582/', $url);
    }

    public function testTopicUrlWithoutEntityDataReturnsNull(): void
    {
        $url = $this->profile->generateTopicUrl(9999);
        $this->assertNull($url, 'Must not produce a placeholder/fake slug');
    }

    public function testUnicodeTopicUrlGeneration(): void
    {
        $this->entityContext->setTopicTitle(582, 'آموزش نصب phpBB');
        $url = $this->profile->generateTopicUrl(582);
        $this->assertNotNull($url);
        $this->assertStringContainsString('582', $url);
        $this->assertStringContainsString('phpbb', $url);
        // Persian characters should survive or be represented in slug
        $this->assertStringContainsString('/topic/', $url);
    }

    // ── Topic pagination ──────────────────────────────────────────────────────

    public function testTopicPaginationPage2(): void
    {
        $this->entityContext->setTopicTitle(582, 'phpBB SEO Framework');
        $url = $this->profile->generateTopicPageUrl(582, 20, 20); // start=20, 20 per page → page 2
        $this->assertSame('/topic/phpbb-seo-framework-582/page-2/', $url);
    }

    public function testTopicPage1CanonicalIsBaseUrl(): void
    {
        $this->entityContext->setTopicTitle(582, 'phpBB SEO Framework');
        $url = $this->profile->generateTopicPageUrl(582, 0, 20); // start=0 → page 1 → base URL
        $this->assertSame('/topic/phpbb-seo-framework-582/', $url);
    }

    public function testTopicPaginationWithoutEntityDataReturnsNull(): void
    {
        $url = $this->profile->generateTopicPageUrl(9999, 20, 20);
        $this->assertNull($url);
    }

    // ── Member ────────────────────────────────────────────────────────────────

    public function testMemberUrlGeneration(): void
    {
        $this->entityContext->setUsername(35, 'john-doe');
        $url = $this->profile->generateMemberUrl(35);
        $this->assertSame('/member/john-doe-35/', $url);
    }

    public function testMemberUrlWithoutEntityDataReturnsNull(): void
    {
        $url = $this->profile->generateMemberUrl(9999);
        $this->assertNull($url);
    }

    // ── Inbound matching ─────────────────────────────────────────────────────

    public function testInboundTopicMatch(): void
    {
        $result = $this->profile->matchTopic('/topic/phpbb-seo-framework-582/');
        $this->assertNotNull($result);
        $this->assertSame(582, $result['id']);
        $this->assertSame('phpbb-seo-framework', $result['slug']);
        $this->assertNull($result['page']);
    }

    public function testInboundTopicPageMatch(): void
    {
        $result = $this->profile->matchTopic('/topic/phpbb-seo-framework-582/page-3/');
        $this->assertNotNull($result);
        $this->assertSame(582, $result['id']);
        $this->assertSame(3, $result['page']);
    }

    public function testInboundForumMatch(): void
    {
        $result = $this->profile->matchForum('/forum/general-discussion-12/');
        $this->assertNotNull($result);
        $this->assertSame(12, $result['id']);
    }

    public function testInboundMemberMatch(): void
    {
        $result = $this->profile->matchMember('/member/john-doe-35/');
        $this->assertNotNull($result);
        $this->assertSame(35, $result['id']);
    }

    public function testInboundNonMatchReturnsNull(): void
    {
        $result = $this->profile->matchTopic('/forum/general-discussion-12/');
        $this->assertNull($result);
    }

    // ── ID extraction ─────────────────────────────────────────────────────────

    public function testIdExtractedNotSlug(): void
    {
        // ID is what matters — slug can be anything
        $result = $this->profile->matchTopic('/topic/anything-at-all-582/');
        $this->assertNotNull($result);
        $this->assertSame(582, $result['id']);
    }

    // ── Stale slug detection ──────────────────────────────────────────────────

    public function testStaleTopicSlugDetected(): void
    {
        $this->entityContext->setTopicTitle(582, 'phpBB SEO Framework');
        $canonical = $this->profile->detectStaleTopic(582, 'old-title');
        $this->assertNotNull($canonical);
        $this->assertSame('/topic/phpbb-seo-framework-582/', $canonical);
    }

    public function testFreshTopicSlugReturnsNull(): void
    {
        $this->entityContext->setTopicTitle(582, 'phpBB SEO Framework');
        $canonical = $this->profile->detectStaleTopic(582, 'phpbb-seo-framework');
        $this->assertNull($canonical, 'Fresh slug must not trigger redirect');
    }

    public function testStaleSlugWithUnicode(): void
    {
        $this->entityContext->setTopicTitle(582, 'آموزش نصب phpBB');
        $currentSlug = (new DefaultSlugGenerator(new SlugOptions()))->generate('آموزش نصب phpBB');
        $stale = $this->profile->detectStaleTopic(582, 'old-title');
        $this->assertNotNull($stale, 'Stale unicode slug must trigger redirect');
    }

    public function testStalenessWithoutEntityContextReturnsNull(): void
    {
        // Cannot determine staleness without entity data — must return null, not crash
        $result = $this->profile->detectStaleTopic(9999, 'some-slug');
        $this->assertNull($result);
    }
}
