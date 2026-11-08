<?php
define('IN_PHPBB', true);
function check($condition, $message)
{
    if (!$condition) { throw new RuntimeException($message); }
}
$packs = [];
foreach (['en', 'fr'] as $locale)
{
    $lang = [];
    require __DIR__ . '/../language/' . $locale . '/info_acp_antispam.php';
    $packs[$locale] = $lang;
    foreach ($lang as $key => $value)
    {
        check(mb_check_encoding($value, 'UTF-8'), 'Invalid UTF-8 translation: ' . $locale . '/' . $key);
    }
}
check(array_keys($packs['en']) === array_keys($packs['fr']), 'English and French keys differ');
foreach ($packs['en'] as $key => $value)
{
    preg_match_all('/%(?:[0-9]+\$)?[ds]/', $value, $en);
    preg_match_all('/%(?:[0-9]+\$)?[ds]/', $packs['fr'][$key], $fr);
    check($en[0] === $fr[0], 'Translation placeholders differ: ' . $key);
}
$template = file_get_contents(__DIR__ . '/../adm/style/acp_bca_antispam.html');
preg_match_all('/\{L_(ACP_BCA_ANTISPAM_[A-Z0-9_]+)\}/', $template, $references);
foreach (array_unique($references[1]) as $key)
{
    check(isset($packs['en'][$key], $packs['fr'][$key]), 'Missing template translation: ' . $key);
}
$visible = preg_replace('/<!--.*?-->/s', '', $template);
$visible = strip_tags($visible);
$visible = preg_replace('/\{[^}]+\}/', '', $visible);
check(!preg_match('/[A-Za-z]/', $visible), 'Template still contains hardcoded interface text');
foreach (['en', 'fr'] as $locale)
{
    $rendered = strtr($template, array_combine(
        array_map(function ($key) { return '{L_' . $key . '}'; }, array_keys($packs[$locale])),
        array_values($packs[$locale])
    ));
    check(strpos($rendered, '{L_ACP_BCA_ANTISPAM_') === false, 'A template label was left unresolved');
    check(strpos($rendered, $packs[$locale]['ACP_BCA_ANTISPAM_FORUM_CONTEXT']) !== false, 'Missing localized context field');
}
check($packs['fr']['ACP_BCA_ANTISPAM_FORUM_CONTEXT'] === 'Contexte du forum', 'French context label is missing');
echo "ACP language completeness, UTF-8 and placeholder tests passed\n";
