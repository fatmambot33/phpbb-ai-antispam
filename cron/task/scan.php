<?php
namespace bca\antispam\cron\task;

class scan extends \phpbb\cron\task\base
{
    private $config;
    private $scanner;
    private $request;

    public function __construct(\phpbb\config\config $config, \bca\antispam\service\scanner $scanner, ?\phpbb\request\request_interface $request = null)
    {
        $this->config = $config;
        $this->scanner = $scanner;
        $this->request = $request;
    }

    public function is_runnable()
    {
        return (bool) $this->config['bca_antispam_enabled'] &&
            (bool) $this->config['bca_antispam_forum_ids'] &&
            $this->request !== null && (bool) $this->request->server('BCA_ANTISPAM_OPENAI_API_KEY', '');
    }

    public function should_run()
    {
        return (int) $this->config['bca_antispam_last_run'] <= time() - (int) $this->config['bca_antispam_interval'];
    }

    public function run()
    {
        // Each post is classified once per scan; API errors are recorded as uncertain.
        try
        {
            return $this->scanner->run();
        }
        finally
        {
            $this->config->set('bca_antispam_last_run', time());
        }
    }
}
