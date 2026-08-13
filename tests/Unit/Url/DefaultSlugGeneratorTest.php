<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Url;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Url\DefaultSlugGenerator;
use phpbbseo\framework\Url\SlugOptions;

class DefaultSlugGeneratorTest extends TestCase
{
    public function testGeneratesUtf8Slugs(): void
    {
        $generator = new DefaultSlugGenerator(new SlugOptions());
        
        // Persian/Arabic
        $this->assertSame('سلام-دنیا', $generator->generate('سلام دنیا!'));
        // Russian
        $this->assertSame('привет-мир', $generator->generate('Привет Мир'));
        // Accented
        $this->assertSame('café-au-lait', $generator->generate('Café au Lait'));
    }

    public function testTruncationWithoutSplittingCodePoints(): void
    {
        $options = new SlugOptions(maxLength: 8);
        $generator = new DefaultSlugGenerator($options);
        
        // "سلام دنیا" is 9 chars. "سلام-دنی" is 8 chars
        $this->assertSame('سلام-دنی', $generator->generate('سلام دنیا'));
    }

    public function testEmptyInputFallback(): void
    {
        $generator = new DefaultSlugGenerator(new SlugOptions(fallback: 'default'));
        
        $this->assertSame('default', $generator->generate('!!!???'));
        $this->assertSame('default', $generator->generate('   '));
    }
}
