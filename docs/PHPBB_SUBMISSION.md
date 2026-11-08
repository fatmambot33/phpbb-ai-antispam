# phpBB submission pack — 0.3.0-beta

This is a preparation pack, not a submitted or approved phpBB extension. Version 0.3.0-beta remains an experimental test build. The maintainer confirmed on 2026-11-08 that this version has not yet been installed and checked on the forum.

## Package and evidence

| Item | Value |
| --- | --- |
| Extension name | AI Anti-Spam for phpBB |
| Composer identity / install directory | bca/antispam / ext/bca/antispam/ |
| Version | 0.3.0-beta |
| License | GPL-2.0-only |
| Package source commit | 5357ec0930f2fd5095f5c6df3411a32be16f53a0 |
| Install ZIP | bca-antispam-0.3.0-beta.zip |
| ZIP SHA-256 | a0606c3c74eaa75a3916b4f71d6b1b0ed8ffc23d9e856c701df261425c6fa629 |
| Package download | [GitHub draft release](https://github.com/fatmambot33/phpbb-ai-antispam/releases/tag/untagged-abcf0c1636736ed7ec8a) — requires repository write access while a draft |
| Source | https://github.com/fatmambot33/phpbb-ai-antispam |
| Support / bugs | https://github.com/fatmambot33/phpbb-ai-antispam/issues |
| Private security reports | [Security policy](../SECURITY.md) |

The ZIP's contents were downloaded from GitHub and compared byte-for-byte with the tested local package. The draft download is not a public support/download URL. Provide a public download when announcing a beta publicly, and follow the current phpBB submission procedure for the validation package.

## Copy-ready description

AI Anti-Spam for phpBB reviews already published posts in administrator-selected public forums. It sends eligible post content to OpenAI for a spam, legitimate or uncertain classification, then records the decision in the administration control panel with a link to the original message.

Scanning and automatic hiding are disabled by default. Administrators can enable observation mode first. Optional automatic hiding uses phpBB's reversible soft-deletion service; API errors and invalid responses are recorded as uncertain and do not trigger hiding. AI scores are not proof that a message is spam.

Features:

- Select public forums by forum ID; private, password-protected and unapproved posts are excluded.
- Scheduled phpBB cron scans and an ACP Scan now action.
- Configurable forum context, scan interval, lookback period and excluded author IDs.
- Reclassification of edited posts that were previously audited.
- Latest 30 classification decisions, total result count and direct post links.
- English and French administration interface.
- API key management exclusively through the ACP, shared by web and CLI scans.

Requirements: phpBB 3.3, PHP 7.4 or later, cURL and mbstring, outbound HTTPS access to OpenAI, and an OpenAI API account with access to the configured model/endpoint and sufficient billing allowance. The extension currently uses gpt-6-luna through the Decisions API. The forum server does not need Python, Docker or a Composer install for this extension.

The declared phpBB compatibility range is 3.3.x. Automated database integration was exercised on phpBB 3.3.19 and MariaDB 10.11; this is not a claim of integration testing on every supported phpBB/PHP/database combination.

## External service and privacy disclosure

OpenAI is an external paid service. Each classification sends the selected post text and topic/subject context over HTTPS. The configured forum context is also sent. Email addresses and IP addresses are not deliberately added, but user-written text can contain personal information. Public accessibility does not remove data-protection obligations.

The extension's audit table retains identifiers, content hashes, timestamps, decisions and scores with deterministic reasons, rather than a second copy of raw post text. OpenAI API data is not used for training by default unless the organization opts in; this does not guarantee zero retention. The administrator must review the endpoint's current terms, account controls, billing and applicable privacy disclosures. See [SECURITY.md](../SECURITY.md).

The API key is stored unencrypted in phpBB's uncached config_text database table and is included in database backups. Only administrators with board-configuration permission can manage it through CSRF-protected forms. The saved value is not redisplayed. Environment keys are not read or imported.

The extension processes posts after publication. It cannot prevent or recall notification emails already sent by phpBB. Hidden posts are restored through phpBB's normal moderation tools.

## Automated test evidence

| Check | Evidence |
| --- | --- |
| PHP syntax and regression suites | Passed on PHP 7.4, 8.2 and 8.4 in [main CI](https://github.com/fatmambot33/phpbb-ai-antispam/actions/runs/42528801616) |
| Official Extension Pre-Validator | Zero findings locally; passed in main CI. Pinned revision: 8ecb57b61a23a7925b0adbf7b53016e4efee4a24 |
| Real phpBB / MariaDB integration | Passed in main CI. Local run: 22 cases, 140 assertions, using synthetic data and a deterministic classifier |
| Packaging | Reproducible ZIP tests passed; [GitHub package workflow](https://github.com/fatmambot33/phpbb-ai-antispam/actions/runs/42528805910) succeeded |

The integration suite exercises installation, ACL filtering, scheduled dispatch, manual ACP scans, CSRF, key storage/removal, English/French Twig rendering, edited posts, core deletion/restoration with counters, cron locking, disable/re-enable and upgrade from the pinned legacy package. Exact coverage and limits: [INTEGRATION_TESTING.md](INTEGRATION_TESTING.md).

It does not establish model accuracy, real provider availability, actual email delivery or browser-login behavior. No real OpenAI credential or live forum database was used. Automated passes do not constitute phpBB Extensions Team approval.

## Forum acceptance record — pending

Record results for this exact ZIP before proposing a release for wider use. Keep automatic hiding disabled on the live forum. Exercise optional moderation behavior on a disposable staging board.

| Check | Expected result | Recorded result |
| --- | --- | --- |
| Upgrade without deleting extension data | Extension re-enables; prior settings and audit decisions remain | Pending |
| ACP credentials | Key saves; returning to the page shows configured status and an empty password field | Pending |
| Manual scan | A new eligible public fixture post receives an audit decision; View message opens that post | Pending |
| Scheduled scan | A second new eligible post is classified without Scan now; last-attempt time advances | Pending |
| Restricted forum | Content outside the public opt-in list is not classified | Pending |
| Legitimate / spam review | Maintainer reviews representative results and records false positives, false negatives and sample size | Pending |
| Missing key on staging | Scheduling stops and no post is hidden; restoring the key restores eligibility | Pending |
| Optional moderation on staging | Moderator can restore a soft-deleted reply and topic starter; counters and topic lists remain correct | Pending |
| Upgrade rollback rehearsal on staging | Known-good package and database backup can be restored together | Pending |

Use synthetic posts for screenshots. Capture the credentials/status area with no key entered, scan settings and the results table with a working View message link. Crop unrelated navigation and redact user names, domains if private, session IDs and other unrelated details. Do not upload raw ACP HTML, database exports or hosting configuration. The screenshots are still to be supplied; no production screenshot or acceptance result has been fabricated.

## Submission steps still requiring the maintainer

1. Complete the acceptance record and select the phpBB.com account responsible for support. The repository author label is BCA Contributors; confirm the desired attribution before entering submission fields.
2. Read the current rules from [phpBB Extensions](https://www.phpbb.com/extensions/) and its linked submission policies. The environment could not access phpBB.com on 2026-11-08 because its network proxy rejected the destination. Acceptance of beta versions, required fields/screenshots and support-topic requirements have not been verified against current policy.
3. Choose the submission version/status based on those rules and test results. Do not rename this beta to a stable version without reviewing metadata, changelog, package and validation together.
4. Prepare the required screenshots and a publicly usable support/download path when appropriate. Review any public beta announcement before posting it.
5. Sign in on phpBB.com and follow its extension submission flow. Use the install ZIP containing bca/antispam/, not GitHub's source archive. Include the external-service, billing and credential-storage disclosures above. Verify the uploaded file's identity and version.
6. Track the phpBB review and address findings. Claim phpBB validation only after the Extensions Team approves the submission.

No phpBB.com submission, public announcement or stable release has been made by preparing this document.
