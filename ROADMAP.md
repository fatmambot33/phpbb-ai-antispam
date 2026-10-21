# Roadmap

## V0.1 — Prototype (in this archive)
- [x] Native phpBB extension metadata and migrations
- [x] ACP configuration (opt-in forums, interval, threshold, observe/soft-delete)
- [x] Incremental scan of already approved posts; limited batching
- [x] OpenAI small-model classification with JSON validation
- [x] Reversible soft-delete via phpBB content visibility API
- [x] Classification results retained in a dedicated table
- [x] PHP syntax / JSON parser tests in CI
- [ ] Integration verification against phpBB 3.3 and MariaDB
- [ ] Integration tests for topic starter, last reply, restored post, concurrent editing

## V0.2 — Moderator experience
- [x] ACP latest-30 results list (basic audit view)
- [ ] Detailed filters, pagination and audit export
- [ ] One-click restore and mark false positive
- [ ] Observe-only evaluation dashboard (confusion matrix, false positives)
- [ ] Mark and skip moderator-reviewed legitimate posts
- [ ] Notify staff on suspected spam (not regular subscribers)

## V0.3 — Operational resilience
- [ ] Adaptive retry/backoff on API rate limits; cost caps
- [ ] Add lock/idempotency guard for concurrent cron executions
- [ ] Support author-based heuristics and allowlists
- [ ] Backfill with pause/resume and visible progress
- [ ] Privacy minimization and configurable data retention
- [ ] phpBB Extension Validator and phpBB test-framework CI
