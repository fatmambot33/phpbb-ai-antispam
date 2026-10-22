<?php
/**
 * Incremental scanner for approved posts in explicitly opted-in forums.
 */
namespace bca\antispam\service;

class scanner
{
    private $config;
    private $db;
    private $visibility;
    private $classifier;
    private $prefix;

    public function __construct(\phpbb\config\config $config, \phpbb\db\driver\driver_interface $db, \phpbb\content_visibility $visibility, classifier $classifier, $table_prefix)
    {
        $this->config = $config;
        $this->db = $db;
        $this->visibility = $visibility;
        $this->classifier = $classifier;
        $this->prefix = $table_prefix;
    }

    /** Process up to 10 posts per run, returning number successfully processed. */
    public function run()
    {
        $forums = $this->parse_ids($this->config['bca_antispam_forum_ids']);
        if (!$forums)
        {
            return 0; // Fail closed. Never scan unspecified/private forums.
        }
        $excluded = $this->parse_ids($this->config['bca_antispam_excluded_user_ids']);
        $cutoff = time() - (int) $this->config['bca_antispam_days'] * 86400;
        $cursor = (int) $this->config['bca_antispam_cursor'];
        $posts = [];

        $sql = 'SELECT p.post_id, p.post_text, p.post_subject, p.post_edit_time,
                       p.post_visibility, p.poster_id, p.topic_id, p.forum_id, p.post_time,
                       t.topic_title, t.topic_first_post_id, t.topic_last_post_id
                FROM ' . $this->prefix . 'posts p
                INNER JOIN ' . $this->prefix . 'topics t ON p.topic_id = t.topic_id
                WHERE p.post_id > ' . $cursor . '
                  AND p.post_time >= ' . $cutoff . '
                  AND p.post_visibility = ' . (int) ITEM_APPROVED . '
                  AND t.topic_visibility = ' . (int) ITEM_APPROVED . '
                  AND ' . $this->db->sql_in_set('p.forum_id', $forums) . '
                ORDER BY p.post_id ASC';
        if (count($posts) < 10)
        {
            $result = $this->db->sql_query_limit($sql, 10 - count($posts));
            while ($row = $this->db->sql_fetchrow($result))
            {
                $posts[] = $row;
            }
            $this->db->sql_freeresult($result);
        }

        // After the newest posts, detect edits to previously classified posts.
        // Exclude previously unseen posts: those are handled by the main cursor.
        if (count($posts) < 10)
        {
            $edits_sql = 'SELECT p.post_id, p.post_text, p.post_subject, p.post_edit_time,
                                 p.post_visibility, p.poster_id, p.topic_id, p.forum_id, p.post_time,
                                 t.topic_title, t.topic_first_post_id, t.topic_last_post_id
                          FROM ' . $this->prefix . 'posts p
                          INNER JOIN ' . $this->prefix . 'topics t ON p.topic_id = t.topic_id
                          INNER JOIN ' . $this->prefix . 'bca_antispam_results r ON p.post_id = r.post_id
                          WHERE p.post_id <= ' . $cursor . '
                            AND p.post_edit_time > r.checked_at
                            AND p.post_visibility = ' . (int) ITEM_APPROVED . '
                            AND t.topic_visibility = ' . (int) ITEM_APPROVED . '
                            AND ' . $this->db->sql_in_set('p.forum_id', $forums) . '
                          ORDER BY p.post_edit_time ASC';
            $r = $this->db->sql_query_limit($edits_sql, 10 - count($posts));
            while ($post = $this->db->sql_fetchrow($r))
            {
                $posts[] = $post;
            }
            $this->db->sql_freeresult($r);
        }

        $processed = 0;
        foreach ($posts as $post)
        {
            $id = (int) $post['post_id'];
            if (in_array((int) $post['poster_id'], $excluded, true))
            {
                if ($id > $cursor)
                {
                    $cursor = $id;
                    $this->config->set('bca_antispam_cursor', $cursor);
                }
                continue;
            }
            $fingerprint = hash('sha256', $post['post_subject'] . "\0" . $post['post_text']);
            try
            {
                $classification = $this->classifier->classify($post['topic_title'] . ' — ' . $post['post_subject'], $post['post_text']);
                $action = 'observed';
                if ((int) $this->config['bca_antispam_auto_hide'] &&
                    $classification['classification'] === 'spam' &&
                    $classification['confidence'] >= (int) $this->config['bca_antispam_threshold'])
                {
                    $action = $this->soft_delete_if_unchanged($post, $fingerprint) ? 'soft_deleted' : 'skipped_changed';
                }
                $this->store_result($id, $fingerprint, $classification, $action);
                if ($id > $cursor)
                {
                    $cursor = $id;
                    $this->config->set('bca_antispam_cursor', $cursor);
                }
                $processed++;
            }
            catch (\Throwable $e)
            {
                // An unpersisted failure must not advance the cursor.
                error_log('[bca/antispam] scan failed for post ' . $id . ' (check DB/visibility)');
                break;
            }
        }
        return $processed;
    }

    private function soft_delete_if_unchanged(array $post, $fingerprint)
    {
        $id = (int) $post['post_id'];
        $sql = 'SELECT post_text, post_subject, post_visibility FROM ' . $this->prefix . 'posts WHERE post_id = ' . $id;
        $r = $this->db->sql_query($sql);
        $fresh = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        if (!$fresh || (int) $fresh['post_visibility'] !== (int) ITEM_APPROVED)
        {
            return false;
        }
        if (!hash_equals($fingerprint, hash('sha256', $fresh['post_subject'] . "\0" . $fresh['post_text'])))
        {
            // Do not moderate a post that changed after classification.
            throw new \RuntimeException('Post changed while awaiting classification');
        }
        // Core API updates counters, latest-post fields and topic visibility.
        // 0 denotes the automated system actor; never impersonate a moderator.
        $changed = $this->visibility->set_post_visibility(
            ITEM_DELETED,
            $id,
            (int) $post['topic_id'],
            (int) $post['forum_id'],
            0,
            time(),
            'Automatically hidden by AI Anti-Spam; reversible',
            $id === (int) $post['topic_first_post_id'],
            $id === (int) $post['topic_last_post_id']
        );
        return !empty($changed);
    }

    private function store_result($id, $fingerprint, array $classification, $action)
    {
        $table = $this->prefix . 'bca_antispam_results';
        $sql = 'SELECT post_id FROM ' . $table . ' WHERE post_id = ' . (int) $id;
        $r = $this->db->sql_query($sql);
        $previous = $this->db->sql_fetchrow($r);
        $this->db->sql_freeresult($r);
        $values = [
            'checked_at' => time(),
            'post_hash' => $fingerprint,
            'classification' => $classification['classification'],
            'confidence' => $classification['confidence'],
            'spam_probability' => $classification['spam_probability'],
            'provider_confidence' => $classification['provider_confidence'],
            'reason' => $classification['reason'],
            'action_taken' => $action,
            'error_code' => $classification['error_code'],
        ];
        if ($previous)
        {
            $sql = 'UPDATE ' . $table . ' SET ' . $this->db->sql_build_array('UPDATE', $values) . ' WHERE post_id = ' . (int) $id;
        }
        else
        {
            $sql = 'INSERT INTO ' . $table . ' ' . $this->db->sql_build_array('INSERT', array_merge(['post_id' => $id], $values));
        }
        $this->db->sql_query($sql);
    }

    private function parse_ids($csv)
    {
        return array_values(array_unique(array_filter(array_map('intval', preg_split('/[^0-9]+/', (string) $csv)))));
    }
}
