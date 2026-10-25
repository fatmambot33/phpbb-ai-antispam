# Engineering instructions

This project is a phpBB 3.3 native PHP extension. Favor phpBB public service APIs, no direct writes to `phpbb_posts` or `phpbb_topics`.

- Default behavior must be **no destructive action**; require opt-in to monitored public forum IDs and auto-hide.
- Classifier outages or invalid responses must return `uncertain` without modifying forum posts. Keep one API attempt per post per scan; no automatic retry, provider pause, or circuit breaker.
- Respect phpBB ACLs and CSRF in ACP; do not expose tokens or private forum contents.
- Keep model decisions auditable; never use model score alone as proof of correctness.
- On every change, run `find . -name '*.php' -print0 | xargs -0 -n1 php -l` and `php tests/classifier_test.php`.
- Before release, validate on phpBB 3.3 with MariaDB including edits, deletion/restoration, first/last post and notifications.
- Prefer small, reviewable PRs with deterministic checks. Reserve quota-limited AI reviews for merge-ready checkpoints.
