<?php
declare(strict_types=1);

namespace phpbbseo\framework\Update;

use phpbb\cache\driver\driver_interface as cache_interface;
use phpbbseo\framework\Version\Version;

/**
 * Lightweight, cached GitHub Releases update checker for phpBB SEO Framework Lite.
 *
 * Runs exclusively in ACP contexts. Emits 0 HTTP requests on normal requests while
 * cached, and handles network or API anomalies gracefully without disrupting ACP.
 */
class UpdateChecker
{
    public const CACHE_KEY = '_pseo_framework_update_info';
    public const CACHE_TTL = 86400; // 24 hours
    public const TIMEOUT_SECONDS = 4;
    public const GITHUB_API_URL = 'https://api.github.com/repos/' . Version::REPOSITORY . '/releases/latest';
    public const GITHUB_REPO_URL = 'https://github.com/' . Version::REPOSITORY;

    public function __construct(
        private cache_interface $cache
    ) {}

    /**
     * Check for available updates.
     *
     * @param bool $forceRefresh If true, bypasses cache and queries remote source immediately.
     * @return UpdateResult
     */
    public function check(bool $forceRefresh = false): UpdateResult
    {
        $currentVersion = Version::getVersion();

        if (!$forceRefresh) {
            $cached = $this->cache->get(self::CACHE_KEY);
            if (is_array($cached) && !empty($cached)) {
                $result = UpdateResult::fromArray($cached);
                // Ensure current version in cached VO matches actual running version
                if ($result->getCurrentVersion() === $currentVersion) {
                    return $result;
                }
            }
        }

        // Perform remote check
        $result = $this->fetchLatestRelease($currentVersion);

        // If check succeeded or resulted in a valid state, update cache
        if ($result->getStatus() !== UpdateResult::STATUS_UNAVAILABLE) {
            $this->cache->put(self::CACHE_KEY, $result->toArray(), self::CACHE_TTL);
        } else {
            // On failure, check if we had a previous valid cache to keep displaying
            $previousCached = $this->cache->get(self::CACHE_KEY);
            if (is_array($previousCached) && !empty($previousCached) && ($previousCached['status'] ?? '') !== UpdateResult::STATUS_UNAVAILABLE) {
                return UpdateResult::fromArray($previousCached);
            }
        }

        return $result;
    }

    /**
     * Fetch latest stable release from official GitHub Releases API.
     *
     * @param string $currentVersion
     * @return UpdateResult
     */
    protected function fetchLatestRelease(string $currentVersion): UpdateResult
    {
        $timeNow = (int) time();
        $responseBody = $this->executeHttpRequest(self::GITHUB_API_URL);

        if ($responseBody === null || $responseBody === '') {
            return new UpdateResult(
                $currentVersion,
                $currentVersion,
                false,
                false,
                UpdateResult::STATUS_UNAVAILABLE,
                self::GITHUB_REPO_URL . '/releases',
                null,
                null,
                $timeNow,
                'Unable to contact GitHub Releases API'
            );
        }

        $data = json_decode($responseBody, true);
        if (!is_array($data) || !isset($data['tag_name'])) {
            return new UpdateResult(
                $currentVersion,
                $currentVersion,
                false,
                false,
                UpdateResult::STATUS_UNAVAILABLE,
                self::GITHUB_REPO_URL . '/releases',
                null,
                null,
                $timeNow,
                'Invalid release JSON payload'
            );
        }

        // Filter out drafts or unwanted prereleases if flagged
        if (!empty($data['draft'])) {
            return new UpdateResult(
                $currentVersion,
                $currentVersion,
                false,
                false,
                UpdateResult::STATUS_UNAVAILABLE,
                self::GITHUB_REPO_URL . '/releases',
                null,
                null,
                $timeNow,
                'Draft release ignored'
            );
        }

        // 1. Normalize and validate version tag
        $rawTag = (string) $data['tag_name'];
        $latestVersion = $this->normalizeVersion($rawTag);

        if (!$this->isValidVersionString($latestVersion)) {
            return new UpdateResult(
                $currentVersion,
                $currentVersion,
                false,
                false,
                UpdateResult::STATUS_UNAVAILABLE,
                self::GITHUB_REPO_URL . '/releases',
                null,
                null,
                $timeNow,
                'Malformed remote version tag'
            );
        }

        // 2. Validate release HTML URL
        $releaseUrl = (string) ($data['html_url'] ?? (self::GITHUB_REPO_URL . '/releases/tag/' . rawurlencode($rawTag)));
        if (!str_starts_with($releaseUrl, self::GITHUB_REPO_URL)) {
            $releaseUrl = self::GITHUB_REPO_URL . '/releases';
        }

        // 3. Discover official release download asset (e.g. .zip)
        $downloadUrl = null;
        if (isset($data['assets']) && is_array($data['assets'])) {
            foreach ($data['assets'] as $asset) {
                if (is_array($asset) && isset($asset['name'], $asset['browser_download_url'])) {
                    $assetName = (string) $asset['name'];
                    $assetUrl = (string) $asset['browser_download_url'];

                    // Match .zip asset from official repo
                    if (str_ends_with(strtolower($assetName), '.zip') && str_starts_with($assetUrl, self::GITHUB_REPO_URL)) {
                        $downloadUrl = $assetUrl;
                        break;
                    }
                }
            }
        }

        $publishedAt = isset($data['published_at']) ? (string) $data['published_at'] : null;

        // 4. Semver comparison
        $comparison = version_compare($latestVersion, $currentVersion);
        if ($comparison > 0) {
            $status = UpdateResult::STATUS_UPDATE_AVAILABLE;
            $updateAvailable = true;
            $isAhead = false;
        } elseif ($comparison < 0) {
            $status = UpdateResult::STATUS_AHEAD;
            $updateAvailable = false;
            $isAhead = true;
        } else {
            $status = UpdateResult::STATUS_UP_TO_DATE;
            $updateAvailable = false;
            $isAhead = false;
        }

        return new UpdateResult(
            $currentVersion,
            $latestVersion,
            $updateAvailable,
            $isAhead,
            $status,
            $releaseUrl,
            $downloadUrl,
            $publishedAt,
            $timeNow,
            null
        );
    }

    /**
     * Perform lightweight HTTP GET with strict timeout and fallback.
     *
     * @param string $url
     * @return ?string
     */
    protected function executeHttpRequest(string $url): ?string
    {
        $userAgent = 'phpBB-SEO-Framework-Updater/' . Version::VERSION . ' (+https://www.phpbbseo.com/)';

        // Prefer cURL if available
        if (function_exists('curl_init')) {
            $ch = @curl_init();
            if ($ch !== false) {
                @curl_setopt($ch, CURLOPT_URL, $url);
                @curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                @curl_setopt($ch, CURLOPT_TIMEOUT, self::TIMEOUT_SECONDS);
                @curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, self::TIMEOUT_SECONDS);
                @curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                @curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
                @curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
                @curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
                @curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'User-Agent: ' . $userAgent,
                    'Accept: application/vnd.github.v3+json',
                ]);

                $response = @curl_exec($ch);
                $httpCode = (int) @curl_getinfo($ch, CURLINFO_HTTP_CODE);
                @curl_close($ch);

                if ($response !== false && $httpCode >= 200 && $httpCode < 300) {
                    return (string) $response;
                }
            }
        }

        // Fallback to PHP streams
        $context = @stream_context_create([
            'http' => [
                'method'          => 'GET',
                'timeout'         => self::TIMEOUT_SECONDS,
                'follow_location' => 1,
                'max_redirects'   => 3,
                'header'          => "User-Agent: {$userAgent}\r\nAccept: application/vnd.github.v3+json\r\n",
                'ignore_errors'   => true,
            ],
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        return ($response !== false) ? $response : null;
    }

    /**
     * Normalize version string (strips leading 'v' / 'V' and trims whitespace).
     *
     * @param string $rawTag
     * @return string
     */
    public function normalizeVersion(string $rawTag): string
    {
        $trimmed = trim($rawTag);
        return ltrim($trimmed, 'vV');
    }

    /**
     * Validate semantic version string format.
     *
     * @param string $version
     * @return bool
     */
    public function isValidVersionString(string $version): bool
    {
        return (bool) preg_match('/^[0-9]+(?:\.[0-9]+)+(?:-[a-zA-Z0-9.]+)?$/', $version);
    }
}
