# AI Anti-Spam for phpBB

**Experimental PHP extension for phpBB 3.3. Not production-ready.**

The extension reviews **already published** posts and classifies them with a small OpenAI model (`gpt-6-luna` via the OpenAI Decisions API). It does **not** block new posts or recall subscriber emails already sent by phpBB. It can optionally *soft-delete* clear spam using phpBB's core visibility service, making it invisible to regular visitors without irreversibly removing the post.

## Capabilities in 0.1.0-beta

- Run as a native phpBB cron task (no Python/Docker dependency).
- Only scan explicitly configured forum IDs that guests can list and read; password-protected forums are excluded. Public access is rechecked before each request.
- Only scan approved posts; scan up to 10 posts per run from a configurable lookback window.
- Re-check previously scanned posts if subsequently edited.
- Store `post_id`, content hash, decision, timestamp and concise rationale for audit; no raw post text persisted in extension table.
- API failures or malformed answers are recorded as `uncertain`, with no moderation action. No automatic retries, cooldowns or suspensions.
- Default: scanner disabled and auto-hide disabled.
- Soft-delete through `content.visibility`, not direct database updates.

## Install in test environment first

1. Back up the phpBB database and files. Verify phpBB 3.3, PHP >= 7.4, cURL and mbstring.
2. Download an **install package**, extract it, and copy the extracted **`bca`** folder into **`<phpbb-root>/ext/`**. The final path must be **`<phpbb-root>/ext/bca/antispam/composer.json`**. See the download options below.
3. Supply `BCA_ANTISPAM_OPENAI_API_KEY` as an environment variable visible to phpBB PHP workers and CLI processes. Do not put the key in Git or ACP settings.
4. In phpBB ACP: **Customise > Manage extensions > AI Anti-Spam > Enable**.
5. In **Extensions > AI Anti-Spam**, opt in to specific **public** forum IDs. Set scan enabled, **leave automatic soft-delete disabled**.
6. Schedule phpBB's standard cron runner via hosting cron / phpBB CLI. The default job interval is 120 seconds, but actual timing depends on cron deployment and job time budget.
7. Validate returned classifications from test posts, including real legitimate club advertisements, before even considering automatic soft-deletion.
8. Observe phpBB database state, counters, topic-first/last handling and moderator restoration on a **staging clone** prior to production deployment.

### Get the install ZIP

- **From GitHub:** open [Actions → Install package](https://github.com/fatmambot33/phpbb-ai-antispam/actions/workflows/package.yml), choose **Run workflow** on the branch you want to test, then follow the **Download ZIP from the draft release** link in the completed run’s summary. Download **`bca-antispam-0.1.0-beta.zip`** under the draft release’s assets. The ZIP already contains `bca/antispam/`. Draft releases require repository write access to view; publish the draft manually when you want to share it. Each run attempt creates a separate draft, and version tags also run this workflow.
- **From a local checkout:** run `python3 scripts/package.py`. Upload/extract the resulting `dist/bca-antispam-0.1.0-beta.zip` using the same instructions. Python is only needed to build the ZIP, never on your forum server.
- GitHub's green **Code → Download ZIP** button downloads the development repository. If you use that archive, rename its extracted project folder to `antispam` and put it inside `ext/bca/`. The install package avoids that renaming step.

The workflow uses GitHub Release assets instead of Actions artifacts, so it does not consume the Actions artifact storage quota. It creates a draft prerelease and does not publish it automatically.

After copying, your forum files should look like:

```text
<phpbb-root>/
└── ext/
    └── bca/
        └── antispam/
            ├── composer.json
            ├── acp/
            ├── adm/
            ├── config/
            ├── cron/
            ├── language/
            ├── migrations/
            └── service/
```

There is no Composer install or separate application to run on the server. Enable the extension through phpBB, then configure its API key, selected forum IDs and cron as described above. These packages are experimental test builds, not phpBB-validated releases.

### Update or remove

To update, back up the database and extension files, disable **AI Anti-Spam** in the ACP, replace `ext/bca/antispam/` with the new package's `bca/antispam/` directory, then enable the extension again. **Do not choose Delete data during an update.**

To remove it permanently, disable it, choose **Delete data** in phpBB's extension manager, then remove `ext/bca/antispam/`. Deleting data removes the extension's settings and audit records; it does not restore posts previously hidden by the extension. Restore those through phpBB's moderation tools first if needed.

**Important:** The ACP displays the last 30 classifications. Click **View message** beside a post ID to open it in a new tab for visual verification. Per-result feedback and restoration are not yet available in the extension. Revert soft-deleted posts through phpBB moderation tools. Do not enable auto-hide until you have reviewed representative results.

### Privacy

Posts are sent to the OpenAI Decisions API over HTTPS. The extension does not submit a `store` flag. No email address or IP address is intentionally included, though user-authored post text may itself contain personal data. Opt in only to appropriate public forums, document processing under GDPR, and obtain any necessary approvals.

### Limitations

- It cannot retract notifications already delivered.
- Model confidence is not a calibrated probability. False positives are possible.
- Each eligible post gets one classification attempt per scan; failures remain `uncertain` until a manual re-scan or content edit. Cron throughput is limited by 10 posts/run and 25s API timeout/post. At 120-second intervals it may fall behind on busy forums; tune limits and scheduling after tests.
- Modifying visibility through the core API avoids direct database corruption but still needs integration testing on phpBB 3.3 before production.
- Restoration is via phpBB's normal moderation tooling; no custom moderator dashboard yet.
- The prototype scans historic published posts and edits on previously scanned posts. It does not discover edits to old, never-scanned posts outside the lookback window.
- Reason and post ID are stored for audit, but the current prototype does not support feedback loops or per-message allowlisting.

## Local checks

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/classifier_test.php
php tests/scanner_test.php
python3 tests/package_test.py
```

## Repository and package layout

The repository root is the extension root, following the official [phpBB Board Announcements extension](https://github.com/phpbb-extensions/boardannouncements). Clone it directly into `ext/bca/antispam/` for development; a second nested extension directory is unnecessary.

`scripts/package.py` builds a reproducible install ZIP containing the runtime directories, metadata, license and user documentation. Tests, build scripts, GitHub configuration and local credentials are excluded. `.gitattributes` also excludes development files from Git archives. CI verifies the install path, file contents and reproducibility.

## Roadmap

See [ROADMAP.md](ROADMAP.md), [SECURITY.md](SECURITY.md).

## Attribution and licensing

SPDX-License-Identifier: GPL-2.0-only. Intended for compatibility with phpBB licensing.
