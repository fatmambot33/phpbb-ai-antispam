<?php
// ACP regression: links come from persisted audit IDs, not a live topic lookup.
require_once __DIR__ . '/../acp/main_module.php';
define('POSTS_TABLE', 'phpbb_posts');

function check($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}
function add_form_key($name) {}
function append_sid($url, $query) { return $url . '?' . $query; }

class link_request
{
    public function is_set_post($name) { return false; }
}
class link_user
{
    public function add_lang_ext($extension, $file) {}
    public function format_date($time) { return (string) $time; }
}
class link_template
{
    public $rows = [];
    public function assign_block_vars($name, $values) { $this->rows[] = $values; }
    public function assign_vars($values) {}
}
class link_database
{
    public function sql_query_limit($sql, $limit)
    {
        $audit = [
            'checked_at' => 1, 'classification' => 'legitimate', 'confidence' => 99,
            'spam_probability' => 0, 'provider_confidence' => 99,
            'reason' => 'fixture', 'action_taken' => 'observed',
        ];
        // Audit records never store a URL or scan source. Existing and orphaned
        // rows must both retain links when viewed later in an ACP web request.
        return (object) ['rows' => [
            array_merge($audit, ['post_id' => 101, 'existing_post_id' => 101, 'topic_id' => 9]),
            array_merge($audit, ['post_id' => 102, 'existing_post_id' => null, 'topic_id' => null]),
            array_merge($audit, ['post_id' => 103, 'existing_post_id' => 103, 'topic_id' => 10]),
        ]];
    }
    public function sql_fetchrow($result) { return array_shift($result->rows); }
    public function sql_freeresult($result) {}
}

$config = [
    'bca_antispam_enabled' => 1, 'bca_antispam_auto_hide' => 0,
    'bca_antispam_threshold' => 95, 'bca_antispam_interval' => 120,
    'bca_antispam_days' => 30, 'bca_antispam_forum_ids' => '2',
    'bca_antispam_excluded_user_ids' => '', 'bca_antispam_cursor' => 103,
];
$request = new link_request();
$user = new link_user();
$template = new link_template();
$db = new link_database();
$table_prefix = 'phpbb_';
$phpbb_root_path = '../';
$phpEx = 'php';
$module = new \bca\antispam\acp\main_module();
$module->u_action = 'admin.php';
$module->main('', 'settings');

check(count($template->rows) === 3, 'An older audit row disappeared');
foreach ($template->rows as $i => $row)
{
    $id = 101 + $i;
    check($row['U_POST'] === '../viewtopic.php?p=' . $id . '#p' . $id, 'Missing direct post permalink for audit row ' . $id);
    check($row['S_POST_MISSING'] === ($id === 102), 'Wrong missing-post status for audit row ' . $id);
}
echo "ACP audit post permalink and missing-post status tests passed\n";
