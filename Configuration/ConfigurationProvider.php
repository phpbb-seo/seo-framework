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

    public function isMigrationRedirectEnabled(): bool
    {
        return $this->isEnabled() && (bool) ($this->config['seo_migration_redirect_enabled'] ?? false);
    }

    public function isMigrationPreserveIdsEnabled(): bool
    {
        return (bool) ($this->config['seo_migration_preserve_ids'] ?? false);
    }

    /**
     * Returns list of enabled migration platforms (lowercase).
     * Defaults to all supported platforms: xenforo, vbulletin, mybb, smf.
     *
     * @return string[]
     */
    public function getMigrationPlatforms(): array
    {
        $raw = (string) ($this->config['seo_migration_platforms'] ?? 'xenforo,vbulletin,mybb,smf');
        $parts = array_filter(array_map('trim', explode(',', strtolower($raw))));
        return !empty($parts) ? array_values($parts) : ['xenforo', 'vbulletin', 'mybb', 'smf'];
    }

    public function isMigrationPlatformEnabled(string $platform): bool
    {
        if (!$this->isMigrationRedirectEnabled()) {
            return false;
        }

        $platforms = $this->getMigrationPlatforms();
        return in_array(strtolower($platform), $platforms, true);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->config[$key] ?? $default;
    }
}


