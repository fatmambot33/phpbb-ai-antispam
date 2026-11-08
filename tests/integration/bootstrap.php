<?php
// Only an isolated board created by run.py may be used.
$board = rtrim($argv[1] ?? '', '/') . '/';
if (!is_file($board . '.antispam-integration') || !is_file($board . 'config.php'))
{
    throw new RuntimeException('Refusing to bootstrap a board without the integration marker');
}
define('IN_PHPBB', true);
$phpbb_root_path = $board;
$phpEx = 'php';
require $board . 'common.php';
require $board . 'includes/functions_posting.php';
require $board . 'includes/functions_admin.php';
require $board . 'includes/functions_acp.php';
$user->session_begin();
$user->session_create(2, true);
$auth->acl($user->data);
$user->setup('posting');
set_error_handler(function ($severity, $message, $file, $line) {
    if (error_reporting() & $severity && $severity !== E_DEPRECATED && $severity !== E_USER_DEPRECATED)
    {
        throw new RuntimeException($message . ' at ' . $file . ':' . $line);
    }
    return false;
});
function integration_check($condition, $message)
{
    $GLOBALS['assertion_count']++;
    if (!$condition) { throw new RuntimeException($message); }
}
function integration_row($table, $field, $id)
{
    global $db;
    $r = $db->sql_query('SELECT * FROM ' . $table . ' WHERE ' . $field . ' = ' . (int) $id);
    $row = $db->sql_fetchrow($r);
    $db->sql_freeresult($r);
    return $row;
}
function integration_count($table)
{
    global $db;
    $r = $db->sql_query('SELECT COUNT(*) AS total FROM ' . $table);
    $count = (int) $db->sql_fetchfield('total', false, $r);
    $db->sql_freeresult($r);
    return $count;
}
function integration_post($subject, $text, $forum_id = 2, $topic_id = 0, $visibility = ITEM_APPROVED)
{
    global $user;
    $forum = integration_row(FORUMS_TABLE, 'forum_id', $forum_id);
    $uid = $bitfield = ''; $flags = 0;
    generate_text_for_storage($text, $uid, $bitfield, $flags, false, false, false);
    $data = [
        'forum_id' => $forum_id, 'topic_id' => $topic_id, 'icon_id' => 0,
        'enable_bbcode' => false, 'enable_smilies' => false, 'enable_urls' => false,
        'enable_sig' => false, 'message' => $text, 'message_md5' => md5($text),
        'bbcode_uid' => $uid, 'bbcode_bitfield' => $bitfield, 'post_edit_locked' => 0,
        'topic_title' => $subject, 'notify_set' => false, 'notify' => false,
        'post_time' => time(), 'forum_name' => $forum['forum_name'],
        'force_approved_state' => $visibility,
    ];
    if ($topic_id)
    {
        $data = array_merge(integration_row(TOPICS_TABLE, 'topic_id', $topic_id), $data);
    }
    $poll = [];
    submit_post($topic_id ? 'reply' : 'post', $subject, $user->data['username'], POST_NORMAL, $poll, $data, true, false);
    return ['post_id' => (int) $data['post_id'], 'topic_id' => (int) $data['topic_id'], 'forum_id' => $forum_id];
}
function integration_forum($name, $public, $password = '')
{
    global $db;
    $forum = integration_row(FORUMS_TABLE, 'forum_id', 2);
    unset($forum['forum_id']);
    $r = $db->sql_query('SELECT MAX(right_id) AS max_right FROM ' . FORUMS_TABLE);
    $left = 1 + (int) $db->sql_fetchfield('max_right', false, $r);
    $db->sql_freeresult($r);
    $forum = array_merge($forum, [
        'forum_name' => $name, 'parent_id' => 0, 'left_id' => $left, 'right_id' => $left + 1,
        'forum_parents' => '', 'forum_password' => $password, 'forum_posts_approved' => 0,
        'forum_posts_unapproved' => 0, 'forum_posts_softdeleted' => 0,
        'forum_topics_approved' => 0, 'forum_topics_unapproved' => 0, 'forum_topics_softdeleted' => 0,
        'forum_last_post_id' => 0, 'forum_last_post_time' => 0,
    ]);
    $db->sql_query('INSERT INTO ' . FORUMS_TABLE . ' ' . $db->sql_build_array('INSERT', $forum));
    $id = (int) $db->sql_nextid();
    if ($public)
    {
        $r = $db->sql_query('SELECT * FROM ' . ACL_GROUPS_TABLE . ' WHERE forum_id = 2');
        $permissions = [];
        while ($row = $db->sql_fetchrow($r)) { $row['forum_id'] = $id; $permissions[] = $row; }
        $db->sql_freeresult($r);
        $db->sql_multi_insert(ACL_GROUPS_TABLE, $permissions);
    }
    return $id;
}
class integration_classifier extends \bca\antispam\service\classifier
{
    public $calls = [];
    public $error = '';
    public $during;
    public $callback_completed = true;
    public function classify($subject, $body)
    {
        $this->calls[] = [$subject, $body];
        if ($this->during) { $callback = $this->during; $this->during = null; $this->callback_completed = false; $callback(); $this->callback_completed = true; }
        if ($this->error) { return self::unavailable($this->error); }
        $label = strpos($body, 'SPAM_FIXTURE') !== false ? 'spam' : 'legitimate';
        return [
            'classification' => $label, 'confidence' => 99, 'spam_probability' => $label === 'spam' ? 99 : 0,
            'provider_confidence' => 99, 'reason' => 'Integration fixture', 'error_code' => '',
        ];
    }
}
function integration_edit($post_id, $text)
{
    global $user;
    $post = integration_row(POSTS_TABLE, 'post_id', $post_id);
    $topic = integration_row(TOPICS_TABLE, 'topic_id', (int) $post['topic_id']);
    $forum = integration_row(FORUMS_TABLE, 'forum_id', (int) $post['forum_id']);
    $uid = $bitfield = ''; $flags = 0;
    generate_text_for_storage($text, $uid, $bitfield, $flags, false, false, false);
    $data = array_merge($topic, $post, [
        'message' => $text, 'message_md5' => md5($text), 'bbcode_uid' => $uid, 'bbcode_bitfield' => $bitfield,
        'enable_bbcode' => false, 'enable_smilies' => false, 'enable_urls' => false, 'enable_sig' => false,
        'forum_name' => $forum['forum_name'], 'post_time' => time(),
        'post_edit_reason' => 'Integration edit', 'post_edit_locked' => 0,
        'notify_set' => false, 'notify' => false, 'force_approved_state' => ITEM_APPROVED,
    ]);
    $poll = [];
    submit_post('edit', $post['post_subject'], $user->data['username'], POST_NORMAL, $poll, $data, true, false);
}
function integration_request_post(array $values)
{
    global $request;
    foreach ($values as $name => $value)
    {
        $request->overwrite($name, $value, \phpbb\request\request_interface::POST);
        $request->overwrite($name, $value, \phpbb\request\request_interface::REQUEST);
    }
}
class integration_container extends \Symfony\Component\DependencyInjection\Container
{
    private $delegate;
    private $cron;
    private $manager;
    public function __construct($delegate, $cron)
    {
        parent::__construct();
        $this->delegate = $delegate;
        $this->cron = $cron;
    }
    public function get($id, $invalidBehavior = self::EXCEPTION_ON_INVALID_REFERENCE)
    {
        if ($id === 'bca.antispam.cron.task.scan') { return $this->cron; }
        if ($id === 'cron.task_collection')
        {
            $tasks = [];
            foreach ($this->delegate->get($id) as $task)
            {
                $tasks[] = $task->get_name() === 'bca.antispam.cron.task.scan' ? $this->cron : $task;
            }
            return $tasks;
        }
        if ($id === 'cron.manager')
        {
            if (!$this->manager)
            {
                $this->manager = new \phpbb\cron\manager($this, $this->delegate->get('routing.helper'),
                    $GLOBALS['phpbb_root_path'], 'php', $GLOBALS['template']);
            }
            return $this->manager;
        }
        return $this->delegate->get($id, $invalidBehavior);
    }
    public function has($id) { return $this->delegate->has($id); }
    public function getParameter($name) { return $this->delegate->getParameter($name); }
    public function hasParameter($name) { return $this->delegate->hasParameter($name); }
    public function setParameter($name, $value) { $this->delegate->setParameter($name, $value); }
}
