<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Redirect;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Redirect\UrlSafetyValidator;

class UrlSafetyValidatorTest extends TestCase
{
    private function createValidator(): UrlSafetyValidator
    {
        $config = $this->createMock(\phpbbseo\framework\Configuration\ConfigurationProvider::class);
        $config->method('get')->willReturnMap([
            ['server_name', 'localhost', 'trusted.com'],
        ]);
        return new UrlSafetyValidator($config);
    }

    public function testSafetyValidation(): void
    {
        $validator = $this->createValidator();
        
        $this->assertTrue($validator->isSafe('https://trusted.com/path'));
        $this->assertTrue($validator->isSafe('http://trusted.com/'));
        
        // Subdomain of trusted host
        $this->assertTrue($validator->isSafe('https://forum.trusted.com/topic/test-1/'));
        $this->assertTrue($validator->isSafe('https://sub.forum.trusted.com/topic/test-1/'));
        
        // Current request host explicitly passed
        $this->assertTrue($validator->isSafe('https://sub.domain.com/topic/test-1/', 'sub.domain.com'));

        // Unsafe hosts & boundary collision attacks
        $this->assertFalse($validator->isSafe('https://evil.com/path'));
        $this->assertFalse($validator->isSafe('https://evil-trusted.com/path')); // Hyphenated boundary attack
        $this->assertFalse($validator->isSafe('https://nottrusted.com/path')); // Substring boundary attack
        $this->assertFalse($validator->isSafe('https://trusted.com.evil.com/path')); // Subdomain suffix attack
        
        // Unsafe schemes
        $this->assertFalse($validator->isSafe('javascript:alert(1)'));
        $this->assertFalse($validator->isSafe('data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg=='));
        $this->assertFalse($validator->isSafe('file:///etc/passwd'));
        
        // CRLF
        $this->assertFalse($validator->isSafe("https://trusted.com/path\r\nHeader: injected"));
    }

    public function testUrlNormalizationForLoopDetection(): void
    {
        $validator = $this->createValidator();
        
        // Scheme case, host case, default ports, missing trailing slash on host
        $norm1 = $validator->normalizeUrl('HTTP://TRUSTED.com:80');
        $norm2 = $validator->normalizeUrl('http://trusted.com/');
        $this->assertSame($norm1, $norm2);
        
        $norm3 = $validator->normalizeUrl('https://trusted.com:443/path?query');
        $norm4 = $validator->normalizeUrl('https://trusted.com/path?query');
        $this->assertSame($norm3, $norm4);
    }
}
