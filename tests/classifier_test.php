<?php
require_once __DIR__ . '/../service/classifier.php';
use bca\antispam\service\classifier;

function decision_fixture($label, $p_spam, $provider_confidence)
{
    $values = ['legitimate' => 0.005, 'spam' => $p_spam, 'uncertain' => 0.995 - $p_spam];
    return json_encode(['id' => 'dec_test', 'answers' => [[
        'type' => 'choice', 'name' => 'forum_spam', 'choice' => $label,
        'confidence' => $provider_confidence,
        'probabilities' => array_map(function ($key, $value) {
            return ['value' => $key, 'probability' => $value];
        }, array_keys($values), array_values($values)),
    ]]], JSON_THROW_ON_ERROR);
}
function assert_true($value, $reason)
{
    if (!$value) { throw new RuntimeException($reason); }
}
function assert_invalid($raw)
{
    try { classifier::parse_result($raw); }
    catch (Throwable $error) { return; }
    throw new RuntimeException('Invalid decision was accepted');
}

$payload = classifier::build_payload('BMW cars', '[url]example.com[/url]');
assert_true($payload['model'] === 'gpt-6-luna', 'Wrong model');
assert_true($payload['questions'][0]['type'] === 'choice', 'Wrong question type');
assert_true(count($payload['questions'][0]['choices']) === 3, 'Wrong choices');
assert_true(!isset($payload['store']), 'Decisions must not receive Responses-only options');

$spam = classifier::parse_result(decision_fixture('spam', 0.97, 0.99));
assert_true($spam['classification'] === 'spam' && $spam['confidence'] === 97, 'Spam safety score');
$low_conf = classifier::parse_result(decision_fixture('spam', 0.99, 0.51));
assert_true($low_conf['confidence'] === 51, 'Safety score must require BOTH high values');
$legit = classifier::parse_result(decision_fixture('legitimate', 0.01, 0.91));
assert_true($legit['classification'] === 'legitimate', 'Legitimate choice');
$uncertain = classifier::parse_result(decision_fixture('uncertain', 0.28, 0.62));
assert_true($uncertain['classification'] === 'uncertain', 'Uncertain choice');
assert_invalid('{"answers":[]}');
assert_invalid('{"answers":[{"type":"refusal","name":"forum_spam"}]}');
assert_invalid(str_replace('"name":"forum_spam"', '"name":"wrong"', decision_fixture('spam', 0.95, 0.99)));
assert_invalid(str_replace('"confidence":0.99', '"confidence":1.5', decision_fixture('spam', 0.95, 0.99)));
assert_invalid(str_replace('"probability":0.95', '"probability":-0.95', decision_fixture('spam', 0.95, 0.99)));
assert_invalid(str_replace('"choice":"spam"', '"choice":"delete"', decision_fixture('spam', 0.95, 0.99)));
assert_invalid('{"answers":[{"type":"choice","name":"forum_spam","choice":"spam","confidence":0.95,"probabilities":[]}]}');
echo "Decisions API classifier contract tests passed\n";

// Missing credentials and budget exhaustion are UNCERTAIN, never spam.
$failed = (new classifier())->classify('BMW', 'Spam looking content');
assert_true($failed['classification'] === 'uncertain', 'Absent key must be uncertain');
assert_true($failed['error_code'] === 'missing_api_key', 'Missing key has a stable audit code');
assert_true($failed['confidence'] === 0 && $failed['spam_probability'] === 0, 'Error must not claim spam certainty');
assert_true(classifier::http_error_code(429, '{"error":{"code":"insufficient_quota"}}') === 'budget_exceeded', 'Quota exhaustion must be separate from rate limit');
assert_true(classifier::http_error_code(429, '{"error":{"code":"rate_limit_exceeded"}}') === 'rate_limited', 'Rate limit classification');
assert_true(classifier::http_error_code(500, '') === 'provider_error', 'Provider error classification');
assert_true(classifier::http_error_code(401, '') === 'invalid_api_key', 'Invalid credentials classification');
assert_true(classifier::unavailable('refusal')['classification'] === 'uncertain', 'Refusals must be uncertain');
assert_true(classifier::unavailable('invalid_response')['classification'] === 'uncertain', 'Malformed payload must be uncertain');
assert_true(!array_key_exists('retryable', $failed), 'No custom retry scheduling in classification');
echo "Failure safety tests passed\n";
