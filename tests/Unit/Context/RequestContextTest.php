<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Context\RequestContext;

class RequestContextTest extends TestCase
{
    public function testRequestContextIsImmutableAndReadonly(): void
    {
        $context = new RequestContext(
            'https',
            'example.com',
            '/forum/viewtopic.php',
            'f=2&t=5',
            'viewtopic',
            5,
            null
        );

        $this->assertSame('https', $context->scheme);
        $this->assertSame('example.com', $context->host);
        $this->assertSame('/forum/viewtopic.php', $context->path);
        $this->assertSame('f=2&t=5', $context->query);
        $this->assertSame('viewtopic', $context->route);
        $this->assertSame(5, $context->entityId);
        $this->assertNull($context->pagination);
    }
}
