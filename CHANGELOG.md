# Changelog

## 0.2.0-beta

- Add an ACP forum-context setting, bounded to 240 characters and included in classification requests.
- Use generic forum context for fresh installs; preserve the previous BMW context when upgrading older installations.
- Translate the ACP interface, standard decisions, actions and reason summaries into English and French.
- Preserve original audit explanations and post links.
- Continue to disable scanning and automatic hiding by default.

Upgrade by disabling the extension, replacing its files, then enabling it again to apply migrations. Purge phpBB's cache. Do not delete extension data. Leave automatic hiding off while evaluating the new context.

## 0.1.0-beta

Initial experimental scanner, Decisions classifier, ACP audit view and optional reversible soft-deletion. Subsequent development added public-forum checks, concurrent-edit detection, manual scan controls, install packaging and strict EPV CI.

No release has completed phpBB Extensions Team validation or the full phpBB/database integration checklist.
