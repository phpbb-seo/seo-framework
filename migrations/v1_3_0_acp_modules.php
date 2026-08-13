<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration v1.3.0: Registers ACP SEO modules in phpBB ACP under Extensions tab.
 */
class v1_3_0_acp_modules extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_acp_installed']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_2_0_slug_index'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_acp_installed', '1']],
            ['config.add', ['seo_prev_pattern_forum', '/forum/{slug}-{id}/']],
            ['config.add', ['seo_prev_pattern_topic', '/topic/{slug}-{id}/']],
            ['config.add', ['seo_prev_pattern_member', '/member/{slug}-{id}/']],
            ['config.add', ['seo_prev_pattern_group', '/group/{slug}-{id}/']],
            ['module.add', [
                'acp',
                'ACP_CAT_DOT_MODS',
                'ACP_PHPBBSEO_TITLE',
            ]],
            ['module.add', [
                'acp',
                'ACP_PHPBBSEO_TITLE',
                [
                    'module_basename' => '\phpbbseo\framework\acp\main_module',
                    'modes'           => ['dashboard', 'permalinks'],
                ],
            ]],
        ];
    }
}
