<?php
declare(strict_types=1);

namespace phpbbseo\framework\Migration;

use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbbseo\framework\Rewrite\InboundRouteResult;
use phpbbseo\framework\Url\PaginationResolver;
use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\RouterInterface;

/**
 * Deterministically resolves legacy Ultimate SEO URL (USU) paths to Framework InboundRouteResult.
 *
 * Supports all standard phpBB SEO / USU legacy URL structures:
 *  - Topics: [subfolder/]slug-t{id}[-(s){start}].html, topic{id}[-(s){start}].html, t{id}.html
 *  - Forums: [subfolder/]slug-f{id}[-(s){start}].html, forum{id}[-(s){start}].html, f{id}.html
 *  - Members: [subfolder/]member{id}.html, user{id}.html, u{id}.html, slug-u{id}.html
 *  - Posts: [subfolder/]post{id}.html, p{id}.html
 *
 * Zero database mapping table and zero legacy USU code required.
 */
class UsuMigrationResolver
{
    public function __construct(
        private readonly PaginationResolver $paginationResolver,
        private readonly ConfigurationProvider $configProvider,
        private readonly ?RouterInterface $router = null
    ) {}

    public function isEnabled(): bool
    {
        return $this->configProvider->isLegacyUsuEnabled();
    }

    /**
     * Attempts to match a relative path against known USU URL patterns.
     * Path must be raw or decoded, with or without leading board path.
     */
    public function resolve(string $path): ?InboundRouteResult
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $rawPath = $path;
        $path = rawurldecode($path);

        // Strip query string if present
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        $path = trim($path, '/');
        if ($path === '') {
            return null;
        }

        // If a file extension is present, it must be .html or .htm
        $ext = pathinfo($path, PATHINFO_EXTENSION);
        if ($ext !== '' && !in_array(strtolower($ext), ['html', 'htm'], true)) {
            return null;
        }

        // Extract filename / base segment (USU allowed virtual subfolders like "cat/subcat/topic-t123.html")
        $filename = basename($path);
        $result = null;

        // 1. Match Topic:
        // Examples: topic-slug-t123.html, topic_slug_t123.htm, t123, topic123.html, slug-t123-20.html, slug_t123_s20
        if (preg_match('#^(?:(?P<slug>[^/]+?)[-_])?t(?P<id>[0-9]+)(?:[-_](?:s)?(?P<start>[0-9]+))?(?:\.html?)?$#i', $filename, $matches)) {
            $id = (int) $matches['id'];
            if ($id > 0) {
                $slug = !empty($matches['slug']) ? $matches['slug'] : 'topic';
                $page = null;
                if (!empty($matches['start'])) {
                    $start = (int) $matches['start'];
                    $postsPerPage = (int) $this->configProvider->get('posts_per_page', 20);
                    $page = $this->paginationResolver->startToPage($start, $postsPerPage);
                }
                $result = new InboundRouteResult('topic', $id, $slug, $page);
            }
        } elseif (preg_match('#^topic(?P<id>[0-9]+)(?:[-_](?:s)?(?P<start>[0-9]+))?(?:\.html?)?$#i', $filename, $matches)) {
            $id = (int) $matches['id'];
            if ($id > 0) {
                $page = null;
                if (!empty($matches['start'])) {
                    $start = (int) $matches['start'];
                    $postsPerPage = (int) $this->configProvider->get('posts_per_page', 20);
                    $page = $this->paginationResolver->startToPage($start, $postsPerPage);
                }
                $result = new InboundRouteResult('topic', $id, 'topic', $page);
            }
        } elseif (preg_match('#^(?:(?P<slug>[^/]+?)[-_])?f(?P<id>[0-9]+)(?:[-_](?:s)?(?P<start>[0-9]+))?(?:\.html?)?$#i', $filename, $matches)) {
            $id = (int) $matches['id'];
            if ($id > 0) {
                $slug = !empty($matches['slug']) ? $matches['slug'] : 'forum';
                $page = null;
                if (!empty($matches['start'])) {
                    $start = (int) $matches['start'];
                    $topicsPerPage = (int) $this->configProvider->get('topics_per_page', 50);
                    $page = $this->paginationResolver->startToPage($start, $topicsPerPage);
                }
                $result = new InboundRouteResult('forum', $id, $slug, $page);
            }
        } elseif (preg_match('#^forum(?P<id>[0-9]+)(?:[-_](?:s)?(?P<start>[0-9]+))?(?:\.html?)?$#i', $filename, $matches)) {
            $id = (int) $matches['id'];
            if ($id > 0) {
                $page = null;
                if (!empty($matches['start'])) {
                    $start = (int) $matches['start'];
                    $topicsPerPage = (int) $this->configProvider->get('topics_per_page', 50);
                    $page = $this->paginationResolver->startToPage($start, $topicsPerPage);
                }
                $result = new InboundRouteResult('forum', $id, 'forum', $page);
            }
        } elseif (preg_match('#^(?:member|user|u)(?P<id>[0-9]+)(?:\.html?)?$#i', $filename, $matches) ||
            preg_match('#^(?P<slug>[^/]+?)[-_]u(?P<id>[0-9]+)(?:\.html?)?$#i', $filename, $matches)) {
            $id = (int) $matches['id'];
            if ($id > 0) {
                $slug = !empty($matches['slug']) ? $matches['slug'] : 'member';
                $result = new InboundRouteResult('member', $id, $slug, null);
            }
        } elseif (preg_match('#^(?:post|p)(?P<id>[0-9]+)(?:\.html?)?$#i', $filename, $matches)) {
            $id = (int) $matches['id'];
            if ($id > 0) {
                $result = new InboundRouteResult('post', $id, 'post', null);
            }
        }

        if ($result === null) {
            return null;
        }

        // Approach A: Verify with Symfony router before claiming the path.
        // If a registered controller route exists for this path, yield to Symfony.
        if ($this->isRegisteredRoute($rawPath)) {
            return null;
        }

        return $result;
    }

    /**
     * Checks whether an inbound path matches an existing registered Symfony controller route.
     * Prevents legacy USU patterns from shadowing or hijacking native core or extension routes.
     */
    private function isRegisteredRoute(string $path): bool
    {
        if ($this->router === null) {
            return false;
        }

        // Strip query string if present
        $qPos = strpos($path, '?');
        if ($qPos !== false) {
            $path = substr($path, 0, $qPos);
        }

        $cleanPath = '/' . ltrim($path, '/');
        $scriptPath = rtrim((string) $this->configProvider->get('script_path', '/'), '/');

        $candidates = [];
        if ($scriptPath !== '' && str_starts_with($cleanPath, $scriptPath . '/')) {
            $candidates[] = substr($cleanPath, strlen($scriptPath));
        }
        $candidates[] = $cleanPath;

        $testPaths = [];
        foreach ($candidates as $candidate) {
            $testPaths[] = $candidate;
            $trimmed = rtrim($candidate, '/');
            if ($trimmed !== '' && $trimmed !== $candidate) {
                $testPaths[] = $trimmed;
            }
        }
        $testPaths = array_unique($testPaths);

        foreach ($testPaths as $candidate) {
            try {
                $match = $this->router->match($candidate);
                if (!empty($match)) {
                    return true;
                }
            } catch (MethodNotAllowedException) {
                // Route is registered, though method does not match
                return true;
            } catch (ResourceNotFoundException) {
                // Definitively not matched for this candidate; try remaining candidates
                continue;
            } catch (\Throwable) {
                // Fail-safe (fail-closed toward NOT redirecting): An unexpected router error
                // means we cannot guarantee this path does not belong to a controller route.
                // Erring on the side of not redirecting prevents hijacking another extension's route,
                // which has a far worse real-world impact than skipping a legacy USU redirect.
                return true;
            }
        }

        return false;
    }
}
