<?php
namespace phpbb\db\migration {
    // Only the data declarations are under test, not the full database migrator.
    class migration {}
}
namespace {
    if ($argc !== 2) { throw new \RuntimeException('Pass the pinned phpBB checkout root'); }
    $root = rtrim($argv[1], '/');
    require $root . '/phpBB/phpbb/config/config.php';
    require $root . '/phpBB/phpbb/db/migration/tool/tool_interface.php';
    require $root . '/phpBB/phpbb/db/migration/tool/config.php';
    require __DIR__ . '/../migrations/install.php';
    require __DIR__ . '/../migrations/forum_context.php';

    function check($condition, $message) {
        if (!$condition) { throw new \RuntimeException($message); }
    }
    function apply_config_adds($migration, $config) {
        $tool = new \phpbb\db\migration\tool\config($config);
        foreach ($migration->update_data() as $step) {
            if ($step[0] === 'config.add') { $tool->add(...$step[1]); }
        }
    }
    $install = new \bca\antispam\migrations\install();
    $upgrade = new \bca\antispam\migrations\forum_context();
    $key = 'bca_antispam_forum_context';

    $fresh = new \phpbb\config\config([]);
    apply_config_adds($install, $fresh);
    apply_config_adds($upgrade, $fresh);
    check($fresh[$key] === '', 'New installations must use generic context');
    check((int) $fresh['bca_antispam_enabled'] === 0 && (int) $fresh['bca_antispam_auto_hide'] === 0,
        'Fresh installation changed moderation defaults');

    $old = new \phpbb\config\config(['bca_antispam_enabled' => 1, 'bca_antispam_auto_hide' => 0]);
    apply_config_adds($upgrade, $old);
    check(strpos($old[$key], 'French classic BMW owners club.') === 0, 'Existing installation lost BMW context');
    check(mb_strlen($old[$key], 'UTF-8') <= 240, 'Legacy context exceeds storage bound');
    check((int) $old['bca_antispam_enabled'] === 1 && (int) $old['bca_antispam_auto_hide'] === 0,
        'Upgrade changed saved scan/moderation flags');
    $old->set($key, 'Aquarium keepers & récifs');
    apply_config_adds($upgrade, $old);
    check($old[$key] === 'Aquarium keepers & récifs', 'Repeated upgrade overwrote custom context');
    check($upgrade::depends_on() === ['\\bca\\antispam\\migrations\\install'], 'Upgrade migration dependency is wrong');
    echo "Context migration declarations with real phpBB 3.3.19 config.add: fresh, legacy and repeat-run cases passed\n";
}
