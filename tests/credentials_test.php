<?php
namespace phpbb\request {
    interface request_interface {
        public function server($name, $default = '');
    }
}
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
    class server_request implements \phpbb\request\request_interface {
        public $values = [];
        public $queried = [];
        public function server($name, $default = '') {
            $this->queried[] = $name;
            return $this->values[$name] ?? $default;
        }
    }
    $request = new server_request();
    $classifier = new \bca\antispam\service\classifier($request);
    $missing = $classifier->classify('Test', 'Test');
    check($missing['classification'] === 'uncertain' && $missing['error_code'] === 'missing_api_key',
        'Missing request credential must fail open');
    check(!isset($GLOBALS['captured_options']), 'Missing credential reached transport');

    $request->values['HTTP_BCA_ANTISPAM_OPENAI_API_KEY'] = 'untrusted-header-fixture';
    check($classifier->classify('Test', 'Test')['error_code'] === 'missing_api_key',
        'A client header was used as the API key');

    $request->values['BCA_ANTISPAM_OPENAI_API_KEY'] = 'non-secret-test-fixture';
    $result = $classifier->classify('Test', 'Test');
    check($result['classification'] === 'legitimate', 'Request credential was not consumed');
    check(in_array('Authorization: Bearer non-secret-test-fixture',
        $GLOBALS['captured_options'][CURLOPT_HTTPHEADER], true), 'Wrong authorization header');
    check($GLOBALS['captured_options'][CURLOPT_SSL_VERIFYPEER] === true &&
        $GLOBALS['captured_options'][CURLOPT_SSL_VERIFYHOST] === 2, 'TLS verification was disabled');
    check(array_unique($request->queried) === ['BCA_ANTISPAM_OPENAI_API_KEY'],
        'Unexpected credential source was queried');
    echo "phpBB request credential and classifier transport tests passed\n";
}
