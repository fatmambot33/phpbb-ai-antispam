# AI Anti-Spam for phpBB

**Experimental PHP extension for phpBB 3.3. Not production-ready.**

The extension reviews **already published** posts and classifies them with a small OpenAI model (`gpt-5-nano`). It does **not** block new posts or recall subscriber emails already sent by phpBB. It can optionally *soft-delete* clear spam using phpBB's core visibility service, making it invisible to regular visitors without irreversibly removing the post.

## Capabilities in 0.1.0-beta

- Run as a native phpBB cron task (no Python/Docker dependency).
- Only scan explicitly configured forum IDs, never private forums by default.
- Only scan approved posts; scan up to 10 posts per run from a configurable lookback window.
- Re-check previously scanned posts if subsequently edited.
- Store `post_id`, content hash, decision, timestamp and concise rationale for audit; no raw post text persisted in extension table.
- Fail closed on API error: no soft-deletion.
- Default: scanner disabled and auto-hide disabled.
- Soft-delete through `content.visibility`, not direct database updates.

## Install in test environment first

1. Back up the phpBB database and files. Verify phpBB 3.3, PHP >= 7.4, cURL and mbstring.
2. Put this project in **`<phpbb-root>/ext/bca/antispam/`**. `composer.json` must be at that path.
3. Supply `BCA_ANTISPAM_OPENAI_API_KEY` as an environment variable visible to phpBB PHP workers and CLI processes. Do not put the key in Git or ACP settings.
4. In phpBB ACP: **Customise > Manage extensions > AI Anti-Spam > Enable**.
5. In **Extensions > AI Anti-Spam**, opt in to specific **public** forum IDs. Set scan enabled, **leave automatic soft-delete disabled**.
6. Schedule phpBB's standard cron runner via hosting cron / phpBB CLI. The default job interval is 120 seconds, but actual timing depends on cron deployment and job time budget.
7. Validate returned classifications from test posts, including real legitimate club advertisements, before even considering automatic soft-deletion.
8. Observe phpBB database state, counters, topic-first/last handling and moderator restoration on a **staging clone** prior to production deployment.

**Important:** The ACP displays the last 30 classifications, but does not yet allow per-result feedback or restoration. Revert soft-deleted posts through phpBB moderation tools. Do not enable auto-hide until you have reviewed representative results.

### Privacy

Posts are sent to the OpenAI API over HTTPS with `store: false`. No email address or IP address is intentionally included, though user-authored post text may itself contain personal data. Opt in only to appropriate public forums, document processing under GDPR, and obtain any necessary approvals.

### Limitations

- It cannot retract notifications already delivered.
- Model confidence is not a calibrated probability. False positives are possible.
- Cron throughput is limited by 10 posts/run and 25s API timeout/post. At 120-second intervals it may fall behind on busy forums; tune limits and scheduling after tests.
- Modifying visibility through the core API avoids direct database corruption but still needs integration testing on phpBB 3.3 before production.
- Restoration is via phpBB's normal moderation tooling; no custom moderator dashboard yet.
- The prototype scans historic published posts and edits on previously scanned posts. It does not discover edits to old, never-scanned posts outside the lookback window.
- Reason and post ID are stored for audit, but the current prototype does not support feedback loops or per-message allowlisting.

## Local checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/classifier_test.php
```

## Roadmap

See [ROADMAP.md](ROADMAP.md), [SECURITY.md](SECURITY.md).

## Attribution and licensing

SPDX-License-Identifier: GPL-2.0-only. Intended for compatibility with phpBB licensing.
