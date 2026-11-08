# phpBB/MariaDB integration tests

This suite installs a fresh, disposable phpBB 3.3.19 board on MariaDB 10.11 and enables the generated install ZIP using phpBB's CLI extension manager. It uses the real database driver, migration tools, ACL engine, config persistence, service container, cron manager, CSRF helpers, Twig renderer, posting API and content visibility service.

Only the classifier is deterministic. No OpenAI request is made, no real API key is required, and the runner removes the provider-key environment variable from its child processes. Other phpBB services are delegated unchanged through a test container adapter. Test decisions use synthetic fixture posts.

## Local execution

Requirements: Python 3, Git, PHP 8.4 with mysqli, mbstring, DOM/XML, tokenizer, ctype, iconv and fileinfo; Composer; and a disposable MariaDB 10.11 service.

Get phpBB at commit 10356c2c3be3804402c7616e18bb53d54c02f143, then install its own locked production dependencies:

    git clone https://github.com/phpbb/phpbb.git /tmp/phpbb-antispam-core
    git -C /tmp/phpbb-antispam-core checkout 10356c2c3be3804402c7616e18bb53d54c02f143
    composer install --working-dir=/tmp/phpbb-antispam-core/phpBB --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins

For a local Docker test database:

    docker run --detach --name antispam-integration-db       --publish 127.0.0.1:33306:3306       --env MARIADB_ALLOW_EMPTY_ROOT_PASSWORD=1       --env MARIADB_DATABASE=antispam_integration       mariadb:10.11@sha256:d769e37960821dfdcf096954f6e7ac89f747e116c1bba98e48edd265dfc7deae
    docker exec antispam-integration-db healthcheck.sh --connect --innodb_initialized

After the health check succeeds, run from the extension checkout:

    python3 tests/integration/run.py       --phpbb-checkout /tmp/phpbb-antispam-core       --db-port 33306

This test database has no password and is bound only to local loopback. It must never be used for a deployed forum. For a password-protected disposable test service, provide ANTISPAM_TEST_DB_PASSWORD through the process environment.

The runner accepts only local/container hosts and test database names beginning with antispam_test or antispam_integration (with an optional alphanumeric suffix). It refuses a database containing any tables and refuses an already-installed phpBB source checkout. It creates its own temporary board, marked with .antispam-integration, and never runs against the live forum.

The extension repository must include full Git history: the upgrade case extracts the pre-context 0.1.0-beta runtime at commit ad84ce8faa647be6cfd0183f726e44cad5f6d338 before upgrading to the current package.

## Outcomes and repeatability

The expected case names are listed in tests/integration/cases.json. A successful PHP exit alone is insufficient: phpBB can render an error page and exit zero. Every phase must produce a fresh JSON report with the current run ID, the exact expected cases, passed outcomes and a nonzero assertion count.

Reports and logs are written to test-results/integration under a unique run directory. Real Twig output is retained as acp-en.html and acp-fr.html. These contain synthetic test sessions/posts, are ignored by Git and are excluded from install packages. CI prints outcomes directly instead of consuming Actions artifact storage.

Temporary board files are removed on success unless --keep-board is supplied. On failure, the board and logs are retained for diagnosis. The database is intentionally not cleared automatically. To repeat, remove only the disposable container created above and create a new one:

    docker rm --force antispam-integration-db

Alternatively, provide another empty test database. Do not point the runner at an existing user's database.

## Coverage

- Clean installation, safe defaults, ACP module registration and service resolution.
- Scanning a newly published post in observation mode.
- Registered cron-manager dispatch and configured interval.
- Private, password-protected, unselected, unapproved and excluded-author filtering.
- API budget-failure fallback to uncertain without hiding or adding stored notifications, and no retry of unchanged failed posts.
- Core soft-deletion/restoration of latest replies, single-post topics and topic starters, with counters and first/last post fields checked.
- Manual ACP scanning with real CSRF validation.
- Real English/French Twig rendering, escaped context and audit post links.
- Edits to previously audited posts are rediscovered and update one audit row.
- Content edits during classification, with the changed post left visible and reclassified later.
- Exclusion of a second worker while phpBB's database cron lock is held.
- Disable/re-enable persistence.
- Purge/reinstall only inside the disposable board, followed by a real upgrade from the pinned legacy runtime; audit data, scan flags and BMW-context migration are checked.

## Limits

These tests establish deterministic phpBB/database integration outcomes, not model accuracy or production readiness on every hosting setup. They do not call the external API, deliver real emails, run two scanner processes simultaneously, or exercise a browser login flow. The cron lock check uses two lock instances against the real database; the edit check injects an edit during the classifier call.

Before submitting to phpBB.com, evaluate classifications on representative data, review false positives and privacy/billing disclosures, and verify the deployment's actual web/system cron and credential configuration.

Update the pinned phpBB commit, MariaDB digest and legacy baseline deliberately, rerunning the suite when any change.
