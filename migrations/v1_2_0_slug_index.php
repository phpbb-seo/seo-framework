<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

/**
 * Migration to create the framework-owned phpbb_seo_slugs read-model table.
 */
class v1_2_0_slug_index extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return $this->db_tools->sql_table_exists($this->table_prefix . 'seo_slugs');
    }

    public static function depends_on(): array
    {
        return ['\phpbbseo\framework\migrations\v1_1_0_rewrite_config'];
    }

    public function update_schema(): array
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'seo_slugs' => [
                    'COLUMNS' => [
                        'resource_type' => ['UINT:8', 0], // 1 = forum, 2 = topic, 3 = member, 4 = group
                        'resource_id'   => ['UINT', 0],
                        'slug'          => ['VCHAR:255', ''],
                        'updated_at'    => ['TIMESTAMP', 0],
                    ],
                    'PRIMARY_KEY' => ['resource_type', 'resource_id'],
                ],
            ],
        ];
    }

    public function revert_schema(): array
    {
        return [
            'drop_tables' => [
                $this->table_prefix . 'seo_slugs',
            ],
        ];
    }

    public function update_data(): array
    {
        return [];
    }
}
