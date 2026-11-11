# phpBB submission pack — 0.3.0-beta

This is a preparation pack, not a submitted or approved phpBB extension. Version 0.3.0-beta is a public experimental beta on GitHub. On 2026-11-11 the maintainer reported that the requested forum scan checks were successful; the acceptance record below distinguishes this report from automated evidence and checks still pending.

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
| Package download | [0.3.0-beta release](https://github.com/fatmambot33/phpbb-ai-antispam/releases/tag/v0.3.0-beta) / [install ZIP](https://github.com/fatmambot33/phpbb-ai-antispam/releases/download/v0.3.0-beta/bca-antispam-0.3.0-beta.zip) |
| Source | https://github.com/fatmambot33/phpbb-ai-antispam |
| Support / bugs | https://github.com/fatmambot33/phpbb-ai-antispam/issues |
| Private security reports | [Security policy](../SECURITY.md) |

The ZIP's contents were downloaded from GitHub and compared byte-for-byte with the tested local package. The release was published as a prerelease on 2026-11-11; the public download was verified without authentication and matches the checksum above. Follow the separate phpBB submission procedure when preparing a CDB validation package.

## Development-forum route

On 2026-11-11 the maintainer supplied the text titled "Extension Development Forum Rules" from phpBB.com. This is supplied policy text, not an independent live-site verification. The published environment settings now permit phpBB.com, but direct requests receive a Cloudflare challenge. The supplied text resolves the development-topic requirements below; it does not include the separate Extension Validation Policy.

| Supplied rule | Action for this extension |
| --- | --- |
| Every topic needs an allowed stage prefix | Use `[BETA] AI Anti-Spam for phpBB — 0.3.0-beta`. Do not use `[CDB]` before acceptance. |
| BETA / RC need a public download; no registration may be required | Link the public GitHub install ZIP above. A draft release does not qualify. |
| Downloads must be linked, not attached directly to the topic | Use the ZIP link in the prepared topic. |
| Recommended topic format is optional; screenshots and demo are optional | A text-only beta topic is possible. No demo login is needed. |
| Extension author provides support | Support questions belong in the development topic; do not imply phpBB Teams provide support. |
| Security reports go to the author and Extension Security Tracker | The prepared topic explains both reporting destinations. |
| Redistribution restrictions are disallowed; phpBB-derived code requires GPL v2 | The package declares GPL-2.0-only and contains license.txt. |
| Development topics must intend eventual CDB submission and meet its packaging/license requirements | State CDB intent. The install ZIP passes EPV; still review the full Packaging and Version section of the separate Extension Validation Policy. |

Prepared BBCode body: [PHPBB_BETA_TOPIC.txt](PHPBB_BETA_TOPIC.txt). After reviewing the Packaging and Version requirements referenced by these rules, create a topic in the Extensions in Development forum with the title above, paste the body and use Preview before submitting. The author line uses the maintainer-confirmed phpBB.com username, bmwclubarmorique.fr. The package attribution is BCA Contributors. Verify the public download first. No topic has been posted automatically.

The beta announcement and a formal CDB submission are separate steps. The development-forum text does not establish that a beta version is acceptable for CDB validation. Keep version/status unchanged until the separate validation rules and remaining acceptance checks are reviewed.

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

## Forum acceptance record

Continue recording results for this exact ZIP before proposing a stable release. Keep automatic hiding disabled on the live forum. Exercise optional moderation behavior on a disposable staging board.

On 2026-11-11 the maintainer reported success after being asked to verify manual scanning, scheduled scanning and the message links. These outcomes are recorded as maintainer-reported, without independently collected forum logs or screenshots. This does not establish model accuracy, privacy-filter coverage or completion of the separate staging checks below.

| Check | Expected result | Recorded result |
| --- | --- | --- |
| Upgrade without deleting extension data | Extension re-enables; prior settings and audit decisions remain | Pending |
| ACP credentials | Key saves; returning to the page shows configured status and an empty password field | Pending |
| Manual scan | A new eligible public fixture post receives an audit decision; View message opens that post | Passed — maintainer report, 2026-11-11 |
| Scheduled scan | A second new eligible post is classified without Scan now; last-attempt time advances | Passed — maintainer report, 2026-11-11 |
| Restricted forum | Content outside the public opt-in list is not classified | Pending |
| Legitimate / spam review | Maintainer reviews representative results and records false positives, false negatives and sample size | Pending |
| Missing key on staging | Scheduling stops and no post is hidden; restoring the key restores eligibility | Pending |
| Optional moderation on staging | Moderator can restore a soft-deleted reply and topic starter; counters and topic lists remain correct | Pending |
| Upgrade rollback rehearsal on staging | Known-good package and database backup can be restored together | Pending |

Use synthetic posts for screenshots. Capture the credentials/status area with no key entered, scan settings and the results table with a working View message link. Crop unrelated navigation and redact user names, domains if private, session IDs and other unrelated details. Do not upload raw ACP HTML, database exports or hosting configuration. The screenshots are still to be supplied; no production screenshot or acceptance result has been fabricated.

## Submission steps still requiring the maintainer

1. Complete the acceptance record. The maintainer has selected bmwclubarmorique.fr as the phpBB.com author account; use it for the topic and support. The repository author label is BCA Contributors.
2. Review Packaging and Version in the separate Extension Validation Policy before posting the beta topic; the supplied development-forum rules explicitly apply those packaging/license requirements too. Review the full policy before formal CDB submission. The development-forum text's optional screenshot/demo guidance does not establish CDB requirements. Earlier proxy denials were resolved by the published environment settings; live-site policy verification is now blocked by Cloudflare.
3. Choose the submission version/status based on those rules and test results. Do not rename this beta to a stable version without reviewing metadata, changelog, package and validation together.
4. Review the prepared beta announcement, its public ZIP link and author attribution before posting it. Screenshots and a demo are optional under the supplied development-forum rules. Check any separate CDB requirements before submission.
5. Sign in on phpBB.com and follow its extension submission flow. Use the install ZIP containing bca/antispam/, not GitHub's source archive. Include the external-service, billing and credential-storage disclosures above. Verify the uploaded file's identity and version.
6. Track the phpBB review and address findings. Claim phpBB validation only after the Extensions Team approves the submission.

The GitHub beta prerelease is public. No phpBB.com topic or formal submission has been posted, and no stable release or phpBB validation is claimed.
