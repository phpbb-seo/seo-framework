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

    public function isSafe(string $url): bool
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

        $rawHost = $this->configProvider->get('server_name', 'localhost');
        $trustedHost = parse_url('http://' . $rawHost, PHP_URL_HOST) ?: $rawHost;

        if (isset($parsed['host']) && strtolower($parsed['host']) !== strtolower($trustedHost)) {
            return false;
        }

        return true;
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
