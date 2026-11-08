# phpBB validation preparation

Passing the Extension Pre-Validator (EPV) is an automated preflight, not approval by the phpBB Extensions Team or a completed phpBB integration test.

## Run the same check as CI

EPV is the official https://github.com/phpbb/epv tool. Its exact revision is pinned in scripts/epv-revision.txt. It uses its own committed Composer lockfile, outside the extension. The forum server does not need EPV, Python or Composer.

From the extension checkout, with PHP, Composer, Python and Git installed:

    epv_checkout=/tmp/phpbb-antispam-epv
    git clone https://github.com/phpbb/epv.git "$epv_checkout"
    git -C "$epv_checkout" checkout "$(cat scripts/epv-revision.txt)"
    composer install --working-dir="$epv_checkout" --no-dev --prefer-dist --no-interaction --no-progress --no-scripts --no-plugins
    python3 scripts/package.py
    python3 -m zipfile -e dist/bca-antispam-0.3.0-beta.zip /tmp/phpbb-antispam-install
    php scripts/epv.php "$epv_checkout" /tmp/phpbb-antispam-install
    python3 tests/epv_test.py --epv-root "$epv_checkout"

Use fresh temporary directories, or remove only directories created by these commands before repeating. Run against the extracted install ZIP, retaining its bca/antispam/ wrapper. Running against the development repository would include files that are not distributed and lacks that packaging wrapper.

Our wrapper uses EPV's finding counters and exits nonzero for fatal errors, errors, warnings or notices. EPV's standard CLI at the pinned revision exits zero for nonfatal errors and warnings even when its report says FAILED. The regression tests exercise real EPV runs to ensure our gate catches that case and incomplete validation.

When updating EPV, update its revision pin deliberately, review its upstream changes and lockfile, and rerun the gate tests. Do not silence new findings to keep CI green.

## Changes made for pre-validation

- Ship the GPL text as license.txt, as required by EPV.
- Version 0.3.0-beta manages the key only through the ACP and stores it in the uncached config_text service. It never reads environment keys. The saved key is not redisplayed; database backups contain the unencrypted value.
- Use utf8_htmlspecialchars() for ACP audit output.
- Cast integer query values explicitly where they enter SQL.

After updating extension files, enable the extension to apply migrations and purge phpBB's cache so the container uses the database-backed key service. Save the key once through the ACP. Both web and CLI scans use it. Leave automatic hiding off during testing.

## Forum context and ACP languages

Version 0.2.0-beta adds a bounded forum-context setting, a migration preserving the previous BMW context for existing installations, and a generic default for fresh installations. All ACP labels and standard decision/action/reason summaries have English and French translations. Original audit explanations remain available without changing stored evidence.

The context migration test uses the actual config.add tool and config container from a pinned phpBB 3.3.19 checkout. It checks fresh-install, legacy-upgrade and repeated/custom-value cases; it does not exercise the full database migrator.

CI loads that sparse fixture into .phpbb-fixture. To run its check locally after obtaining the same checkout:

    php tests/context_migration_test.php /path/to/phpbb-checkout

## Remaining work before submission

- Run the [phpBB/MariaDB integration suite](INTEGRATION_TESTING.md) and review its exact coverage. The deterministic cases cover installation/lifecycle/upgrade, scanning, moderation, ACLs, CSRF, Twig rendering and cron-lock exclusion. Concurrent scanner processes, real provider calls and actual email delivery remain outside that suite.
- Review external API billing, data transmission/retention disclosures and credential setup with the submission.
- Prepare an appropriately versioned release, support details and redacted screenshots, then review current phpBB submission policies.

Standalone tests and EPV alone do not establish integration readiness. The integration suite adds real phpBB/database evidence within its documented limits; none of these checks constitutes official phpBB validation.
