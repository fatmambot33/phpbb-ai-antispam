<?php
namespace bca\antispam\acp;

class main_info
{
    public function module()
    {
        return [
            'filename' => '\\bca\\antispam\\acp\\main_module',
            'title' => 'ACP_BCA_ANTISPAM_TITLE',
            'modes' => [
                'settings' => [
                    'title' => 'ACP_BCA_ANTISPAM_SETTINGS',
                    'auth' => 'ext_bca/antispam && acl_a_board',
                    'cat' => ['ACP_BCA_ANTISPAM_TITLE'],
                ],
            ],
        ];
    }
}
