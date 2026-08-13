<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Rewrite;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Rewrite\CompiledUrlPattern;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;

class UrlPatternCompilerTest extends TestCase
{
    private UrlPatternCompiler $compiler;

    protected function setUp(): void
    {
        $this->compiler = new UrlPatternCompiler();
    }

    // ── Generation ───────────────────────────────────────────────────────────

    public function testForumUrlGeneration(): void
    {
        $pattern = $this->compiler->compile('/forum/{slug}-{id}/', ['id']);
        $url = $pattern->generate(['id' => 12, 'slug' => 'general-discussion']);
        $this->assertSame('/forum/general-discussion-12/', $url);
    }

    public function testTopicUrlGeneration(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/', ['id']);
        $url = $pattern->generate(['id' => 582, 'slug' => 'phpbb-seo-framework']);
        $this->assertSame('/topic/phpbb-seo-framework-582/', $url);
    }

    public function testMemberUrlGeneration(): void
    {
        $pattern = $this->compiler->compile('/member/{slug}-{id}/', ['id']);
        $url = $pattern->generate(['id' => 35, 'slug' => 'john-doe']);
        $this->assertSame('/member/john-doe-35/', $url);
    }

    public function testPaginatedTopicUrlGeneration(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/page-{page}/', ['id', 'page']);
        $url = $pattern->generate(['id' => 582, 'slug' => 'phpbb-seo-framework', 'page' => 2]);
        $this->assertSame('/topic/phpbb-seo-framework-582/page-2/', $url);
    }

    public function testCompactForumPattern(): void
    {
        $pattern = $this->compiler->compile('/f/{id}/{slug}/', ['id']);
        $url = $pattern->generate(['id' => 12, 'slug' => 'general-discussion']);
        $this->assertSame('/f/12/general-discussion/', $url);
    }

    public function testClassicTopicPattern(): void
    {
        $pattern = $this->compiler->compile('/{slug}-t{id}.html', ['id']);
        $url = $pattern->generate(['id' => 582, 'slug' => 'phpbb-seo-framework']);
        $this->assertSame('/phpbb-seo-framework-t582.html', $url);
    }

    // ── Matching ─────────────────────────────────────────────────────────────

    public function testInboundTopicMatching(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/', ['id']);
        $result = $pattern->match('/topic/phpbb-seo-framework-582/');
        $this->assertNotNull($result);
        $this->assertSame('582', $result['id']);
        $this->assertSame('phpbb-seo-framework', $result['slug']);
    }

    public function testInboundForumMatching(): void
    {
        $pattern = $this->compiler->compile('/forum/{slug}-{id}/', ['id']);
        $result = $pattern->match('/forum/general-discussion-12/');
        $this->assertNotNull($result);
        $this->assertSame('12', $result['id']);
    }

    public function testInboundMemberMatching(): void
    {
        $pattern = $this->compiler->compile('/member/{slug}-{id}/', ['id']);
        $result = $pattern->match('/member/john-doe-35/');
        $this->assertNotNull($result);
        $this->assertSame('35', $result['id']);
        $this->assertSame('john-doe', $result['slug']);
    }

    public function testInboundPaginatedTopicMatching(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/page-{page}/', ['id', 'page']);
        $result = $pattern->match('/topic/phpbb-seo-framework-582/page-2/');
        $this->assertNotNull($result);
        $this->assertSame('582', $result['id']);
        $this->assertSame('2', $result['page']);
    }

    public function testNonMatchingPathReturnsNull(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/', ['id']);
        $result = $pattern->match('/forum/general-12/');
        $this->assertNull($result);
    }

    public function testInvalidRouteRejected(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/', ['id']);
        $result = $pattern->match('/some-random-path/');
        $this->assertNull($result);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function testMissingRequiredTokenRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic/{slug}/', ['id']); // Missing {id}
    }

    public function testUnknownTokenRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic/{slug}-{id}/{custom}/', ['id']); // {custom} not allowed
    }

    public function testDuplicateTokenRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic/{id}/{id}/', ['id']); // Duplicate {id}
    }

    public function testQueryStringInjectionRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic/{slug}-{id}/?t=1', ['id']);
    }

    public function testFragmentInjectionRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic/{slug}-{id}/#section', ['id']);
    }

    public function testPathTraversalRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic/../{slug}-{id}/', ['id']);
    }

    public function testDoubleSlashRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->compiler->compile('/topic//{slug}-{id}/', ['id']);
    }

    // ── Consistency: same pattern for generation and matching ─────────────────

    public function testGeneratedUrlMatchesOwnPattern(): void
    {
        $pattern = $this->compiler->compile('/topic/{slug}-{id}/', ['id']);
        $generated = $pattern->generate(['id' => 582, 'slug' => 'phpbb-seo-framework']);
        $matchResult = $pattern->match($generated);
        $this->assertNotNull($matchResult, 'Generated URL must be matched by same pattern');
        $this->assertSame('582', $matchResult['id']);
        $this->assertSame('phpbb-seo-framework', $matchResult['slug']);
    }
}
