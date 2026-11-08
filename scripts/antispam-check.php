<?php
/**
 * Temporary, read-only discovery check. Upload beside phpBB's config.php.
 * Requires a signed-in administrator with board configuration permission.
 * Delete this file after use.
 */
define('IN_PHPBB', true);
$phpbb_root_path = __DIR__ . '/';
$phpEx = 'php';

if (!is_file($phpbb_root_path . 'common.php'))
{
    http_response_code(404);
    header('Content-Type: text/plain; charset=UTF-8');
    exit("Place this file in the active phpBB root, beside common.php and config.php.\n");
}

require $phpbb_root_path . 'common.php';
$user->session_begin();
$auth->acl($user->data);

header('Content-Type: text/plain; charset=UTF-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');

if (empty($user->data['is_registered']) || !$auth->acl_get('a_board'))
{
    http_response_code(403);
    exit("Sign in to this forum as an administrator, then reopen this page.\n");
}

echo "AI Anti-Spam discovery check (no extension data is changed)\n";
echo "phpBB version: " . PHPBB_VERSION . "\n";
echo "PHP version: " . PHP_VERSION . "\n";
echo "Active forum directory: " . __DIR__ . "\n\n";

$extension = $phpbb_root_path . 'ext/bca/antispam/';
foreach (['ext/', 'ext/bca/', 'ext/bca/antispam/'] as $relative)
{
    $path = $phpbb_root_path . $relative;
    echo $relative . ': ' . (is_dir($path) ? 'directory exists' : 'MISSING') .
        '; readable: ' . (is_readable($path) ? 'yes' : 'no') . "\n";
}
foreach (['composer.json', 'acp/main_info.php', 'acp/main_module.php', 'config/services.yml', 'migrations/install.php'] as $relative)
{
    $path = $extension . $relative;
    echo 'ext/bca/antispam/' . $relative . ': ' .
        (is_file($path) ? 'file exists' : 'MISSING') .
        '; readable: ' . (is_readable($path) ? 'yes' : 'no') . "\n";
}

echo "\n";
if (is_readable($extension . 'composer.json'))
{
    $metadata = json_decode(file_get_contents($extension . 'composer.json'), true);
    echo 'composer.json valid JSON object: ' . (is_array($metadata) ? 'yes' : 'NO') . "\n";
    echo 'Composer name matches bca/antispam: ' .
        (is_array($metadata) && ($metadata['name'] ?? '') === 'bca/antispam' ? 'yes' : 'NO') . "\n";
}

try
{
    $manager = $phpbb_container->get('ext.manager');
    $metadata_manager = $manager->create_extension_metadata_manager('bca/antispam');
    $metadata_manager->get_metadata('all');
    $metadata_manager->validate('all');
    echo "phpBB metadata validation: PASS\n";
    echo 'phpBB reports available: ' . ($manager->is_available('bca/antispam') ? 'yes' : 'NO') . "\n";
    echo 'phpBB reports enabled: ' . ($manager->is_enabled('bca/antispam') ? 'yes' : 'no') . "\n";
    echo 'phpBB reports disabled: ' . ($manager->is_disabled('bca/antispam') ? 'yes' : 'no') . "\n";
    $available = $manager->all_available();
    echo 'Listed by discovery: ' . (isset($available['bca/antispam']) ? 'yes' : 'NO') . "\n";
}
catch (\Throwable $error)
{
    // phpBB metadata errors normally use language keys. Do not dump arbitrary
    // exception details, stack traces, environment values or configuration.
    $code = $error->getMessage();
    echo 'Discovery error class: ' . get_class($error) . "\n";
    echo 'Discovery error code: ' .
        (preg_match('/^[A-Z][A-Z0-9_]{1,100}$/D', $code) ? $code : 'Details withheld; consult server logs') . "\n";
}

echo "\nDelete antispam-check.php after collecting this output.\n";
