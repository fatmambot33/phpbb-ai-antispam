# Security and privacy

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
