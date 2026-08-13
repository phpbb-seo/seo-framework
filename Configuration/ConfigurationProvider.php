<?php
declare(strict_types=1);

namespace phpbbseo\framework\Configuration;

/**
 * Provides access to SEO framework configuration settings.
 */
class ConfigurationProvider
{
    public function __construct(
        private readonly \phpbb\config\config $config
    ) {}

    public function isEnabled(): bool
    {
        return (bool) ($this->config['phpbbseo_framework_enable'] ?? false);
    }

    /**
     * Whether URL rewriting is active. Requires the extension to also be enabled.
     * Reads the 'seo_rewrite_enabled' config key installed by v1_1_0_rewrite_config migration.
     */
    public function isRewriteEnabled(): bool
    {
        return $this->isEnabled() && (bool) ($this->config['seo_rewrite_enabled'] ?? false);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
