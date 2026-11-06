<?php
namespace phpbb\config {
    class config extends \ArrayObject {
        public function set($key, $value) { $this[$key] = $value; }
    }
}
namespace phpbb\cron\task { class base {} }
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
    require_once __DIR__ . '/../cron/task/scan.php';
    require_once __DIR__ . '/../acp/main_module.php';
    define('POSTS_TABLE', 'phpbb_posts');
    function check($value, $message) {
        if (!$value) { throw new \RuntimeException($message); }
    }
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
    class request {
        public $scan = false;
        public function is_set_post($name) { return $name === 'scan_now' && $this->scan; }
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
                'reason' => 'fixture', 'action_taken' => 'observed', 'topic_id' => 2, 'existing_post_id' => 1,
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
    $task = new \bca\antispam\cron\task\scan($config, $scanner);
    $lock = new lock();
    $phpbb_container = new container();
    $phpbb_container->services = ['bca.antispam.cron.task.scan' => $task, 'cron.lock_db' => $lock];
    $valid_form = true;
    $module = new \bca\antispam\acp\main_module();
    $module->u_action = 'admin.php';
    $module->main('', 'settings');
    check(count($template->rows) === 30, 'More than five classifications must display');
    check($template->vars['RESULTS_SUMMARY'] === 'ACP_BCA_ANTISPAM_RESULTS_SUMMARY:30,35', 'Wrong total or displayed result count');
    check($template->vars['S_SCAN_DUE'], 'Never-run task should be due');

    $request->scan = true;
    $old_key = getenv('BCA_ANTISPAM_OPENAI_API_KEY');
    try {
        // Dummy key is only consumed by the runnable check; scanner is stubbed.
        putenv('BCA_ANTISPAM_OPENAI_API_KEY=fixture');
        $valid_form = false;
        invoke_notice($module, 'FORM_INVALID');
        check($scanner->calls === 0 && $lock->attempts === 0, 'CSRF failure must not start scanning');
        $valid_form = true;
        $config['bca_antispam_enabled'] = 0;
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SCAN_UNAVAILABLE');
        check($scanner->calls === 0, 'Disabled scanner was run');
        $config['bca_antispam_enabled'] = 1;
        putenv('BCA_ANTISPAM_OPENAI_API_KEY');
        invoke_notice($module, 'ACP_BCA_ANTISPAM_SCAN_UNAVAILABLE');
        check($scanner->calls === 0, 'Scanner ran without an API key');
        putenv('BCA_ANTISPAM_OPENAI_API_KEY=fixture');
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
        putenv($old_key === false ? 'BCA_ANTISPAM_OPENAI_API_KEY' : 'BCA_ANTISPAM_OPENAI_API_KEY=' . $old_key);
        restore_error_handler();
    }
    echo "ACP manual scan, cron locking and 30-row results tests passed\n";
}
