<?php
require __DIR__ . '/bootstrap.php';
$report_file = $argv[2];
$run_id = $argv[3];
$phase = $argv[4] ?? 'current';
$cases = [];
$assertion_count = 0;
$current_case = 'bootstrap';
$snapshot = [];
function integration_case($name, $callback)
{
    global $cases, $current_case;
    $current_case = $name;
    $callback();
    $cases[] = ['name' => $name, 'status' => 'passed'];
    echo '[PASS] ' . $name . "\n";
}
$result_table = $table_prefix . 'bca_antispam_results';
$fake = new integration_classifier();
$scanner = new \bca\antispam\service\scanner($config, $db, $phpbb_container->get('content.visibility'), $fake, $table_prefix, $auth);
$api_key = $phpbb_container->has('bca.antispam.api_key') ? $phpbb_container->get('bca.antispam.api_key') : null;
$cron = new \bca\antispam\cron\task\scan($config, $scanner, $api_key ?: $request);
$cron->set_name('bca.antispam.cron.task.scan');
// Keep the real compiled container intact. This adapter substitutes only the
// deterministic test cron/classifier while delegating every phpBB service.
$phpbb_container = new integration_container($phpbb_container, $cron);
$manager = $phpbb_container->get('ext.manager');
$completed = false;
try
{
    if ($phase === 'verify-current' || $phase === 'verify-upgrade')
    {
        $expected = json_decode(file_get_contents($argv[5]), true);
        integration_case($phase === 'verify-current' ? 'disable_enable_persistence' : 'legacy_upgrade_persistence',
            function () use ($expected, $phase) {
                global $config, $manager, $result_table, $api_key;
                integration_check($manager->is_enabled('bca/antispam'), 'Extension did not re-enable');
                integration_check(integration_count($result_table) === $expected['snapshot']['audit_count'], 'Lifecycle operation lost audit data');
                foreach ($expected['snapshot']['settings'] as $key => $value) {
                    integration_check((string) $config[$key] === (string) $value, 'Lifecycle operation changed setting ' . $key);
                }
                if ($phase === 'verify-current') {
                    integration_check(hash('sha256', $api_key->get()) === $expected['snapshot']['api_key_hash'], 'Disable/re-enable lost the saved key');
                }
                if ($phase === 'verify-upgrade') {
                    integration_check(!$api_key->is_configured(), 'Upgrade imported an environment credential');
                    integration_check(strpos($config['bca_antispam_forum_context'], 'French classic BMW owners club.') === 0,
                        'Legacy migration did not preserve BMW context');
                }
            });
    }
    else
    {
        integration_case('installation_and_container', function () {
            global $manager, $config, $phpbb_container, $db, $result_table;
            integration_check($manager->is_enabled('bca/antispam'), 'Extension is not enabled');
            integration_check(integration_count($result_table) === 0, 'Fresh extension audit table is not empty');
            integration_check($phpbb_container->get('bca.antispam.scanner') instanceof \bca\antispam\service\scanner, 'Scanner container wiring failed');
            $r = $db->sql_query("SELECT COUNT(*) AS total FROM " . MODULES_TABLE . " WHERE module_mode = 'settings' AND module_langname = 'ACP_BCA_ANTISPAM_SETTINGS'");
            integration_check((int) $db->sql_fetchfield('total', false, $r) === 1, 'ACP module was not registered once');
            $db->sql_freeresult($r);
        });
        integration_case('safe_defaults', function () use ($phase) {
            global $config, $cron;
            integration_check(!(int) $config['bca_antispam_enabled'] && !(int) $config['bca_antispam_auto_hide'], 'Unsafe installation defaults');
            integration_check(!$cron->is_runnable(), 'Disabled scan was scheduled');
            if ($phase === 'current') {
                integration_check($config['bca_antispam_forum_context'] === '', 'Fresh context is not generic');
            } else {
                integration_check(!isset($config['bca_antispam_forum_context']), 'Legacy fixture already has the context migration');
            }
        });
        $r = $db->sql_query('SELECT MAX(post_id) AS maximum FROM ' . POSTS_TABLE);
        $config->set('bca_antispam_cursor', (int) $db->sql_fetchfield('maximum', false, $r));
        $db->sql_freeresult($r);
        $config->set('bca_antispam_enabled', 1);
        $config->set('bca_antispam_forum_ids', '2');
        if ($phase === 'current')
        {
            integration_case('acp_key_lifecycle_and_storage', function () {
                global $config, $db, $api_key, $auth, $user, $request, $phpbb_root_path;
                $module = new \bca\antispam\acp\main_module();
                $module->u_action = 'integration.php';
                $fixture = 'acp-integration-fixture-not-a-real-key';
                $created = time() - 2;
                $token = sha1($created . $user->data['user_form_salt'] . 'bca_antispam_settings');
                integration_request_post(['save_api_key' => 1, 'new_api_key' => $fixture,
                    'creation_time' => $created, 'form_token' => $token]);
                $guest = $auth->obtain_user_data(ANONYMOUS);
                $auth->acl($guest);
                try { $module->main('', 'settings'); throw new RuntimeException('Non-admin key save accepted'); }
                catch (RuntimeException $error) {
                    integration_check(strpos($error->getMessage(), 'NOT_AUTHORISED') !== false, 'Wrong non-admin outcome');
                }
                $auth->acl($user->data);
                integration_check(!$api_key->is_configured(), 'Non-admin stored a key');
                integration_request_post(['form_token' => 'invalid']);
                try { $module->main('', 'settings'); throw new RuntimeException('Invalid CSRF key save accepted'); }
                catch (RuntimeException $error) { integration_check(strpos($error->getMessage(), 'FORM_INVALID') !== false, 'Wrong key CSRF outcome'); }
                integration_request_post(['form_token' => $token]);
                try { $module->main('', 'settings'); throw new RuntimeException('Key save did not report completion'); }
                catch (RuntimeException $error) {
                    integration_check(strpos($error->getMessage(), 'API key saved.') !== false, 'Wrong key save outcome');
                }
                integration_check($api_key->get() === $fixture, 'ACP key did not persist in config_text');
                $r = $db->sql_query("SELECT COUNT(*) AS total FROM " . CONFIG_TABLE . " WHERE config_name = 'bca_antispam_api_key'");
                integration_check((int) $db->sql_fetchfield('total', false, $r) === 0, 'Key leaked into global config table');
                $db->sql_freeresult($r);
                integration_request_post(['new_api_key' => '']);
                try { $module->main('', 'settings'); throw new RuntimeException('Blank key save did not complete'); }
                catch (RuntimeException $error) { integration_check(strpos($error->getMessage(), 'API key unchanged.') !== false, 'Wrong blank key outcome'); }
                integration_check($api_key->get() === $fixture, 'Blank field erased key');
                foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($phpbb_root_path . 'cache', FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isFile()) {
                        integration_check(strpos(file_get_contents($file->getPathname()), $fixture) === false, 'Key leaked into a phpBB cache file');
                    }
                }
                integration_request_post(['save_api_key' => null, 'remove_api_key' => 1]);
                try { $module->main('', 'settings'); throw new RuntimeException('Key removal did not complete'); }
                catch (RuntimeException $error) { integration_check(strpos($error->getMessage(), 'API key removed.') !== false, 'Wrong key removal outcome'); }
                integration_check(!$api_key->is_configured(), 'Removed key stayed active');
                integration_request_post(['remove_api_key' => null, 'new_api_key' => null, 'creation_time' => null, 'form_token' => null]);
            });
            integration_case('scan_prerequisite_gates', function () {
                global $config, $cron, $request, $fake, $api_key;
                integration_check(!$cron->is_runnable(), 'Missing key did not block scheduling');
                $api_key->replace('integration-fixture-only');
                $config->set('bca_antispam_forum_ids', '');
                integration_check(!$cron->is_runnable(), 'Missing forum selection did not block scheduling');
                $config->set('bca_antispam_forum_ids', '2');
                integration_check($cron->is_runnable(), 'Configured scanner is not runnable');
                integration_check(count($fake->calls) === 0, 'Readiness checks invoked the classifier');
            });
        }
        if ($api_key) { $api_key->replace('integration-fixture-only'); }
        else { $request->overwrite('BCA_ANTISPAM_OPENAI_API_KEY', 'integration-fixture-only', \phpbb\request\request_interface::SERVER); }
        integration_case('observe_new_posts', function () {
            global $scanner, $result_table;
            $post = integration_post('Observed topic', 'SPAM_FIXTURE observation only');
            $before = integration_row(FORUMS_TABLE, 'forum_id', 2)['forum_posts_approved'];
            integration_check($scanner->run() === 1, 'New public post was not scanned');
            $audit = integration_row($result_table, 'post_id', $post['post_id']);
            integration_check($audit['classification'] === 'spam' && $audit['action_taken'] === 'observed', 'Wrong observation decision/action');
            integration_check((int) integration_row(POSTS_TABLE, 'post_id', $post['post_id'])['post_visibility'] === ITEM_APPROVED, 'Observation hid a post');
            integration_check(integration_row(FORUMS_TABLE, 'forum_id', 2)['forum_posts_approved'] === $before, 'Observation changed counters');
        });
        if ($phase === 'current')
        {
            integration_case('scheduled_cron_dispatch', function () {
                global $phpbb_container, $config, $result_table;
                $post = integration_post('Scheduled topic', 'Legitimate scheduled fixture');
                $config->set('bca_antispam_last_run', 0);
                $task = $phpbb_container->get('cron.manager')->find_task('bca.antispam.cron.task.scan');
                integration_check($task && $task->is_ready(), 'Task is not discoverable/ready through cron.manager');
                $lock = $phpbb_container->get('cron.lock_db');
                integration_check($lock->acquire(), 'Scheduled runner could not acquire lock');
                try { $task->run(); } finally { $lock->release(); }
                integration_check((int) $config['bca_antispam_last_run'] > 0, 'Cron did not record attempt');
                integration_check((bool) integration_row($result_table, 'post_id', $post['post_id']), 'Cron did not persist classification');
                integration_check(!$task->is_ready(), 'Cron ignored interval after completion');
            });
            integration_case('private_password_and_opt_in', function () {
                global $scanner, $fake, $config, $result_table;
                $private = integration_forum('Private fixture', false);
                $protected = integration_forum('Protected fixture', true, 'not-a-real-password-hash');
                $unselected = integration_forum('Unselected fixture', true);
                $ids = [];
                foreach ([$private, $protected, $unselected] as $forum) {
                    $ids[] = integration_post('Restricted topic', 'PRIVATE_FIXTURE', $forum)['post_id'];
                }
                $config->set('bca_antispam_forum_ids', '2,' . $private . ',' . $protected);
                $calls = count($fake->calls);
                integration_check($scanner->run() === 0 && count($fake->calls) === $calls, 'Restricted content reached classifier');
                foreach ($ids as $id) { integration_check(!integration_row($result_table, 'post_id', $id), 'Restricted post was audited'); }
                $config->set('bca_antispam_forum_ids', '2');
            });
            integration_case('unapproved_and_excluded_posts', function () {
                global $scanner, $config, $fake, $result_table;
                $post = integration_post('Unapproved topic', 'UNAPPROVED_FIXTURE', 2, 0, ITEM_UNAPPROVED);
                $calls = count($fake->calls);
                integration_check($scanner->run() === 0 && count($fake->calls) === $calls, 'Unapproved content reached classifier');
                $excluded = integration_post('Excluded author', 'Excluded author fixture');
                $config->set('bca_antispam_excluded_user_ids', '2');
                integration_check($scanner->run() === 0, 'Excluded author was scanned');
                integration_check(!integration_row($result_table, 'post_id', $excluded['post_id']), 'Excluded author was audited');
                $config->set('bca_antispam_excluded_user_ids', '');
            });
            integration_case('provider_failure_is_uncertain', function () {
                global $scanner, $fake, $config, $result_table;
                $post = integration_post('Provider failure', 'SPAM_FIXTURE provider failure');
                $before = integration_count(NOTIFICATIONS_TABLE);
                $config->set('bca_antispam_auto_hide', 1);
                $fake->error = 'budget_exceeded';
                integration_check($scanner->run() === 1, 'Provider failure was not persisted');
                $audit = integration_row($result_table, 'post_id', $post['post_id']);
                integration_check($audit['classification'] === 'uncertain' && $audit['error_code'] === 'budget_exceeded', 'Failure did not fail open with audit code');
                integration_check((int) integration_row(POSTS_TABLE, 'post_id', $post['post_id'])['post_visibility'] === ITEM_APPROVED, 'Failure hid a post');
                integration_check(integration_count(NOTIFICATIONS_TABLE) === $before, 'Failure generated notifications');
                $calls = count($fake->calls);
                integration_check($scanner->run() === 0 && count($fake->calls) === $calls, 'Unchanged provider failure was retried automatically');
                $fake->error = '';
            });
            integration_case('latest_reply_delete_and_restore', function () {
                global $scanner, $phpbb_container, $result_table;
                $first = integration_post('Latest reply topic', 'Legitimate starter');
                $middle = integration_post('Latest reply topic', 'Legitimate middle', 2, $first['topic_id']);
                $last = integration_post('Latest reply topic', 'SPAM_FIXTURE latest', 2, $first['topic_id']);
                integration_check($scanner->run() === 3, 'Topic/replies were not scanned');
                $topic = integration_row(TOPICS_TABLE, 'topic_id', $first['topic_id']);
                integration_check((int) $topic['topic_posts_approved'] === 2 && (int) $topic['topic_posts_softdeleted'] === 1, 'Latest reply counters are wrong');
                integration_check((int) $topic['topic_last_post_id'] === $middle['post_id'], 'Latest visible reply was not updated');
                integration_check((int) integration_row(POSTS_TABLE, 'post_id', $last['post_id'])['post_visibility'] === ITEM_DELETED, 'Spam reply was not hidden');
                integration_check(integration_row($result_table, 'post_id', $last['post_id'])['action_taken'] === 'soft_deleted', 'Deletion not audited');
                $phpbb_container->get('content.visibility')->set_post_visibility(ITEM_APPROVED, $last['post_id'], $last['topic_id'], 2, 2, time(), 'Integration restore', false, true);
                $topic = integration_row(TOPICS_TABLE, 'topic_id', $first['topic_id']);
                integration_check((int) $topic['topic_posts_approved'] === 3 && (int) $topic['topic_posts_softdeleted'] === 0, 'Reply restore counters are wrong');
                integration_check((int) $topic['topic_last_post_id'] === $last['post_id'], 'Restored latest reply not selected');
            });
            integration_case('single_post_topic_delete_and_restore', function () {
                global $scanner, $phpbb_container;
                $post = integration_post('Single spam topic', 'SPAM_FIXTURE only post');
                $before = integration_row(FORUMS_TABLE, 'forum_id', 2);
                integration_check($scanner->run() === 1, 'Single post was not scanned');
                $topic = integration_row(TOPICS_TABLE, 'topic_id', $post['topic_id']);
                integration_check((int) $topic['topic_visibility'] === ITEM_DELETED, 'Single-post topic remained visible');
                $forum = integration_row(FORUMS_TABLE, 'forum_id', 2);
                integration_check((int) $forum['forum_topics_approved'] === (int) $before['forum_topics_approved'] - 1, 'Single topic forum counter not decremented');
                $phpbb_container->get('content.visibility')->set_post_visibility(ITEM_APPROVED, $post['post_id'], $post['topic_id'], 2, 2, time(), 'Integration restore', true, true);
                $forum = integration_row(FORUMS_TABLE, 'forum_id', 2);
                integration_check((int) $forum['forum_topics_approved'] === (int) $before['forum_topics_approved'], 'Single topic restore counter mismatch');
            });
            integration_case('starter_delete_and_restore', function () {
                global $scanner, $phpbb_container;
                $first = integration_post('Spam starter topic', 'SPAM_FIXTURE first');
                $last = integration_post('Spam starter topic', 'Legitimate reply', 2, $first['topic_id']);
                integration_check($scanner->run() === 2, 'Starter/reply not scanned');
                $topic = integration_row(TOPICS_TABLE, 'topic_id', $first['topic_id']);
                integration_check((int) $topic['topic_first_post_id'] === $last['post_id'] && (int) $topic['topic_visibility'] === ITEM_APPROVED, 'Starter deletion damaged topic visibility/first post');
                $phpbb_container->get('content.visibility')->set_post_visibility(ITEM_APPROVED, $first['post_id'], $first['topic_id'], 2, 2, time(), 'Integration restore', true, false);
                integration_check((int) integration_row(TOPICS_TABLE, 'topic_id', $first['topic_id'])['topic_first_post_id'] === $first['post_id'], 'Restored starter not selected');
            });
            integration_case('manual_acp_scan_and_csrf', function () {
                global $scanner, $fake, $config, $request, $user, $result_table;
                $config->set('bca_antispam_auto_hide', 0);
                $post = integration_post('Manual ACP topic', 'Legitimate manual fixture');
                $module = new \bca\antispam\acp\main_module();
                $module->u_action = 'integration.php';
                integration_request_post(['scan_now' => 1, 'creation_time' => time() - 2, 'form_token' => 'invalid']);
                $calls = count($fake->calls);
                try { $module->main('', 'settings'); throw new RuntimeException('Invalid CSRF was accepted'); }
                catch (RuntimeException $error) {
                    integration_check(strpos($error->getMessage(), 'FORM_INVALID') !== false, 'Wrong invalid-form outcome');
                }
                integration_check(count($fake->calls) === $calls, 'Invalid CSRF started scanning');
                $created = time() - 2;
                integration_request_post(['creation_time' => $created,
                    'form_token' => sha1($created . $user->data['user_form_salt'] . 'bca_antispam_settings')]);
                try { $module->main('', 'settings'); throw new RuntimeException('Manual scan did not report completion'); }
                catch (RuntimeException $error) {
                    integration_check(strpos($error->getMessage(), 'Scan finished: 1 post(s) classified.') !== false, 'Wrong manual scan outcome: ' . $error->getMessage());
                }
                integration_check((bool) integration_row($result_table, 'post_id', $post['post_id']), 'Manual scan did not save result');
                integration_request_post(['scan_now' => null, 'creation_time' => null, 'form_token' => null]);
            });
            integration_case('english_and_french_acp_rendering', function () {
                global $config, $user, $template, $phpbb_container, $phpbb_root_path, $report_file, $api_key;
                $config->set('bca_antispam_forum_context', '</textarea><script>fixture</script> & café');
                $template->set_custom_style([['name' => 'adm', 'ext_path' => 'adm/style/']], $phpbb_root_path . 'adm/style');
                foreach (['en' => 'Forum context', 'fr' => 'Contexte du forum'] as $locale => $label)
                {
                    $phpbb_container->get('language')->set_user_language($locale, true);
                    $template->destroy_block_vars('result');
                    $module = new \bca\antispam\acp\main_module();
                    $module->u_action = 'integration.php';
                    $module->main('', 'settings');
                    $template->set_filenames(['body' => '@bca_antispam/acp_bca_antispam.html']);
                    $html = $template->assign_display('body');
                    integration_check(strpos($html, $api_key->get()) === false, 'Saved key leaked into ACP HTML');
                    integration_check(strpos($html, 'name="new_api_key" value=""') !== false, 'Key field is not blank');
                    integration_check(strpos($html, $label) !== false, 'Context field did not render in ' . $locale);
                    integration_check(strpos($html, 'ACP_BCA_ANTISPAM_') === false, 'Unresolved language key in rendered ACP');
                    integration_check(strpos($html, '&lt;/textarea&gt;&lt;script&gt;fixture&lt;/script&gt; &amp; café') !== false, 'Stored context can escape textarea');
                    integration_check(strpos($html, 'viewtopic.php?p=') !== false, 'Audit post links missing from real Twig render');
                    file_put_contents(dirname($report_file) . '/acp-' . $locale . '.html', $html);
                }
                $phpbb_container->get('language')->set_user_language('en', true);
            });
            integration_case('edited_audited_post_is_reclassified', function () {
                global $scanner, $result_table, $config;
                $config->set('bca_antispam_auto_hide', 0);
                $post = integration_post('Previously audited edit', 'Legitimate original content');
                integration_check($scanner->run() === 1, 'Original post was not classified');
                $before = integration_row($result_table, 'post_id', $post['post_id']);
                $rows = integration_count($result_table);
                integration_edit($post['post_id'], 'SPAM_FIXTURE edited content');
                usleep(1100000);
                integration_check($scanner->run() === 1, 'Previously audited edit was not rediscovered');
                $after = integration_row($result_table, 'post_id', $post['post_id']);
                integration_check($after['classification'] === 'spam' && $after['post_hash'] !== $before['post_hash'], 'Edited text did not replace the audit decision/hash');
                integration_check(integration_count($result_table) === $rows, 'Edited post created duplicate audit rows');
            });
            integration_case('concurrent_edit_guard_and_rescan', function () {
                global $scanner, $fake, $result_table, $config;
                $config->set('bca_antispam_auto_hide', 1);
                $post = integration_post('Concurrent edit', 'SPAM_FIXTURE original');
                $fake->during = function () use ($post) { integration_edit($post['post_id'], 'Legitimate edited content'); };
                integration_check($scanner->run() === 0, 'Changed post should not be persisted as checked');
                integration_check($fake->callback_completed, 'Injected edit did not complete through phpBB posting API');
                integration_check(!integration_row($result_table, 'post_id', $post['post_id']), 'Old content was recorded as checked');
                integration_check((int) integration_row(POSTS_TABLE, 'post_id', $post['post_id'])['post_visibility'] === ITEM_APPROVED, 'Changed content was hidden');
                usleep(1100000);
                integration_check($scanner->run() === 1, 'Edited post did not remain eligible');
                integration_check(integration_row($result_table, 'post_id', $post['post_id'])['classification'] === 'legitimate', 'Edited version was not classified');
            });
            integration_case('cron_lock_exclusion', function () {
                global $config, $db, $phpbb_container;
                $first = $phpbb_container->get('cron.lock_db');
                $second = new \phpbb\lock\db('cron_lock', $config, $db);
                integration_check($first->acquire(), 'First cron lock acquisition failed');
                try { integration_check(!$second->acquire(), 'Second worker acquired a held cron lock'); }
                finally { $first->release(); }
                integration_check($second->acquire(), 'Released cron lock could not be acquired');
                $second->release();
            });
        }
        $config->set('bca_antispam_auto_hide', 0);
        if ($phase === 'current') { $config->set('bca_antispam_forum_context', 'Integration community & café'); }
        $snapshot = ['audit_count' => integration_count($result_table), 'settings' => []];
        foreach (['bca_antispam_enabled', 'bca_antispam_auto_hide', 'bca_antispam_forum_ids', 'bca_antispam_cursor'] as $key) {
            $snapshot['settings'][$key] = $config[$key];
        }
        if ($phase === 'current') { $snapshot['api_key_hash'] = hash('sha256', $api_key->get()); $snapshot['settings']['bca_antispam_forum_context'] = $config['bca_antispam_forum_context']; }
    }
    $completed = true;
}
catch (\Throwable $error)
{
    $cases[] = ['name' => $current_case, 'status' => 'failed', 'message' => $error->getMessage()];
    fwrite(STDERR, '[FAIL] ' . $current_case . ': ' . $error->getMessage() . "\n" . $error->getTraceAsString() . "\n");
}
file_put_contents($report_file, json_encode([
    'run_id' => $run_id, 'phase' => $phase, 'completed' => $completed,
    'cases' => $cases, 'assertions' => $assertion_count, 'snapshot' => $snapshot,
], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
exit($completed ? 0 : 1);
