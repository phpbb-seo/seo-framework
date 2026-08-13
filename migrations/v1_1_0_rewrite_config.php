<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Phase 2: Adds default permalink configuration to phpBB config table.
 * No custom database tables are created.
 */
class v1_1_0_rewrite_config extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['seo_permalink_preset']);
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_0_0_install'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['seo_rewrite_enabled', '1']],
            ['config.add', ['seo_permalink_preset', 'modern']],
            ['config.add', ['seo_pattern_forum', '/forum/{slug}-{id}/']],
            ['config.add', ['seo_pattern_topic', '/topic/{slug}-{id}/']],
            ['config.add', ['seo_pattern_topic_page', '/topic/{slug}-{id}/page-{page}/']],
            ['config.add', ['seo_pattern_member', '/member/{slug}-{id}/']],
            ['config.add', ['seo_pattern_group', '/group/{slug}-{id}/']],
        ];
    }
}
