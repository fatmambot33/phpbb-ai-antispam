<?php
namespace bca\antispam\migrations;

class acp_api_key extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\\bca\\antispam\\migrations\\forum_context'];
    }

    public function update_data()
    {
        // Existing environment keys are intentionally not imported into the DB.
        return [
            ['config_text.add', ['bca_antispam_api_key', '']],
        ];
    }
}
