<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Context\EntitySeoContext;

class EntitySeoContextTest extends TestCase
{
    public function testTopicContextPopulateAndLookup(): void
    {
        $ctx = new EntitySeoContext();
        $ctx->setTopicTitle(582, 'phpBB SEO Framework');
        $this->assertSame('phpBB SEO Framework', $ctx->getTopicTitle(582));
    }

    public function testForumContextPopulateAndLookup(): void
    {
        $ctx = new EntitySeoContext();
        $ctx->setForumName(12, 'General Discussion');
        $this->assertSame('General Discussion', $ctx->getForumName(12));
    }

    public function testMemberContextPopulateAndLookup(): void
    {
        $ctx = new EntitySeoContext();
        $ctx->setUsername(35, 'john-doe');
        $this->assertSame('john-doe', $ctx->getUsername(35));
    }

    public function testMissingEntityReturnsNull(): void
    {
        $ctx = new EntitySeoContext();
        $this->assertNull($ctx->getTopicTitle(9999));
        $this->assertNull($ctx->getForumName(9999));
        $this->assertNull($ctx->getUsername(9999));
    }

    public function testContextIsRequestScoped(): void
    {
        // Each instance is independent (request-scoped, no global state)
        $ctx1 = new EntitySeoContext();
        $ctx2 = new EntitySeoContext();

        $ctx1->setTopicTitle(1, 'First');
        $this->assertNull($ctx2->getTopicTitle(1), 'Entity context must be instance-scoped, not global');
    }
}
