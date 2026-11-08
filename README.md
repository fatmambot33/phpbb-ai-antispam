# AI Anti-Spam for phpBB

**Experimental PHP extension for phpBB 3.3. Not production-ready.**

The extension reviews **already published** posts and classifies them with a small OpenAI model (`gpt-6-luna` via the OpenAI Decisions API). It does **not** block new posts or recall subscriber emails already sent by phpBB. It can optionally *soft-delete* clear spam using phpBB's core visibility service, making it invisible to regular visitors without irreversibly removing the post.

## Capabilities in 0.2.0-beta

- Configurable forum context and English/French ACP interface.
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

- **From GitHub:** open [Actions → Install package](https://github.com/fatmambot33/phpbb-ai-antispam/actions/workflows/package.yml), choose **Run workflow** on the branch you want to test, then follow the **Download ZIP from the draft release** link in the completed run’s summary. Download **`bca-antispam-0.2.0-beta.zip`** under the draft release’s assets. The ZIP already contains `bca/antispam/`. Draft releases require repository write access to view; publish the draft manually when you want to share it. Each run attempt creates a separate draft, and version tags also run this workflow.
- **From a local checkout:** run `python3 scripts/package.py`. Upload/extract the resulting `dist/bca-antispam-0.2.0-beta.zip` using the same instructions. Python is only needed to build the ZIP, never on your forum server.
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

**Important:** The ACP displays the last 30 classifications. Click **View message** beside a post ID to open it in a new tab for visual verification. Links use the saved post ID for both scheduled and manual scans, including older audit rows. If the post is no longer found in phpBB, the row displays a notice; opening the link cannot restore a permanently deleted post. Per-result feedback and restoration are not yet available in the extension. Revert soft-deleted posts through phpBB moderation tools. Do not enable auto-hide until you have reviewed representative results.

### If new posts are missing from the results

In the ACP, check **Scan status** for the last scan attempt and whether the next scan is due. Use **Scan now** to process one batch immediately with the saved settings; it uses the same phpBB cron lock as scheduled jobs. Keep automatic hiding disabled while diagnosing. The batch may take several minutes.

The table shows up to 30 results and the total number of classified posts. It keeps one row per post, not one per scan. Refresh the page to see updated results. A scan with no eligible posts does not add rows.

For new posts to qualify, they must be approved, in an approved topic, in a selected public forum readable by guests, and within the lookback period. Excluded users and password-protected forums are skipped. If new posts qualify but **Scan now** reports zero, check the PHP server logs for scanner failures.

If manual scanning works but the last scheduled attempt remains old, check phpBB cron delivery. Page-triggered cron relies on visits to the forum; the ACP alone does not trigger it. With system cron enabled, the hosting task must execute phpBB cron, and the API key must be available to CLI PHP as well as web PHP. A key set in Apache's `.htaccess` does not configure CLI PHP.

To revisit older posts or include older posts after expanding the selected forums/lookback period, tick **Re-scan lookback period** and save. This resets the cursor and can make new paid API calls for previously classified posts.

### Forum context and the 0.2.0-beta update

In the extension settings, describe the forum subject and examples of legitimate commercial posts in **Forum context** (240 characters maximum). This context is sent with each classification. Keep automatic hiding disabled while testing a new context.

Fresh installations use a generic discussion-forum context when the field is blank. Upgrades preserve the previous BMW-club context through a migration; administrators can edit it or clear it for the generic default. Existing custom context is not overwritten on subsequent enable operations.

To update, disable the extension, upload the complete new package, and enable it again so phpBB runs the migration. Do not delete extension data. Purge phpBB’s cache to refresh the service container, translations and template. Changing context affects future requests; use the re-scan option only when you want new classifications for unchanged historical posts.

Decision and action labels and standard reason summaries follow the administrator’s language. Original audit explanations remain available as tooltips; custom/historical explanations are displayed unchanged.

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
php tests/credentials_test.php
php tests/scanner_test.php
php tests/acp_test.php
php tests/language_test.php
php tests/post_links_test.php
python3 tests/package_test.py
```

## Repository and package layout

The repository root is the extension root, following the official [phpBB Board Announcements extension](https://github.com/phpbb-extensions/boardannouncements). Clone it directly into `ext/bca/antispam/` for development; a second nested extension directory is unnecessary.

`scripts/package.py` builds a reproducible install ZIP containing the runtime directories, metadata, license and user documentation. Only tracked runtime files are included; common deployment-only filenames are rejected. Tests, build scripts and GitHub configuration are excluded. Building requires Git and a Git checkout. `.gitattributes` also excludes development files from Git archives. CI verifies the install path, file contents and reproducibility.

## phpBB pre-validation

CI validates the actual install ZIP with the official [phpBB Extension Pre-Validator](https://github.com/phpbb/epv), pinned to a reviewed revision. The gate fails on all findings, including ordinary errors and warnings that EPV's own CLI can report without a failing exit status.

See [phpBB validation preparation](https://github.com/fatmambot33/phpbb-ai-antispam/blob/main/docs/PHPBB_VALIDATION.md) for local commands and the remaining submission work. An EPV pass is not official phpBB approval or a completed integration test.

## phpBB/database integration

A separate CI job installs the packaged extension on a disposable phpBB 3.3.19/MariaDB 10.11 board. It checks scanning, ACL filtering, CSRF, real ACP rendering, reversible moderation, persistence and upgrade behavior with deterministic classification fixtures.

See [integration test instructions](https://github.com/fatmambot33/phpbb-ai-antispam/blob/main/docs/INTEGRATION_TESTING.md) for setup, exact coverage and limits. No OpenAI calls or live forum data are used.

## Security reports

Follow [SECURITY.md](SECURITY.md) to report vulnerabilities privately. Do not post API keys, session IDs or private forum content in issues.

## Roadmap

See [ROADMAP.md](ROADMAP.md), [SECURITY.md](SECURITY.md).

## Attribution and licensing

SPDX-License-Identifier: GPL-2.0-only. Intended for compatibility with phpBB licensing.
