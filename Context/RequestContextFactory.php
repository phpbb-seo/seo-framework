<?php
declare(strict_types=1);

namespace phpbbseo\framework\Context;

use phpbbseo\framework\Configuration\ConfigurationProvider;
use phpbb\request\request_interface;

/**
 * Factory for creating and sanitizing a RequestContext.
 */
class RequestContextFactory
{
    public function __construct(
        private readonly ConfigurationProvider $configProvider
    ) {}

    public function createFromPhpbbRequest(request_interface $request): RequestContext
    {
        $observedHost = $request->server('HTTP_HOST', '');
        $trustedHost = $this->determineTrustedHost($observedHost);
        
        $scheme = $request->server('HTTPS', 'off') !== 'off' ? 'https' : 'http';
        $canonicalScheme = $this->configProvider->get('server_protocol', $scheme . '://');
        $scheme = str_replace('://', '', $canonicalScheme);

        $rawUri = $request->server('SEO_PUBLIC_REQUEST_URI', '');
        if ($rawUri === '') {
            $rawUri = $request->server('REQUEST_URI', '');
        }
        $qPos = strpos($rawUri, '?');
        $rawPath = ($qPos !== false) ? substr($rawUri, 0, $qPos) : $rawUri;
        $rawQuery = ($qPos !== false) ? substr($rawUri, $qPos + 1) : '';

        $path = $this->sanitizePath($rawPath !== '' ? $rawPath : $request->server('SCRIPT_NAME', ''));
        $query = $this->sanitizeQuery($rawQuery);

        // Extracted later by route mapping
        $route = 'index';
        $entityId = null;
        $pagination = null;

        return new RequestContext(
            $scheme,
            $trustedHost,
            $path,
            $query,
            $route,
            $entityId,
            $pagination
        );
    }

    private function determineTrustedHost(string $observedHost): string
    {
        // Reject CR/LF
        if (preg_match('/[\r\n]/', $observedHost)) {
            return $this->getConfiguredHost();
        }

        $observedHost = strtolower(trim($observedHost));
        $configuredHost = strtolower($this->getConfiguredHost());

        // For foundation, we only trust the configured host.
        // If they differ, we enforce the configured host.
        if ($observedHost !== $configuredHost) {
            return $configuredHost;
        }

        return $observedHost;
    }

    private function getConfiguredHost(): string
    {
        $rawServerName = $this->configProvider->get('server_name', 'localhost');
        $serverName = parse_url('http://' . $rawServerName, PHP_URL_HOST) ?: $rawServerName;
        $serverPort = (int) $this->configProvider->get('server_port', 80);

        if ($serverPort !== 80 && $serverPort !== 443) {
            return $serverName . ':' . $serverPort;
        }

        return $serverName;
    }

    private function sanitizePath(string $path): string
    {
        // Prevent basic traversal and malformed unicode
        $path = mb_convert_encoding($path, 'UTF-8', 'UTF-8');
        return preg_replace('#//+#', '/', $path) ?? '/';
    }

    private function sanitizeQuery(string $query): string
    {
        return mb_convert_encoding($query, 'UTF-8', 'UTF-8');
    }
}
