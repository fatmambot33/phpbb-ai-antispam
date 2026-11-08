<?php
namespace bca\antispam\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $config, $request, $template, $user, $db, $table_prefix, $phpbb_root_path, $phpEx, $phpbb_container, $auth;

        if (!$auth->acl_get('a_board'))
        {
            trigger_error('NOT_AUTHORISED', E_USER_WARNING);
        }
        $api_key = $phpbb_container->get('bca.antispam.api_key');

        $user->add_lang_ext('bca/antispam', 'info_acp_antispam');
        $this->tpl_name = 'acp_bca_antispam';
        $this->page_title = 'ACP_BCA_ANTISPAM_TITLE';
        add_form_key('bca_antispam_settings');

        if ($request->is_set_post('save_api_key') || $request->is_set_post('remove_api_key'))
        {
            if (!check_form_key('bca_antispam_settings'))
            {
                trigger_error('FORM_INVALID', E_USER_WARNING);
            }
            if ($request->is_set_post('remove_api_key'))
            {
                $api_key->remove();
                trigger_error($user->lang('ACP_BCA_ANTISPAM_KEY_REMOVED') . adm_back_link($this->u_action));
            }
            try
            {
                $changed = $api_key->replace(htmlspecialchars_decode($request->variable('new_api_key', '', false, \phpbb\request\request_interface::POST), ENT_COMPAT));
            }
            catch (\InvalidArgumentException $error)
            {
                trigger_error($user->lang('ACP_BCA_ANTISPAM_KEY_INVALID') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            trigger_error($user->lang($changed ? 'ACP_BCA_ANTISPAM_KEY_SAVED' : 'ACP_BCA_ANTISPAM_KEY_UNCHANGED') . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('scan_now'))
        {
            if (!check_form_key('bca_antispam_settings'))
            {
                trigger_error('FORM_INVALID', E_USER_WARNING);
            }
            $task = $phpbb_container->get('bca.antispam.cron.task.scan');
            if (!$task->is_runnable())
            {
                trigger_error($user->lang('ACP_BCA_ANTISPAM_SCAN_UNAVAILABLE') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            $lock = $phpbb_container->get('cron.lock_db');
            if (!$lock->acquire())
            {
                trigger_error($user->lang('ACP_BCA_ANTISPAM_SCAN_BUSY') . adm_back_link($this->u_action), E_USER_WARNING);
            }
            try
            {
                $processed = $task->run();
            }
            finally
            {
                $lock->release();
            }
            trigger_error($user->lang('ACP_BCA_ANTISPAM_SCAN_COMPLETE', $processed) . adm_back_link($this->u_action));
        }

        if ($request->is_set_post('submit'))
        {
            if (!check_form_key('bca_antispam_settings'))
            {
                trigger_error('FORM_INVALID', E_USER_WARNING);
            }

            $settings = [
                'forum_context' => $request->is_set_post('forum_context') ? utf8_substr(htmlspecialchars_decode($request->variable('forum_context', '', true), ENT_COMPAT), 0, 240) : ($config['bca_antispam_forum_context'] ?? ''),
                'enabled' => (int) $request->variable('enabled', 0),
                'auto_hide' => (int) $request->variable('auto_hide', 0),
                'threshold' => max(80, min(100, (int) $request->variable('threshold', 95))),
                'interval' => max(60, min(3600, (int) $request->variable('interval', 120))),
                'days' => max(1, min(365, (int) $request->variable('days', 30))),
                'forum_ids' => $this->clean_ids($request->variable('forum_ids', '')),
                'excluded_user_ids' => $this->clean_ids($request->variable('excluded_user_ids', '')),
            ];

            foreach ($settings as $key => $value)
            {
                $config->set('bca_antispam_' . $key, $value);
            }

            if ($request->variable('reset_cursor', 0))
            {
                // Re-scan posts in the configured lookback window.
                $config->set('bca_antispam_cursor', 0);
            }
            trigger_error($user->lang('ACP_BCA_ANTISPAM_SAVED') . adm_back_link($this->u_action));
        }

        $count_result = $db->sql_query('SELECT COUNT(*) AS total FROM ' . $table_prefix . 'bca_antispam_results');
        $total = (int) $db->sql_fetchfield('total', false, $count_result);
        $db->sql_freeresult($count_result);
        $shown = 0;
        $sql = 'SELECT r.post_id, r.checked_at, r.classification, r.confidence, r.spam_probability, r.provider_confidence,
                       r.reason, r.action_taken, r.error_code, p.post_id AS existing_post_id
                FROM ' . $table_prefix . 'bca_antispam_results r
                LEFT JOIN ' . POSTS_TABLE . ' p ON r.post_id = p.post_id
                ORDER BY r.checked_at DESC, r.post_id DESC';
        $decision_labels = [
            'legitimate' => 'ACP_BCA_ANTISPAM_DECISION_LEGITIMATE',
            'spam' => 'ACP_BCA_ANTISPAM_DECISION_SPAM',
            'uncertain' => 'ACP_BCA_ANTISPAM_DECISION_UNCERTAIN',
        ];
        $action_labels = [
            'observed' => 'ACP_BCA_ANTISPAM_ACTION_OBSERVED',
            'soft_deleted' => 'ACP_BCA_ANTISPAM_ACTION_SOFT_DELETED',
            'skipped_changed' => 'ACP_BCA_ANTISPAM_ACTION_SKIPPED_CHANGED',
        ];
        $result = $db->sql_query_limit($sql, 30);
        while ($row = $db->sql_fetchrow($result))
        {
            $shown++;
            $post_id = (int) $row['post_id'];
            $template->assign_block_vars('result', [
                'POST_ID' => $post_id,
                'DATE' => $user->format_date((int) $row['checked_at']),
                'CLASSIFICATION_LABEL' => utf8_htmlspecialchars(isset($decision_labels[$row['classification']]) ? $user->lang($decision_labels[$row['classification']]) : $row['classification']),
                'ACTION_LABEL' => utf8_htmlspecialchars(isset($action_labels[$row['action_taken']]) ? $user->lang($action_labels[$row['action_taken']]) : $row['action_taken']),
                'CLASSIFICATION' => utf8_htmlspecialchars($row['classification']),
                'CONFIDENCE' => (int) $row['confidence'],
                'SPAM_PROBABILITY' => (int) $row['spam_probability'],
                'PROVIDER_CONFIDENCE' => (int) $row['provider_confidence'],
                'REASON_LABEL' => utf8_htmlspecialchars($this->reason_label($row, $user, $decision_labels)),
                'REASON' => utf8_htmlspecialchars($row['reason']),
                'ACTION' => utf8_htmlspecialchars($row['action_taken']),
                // The audit post ID is sufficient for phpBB's canonical permalink.
                // Keep it even when the live-post lookup no longer matches.
                'U_POST' => $post_id > 0 ? append_sid($phpbb_root_path . 'viewtopic.' . $phpEx, 'p=' . $post_id) . '#p' . $post_id : '',
                'S_POST_MISSING' => empty($row['existing_post_id']),
            ]);
        }
        $db->sql_freeresult($result);

        $template->assign_vars([
            'LAST_RUN' => !empty($config['bca_antispam_last_run']) ? $user->format_date((int) $config['bca_antispam_last_run']) : $user->lang('ACP_BCA_ANTISPAM_NEVER'),
            'S_SCAN_DUE' => (int) $config['bca_antispam_last_run'] <= time() - (int) $config['bca_antispam_interval'],
            'S_SYSTEM_CRON' => !empty($config['use_system_cron']),
            'RESULTS_SUMMARY' => $user->lang('ACP_BCA_ANTISPAM_RESULTS_SUMMARY', $shown, $total),
            'U_ACTION' => $this->u_action,
            'S_ENABLED' => (bool) $config['bca_antispam_enabled'],
            'S_AUTO_HIDE' => (bool) $config['bca_antispam_auto_hide'],
            'THRESHOLD' => (int) $config['bca_antispam_threshold'],
            'INTERVAL' => (int) $config['bca_antispam_interval'],
            'DAYS' => (int) $config['bca_antispam_days'],
            'FORUM_CONTEXT' => utf8_htmlspecialchars($config['bca_antispam_forum_context'] ?? ''),
            'FORUM_IDS' => $config['bca_antispam_forum_ids'],
            'EXCLUDED_USER_IDS' => $config['bca_antispam_excluded_user_ids'],
            'CURSOR' => (int) $config['bca_antispam_cursor'],
            'API_KEY_SET' => $api_key->is_configured(),
        ]);
    }

    private function reason_label(array $row, $user, array $decision_labels)
    {
        $code = $row['error_code'] ?? '';
        $known_errors = ['missing_api_key', 'missing_curl', 'network_error', 'timeout',
            'rate_limited', 'budget_exceeded', 'invalid_api_key', 'provider_error',
            'invalid_response', 'refusal', 'request_rejected'];
        if (in_array($code, $known_errors, true) && $row['reason'] === 'Classification unavailable: ' . $code)
        {
            return $user->lang('ACP_BCA_ANTISPAM_REASON_UNAVAILABLE',
                $user->lang('ACP_BCA_ANTISPAM_ERROR_' . strtoupper($code)));
        }
        $canonical = sprintf('Decisions: %s; P(spam)=%d%%; model confidence=%d%%',
            $row['classification'], (int) $row['spam_probability'], (int) $row['provider_confidence']);
        if (isset($decision_labels[$row['classification']]) && $row['reason'] === $canonical)
        {
            return $user->lang('ACP_BCA_ANTISPAM_REASON_DECISION',
                $user->lang($decision_labels[$row['classification']]),
                (int) $row['spam_probability'], (int) $row['provider_confidence']);
        }
        // Keep custom or historical audit explanations intact.
        return $row['reason'];
    }

    private function clean_ids($input)
    {
        $ids = array_filter(array_map('intval', preg_split('/[^0-9]+/', $input)));
        return implode(',', array_unique($ids));
    }
}
