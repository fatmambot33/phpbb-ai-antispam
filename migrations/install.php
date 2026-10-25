<?php
/**
 * Initial schema, configuration, and ACP module for BCA AI Anti-Spam.
 */
namespace bca\antispam\migrations;

class install extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\\phpbb\\db\\migration\\data\\v320\\v320'];
    }

    public function update_schema()
    {
        return [
            'add_tables' => [
                $this->table_prefix . 'bca_antispam_results' => [
                    'COLUMNS' => [
                        'post_id' => ['UINT', 0],
                        'checked_at' => ['TIMESTAMP', 0],
                        'post_hash' => ['VCHAR:64', ''],
                        'classification' => ['VCHAR:16', ''],
                        'confidence' => ['UINT:3', 0],
                        'spam_probability' => ['UINT:3', 0],
                        'provider_confidence' => ['UINT:3', 0],
                        'reason' => ['VCHAR:255', ''],
                        'action_taken' => ['VCHAR:24', ''],
                        'error_code' => ['VCHAR:32', ''],
                    ],
                    'PRIMARY_KEY' => 'post_id',
                    'KEYS' => ['checked_at' => ['INDEX', 'checked_at']],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return ['drop_tables' => [$this->table_prefix . 'bca_antispam_results']];
    }

    public function update_data()
    {
        return [
            ['config.add', ['bca_antispam_enabled', 0]],
            ['config.add', ['bca_antispam_auto_hide', 0]],
            ['config.add', ['bca_antispam_forum_ids', '']],
            ['config.add', ['bca_antispam_excluded_user_ids', '']],
            ['config.add', ['bca_antispam_threshold', 95]],
            ['config.add', ['bca_antispam_interval', 120]],
            ['config.add', ['bca_antispam_last_run', 0]],
            ['config.add', ['bca_antispam_cursor', 0]],
            ['config.add', ['bca_antispam_days', 30]],
            ['module.add', ['acp', 'ACP_CAT_DOT_MODS', 'ACP_BCA_ANTISPAM_TITLE']],
            ['module.add', ['acp', 'ACP_BCA_ANTISPAM_TITLE', [
                'module_basename' => '\\bca\\antispam\\acp\\main_module',
                'modes' => ['settings'],
            ]]],
        ];
    }
}
