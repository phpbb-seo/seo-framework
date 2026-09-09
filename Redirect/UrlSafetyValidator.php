<?php
declare(strict_types=1);

namespace phpbbseo\framework\Redirect;

/**
 * Validates URLs to prevent open redirects and unsafe schemes.
 */
class UrlSafetyValidator
{
    private array $allowedSchemes = ['http', 'https'];

    public function __construct(
        private readonly \phpbbseo\framework\Configuration\ConfigurationProvider $configProvider
    ) {}

    public static function extractDomain(string $rawHost): string
    {
        $clean = trim($rawHost);
        if (preg_match('#^https?://#i', $clean)) {
            $domain = (string) parse_url($clean, PHP_URL_HOST);
        } else {
            $domain = (string) parse_url('http://' . $clean, PHP_URL_HOST) ?: $clean;
        }
        return strtolower(rtrim($domain, '/'));
    }

    public function isHostTrusted(string $targetHost, ?string $requestHost = null): bool
    {
        $cleanTargetHost = self::extractDomain($targetHost);
        if ($cleanTargetHost === '') {
            return false;
        }

        // 1. Check against current request host if provided
        if ($requestHost !== null && $requestHost !== '') {
            $cleanRequestHost = self::extractDomain($requestHost);
            if ($cleanRequestHost !== '' && $cleanTargetHost === $cleanRequestHost) {
                return true;
            }
        }

        // 2. Check against generate_board_url() host if available
        if (function_exists('generate_board_url')) {
            $boardUrl = generate_board_url();
            if ($boardUrl !== '') {
                $boardUrlHost = self::extractDomain($boardUrl);
                if ($boardUrlHost !== '' && $cleanTargetHost === $boardUrlHost) {
                    return true;
                }
            }
        }

        // 3. Check against configured server_name in phpbb_config
        $rawConfigHost = (string) $this->configProvider->get('server_name', 'localhost');
        $trustedHost = self::extractDomain($rawConfigHost);

        if ($trustedHost !== '') {
            // Exact match
            if ($cleanTargetHost === $trustedHost) {
                return true;
            }
            // Strict subdomain of configured host (e.g. forum.example.com under example.com)
            // Note: Must end with '.' . $trustedHost to strictly prevent evil-domain.com matching domain.com
            if (str_ends_with($cleanTargetHost, '.' . $trustedHost)) {
                return true;
            }
            // If configured server_name is localhost/127.0.0.1, trust observed requestHost
            if (($trustedHost === 'localhost' || $trustedHost === '127.0.0.1') && $requestHost !== null && $requestHost !== '') {
                $cleanRequestHost = self::extractDomain($requestHost);
                if ($cleanRequestHost !== '' && $cleanTargetHost === $cleanRequestHost) {
                    return true;
                }
            }
        }

        return false;
    }

    public function isSafe(string $url, ?string $requestHost = null): bool
    {
        // Reject CR/LF
        if (preg_match('/[\r\n]/', $url)) {
            return false;
        }

        $parsed = parse_url($url);
        if ($parsed === false) {
            return false;
        }

        if (isset($parsed['scheme']) && !in_array(strtolower($parsed['scheme']), $this->allowedSchemes, true)) {
            return false;
        }

        if (!isset($parsed['host'])) {
            return true;
        }

        return $this->isHostTrusted($parsed['host'], $requestHost);
    }
    
    public function normalizeUrl(string $url): string
    {
        // Basic normalization for loop detection
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }
        
        $scheme = isset($parsed['scheme']) ? strtolower($parsed['scheme']) . '://' : '';
        $host = isset($parsed['host']) ? strtolower($parsed['host']) : '';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        
        // Remove default ports
        if (($scheme === 'http://' && $port === ':80') || ($scheme === 'https://' && $port === ':443')) {
            $port = '';
        }
        
        $path = $parsed['path'] ?? '/';
        $path = preg_replace('#//+#', '/', $path) ?? '/';
        $path = rawurldecode($path);
        
        $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
        $fragment = isset($parsed['fragment']) ? '#' . $parsed['fragment'] : '';
        
        return $scheme . $host . $port . $path . $query . $fragment;
    }
}
