<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration v1.5.0: Adds XML Sitemap configuration and registers ACP mode.
 */
class v1_5_0_sitemap_modules extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_sitemap_installed']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_4_0_meta_modules'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_sitemap_installed', '1']],
            ['config.add', ['seo_sitemap_enable', '1']],
            ['config.add', ['seo_sitemap_urls_per_file', '50000']],
            ['module.add', [
                'acp',
                'ACP_PHPBBSEO_TITLE',
                [
                    'module_basename' => '\phpbbseo\framework\acp\main_module',
                    'modes'           => ['sitemap'],
                ],
            ]],
        ];
    }
}
