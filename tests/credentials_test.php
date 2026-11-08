<?php
namespace phpbb\config { class config extends \ArrayObject {} }
namespace bca\antispam\service {
    // No real HTTP calls: exercise the classifier's complete request path.
    function function_exists($name) { return $name === 'curl_init' || \function_exists($name); }
    function curl_init($url) { return new \stdClass(); }
    function curl_setopt_array($handle, $options) {
        $GLOBALS['captured_options'] = $options;
        return true;
    }
    function curl_exec($handle) {
        return '{"answers":[{"type":"choice","name":"forum_spam","choice":"legitimate","confidence":0.9,"probabilities":[{"value":"legitimate","probability":0.9},{"value":"spam","probability":0.01},{"value":"uncertain","probability":0.09}]}]}';
    }
    function curl_getinfo($handle, $option) { return 200; }
    function curl_errno($handle) { return 0; }
    function curl_close($handle) {}
}
namespace {
    require_once __DIR__ . '/api_key_fixture.php';
    require_once __DIR__ . '/../service/api_key.php';
    require_once __DIR__ . '/../service/classifier.php';
    foreach (['CURLOPT_POST', 'CURLOPT_HTTPHEADER', 'CURLOPT_POSTFIELDS',
        'CURLOPT_RETURNTRANSFER', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT',
        'CURLOPT_FOLLOWLOCATION', 'CURLOPT_SSL_VERIFYPEER', 'CURLOPT_SSL_VERIFYHOST',
        'CURLINFO_HTTP_CODE'] as $index => $name) {
        if (!defined($name)) { define($name, 10000 + $index); }
    }
    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
    }
    $storage = new \phpbb\config\db_text();
    $api_key = new \bca\antispam\service\api_key($storage);
    $config = new \phpbb\config\config(['bca_antispam_forum_context' => 'Aquarium keepers & récifs']);
    $classifier = new \bca\antispam\service\classifier($api_key, $config);
    $missing = $classifier->classify('Test', 'Test');
    check($missing['classification'] === 'uncertain' && $missing['error_code'] === 'missing_api_key',
        'Missing request credential must fail open');
    check(!isset($GLOBALS['captured_options']), 'Missing credential reached transport');

    putenv('BCA_ANTISPAM_OPENAI_API_KEY=ignored-environment-fixture');
    check($classifier->classify('Test', 'Test')['error_code'] === 'missing_api_key',
        'Environment key was used despite ACP-only storage');

    $api_key->replace('non-secret-test-fixture');
    $result = $classifier->classify('Test', 'Test');
    check($result['classification'] === 'legitimate', 'Request credential was not consumed');
    check(in_array('Authorization: Bearer non-secret-test-fixture',
        $GLOBALS['captured_options'][CURLOPT_HTTPHEADER], true), 'Wrong authorization header');
    check($GLOBALS['captured_options'][CURLOPT_SSL_VERIFYPEER] === true &&
        $GLOBALS['captured_options'][CURLOPT_SSL_VERIFYHOST] === 2, 'TLS verification was disabled');
    $payload = json_decode($GLOBALS['captured_options'][CURLOPT_POSTFIELDS], true);
    check(strpos($payload['input'], 'Forum: Aquarium keepers & récifs') === 0, 'Saved context did not reach the real request payload');
    $config['bca_antispam_forum_context'] = 'Woodworking hobbyists';
    $classifier->classify('Test', 'Test');
    $payload = json_decode($GLOBALS['captured_options'][CURLOPT_POSTFIELDS], true);
    check(strpos($payload['input'], 'Forum: Woodworking hobbyists') === 0, 'Context changes were not used on subsequent requests');
    $api_key->remove();
    check($classifier->classify('Test', 'Test')['error_code'] === 'missing_api_key', 'Removed key remained active');
    putenv('BCA_ANTISPAM_OPENAI_API_KEY');
    echo "ACP-only credential and classifier transport tests passed\n";
}
