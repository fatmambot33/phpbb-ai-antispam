<?php
require __DIR__ . '/api_key_fixture.php';
require __DIR__ . '/../service/api_key.php';
function check($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
$storage = new \phpbb\config\db_text();
$key = new \bca\antispam\service\api_key($storage);
check(!$key->is_configured(), 'Empty storage reported configured');
check($key->replace(' fixture-key ') && $key->get() === 'fixture-key', 'Key not normalized/stored');
check(!$key->replace('') && $key->get() === 'fixture-key', 'Blank input did not preserve key');
$writes = $storage->writes;
$key->replace('fixture-key');
check($storage->writes === $writes, 'Identical key caused a redundant DB update');
foreach (["bad\r\nInjected: header", 'bad key', str_repeat('a', 1025), 'non-ascii-é'] as $invalid) {
    try { $key->replace($invalid); throw new RuntimeException('Invalid key was accepted'); }
    catch (InvalidArgumentException $error) {
        check($error->getMessage() === 'invalid_api_key_format', 'Error disclosed input');
    }
    check($key->get() === 'fixture-key', 'Invalid input changed saved key');
}
$key->replace('replacement-fixture');
check($key->get() === 'replacement-fixture', 'Replacement key was not saved');
$key->remove();
check(!$key->is_configured() && $storage->values === [], 'Key was not removed');
echo "Uncached ACP key replacement, preservation, validation and removal tests passed\n";
