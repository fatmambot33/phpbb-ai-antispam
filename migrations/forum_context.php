<?php
namespace bca\antispam\migrations;

class forum_context extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\\bca\\antispam\\migrations\\install'];
    }

    public function update_data()
    {
        // The initial migration supplies an empty generic context on new installs.
        // config.add preserves existing values; only older installs get the
        // previous BMW-specific context so an upgrade does not change their focus.
        return [
            ['config.add', ['bca_antispam_forum_context',
                'French classic BMW owners club. BMW cars/parts classifieds, garages and suppliers recommended in context, club events, discussions, greetings and relevant links can be legitimate, including commercial names.']],
        ];
    }
}
