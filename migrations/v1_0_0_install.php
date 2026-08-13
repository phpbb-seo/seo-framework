<?php
declare(strict_types=1);

namespace phpbbseo\framework\migrations;

class v1_0_0_install extends \phpbb\db\migration\migration
{
    public function effectively_installed(): bool
    {
        return isset($this->config['phpbbseo_framework_version']);
    }

    public static function depends_on(): array
    {
        return ['\phpbb\db\migration\data\v330\v330'];
    }

    public function update_data(): array
    {
        return [
            ['config.add', ['phpbbseo_framework_version', '1.0.0']],
            ['config.add', ['phpbbseo_framework_enable', '1']],
        ];
    }
}
