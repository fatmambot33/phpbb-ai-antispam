<?php
namespace bca\antispam\cron\task;

class scan extends \phpbb\cron\task\base
{
    private $config;
    private $scanner;
    private $api_key;

    public function __construct(\phpbb\config\config $config, \bca\antispam\service\scanner $scanner, ?\bca\antispam\service\api_key $api_key = null)
    {
        $this->config = $config;
        $this->scanner = $scanner;
        $this->api_key = $api_key;
    }

    public function is_runnable()
    {
        return (bool) $this->config['bca_antispam_enabled'] &&
            (bool) $this->config['bca_antispam_forum_ids'] &&
            $this->api_key !== null && $this->api_key->is_configured();
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
