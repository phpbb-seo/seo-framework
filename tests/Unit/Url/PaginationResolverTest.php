<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Url\PaginationResolver;

class PaginationResolverTest extends TestCase
{
    private PaginationResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new PaginationResolver();
    }

    public function testPage1ReturnsNull(): void
    {
        $this->assertNull($this->resolver->startToPage(0, 20));
    }

    public function testPage2(): void
    {
        $this->assertSame(2, $this->resolver->startToPage(20, 20));
    }

    public function testPage3(): void
    {
        $this->assertSame(3, $this->resolver->startToPage(40, 20));
    }

    public function testPageToStartPage1(): void
    {
        $this->assertSame(0, $this->resolver->pageToStart(1, 20));
    }

    public function testPageToStartPage2(): void
    {
        $this->assertSame(20, $this->resolver->pageToStart(2, 20));
    }

    public function testInvalidPerPageReturnsNull(): void
    {
        $this->assertNull($this->resolver->startToPage(20, 0));
    }

    public function testResourceSpecificPageSize(): void
    {
        // topics_per_page = 25
        $this->assertSame(2, $this->resolver->startToPage(25, 25));
        $this->assertSame(25, $this->resolver->pageToStart(2, 25));
    }
}
