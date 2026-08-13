<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Rewrite;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Rewrite\CompiledUrlPattern;
use phpbbseo\framework\Rewrite\PatternConflictDetector;
use phpbbseo\framework\Rewrite\UrlPatternCompiler;

class PatternConflictDetectorTest extends TestCase
{
    private UrlPatternCompiler $compiler;
    private PatternConflictDetector $detector;

    protected function setUp(): void
    {
        $this->compiler = new UrlPatternCompiler();
        $this->detector = new PatternConflictDetector();
    }

    public function testDistinctPrefixesDoNotConflict(): void
    {
        $patterns = [
            'forum'  => $this->compiler->compile('/forum/{slug}-{id}/', ['id']),
            'topic'  => $this->compiler->compile('/topic/{slug}-{id}/', ['id']),
            'member' => $this->compiler->compile('/member/{slug}-{id}/', ['id']),
        ];

        // Must not throw
        $this->detector->detect($patterns);
        $this->assertTrue(true);
    }

    public function testAmbiguousSamePrefixPatternsDetected(): void
    {
        $patterns = [
            'a' => $this->compiler->compile('/x/{slug}-{id}/', ['id']),
            'b' => $this->compiler->compile('/x/{slug}-{id}/', ['id']),
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->detector->detect($patterns);
    }
}
