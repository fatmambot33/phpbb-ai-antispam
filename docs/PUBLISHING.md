# Making the repository public

This repository can be shared as an experimental project; that does not establish production readiness or phpBB Extensions Team validation.

Before changing visibility:

1. Scan all Git history and branches for credentials. Revoke exposed credentials before removing or rewriting them. Ignore rules do not erase history.
2. Review issues, pull-request discussions, screenshots, Actions logs and published release attachments for keys, session IDs, private posts or database exports. GitHub content outside Git requires a separate review.
3. Confirm you can redistribute all contributed or imported code under the included GPL-2.0-only license.
4. Run the checks documented in the README. Keep the experimental warning and scanning/auto-hide disabled by default.
5. In GitHub Settings, enable secret scanning, push protection and private vulnerability reporting where available. Review Actions permissions and branch protection for main.
6. Change visibility under Settings → General → Danger Zone → Change repository visibility. Read GitHub's consequences and complete its confirmation.

After publication, verify the README, license, security policy and intended download assets are accessible as expected. Draft release assets remain restricted until the draft is published.

Never upload the live forum's .htaccess, config.php, database backups, logs or user posts to demonstrate an installation problem.
