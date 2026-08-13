<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\Context;

use PHPUnit\Framework\TestCase;
use phpbbseo\framework\Context\RequestContextFactory;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbb\request\request_interface;

class RequestContextFactoryTest extends TestCase
{
    public function testHostValidationRejectsCrLf(): void
    {
        $config = $this->createMock(ConfigurationProvider::class);
        $config->method('get')->willReturnMap([
            ['server_name', 'localhost', 'example.com'],
            ['server_port', 80, 80],
            ['server_protocol', 'http://', 'https://']
        ]);

        $request = $this->createMock(request_interface::class);
        $request->method('server')->willReturnMap([
            ['HTTP_HOST', '', "example.com\r\nInjected: Header"],
            ['HTTPS', 'off', 'on'],
            ['SCRIPT_NAME', '', '/test.php'],
            ['QUERY_STRING', '', 'a=1']
        ]);

        $factory = new RequestContextFactory($config);
        $context = $factory->createFromPhpbbRequest($request);

        $this->assertSame('example.com', $context->host, 'Host should fallback to configured host on CRLF');
    }

    public function testHostFallsBackToConfiguredHostOnMismatch(): void
    {
        $config = $this->createMock(ConfigurationProvider::class);
        $config->method('get')->willReturnMap([
            ['server_name', 'localhost', 'trusted.com'],
            ['server_port', 80, 80],
            ['server_protocol', 'http://', 'https://']
        ]);

        $request = $this->createMock(request_interface::class);
        $request->method('server')->willReturnMap([
            ['HTTP_HOST', '', 'untrusted.com'],
            ['HTTPS', 'off', 'on'],
            ['SCRIPT_NAME', '', '/test.php'],
            ['QUERY_STRING', '', '']
        ]);

        $factory = new RequestContextFactory($config);
        $context = $factory->createFromPhpbbRequest($request);

        $this->assertSame('trusted.com', $context->host, 'Should reject untrusted host and use configured');
    }
}
