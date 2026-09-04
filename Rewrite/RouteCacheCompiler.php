<?php
declare(strict_types=1);

namespace phpbbseo\framework\Rewrite;

use RuntimeException;
use InvalidArgumentException;
use phpbbseo\framework\Configuration\ConfigurationProvider;

/**
 * Compiles permalink patterns into regexes and atomically writes the pre-bootstrap
 * route cache file (store/compiled_routes.php) with rollback protection.
 */
class RouteCacheCompiler
{
    public function __construct(
        private readonly UrlPatternCompiler $patternCompiler,
        private readonly PatternConflictDetector $conflictDetector,
        private readonly string $storeDir,
        private readonly ?ConfigurationProvider $configProvider = null
    ) {}

    /**
     * Compiles patterns into regex routes and writes them atomically to compiled_routes.php.
     *
     * @param array<string, string> $patterns Array of resource => raw pattern
     * @param array<string, string> $prevPatterns Array of resource => previously active raw pattern (for legacy 301 support)
     * @param bool|null $legacyUsuEnabled Explicit toggle for legacy USU routes (null = read from configProvider)
     * @throws InvalidArgumentException if validation or conflict detection fails
     * @throws RuntimeException if disk write or atomic rename fails
     */
    public function compileAndDump(array $patterns, array $prevPatterns = [], ?bool $legacyUsuEnabled = null): void
    {
        // 1. Validate and compile all active patterns in memory
        $compiledPatterns = [];
        foreach ($patterns as $resource => $pattern) {
            $required = ($resource === 'topic_page' || $resource === 'forum_page') ? ['id', 'page'] : ['id'];
            $compiledPatterns[$resource] = $this->patternCompiler->compile($pattern, $required);
        }

        // 2. Conflict detection across entire active pattern set
        $this->conflictDetector->detect($compiledPatterns);

        // 3. Build route array for pre-bootstrap rewrite.php
        $routes = [];

        // Add active routes (pages first, then base resources)
        $routeOrder = ['topic_page', 'topic', 'forum_page', 'forum', 'member', 'group'];
        foreach ($routeOrder as $key) {
            if (isset($patterns[$key])) {
                $baseResource = str_replace('_page', '', $key);
                $routes[] = [
                    'regex'    => $this->patternToRouteRegex($patterns[$key]),
                    'resource' => $baseResource,
                    'is_page'  => str_contains($key, '_page'),
                ];

                // Support both /page/{page}/ and /page-{page}/ inbound styles
                if (str_contains($patterns[$key], 'page/{page}')) {
                    $alt = str_replace('page/{page}', 'page-{page}', $patterns[$key]);
                    $routes[] = [
                        'regex'    => $this->patternToRouteRegex($alt),
                        'resource' => $baseResource,
                        'is_page'  => true,
                    ];
                } elseif (str_contains($patterns[$key], 'page-{page}')) {
                    $alt = str_replace('page-{page}', 'page/{page}', $patterns[$key]);
                    $routes[] = [
                        'regex'    => $this->patternToRouteRegex($alt),
                        'resource' => $baseResource,
                        'is_page'  => true,
                    ];
                }
            }
        }

        // Add legacy previous routes for seamless 301 redirection
        foreach ($prevPatterns as $resource => $prevPattern) {
            if ($prevPattern !== '' && (!isset($patterns[$resource]) || $prevPattern !== $patterns[$resource])) {
                $routes[] = [
                    'regex'    => $this->patternToRouteRegex($prevPattern),
                    'resource' => str_replace('_page', '', $resource),
                    'is_page'  => str_contains($resource, '_page'),
                    'legacy'   => true,
                ];
            }
        }

        if ($legacyUsuEnabled === null) {
            $legacyUsuEnabled = $this->configProvider !== null ? $this->configProvider->isLegacyUsuEnabled() : false;
        }

        // Add legacy Ultimate SEO URLs (USU) migration routes for 301 redirection
        if ($legacyUsuEnabled) {
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?P<slug>[^/]+?)-t(?P<id>[0-9]+)-(?:s)?(?P<start>[0-9]+)\.html$#i',
                'resource' => 'topic',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?P<slug>[^/]+?)-t(?P<id>[0-9]+)\.html$#i',
                'resource' => 'topic',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?:topic|t)(?P<id>[0-9]+)(?:-(?:s)?(?P<start>[0-9]+))?\.html$#i',
                'resource' => 'topic',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?P<slug>[^/]+?)-f(?P<id>[0-9]+)-(?:s)?(?P<start>[0-9]+)\.html$#i',
                'resource' => 'forum',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?P<slug>[^/]+?)-f(?P<id>[0-9]+)\.html$#i',
                'resource' => 'forum',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?:forum|f)(?P<id>[0-9]+)(?:-(?:s)?(?P<start>[0-9]+))?\.html$#i',
                'resource' => 'forum',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?:member|user|(?P<slug>[^/]+?)-u|u)(?P<id>[0-9]+)\.html$#i',
                'resource' => 'member',
                'is_page'  => false,
                'legacy'   => true,
            ];
            $routes[] = [
                'regex'    => '#^(?:.*/)?(?:post|p)(?P<id>[0-9]+)\.html$#i',
                'resource' => 'post',
                'is_page'  => false,
                'legacy'   => true,
            ];
        }

        // 4. Ensure store directory exists
        if (!is_dir($this->storeDir) && !@mkdir($this->storeDir, 0777, true)) {
            throw new RuntimeException("Unable to create store directory: {$this->storeDir}");
        }

        // 5. Write to temporary file first
        $tempFile = $this->storeDir . 'compiled_routes.php.tmp_' . bin2hex(random_bytes(4));
        $targetFile = $this->storeDir . 'compiled_routes.php';

        $fileContent = "<?php\n// Auto-generated route cache. Do not edit directly.\ndeclare(strict_types=1);\n\nreturn " . var_export($routes, true) . ";\n";

        if (@file_put_contents($tempFile, $fileContent) === false) {
            throw new RuntimeException("Failed to write temporary route cache: {$tempFile}");
        }

        // 6. Verify written temp file is valid PHP and returns an array
        try {
            $verified = include $tempFile;
            if (!is_array($verified) || empty($verified)) {
                @unlink($tempFile);
                throw new RuntimeException("Compiled route verification failed: invalid artifact structure.");
            }
        } catch (\Throwable $e) {
            @unlink($tempFile);
            throw new RuntimeException("Compiled route verification failed: " . $e->getMessage(), 0, $e);
        }

        // 7. Atomically rename temp file over target file
        if (!@rename($tempFile, $targetFile)) {
            // Windows fallback: unlink target then rename
            @unlink($targetFile);
            if (!@rename($tempFile, $targetFile)) {
                @unlink($tempFile);
                throw new RuntimeException("Failed to atomically replace {$targetFile}");
            }
        }
    }

    public function patternToRouteRegex(string $pattern): string
    {
        $regex = '/' . ltrim($pattern, '/');
        $regex = preg_quote($regex, '#');
        $regex = str_replace(['\\{', '\\}'], ['{', '}'], $regex);

        $regex = preg_replace('/\{id\}/', '(?P<id>[0-9]+)', $regex);
        $regex = preg_replace('/\{page\}/', '(?P<page>[0-9]+)', $regex);
        $regex = preg_replace('/\{slug\}/', '(?P<slug>[^/]+)', $regex);

        $cleanRegex = rtrim(ltrim(trim($regex, '#^$'), '/'), '/');
        return '#^/' . $cleanRegex . '/?$#u';
    }
}
