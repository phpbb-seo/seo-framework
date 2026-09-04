<?php
declare(strict_types=1);

namespace phpbbseo\framework\acp;

/**
 * ACP Module Info: Registers Dashboard and Permalinks modes in phpBB ACP.
 */
class main_info
{
    public function module(): array
    {
        return [
            'filename' => '\phpbbseo\framework\acp\main_module',
            'title'    => 'ACP_PHPBBSEO_TITLE',
            'modes'    => [
                'dashboard'  => [
                    'title' => 'ACP_PHPBBSEO_DASHBOARD',
                    'auth'  => 'ext_phpbbseo/framework && acl_a_board',
                    'cat'   => ['ACP_PHPBBSEO_TITLE'],
                ],
                'permalinks' => [
                    'title' => 'ACP_PHPBBSEO_PERMALINKS',
                    'auth'  => 'ext_phpbbseo/framework && acl_a_board',
                    'cat'   => ['ACP_PHPBBSEO_TITLE'],
                ],
                'titles_meta' => [
                    'title' => 'ACP_PHPBBSEO_TITLES_META',
                    'auth'  => 'ext_phpbbseo/framework && acl_a_board',
                    'cat'   => ['ACP_PHPBBSEO_TITLE'],
                ],
                'sitemap' => [
                    'title' => 'ACP_PHPBBSEO_SITEMAP',
                    'auth'  => 'ext_phpbbseo/framework && acl_a_board',
                    'cat'   => ['ACP_PHPBBSEO_TITLE'],
                ],
                'safe_uninstall' => [
                    'title' => 'ACP_PHPBBSEO_SAFE_UNINSTALL',
                    'auth'  => 'ext_phpbbseo/framework && acl_a_board',
                    'cat'   => ['ACP_PHPBBSEO_TITLE'],
                ],
            ],
        ];
    }
}
