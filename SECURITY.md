# Security and privacy

## Reporting a vulnerability

Use GitHub's **Security → Report a vulnerability** to contact the maintainers privately when private vulnerability reporting is enabled:
https://github.com/fatmambot33/phpbb-ai-antispam/security/advisories/new

If that option is unavailable, open an issue requesting a private reporting channel without publishing the vulnerability details. Do not attach API keys, session IDs, forum database exports, private posts or deployment configuration to issues or pull requests.

This is an experimental phpBB 3.3 extension. Only the latest development revision is maintained; there is no supported production release or guaranteed response time.

## Credentials and deployment files

Keep the OpenAI key in your hosting configuration, outside this extension repository. A forum-root `.htaccess` containing `SetEnv` must remain on your server; it is not an extension file. phpBB's root `config.php`, database backups and logs must also stay out of Git and release attachments.

Ignore rules do not remove files already tracked in Git. If a credential is exposed, revoke or rotate it immediately and investigate Git history, discussions, logs and release assets before publishing the repository.

The install ZIP includes only tracked runtime files and the explicitly listed documentation. It rejects common deployment-only filenames even if tracked. Review source changes before packaging: these safeguards cannot identify every possible embedded credential.

## API data handling

The extension transmits selected public post text and topic/subject context to OpenAI. User-written text may contain personal data. It stores post IDs, content hashes, timestamps, decisions, scores and deterministic audit reasons locally, rather than another copy of raw post text.

OpenAI API data is not used for model training by default unless the organization opts in. This does not establish zero retention. Retention depends on the endpoint, account controls and applicable provider terms; this extension does not guarantee Zero Data Retention. Check [OpenAI's API data controls](https://developers.openai.com/api/docs/guides/your-data) and your organization's settings.

## Operational safeguards

- No source code deployment has been verified on a live phpBB instance.
- Set `BCA_ANTISPAM_OPENAI_API_KEY` in server environment; never commit a token.
- Only opt in to public forum IDs. Do not include private member-only forums without appropriate privacy review.
- Forum text is sent to an external AI provider; review retention and GDPR disclosures before enabling.
- The Decisions request does not set a Responses API `store` flag. Review the provider's data-handling terms and document the processing.
- Soft-deletion must be explicitly enabled after an observation period, evaluation and backup.
- Every AI result is untrusted; never execute its content or treat a confidence score as calibrated.
- API errors and malformed results are `uncertain`; no hiding on classification errors. There is no automatic retry, provider suspension, or cooldown.
- If the plugin is compromised, disable the extension in the ACP and rotate the API key.
- Do not enable on production until integration tests have validated permissions, SQL migrations, cron scheduling, counters, audit, and reversibility on a replica.
