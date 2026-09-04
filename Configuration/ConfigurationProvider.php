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

    public function isSafeUninstallPrepared(): bool
    {
        return (bool) ($this->config['phpbbseo_safe_uninstall_prepared'] ?? false);
    }

    public function isEnabled(): bool
    {
        if ($this->isSafeUninstallPrepared()) {
            return false;
        }

        return (bool) ($this->config['phpbbseo_framework_enable'] ?? false);
    }

    /**
     * Whether URL rewriting is active. Requires the extension to also be enabled
     * and Safe Uninstall fallback not to be actively prepared.
     */
    public function isRewriteEnabled(): bool
    {
        if ($this->isSafeUninstallPrepared()) {
            return false;
        }

        return $this->isEnabled() && (bool) ($this->config['seo_rewrite_enabled'] ?? false);
    }

    public function isLegacyUsuEnabled(): bool
    {
        if (!$this->isRewriteEnabled()) {
            return false;
        }

        return (bool) ($this->config['phpbbseo_legacy_usu_enabled'] ?? false);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}
