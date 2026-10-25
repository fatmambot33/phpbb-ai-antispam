<?php
// Standalone scanner regression tests; no phpBB installation or API key required.
namespace phpbb\config {
    class config extends \ArrayObject {
        public function set($key, $value) { $this[$key] = $value; }
    }
}
namespace phpbb\db\driver { interface driver_interface {} }
namespace phpbb { class content_visibility {} }
namespace phpbb\auth {
    class auth {
        public $permissions = ['f_list' => 1, 'f_read' => 1];
        public function acl_raw_data($user, $options, $forum) {
            return [$user => [$forum => $this->permissions]];
        }
    }
}
namespace bca\antispam\service {
    function time() { return $GLOBALS['scanner_test_time']; }
}
namespace {
    $GLOBALS['scanner_test_time'] = 1000000000;
    define('ANONYMOUS', 1);
    define('ACL_YES', 1);
    define('ACL_NO', 0);
    define('ITEM_APPROVED', 1);
    require_once __DIR__ . '/../service/classifier.php';
    require_once __DIR__ . '/../service/scanner.php';

    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
    }

    class database implements \phpbb\db\driver\driver_interface {
        public $password = '';
        public $post;
        public $saved;
        public $values;
        public $edits_sql;
        public $on_select;
        public function __construct() {
            $this->post = [
                'post_id' => 10, 'forum_id' => 2, 'poster_id' => 3,
                'topic_id' => 4, 'topic_title' => 'BMW',
                'post_subject' => 'Hello', 'post_text' => 'Original',
                'post_edit_time' => 0, 'post_visibility' => 1,
            ];
        }
        public function sql_in_set($field, $values) { return $field . ' IN (' . implode(',', $values) . ')'; }
        public function sql_query_limit($sql, $limit) {
            if ($this->on_select) { call_user_func($this->on_select); }
            if (strpos($sql, 'INNER JOIN phpbb_bca_antispam_results') !== false) {
                $this->edits_sql = $sql;
                $eligible = $this->saved && $this->post['post_edit_time'] >= $this->saved['checked_at'];
                // Make the fake query honor the actual comparison being tested.
                if (strpos($sql, 'p.post_edit_time >= r.checked_at') === false) {
                    $eligible = $this->saved && $this->post['post_edit_time'] > $this->saved['checked_at'];
                }
                return (object) ['rows' => $eligible ? [$this->post] : []];
            }
            return (object) ['rows' => $this->saved ? [] : [$this->post]];
        }
        public function sql_query($sql) {
            $rows = [];
            if (strpos($sql, 'SELECT forum_password') === 0) {
                $rows = $this->password === null ? [] : [['forum_password' => $this->password]];
            } elseif (strpos($sql, 'SELECT post_id') === 0) {
                $rows = $this->saved ? [['post_id' => 10]] : [];
            } elseif (strpos($sql, 'INSERT INTO') === 0 || strpos($sql, 'UPDATE') === 0) {
                $this->saved = $this->values;
            }
            return (object) ['rows' => $rows];
        }
        public function sql_fetchrow($result) { return array_shift($result->rows); }
        public function sql_freeresult($result) {}
        public function sql_build_array($type, $values) { $this->values = $values; return 'fixture'; }
    }
    class fake_classifier extends \bca\antispam\service\classifier {
        public $calls = 0;
        public $during_request;
        public function classify($subject, $body) {
            $this->calls++;
            if ($this->during_request) { call_user_func($this->during_request); }
            return self::unavailable('timeout');
        }
    }
    $db = new database();
    $auth = new \phpbb\auth\auth();
    $classifier = new fake_classifier();
    $config = new \phpbb\config\config([
        'bca_antispam_forum_ids' => '2', 'bca_antispam_excluded_user_ids' => '',
        'bca_antispam_days' => 30, 'bca_antispam_cursor' => 0,
        'bca_antispam_auto_hide' => 0,
    ]);
    $scanner = new \bca\antispam\service\scanner(
        $config, $db, new \phpbb\content_visibility(), $classifier, 'phpbb_', $auth
    );

    foreach ([
        ['f_list' => 1, 'f_read' => 0],
        ['f_list' => 0, 'f_read' => 1],
        ['f_list' => 1, 'f_read' => -1],
        [],
    ] as $permissions) {
        $auth->permissions = $permissions;
        check($scanner->run() === 0 && $classifier->calls === 0, 'Private forum reached provider');
    }
    $auth->permissions = ['f_list' => 1, 'f_read' => 1];
    $db->password = 'protected';
    check($scanner->run() === 0 && $classifier->calls === 0, 'Password forum reached provider');
    $db->password = null;
    check($scanner->run() === 0 && $classifier->calls === 0, 'Missing forum reached provider');
    $db->password = '';
    $db->on_select = function () use ($auth) {
        $auth->permissions = ['f_list' => 1, 'f_read' => 0];
    };
    check($scanner->run() === 0 && $classifier->calls === 0, 'ACL change after batch selection was ignored');
    $db->on_select = null;
    $auth->permissions = ['f_list' => 1, 'f_read' => 1];
    $classifier->during_request = function () use ($db) {
        $db->post['post_text'] = 'Edited while API request was running';
        $db->post['post_edit_time'] = $GLOBALS['scanner_test_time'] + 5;
        $GLOBALS['scanner_test_time'] += 25;
    };
    check($scanner->run() === 1, 'Public post was not scanned');
    check($db->saved['classification'] === 'uncertain', 'Failure must remain uncertain');
    check($db->saved['checked_at'] === 1000000000, 'Snapshot timestamp must precede the slow request');
    $classifier->during_request = null;
    check($scanner->run() === 1 && $classifier->calls === 2, 'Edit during slow request was lost');
    // Force the exact same-second boundary deterministically.
    $db->post['post_edit_time'] = $db->saved['checked_at'];
    $classifier->during_request = null;
    check($scanner->run() === 1 && $classifier->calls === 3, 'Concurrent same-second edit was lost');
    check($db->saved['post_hash'] === hash('sha256', "Hello\0" . $db->post['post_text']), 'Edited version not recorded');
    $auth->permissions = ['f_list' => 1, 'f_read' => 0];
    check($scanner->run() === 0 && $classifier->calls === 3, 'Forum made private was still scanned');
    echo "Scanner privacy and concurrent-edit regression tests passed\n";
}
