<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration v1.4.0: Adds Titles & Meta configuration and registers ACP mode.
 */
class v1_4_0_meta_modules extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_meta_installed']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_3_0_acp_modules'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_meta_installed', '1']],
            ['config.add', ['seo_meta_enable', '1']],
            ['config.add', ['seo_meta_home_title', '{board_name}']],
            ['config.add', ['seo_meta_home_desc', '{site_desc}']],
            ['config.add', ['seo_meta_forum_title', '{forum_name} - {board_name}']],
            ['config.add', ['seo_meta_topic_title', '{topic_title} - {board_name}']],
            ['config.add', ['seo_meta_member_title', '{username} - {board_name}']],
            ['config.add', ['seo_meta_desc_max_len', '155']],
            ['module.add', [
                'acp',
                'ACP_PHPBBSEO_TITLE',
                [
                    'module_basename' => '\phpbbseo\framework\acp\main_module',
                    'modes'           => ['titles_meta'],
                ],
            ]],
        ];
    }
}
