<?php
namespace bca\antispam\service;

/** ACP-managed key stored outside phpBB's globally cached configuration. */
class api_key
{
    private const NAME = 'bca_antispam_api_key';
    private $config_text;

    public function __construct(\phpbb\config\db_text $config_text)
    {
        $this->config_text = $config_text;
    }

    public function get()
    {
        return (string) $this->config_text->get(self::NAME);
    }

    public function is_configured()
    {
        return $this->get() !== '';
    }

    /** Blank input preserves the saved key. Never include values in errors. */
    public function replace($value)
    {
        $value = trim((string) $value);
        if ($value === '') { return false; }
        // Reject whitespace/control characters that could alter HTTP headers.
        if (!preg_match('/^[\\x21-\\x7E]{1,1024}$/D', $value))
        {
            throw new \InvalidArgumentException('invalid_api_key_format');
        }
        if ($value !== $this->get())
        {
            $this->config_text->set(self::NAME, $value);
        }
        return true;
    }

    public function remove()
    {
        $this->config_text->delete(self::NAME);
    }
}
