<?php
declare(strict_types=1);

namespace phpbbseo\framework\tests\Unit\SafeUninstall;

use PHPUnit\Framework\TestCase;
use phpbb\config\config;
use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\SafeUninstall\SafeUninstallManager;

class SafeUninstallManagerTest extends TestCase
{
    private string $tempDir;
    private string $htaccessPath;
    private PermalinkConfiguration $permalinkConfig;
    private config $config;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/phpbbseo_safe_uninstall_test_' . bin2hex(random_bytes(4)) . '/';
        @mkdir($this->tempDir, 0777, true);
        $this->htaccessPath = $this->tempDir . '.htaccess';

        $this->config = new config([
            'seo_permalink_preset' => 'modern',
        ]);
        $configProvider = new ConfigurationProvider($this->config);
        $this->permalinkConfig = new PermalinkConfiguration($configProvider);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->htaccessPath)) {
            @unlink($this->htaccessPath);
        }
        $storeDir = $this->tempDir . 'ext/phpbbseo/framework/store/';
        if (file_exists($storeDir . 'compiled_routes.php')) {
            @unlink($storeDir . 'compiled_routes.php');
        }
        if (is_dir($storeDir)) {
            @rmdir($storeDir);
        }
        @rmdir($this->tempDir);
    }

    public function testGenerateHtaccessRulesContainsAllPresetsAndMarkers(): void
    {
        $manager = new SafeUninstallManager(
            $this->tempDir,
            $this->permalinkConfig,
            $this->config
        );

        $rules = $manager->generateHtaccessRules();

        $this->assertStringContainsString(SafeUninstallManager::MARKER_START, $rules);
        $this->assertStringContainsString(SafeUninstallManager::MARKER_END, $rules);
        $this->assertStringContainsString('RewriteEngine On', $rules);
        $this->assertStringContainsString('RewriteBase', $rules);

        // Modern rules
        $this->assertStringContainsString('^topic/[^/]+-([0-9]+)/page[/-]([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^topic/[^/]+-([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^forum/[^/]+-([0-9]+)/page[/-]([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^forum/[^/]+-([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^member/[^/]+-([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^group/[^/]+-([0-9]+)/?$', $rules);

        // Compact rules
        $this->assertStringContainsString('^t/([0-9]+)/[^/]+/p/([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^t/([0-9]+)/[^/]+/?$', $rules);
        $this->assertStringContainsString('^f/([0-9]+)/[^/]+/p/([0-9]+)/?$', $rules);
        $this->assertStringContainsString('^f/([0-9]+)/[^/]+/?$', $rules);

        // Classic rules
        $this->assertStringContainsString('^.+?-t([0-9]+)-([0-9]+)\.html$', $rules);
        $this->assertStringContainsString('^.+?-t([0-9]+)\.html$', $rules);
        $this->assertStringContainsString('^forum-([0-9]+)/.+?-([0-9]+)\.html$', $rules);
        $this->assertStringContainsString('^forum-([0-9]+)/.+\.html$', $rules);

        // Native phpBB destinations
        $this->assertStringContainsString('viewtopic.php?t=$1', $rules);
        $this->assertStringContainsString('viewforum.php?f=$1', $rules);
        $this->assertStringContainsString('memberlist.php?mode=viewprofile&u=$1', $rules);
        $this->assertStringContainsString('memberlist.php?mode=group&g=$1', $rules);
    }

    public function testGenerateNginxRulesContainsAllDirectives(): void
    {
        $manager = new SafeUninstallManager(
            $this->tempDir,
            $this->permalinkConfig,
            $this->config
        );

        $nginx = $manager->generateNginxRules();

        $this->assertStringContainsString('rewrite ^', $nginx);
        $this->assertStringContainsString('viewtopic.php?t=$1 permanent;', $nginx);
        $this->assertStringContainsString('viewforum.php?f=$1 permanent;', $nginx);
        $this->assertStringContainsString('memberlist.php?mode=viewprofile&u=$1 permanent;', $nginx);
        $this->assertStringContainsString('memberlist.php?mode=group&g=$1 permanent;', $nginx);
    }

    public function testAnalyzeReportsInstallationState(): void
    {
        file_put_contents($this->htaccessPath, "# Existing .htaccess\nRewriteRule ^(.*)$ ext/phpbbseo/framework/rewrite.php [QSA,L]\n");

        $manager = new SafeUninstallManager(
            $this->tempDir,
            $this->permalinkConfig,
            $this->config
        );

        $analysis = $manager->analyze();

        $this->assertSame('modern', $analysis['active_preset']);
        $this->assertTrue($analysis['is_reversible']);
        $this->assertSame(6, $analysis['reversible_count']);
        $this->assertSame(6, $analysis['total_families']);
        $this->assertEmpty($analysis['unreversible']);
        $this->assertTrue($analysis['htaccess_exists']);
        $this->assertTrue($analysis['htaccess_writable']);
        $this->assertFalse($analysis['is_prepared']);
        $this->assertTrue($analysis['is_rewrite_active']);
    }

    public function testPrepareAndRestoreCycle(): void
    {
        $initialHtaccess = <<<HTACCESS
<IfModule mod_rewrite.c>
RewriteEngine On
RewriteBase /phpbb/
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ ext/phpbbseo/framework/rewrite.php [QSA,L]
</IfModule>
HTACCESS;

        file_put_contents($this->htaccessPath, $initialHtaccess);

        $manager = new SafeUninstallManager(
            $this->tempDir,
            $this->permalinkConfig,
            $this->config
        );

        $this->assertFalse($manager->isPrepared());

        // Prepare
        $result = $manager->prepare();
        $this->assertTrue($result);
        $this->assertTrue($manager->isPrepared());

        $preparedContent = (string) file_get_contents($this->htaccessPath);
        $this->assertStringContainsString(SafeUninstallManager::MARKER_START, $preparedContent);
        $this->assertStringContainsString(SafeUninstallManager::MARKER_END, $preparedContent);
        $this->assertStringContainsString('RewriteRule ^(.*)$ app.php [QSA,L]', $preparedContent);
        $this->assertStringNotContainsString('ext/phpbbseo/framework/rewrite.php', $preparedContent);

        $this->assertSame('1', $this->config['phpbbseo_safe_uninstall_prepared']);
        $this->assertSame('0', $this->config['phpbbseo_framework_enable']);
        $this->assertSame('0', $this->config['seo_rewrite_enable']);
        $this->assertSame('0', $this->config['seo_rewrite_enabled']);

        // Restore
        $restoreResult = $manager->restore();
        $this->assertTrue($restoreResult);
        $this->assertFalse($manager->isPrepared());

        $restoredContent = (string) file_get_contents($this->htaccessPath);
        $this->assertStringNotContainsString(SafeUninstallManager::MARKER_START, $restoredContent);
        $this->assertStringNotContainsString(SafeUninstallManager::MARKER_END, $restoredContent);
        $this->assertStringContainsString('RewriteRule ^(.*)$ ext/phpbbseo/framework/rewrite.php [QSA,L]', $restoredContent);

        $this->assertSame('0', $this->config['phpbbseo_safe_uninstall_prepared']);
        $this->assertSame('1', $this->config['phpbbseo_framework_enable']);
        $this->assertSame('1', $this->config['seo_rewrite_enable']);
        $this->assertSame('1', $this->config['seo_rewrite_enabled']);
    }
}
