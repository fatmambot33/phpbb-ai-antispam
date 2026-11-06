<?php
namespace bca\antispam\acp;

class main_module
{
    public $u_action;
    public $tpl_name;
    public $page_title;

    public function main($id, $mode)
    {
        global $config, $request, $template, $user, $db, $table_prefix, $phpbb_root_path, $phpEx, $phpbb_container;

        $user->add_lang_ext('bca/antispam', 'info_acp_antispam');
        $this->tpl_name = 'acp_bca_antispam';
        $this->page_title = 'ACP_BCA_ANTISPAM_TITLE';
        add_form_key('bca_antispam_settings');

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
                       r.reason, r.action_taken, p.post_id AS existing_post_id
                FROM ' . $table_prefix . 'bca_antispam_results r
                LEFT JOIN ' . POSTS_TABLE . ' p ON r.post_id = p.post_id
                ORDER BY r.checked_at DESC, r.post_id DESC';
        $result = $db->sql_query_limit($sql, 30);
        while ($row = $db->sql_fetchrow($result))
        {
            $shown++;
            $post_id = (int) $row['post_id'];
            $template->assign_block_vars('result', [
                'POST_ID' => $post_id,
                'DATE' => $user->format_date((int) $row['checked_at']),
                'CLASSIFICATION' => htmlspecialchars($row['classification'], ENT_QUOTES, 'UTF-8'),
                'CONFIDENCE' => (int) $row['confidence'],
                'SPAM_PROBABILITY' => (int) $row['spam_probability'],
                'PROVIDER_CONFIDENCE' => (int) $row['provider_confidence'],
                'REASON' => htmlspecialchars($row['reason'], ENT_QUOTES, 'UTF-8'),
                'ACTION' => htmlspecialchars($row['action_taken'], ENT_QUOTES, 'UTF-8'),
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
            'FORUM_IDS' => $config['bca_antispam_forum_ids'],
            'EXCLUDED_USER_IDS' => $config['bca_antispam_excluded_user_ids'],
            'CURSOR' => (int) $config['bca_antispam_cursor'],
            'API_KEY_SET' => getenv('BCA_ANTISPAM_OPENAI_API_KEY') !== false && getenv('BCA_ANTISPAM_OPENAI_API_KEY') !== '',
        ]);
    }

    private function clean_ids($input)
    {
        $ids = array_filter(array_map('intval', preg_split('/[^0-9]+/', $input)));
        return implode(',', array_unique($ids));
    }
}
