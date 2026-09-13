<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration v1.7.0: Registers Cross-Platform Migration Redirector config defaults.
 * Defaults to disabled (0) with all platforms available and preserve_ids disabled.
 */
class v1_7_0_migration_redirect_config extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_migration_redirect_installed']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_6_0_safe_uninstall_module'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_migration_redirect_installed', '1']],
            ['config.add', ['seo_migration_redirect_enabled', '0']],
            ['config.add', ['seo_migration_preserve_ids', '0']],
            ['config.add', ['seo_migration_platforms', 'xenforo,vbulletin,mybb,smf']],
        ];
    }
}


