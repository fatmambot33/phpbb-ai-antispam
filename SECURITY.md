# Security and privacy

- No source code deployment has been verified on a live phpBB instance.
- Set `BCA_ANTISPAM_OPENAI_API_KEY` in server environment; never commit a token.
- Only opt in to public forum IDs. Do not include private member-only forums without appropriate privacy review.
- Forum text is sent to an external AI provider; review retention and GDPR disclosures before enabling.
- `store:false` requests no storage in the Responses API, but does not replace a complete data processing/privacy assessment.
- Soft-deletion must be explicitly enabled after an observation period, evaluation and backup.
- Every AI result is untrusted; never execute its content or treat a confidence score as calibrated.
- Provider/DB errors fail closed: no hiding on classification errors.
- If the plugin is compromised, disable the extension in the ACP and rotate the API key.
- Do not enable on production until integration tests have validated permissions, SQL migrations, cron scheduling, counters, audit, and reversibility on a replica.
