<?php
declare(strict_types=1);

namespace phpbbseo\framework\SafeUninstall;

use phpbb\config\config;
use phpbb\cache\driver\driver_interface as cache_driver;
use phpbbseo\framework\Rewrite\PermalinkConfiguration;
use phpbbseo\framework\Rewrite\RouteCacheCompiler;

/**
 * Manages Safe Uninstall preparation, fallback rule generation, and restoration.
 *
 * Ensures that if phpBB SEO Framework Lite is disabled or uninstalled,
 * all existing SEO URLs permanently 301-redirect to native phpBB scripts
 * directly via the web server with zero PHP dependencies.
 */
class SafeUninstallManager
{
    public const MARKER_START = '# BEGIN phpBB SEO Framework Lite - Safe Uninstall Fallback';
    public const MARKER_END   = '# END phpBB SEO Framework Lite - Safe Uninstall Fallback';

    private string $phpbbRootPath;

    public function __construct(
        string $phpbbRootPath,
        private readonly ?PermalinkConfiguration $permalinkConfig = null,
        private readonly ?config $config = null,
        private readonly ?cache_driver $cacheDriver = null,
        private readonly ?RouteCacheCompiler $routeCompiler = null
    ) {
        $this->phpbbRootPath = rtrim(str_replace('\\', '/', $phpbbRootPath), '/') . '/';
    }

    /**
     * Resolves the absolute path to the root .htaccess file.
     */
    public function getHtaccessPath(): string
    {
        return $this->phpbbRootPath . '.htaccess';
    }

    /**
     * Checks if .htaccess exists.
     */
    public function isHtaccessAvailable(): bool
    {
        return file_exists($this->getHtaccessPath());
    }

    /**
     * Checks if .htaccess is writable by PHP.
     */
    public function isHtaccessWritable(): bool
    {
        $path = $this->getHtaccessPath();
        return file_exists($path) ? is_writable($path) : is_writable($this->phpbbRootPath);
    }

    /**
     * Checks if Safe Uninstall fallback rules are currently present in .htaccess.
     */
    public function isPrepared(): bool
    {
        if (!$this->isHtaccessAvailable()) {
            return false;
        }

        $content = (string) file_get_contents($this->getHtaccessPath());
        return str_contains($content, self::MARKER_START);
    }

    /**
     * Resolves the board base path for RewriteBase (e.g. "/phpbb/" or "/").
     */
    public function getBoardBasePath(): string
    {
        if (function_exists('generate_board_url')) {
            $path = (string) parse_url(generate_board_url(), PHP_URL_PATH);
            $trimmed = '/' . trim($path, '/');
            return ($trimmed === '/') ? '/' : $trimmed . '/';
        }

        $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $dir = dirname($scriptName);
        $trimmed = '/' . trim($dir, '/');
        return ($trimmed === '/') ? '/' : $trimmed . '/';
    }

    /**
     * Performs an inspection of the current installation and URL families.
     *
     * @return array<string, mixed>
     */
    public function analyze(): array
    {
        $activePreset = $this->permalinkConfig->getActivePreset();
        $families = [
            'topic'      => ['pattern' => $this->permalinkConfig->getPattern('topic'), 'target' => 'viewtopic.php?t={id}'],
            'topic_page' => ['pattern' => $this->permalinkConfig->getPattern('topic_page'), 'target' => 'viewtopic.php?t={id}&seo_page={page}'],
            'forum'      => ['pattern' => $this->permalinkConfig->getPattern('forum'), 'target' => 'viewforum.php?f={id}'],
            'forum_page' => ['pattern' => $this->permalinkConfig->getPattern('forum_page'), 'target' => 'viewforum.php?f={id}&seo_page={page}'],
            'member'     => ['pattern' => $this->permalinkConfig->getPattern('member'), 'target' => 'memberlist.php?mode=viewprofile&u={id}'],
            'group'      => ['pattern' => $this->permalinkConfig->getPattern('group'), 'target' => 'memberlist.php?mode=group&g={id}'],
        ];

        $reversibleCount = 0;
        $unreversible = [];

        foreach ($families as $type => $data) {
            if (str_contains($data['pattern'], '{id}')) {
                $reversibleCount++;
            } else {
                $unreversible[] = $type;
            }
        }

        $isReversible = (count($unreversible) === 0);

        return [
            'active_preset'        => $activePreset,
            'is_reversible'        => $isReversible,
            'reversible_count'     => $reversibleCount,
            'total_families'       => count($families),
            'unreversible'         => $unreversible,
            'families'             => $families,
            'board_base_path'      => $this->getBoardBasePath(),
            'htaccess_exists'      => $this->isHtaccessAvailable(),
            'htaccess_writable'    => $this->isHtaccessWritable(),
            'is_prepared'          => $this->isPrepared(),
            'is_rewrite_active'    => $this->isLiteRewriteActiveInHtaccess(),
        ];
    }

    /**
     * Checks if the active rewrite.php rule is currently present in .htaccess.
     */
    public function isLiteRewriteActiveInHtaccess(): bool
    {
        if (!$this->isHtaccessAvailable()) {
            return false;
        }

        $content = (string) file_get_contents($this->getHtaccessPath());
        return (bool) preg_match('#ext/phpbbseo/framework/rewrite\.php#i', $content);
    }

    /**
     * Generates the standalone Apache/LiteSpeed .htaccess fallback rules block.
     */
    public function generateHtaccessRules(): string
    {
        $base = $this->getBoardBasePath();

        $rules = [];
        $rules[] = self::MARKER_START;
        $rules[] = '<IfModule mod_rewrite.c>';
        $rules[] = 'RewriteEngine On';
        $rules[] = "RewriteBase {$base}";
        $rules[] = '';

        // 1. Topic Rules
        $rules[] = '# 1. Topics (Paginated & Base)';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^topic/[^/]+-([0-9]+)/page[/-]([0-9]+)/?$ viewtopic.php?t=$1&seo_page=$2 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^topic/[^/]+-([0-9]+)/?$ viewtopic.php?t=$1 [QSA,L,R=301]';
        $rules[] = '';

        // 2. Forum Rules
        $rules[] = '# 2. Forums (Paginated & Base)';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^forum/[^/]+-([0-9]+)/page[/-]([0-9]+)/?$ viewforum.php?f=$1&seo_page=$2 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^forum/[^/]+-([0-9]+)/?$ viewforum.php?f=$1 [QSA,L,R=301]';
        $rules[] = '';

        // 3. Member & Group Profiles
        $rules[] = '# 3. Members & User Groups';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^member/[^/]+-([0-9]+)/?$ memberlist.php?mode=viewprofile&u=$1 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^group/[^/]+-([0-9]+)/?$ memberlist.php?mode=group&g=$1 [QSA,L,R=301]';
        $rules[] = '';

        // 4. Compact Preset Support
        $rules[] = '# 4. Compact Preset Support';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^t/([0-9]+)/[^/]+/p/([0-9]+)/?$ viewtopic.php?t=$1&seo_page=$2 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^t/([0-9]+)/[^/]+/?$ viewtopic.php?t=$1 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^f/([0-9]+)/[^/]+/p/([0-9]+)/?$ viewforum.php?f=$1&seo_page=$2 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^f/([0-9]+)/[^/]+/?$ viewforum.php?f=$1 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^u/([0-9]+)/[^/]+/?$ memberlist.php?mode=viewprofile&u=$1 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^g/([0-9]+)/[^/]+/?$ memberlist.php?mode=group&g=$1 [QSA,L,R=301]';
        $rules[] = '';

        // 5. Classic Preset Support
        $rules[] = '# 5. Classic Preset Support';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^.+?-t([0-9]+)-([0-9]+)\.html$ viewtopic.php?t=$1&seo_page=$2 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^.+?-t([0-9]+)\.html$ viewtopic.php?t=$1 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^forum-([0-9]+)/.+?-([0-9]+)\.html$ viewforum.php?f=$1&seo_page=$2 [QSA,L,R=301]';
        $rules[] = '';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-f';
        $rules[] = 'RewriteCond %{REQUEST_FILENAME} !-d';
        $rules[] = 'RewriteRule ^forum-([0-9]+)/.+\.html$ viewforum.php?f=$1 [QSA,L,R=301]';

        $rules[] = '</IfModule>';
        $rules[] = self::MARKER_END;

        return implode("\n", $rules) . "\n";
    }

    /**
     * Generates Nginx equivalent rewrite directives for manual inclusion.
     */
    public function generateNginxRules(): string
    {
        $base = rtrim($this->getBoardBasePath(), '/');
        $prefix = ($base !== '') ? $base : '';

        $rules = [];
        $rules[] = '# phpBB SEO Framework Lite - Safe Uninstall Fallback (Nginx)';
        $rules[] = "# Add these directives inside your server block:\n";
        $rules[] = "rewrite ^{$prefix}/topic/[^/]+-([0-9]+)/page[/-]([0-9]+)/?$ {$prefix}/viewtopic.php?t=\$1&seo_page=\$2 permanent;";
        $rules[] = "rewrite ^{$prefix}/topic/[^/]+-([0-9]+)/?$ {$prefix}/viewtopic.php?t=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/forum/[^/]+-([0-9]+)/page[/-]([0-9]+)/?$ {$prefix}/viewforum.php?f=\$1&seo_page=\$2 permanent;";
        $rules[] = "rewrite ^{$prefix}/forum/[^/]+-([0-9]+)/?$ {$prefix}/viewforum.php?f=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/member/[^/]+-([0-9]+)/?$ {$prefix}/memberlist.php?mode=viewprofile&u=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/group/[^/]+-([0-9]+)/?$ {$prefix}/memberlist.php?mode=group&g=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/t/([0-9]+)/[^/]+/p/([0-9]+)/?$ {$prefix}/viewtopic.php?t=\$1&seo_page=\$2 permanent;";
        $rules[] = "rewrite ^{$prefix}/t/([0-9]+)/[^/]+/?$ {$prefix}/viewtopic.php?t=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/f/([0-9]+)/[^/]+/p/([0-9]+)/?$ {$prefix}/viewforum.php?f=\$1&seo_page=\$2 permanent;";
        $rules[] = "rewrite ^{$prefix}/f/([0-9]+)/[^/]+/?$ {$prefix}/viewforum.php?f=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/u/([0-9]+)/[^/]+/?$ {$prefix}/memberlist.php?mode=viewprofile&u=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/g/([0-9]+)/[^/]+/?$ {$prefix}/memberlist.php?mode=group&g=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/.+?-t([0-9]+)-([0-9]+)\\.html$ {$prefix}/viewtopic.php?t=\$1&seo_page=\$2 permanent;";
        $rules[] = "rewrite ^{$prefix}/.+?-t([0-9]+)\\.html$ {$prefix}/viewtopic.php?t=\$1 permanent;";
        $rules[] = "rewrite ^{$prefix}/forum-([0-9]+)/.+?-([0-9]+)\\.html$ {$prefix}/viewforum.php?f=\$1&seo_page=\$2 permanent;";
        $rules[] = "rewrite ^{$prefix}/forum-([0-9]+)/.+\\.html$ {$prefix}/viewforum.php?f=\$1 permanent;";

        return implode("\n", $rules) . "\n";
    }

    /**
     * Injects the fallback block into .htaccess and reverts rewrite.php to app.php.
     *
     * @throws \RuntimeException If .htaccess is not writable or update fails.
     */
    public function prepare(): bool
    {
        $path = $this->getHtaccessPath();
        if (!$this->isHtaccessWritable()) {
            throw new \RuntimeException('The root .htaccess file is not writable.');
        }

        $content = file_exists($path) ? (string) file_get_contents($path) : '';

        // 1. Strip existing fallback block to prevent duplicates
        $content = $this->stripExistingFallbackBlock($content);

        // 2. Revert rewrite.php rule to native app.php rule
        $content = preg_replace(
            '#RewriteRule\s+\^\(\.\*\)\$\s+ext/phpbbseo/framework/rewrite\.php\s+\[QSA,L\]#i',
            'RewriteRule ^(.*)$ app.php [QSA,L]',
            $content
        );

        // 3. Generate new fallback block
        $fallbackBlock = $this->generateHtaccessRules();

        // 4. Insert fallback block right before RewriteRule ^(.*)$ app.php
        $targetRuleRegex = '#(RewriteCond\s+%\{REQUEST_FILENAME\}\s+!-f\s*\r?\n\s*RewriteCond\s+%\{REQUEST_FILENAME\}\s+!-d\s*\r?\n\s*RewriteRule\s+\^\(\.\*\)\$\s+app\.php\s+\[QSA,L\])#i';
        $escapedFallbackBlock = str_replace(['\\', '$'], ['\\\\', '\\$'], $fallbackBlock);

        if (preg_match($targetRuleRegex, $content)) {
            $content = preg_replace($targetRuleRegex, $escapedFallbackBlock . "\n\n$1", $content, 1);
        } else {
            // Prepend if main rule structure wasn't explicitly matched
            $content = $fallbackBlock . "\n\n" . $content;
        }

        // 5. Write atomically
        $this->writeHtaccessAtomically($path, $content);

        // 6. Set configuration flags
        $this->config->set('phpbbseo_safe_uninstall_prepared', '1');
        $this->config->set('phpbbseo_framework_enable', '0');
        $this->config->set('seo_rewrite_enable', '0');
        $this->config->set('seo_rewrite_enabled', '0');

        // 7. Write disabled marker to compiled routes
        $storeDir = $this->phpbbRootPath . 'ext/phpbbseo/framework/store/';
        if (is_dir($storeDir)) {
            @file_put_contents($storeDir . 'compiled_routes.php', "<?php\n// Disabled extension route cache marker.\ndeclare(strict_types=1);\n\nreturn ['__disabled' => true];\n");
        }

        // 8. Purge cache driver if available
        if ($this->cacheDriver !== null) {
            $this->cacheDriver->purge();
        }

        return true;
    }

    /**
     * Removes the fallback block from .htaccess and restores rewrite.php.
     *
     * @throws \RuntimeException If .htaccess is not writable or update fails.
     */
    public function restore(): bool
    {
        $path = $this->getHtaccessPath();
        if (!$this->isHtaccessWritable()) {
            throw new \RuntimeException('The root .htaccess file is not writable.');
        }

        $content = file_exists($path) ? (string) file_get_contents($path) : '';

        // 1. Strip fallback block
        $content = $this->stripExistingFallbackBlock($content);

        // 2. Restore rewrite.php rule from app.php rule
        $content = preg_replace(
            '#RewriteRule\s+\^\(\.\*\)\$\s+app\.php\s+\[QSA,L\]#i',
            'RewriteRule ^(.*)$ ext/phpbbseo/framework/rewrite.php [QSA,L]',
            $content
        );

        // 3. Write atomically
        $this->writeHtaccessAtomically($path, $content);

        // 4. Update configuration flags
        $this->config->set('phpbbseo_safe_uninstall_prepared', '0');
        $this->config->set('phpbbseo_framework_enable', '1');
        $this->config->set('seo_rewrite_enable', '1');
        $this->config->set('seo_rewrite_enabled', '1');

        // 5. Restore compiled routes
        if ($this->permalinkConfig !== null && $this->routeCompiler !== null) {
            $patterns = [
                'forum'      => $this->permalinkConfig->getPattern('forum'),
                'forum_page' => $this->permalinkConfig->getPattern('forum_page'),
                'topic'      => $this->permalinkConfig->getPattern('topic'),
                'topic_page' => $this->permalinkConfig->getPattern('topic_page'),
                'member'     => $this->permalinkConfig->getPattern('member'),
                'group'      => $this->permalinkConfig->getPattern('group'),
            ];
            $this->routeCompiler->compileAndDump($patterns);
        }

        // 6. Purge cache driver if available
        if ($this->cacheDriver !== null) {
            $this->cacheDriver->purge();
        }

        return true;
    }

    /**
     * Strips any existing fallback block from the content string.
     */
    private function stripExistingFallbackBlock(string $content): string
    {
        $pattern = '#' . preg_quote(self::MARKER_START, '#') . '.*?' . preg_quote(self::MARKER_END, '#') . '\s*#s';
        return (string) preg_replace($pattern, '', $content);
    }

    /**
     * Writes .htaccess atomically with a temporary file and rename.
     */
    private function writeHtaccessAtomically(string $path, string $content): void
    {
        $tempFile = $path . '.tmp_' . bin2hex(random_bytes(4));
        if (@file_put_contents($tempFile, $content) === false) {
            throw new \RuntimeException("Failed to write temporary .htaccess file: {$tempFile}");
        }

        if (!@rename($tempFile, $path)) {
            @unlink($tempFile);
            throw new \RuntimeException("Failed to atomically replace .htaccess at: {$path}");
        }
    }
}