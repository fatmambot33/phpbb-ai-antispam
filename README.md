# phpBB AI Anti-Spam

Experimental native **phpBB 3.3** extension to classify **already published** forum messages using the [OpenAI Decisions API](https://developers.openai.com/api/docs/guides/decisions) (`gpt-6-luna`) and optionally hide clear spam reversibly.

## Safety contract

**Fail open for moderation:** on any API budget, quota, authentication, transport, timeout, refusal, or invalid-response failure, the effective classification is **`uncertain`**. Never hide, delete or notify subscribers because of a failed classification. Keep a distinct, safe error code in the audit log. Use bounded retries with backoff; pause provider requests when the API budget is exhausted.

- `legitimate`: no action.
- `spam`: optional phpBB soft-delete only when enabled and after thresholds are calibrated.
- `uncertain`: no modification, optionally flagged for human review.
- Defaults: scanner disabled, auto-hide disabled, no forums opted in.
- Data minimization: send opted-in public forum posts only; do not send private forums, IP addresses, or account email.
- No promise to retract emails already sent by phpBB.
- Integration testing on a staging phpBB 3.3 instance is required before deployment.

## Development

Implementation uses native PHP and phpBB services rather than direct changes to the posts/topics tables. The extension package is currently being transferred from the local prototype. Until then this repository is a project foundation, not an installable release.

See the Issues for implementation and acceptance criteria.

License: GPL-2.0-only.
