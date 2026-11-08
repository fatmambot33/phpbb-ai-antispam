<?php
namespace phpbb\config {
    class config extends \ArrayObject {
        public function set($key, $value) { $this[$key] = $value; }
    }
}
namespace phpbb\cron\task { class base {} }
namespace phpbb\request {
    interface request_interface {
        const POST = 1;
        public function server($name, $default = '');
    }
}
namespace bca\antispam\service {
    class scanner {
        public $calls = 0;
        public $fail = false;
        public function run() {
            $this->calls++;
            if ($this->fail) { throw new \RuntimeException('scanner failure'); }
            return 7;
        }
    }
}
namespace {
    require_once __DIR__ . '/api_key_fixture.php';
    require_once __DIR__ . '/../service/api_key.php';
    require_once __DIR__ . '/../cron/task/scan.php';
    require_once __DIR__ . '/../acp/main_module.php';
    define('POSTS_TABLE', 'phpbb_posts');
    function check($value, $message) {
        if (!$value) { throw new \RuntimeException($message); }
    }
    function utf8_htmlspecialchars($value) { return htmlspecialchars($value, ENT_COMPAT, 'UTF-8'); }
    function utf8_substr($value, $start, $length) { return mb_substr($value, $start, $length, 'UTF-8'); }
    function add_form_key($name) {}
    function check_form_key($name) { return $GLOBALS['valid_form']; }
    function adm_back_link($url) { return ''; }
    function append_sid($url, $query) { return $url . '?' . $query; }
    class notice extends \RuntimeException {}
    set_error_handler(function ($severity, $message) {
        if ($severity === E_USER_NOTICE || $severity === E_USER_WARNING) {
            throw new notice($message);
        }
        return false;
    });
    class request implements \phpbb\request\request_interface {
        public $scan = false;
        public $submit = false;
        public $values = [];
        public $key = '';
        public function server($name, $default = '') { return $name === 'BCA_ANTISPAM_OPENAI_API_KEY' ? $this->key : $default; }
        public function is_set_post($name) { return ($name === 'scan_now' && $this->scan) || ($name === 'submit' && $this->submit) || array_key_exists($name, $this->values); }
        public function variable($name, $default, $multibyte = false, $super_global = null) {
            if ($name === 'new_api_key') { check($super_global === \phpbb\request\request_interface::POST, 'API key must be read only from POST'); }
            $value = $this->values[$name] ?? $default;
            return in_array($name, ['forum_context', 'new_api_key'], true) ? htmlspecialchars($value, ENT_COMPAT, 'UTF-8') : $value;
        }
    }
    class auth_fixture {
        public $allowed = true;
        public function acl_get($name) { return $this->allowed; }
    }
    class user {
        public function add_lang_ext($extension, $file) {}
        public function format_date($time) { return (string) $time; }
        public function lang($key, ...$args) { return $key . ':' . implode(',', $args); }
    }
    class template {
        public $vars = [];
        public $rows = [];
        public function assign_vars($values) { $this->vars = $values; }
        public function assign_block_vars($block, $values) { $this->rows[] = $values; }
    }
    class database {
        public function sql_query($sql) { return (object) ['rows' => [['total' => 35]]]; }
        public function sql_fetchfield($name, $row, $result) { return $result->rows[0][$name]; }
        public function sql_query_limit($sql, $limit) {
            check($limit === 30, 'Results query unexpectedly capped below 30');
            return (object) ['rows' => array_fill(0, $limit, [
                'post_id' => 1, 'checked_at' => 1, 'classification' => 'legitimate',
                'confidence' => 99, 'spam_probability' => 0, 'provider_confidence' => 99,
                'reason' => '<script>alert("x")</script> & café', 'action_taken' => 'observed', 'topic_id' => 2, 'existing_post_id' => 1,
            ])];
        }
        public function sql_fetchrow($result) { return array_shift($result->rows); }
        public function sql_freeresult($result) {}
    }
    class lock {
        public $available = true;
        public $attempts = 0;
        public $releases = 0;
        public function acquire() { $this->attempts++; return $this->available; }
        public function release() { $this->releases++; }
    }
    class container {
        public $services;
        public function get($name) { return $this->services[$name]; }
    }
    function invoke_notice($module, $expected) {
        try { $module->main('', 'settings'); }
        catch (notice $error) {
            check(strpos($error->getMessage(), $expected) === 0, $error->getMessage());
            return;
        }
        throw new \RuntimeException('Expected ACP outcome was missing: ' . $expected);
    }
    $config = new \phpbb\config\config([
        'bca_antispam_enabled' => 1, 'bca_antispam_forum_ids' => '2',
        'bca_antispam_last_run' => 0, 'bca_antispam_interval' => 120,
        'bca_antispam_auto_hide' => 0, 'bca_antispam_threshold' => 95,
        'bca_antispam_days' => 30, 'bca_antispam_excluded_user_ids' => '',
        'bca_antispam_cursor' => 5,
    ]);
    $request = new request();
    $user = new user();
    $template = new template();
    $db = new database();
    $table_prefix = 'phpbb_';
    $phpbb_root_path = '../';
    $phpEx = 'php';
    $scanner = new \bca\antispam\service\scanner();
    $storage = new \phpbb\config\db_text();
    $api_key = new \bca\antispam\service\api_key($storage);
    $auth = new auth_fixture();
    $task = new \bca\antispam\cron\task\scan($config, $scanner, $api_key);
    $lock = new lock();
    $phpbb_container = new container();
    $phpbb_container->services = ['bca.antispam.api_key' => $api_key, 'bca.antispam.cron.task.scan' => $task, 'cron.lock_db' => $lock];
    $valid_form = true;
    $module = new \bca\antispam\acp\main_module();
    $module->u_action = 'admin.php';
    $module->main('', 'settings');
    check(count($template->rows) === 30, 'More than five classifications must display');
    check($template->vars['RESULTS_SUMMARY'] === 'ACP_BCA_ANTISPAM_RESULTS_SUMMARY:30,35', 'Wrong total or displayed result count');
    check($template->vars['S_SCAN_DUE'], 'Never-run task should be due');
    check($template->rows[0]['REASON'] === '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt; &amp; café',
        'Untrusted audit reason was not escaped as UTF-8 text');

    try {
        $request->values = ['save_api_key' => 1, 'new_api_key' => 'acp-fixture-key'];
        $auth->allowed = false;
        invoke_notice($module, 'NOT_AUTHORISED');
        check(!$api_key->is_configured(), 'Unauthorized administrator changed key');
        $auth->allowed = true;
        $valid_form = false;
        invoke_notice($module, 'FORM_INVALID');
        check(!$api_key->is_configured(), 'Invalid CSRF saved a key');
        $valid_form = true;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_KEY_SAVED');
        check($api_key->get() === 'acp-fixture-key', 'ACP did not save key');
        check(!isset($config['bca_antispam_api_key']), 'Key entered globally cached config');
        $request->values = ['save_api_key' => 1, 'new_api_key' => ''];
        invoke_notice($module, 'ACP_BCA_ANTISPAM_KEY_UNCHANGED');
        check($api_key->get() === 'acp-fixture-key', 'Blank input erased key');
        $request->values = ['save_api_key' => 1, 'new_api_key' => "invalid\r\nInjected: header"];
        invoke_notice($module, 'ACP_BCA_ANTISPAM_KEY_INVALID');
        check($api_key->get() === 'acp-fixture-key', 'Invalid input replaced key');
        $request->values = [];
        $module->main('', 'settings');
        check($template->vars['API_KEY_SET'] === true, 'Configured status missing');
        check(strpos(json_encode($template->vars), 'acp-fixture-key') === false, 'Saved key reached template variables');
        $request->values = ['remove_api_key' => 1];
        $valid_form = false;
        invoke_notice($module, 'FORM_INVALID');
        check($api_key->is_configured(), 'Invalid CSRF removed key');
        $valid_form = true;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_KEY_REMOVED');
        check(!$api_key->is_configured() && !$task->is_runnable(), 'Removal did not stop scanning');
        $request->values = [];
        $request->submit = true;
        $request->values = ['forum_context' => '</textarea><script>alert("x")</script> & café' . str_repeat('é', 300),
            'enabled' => 1, 'forum_ids' => '2'];
        $valid_form = false;
        invoke_notice($module, 'FORM_INVALID');
        check(!isset($config['bca_antispam_forum_context']), 'CSRF failure saved context');
        $valid_form = true;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SAVED');
        check(mb_strlen($config['bca_antispam_forum_context'], 'UTF-8') === 240, 'Saved context is not character-bounded');
        check(strpos($config['bca_antispam_forum_context'], '& café') !== false, 'Context was stored HTML-encoded');
        $request->submit = false;
        $module->main('', 'settings');
        check(strpos($template->vars['FORUM_CONTEXT'], '&lt;/textarea&gt;') === 0, 'Context can escape its textarea');
        check(strpos($template->vars['FORUM_CONTEXT'], '<script>') === false, 'Context output is not escaped');
        check($template->rows[0]['REASON_LABEL'] === $template->rows[0]['REASON'], 'Custom audit text must remain safely escaped');
        $saved_context = $config['bca_antispam_forum_context'];
        $request->submit = true;
        unset($request->values['forum_context']);
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SAVED');
        check($config['bca_antispam_forum_context'] === $saved_context, 'An older cached form erased the saved context');
        $request->values['forum_context'] = '';
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SAVED');
        check($config['bca_antispam_forum_context'] === '', 'Explicit blank context did not enable the generic default');
        $request->submit = false;
        $request->values = [];
        $request->scan = true;
        // Dummy key is only consumed by the runnable check; scanner is stubbed.
        $api_key->replace('fixture');
        $valid_form = false;
        invoke_notice($module, 'FORM_INVALID');
        check($scanner->calls === 0 && $lock->attempts === 0, 'CSRF failure must not start scanning');
        $valid_form = true;
        $config['bca_antispam_enabled'] = 0;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SCAN_UNAVAILABLE');
        check($scanner->calls === 0, 'Disabled scanner was run');
        $config['bca_antispam_enabled'] = 1;
        $api_key->remove();
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SCAN_UNAVAILABLE');
        check($scanner->calls === 0, 'Scanner ran without an API key');
        $api_key->replace('fixture');
        $lock->available = false;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SCAN_BUSY');
        check($scanner->calls === 0 && $lock->releases === 0, 'Busy cron lock was ignored or released');
        $lock->available = true;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SCAN_COMPLETE:7');
        check($scanner->calls === 1 && $lock->releases === 1, 'Manual scan did not run exactly one locked batch');
        check($config['bca_antispam_last_run'] > 0, 'Scan attempt timestamp was not recorded');
        check($config['bca_antispam_cursor'] === 5 && $config['bca_antispam_auto_hide'] === 0, 'Manual scan changed saved settings');
        $scanner->fail = true;
        try { $module->main('', 'settings'); }
        catch (\RuntimeException $error) {
            check($error->getMessage() === 'scanner failure', 'Unexpected scan failure');
        }
        check($lock->releases === 2, 'Cron lock leaked after failure');
    } finally {
        restore_error_handler();
    }
    echo "ACP manual scan, cron locking and 30-row results tests passed\n";
}
