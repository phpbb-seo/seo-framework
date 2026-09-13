<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration v1.8.0: Registers Latin ASCII slug transliteration config setting.
 * Defaults to disabled (0).
 */
class v1_8_0_transliterate_slugs_config extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_transliterate_slugs']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_7_0_migration_redirect_config'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_transliterate_slugs', '0']],
        ];
    }
}