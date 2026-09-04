<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration v1.6.0: Registers Safe Uninstall ACP mode and default config keys.
 */
class v1_6_0_safe_uninstall_module extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_safe_uninstall_installed']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_5_0_sitemap_modules'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_safe_uninstall_installed', '1']],
            ['config.add', ['phpbbseo_safe_uninstall_prepared', '0']],
            ['module.add', [
                'acp',
                'ACP_PHPBBSEO_TITLE',
                [
                    'module_basename' => '\phpbbseo\framework\acp\main_module',
                    'modes'           => ['safe_uninstall'],
                ],
            ]],
        ];
    }
}
