<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Redirect;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Redirect\UrlSafetyValidator;

class UrlSafetyValidatorTest extends TestCase
{
    public function testSafetyValidation(): void
    {
        $validator = new UrlSafetyValidator('trusted.com');
        
        $this->assertTrue($validator->isSafe('https://trusted.com/path'));
        $this->assertTrue($validator->isSafe('http://trusted.com/'));
        
        // Unsafe hosts
        $this->assertFalse($validator->isSafe('https://evil.com/path'));
        
        // Unsafe schemes
        $this->assertFalse($validator->isSafe('javascript:alert(1)'));
        $this->assertFalse($validator->isSafe('data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='));
        $this->assertFalse($validator->isSafe('file:///etc/passwd'));
        
        // CRLF
        $this->assertFalse($validator->isSafe("https://trusted.com/path\r\nHeader: injected"));
    }

    public function testUrlNormalizationForLoopDetection(): void
    {
        $validator = new UrlSafetyValidator('trusted.com');
        
        // Scheme case, host case, default ports, missing trailing slash on host
        $norm1 = $validator->normalizeUrl('HTTP://TRUSTED.com:80');
        $norm2 = $validator->normalizeUrl('http://trusted.com/');
        $this->assertSame($norm1, $norm2);
        
        $norm3 = $validator->normalizeUrl('https://trusted.com:443/path?query');
        $norm4 = $validator->normalizeUrl('https://trusted.com/path?query');
        $this->assertSame($norm3, $norm4);
    }
}
