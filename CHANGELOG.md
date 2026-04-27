# Changelog

All notable changes to Peptide Repo Core are documented here.
Format: [Semantic Versioning](https://semver.org/).

## [0.4.0] — 2026-04-27

### Added
- **Verification Scanner**: Automated periodic scanning of all published peptides to compute verification status (current/due/overdue) based on days since last verification and configurable velocity-based thresholds.
- **Verification Settings**: New admin settings page under Peptides menu with configuration for scan cadence (daily/weekly/monthly), staleness thresholds (default 180 days, high-velocity 60 days, low-velocity 365 days), and notification email recipients.
- **Dashboard Widget**: "Monographs Needing Review" dashboard widget showing all peptides due or overdue for verification, sorted by staleness, with direct edit links and one-click scan trigger.
- **Editor Sidebar Meta Box**: New "Verification Status" sidebar meta box on peptide edit screen showing last-verified date, velocity selector, status badge, and one-click "Mark Verified Today" button that sets verification date to current time and recomputes status.
- **Admin AJAX Handlers**: Two new admin-ajax actions:
  - `pr_core_mark_verified`: Sets `_pr_last_source_verified` to today, saves optional notes, recomputes status.
  - `pr_core_scan_now`: Runs verification scanner immediately from the dashboard widget.
- **Frontend Verification Display**: On single-peptide pages, displays "Last verified: [date] — methodology" text after the verdict card div (requires non-empty `_pr_last_source_verified` meta field).
- **Verification Meta Fields** (registered in CPT):
  - `_pr_last_source_verified`: ISO datetime of most recent source verification.
  - `_pr_last_reviewed`: ISO datetime of editorial review (phase 2 integration point).
  - `_pr_next_review_by`: ISO datetime of next scheduled review (phase 2 integration point).
  - `_pr_verification_velocity`: Enum (low/medium/high) controlling threshold application.
  - `_pr_verification_notes`: Textarea for reviewer notes on the most recent verification pass.
  - `_pr_verification_status`: Enum (current/due/overdue) computed by scanner; drives widget sorting and badge color.
- **Scan Log**: Option `pr_core_verification_scan_log` stores last 90 scan summaries (timestamp, total count, due count, overdue count) for audit trail and health monitoring.
- **Email Digest**: When scans detect due/overdue peptides, a digest email is sent to configured recipients (default: none, must opt-in) with links to edit each peptide and the verification dashboard.

### Changed
- CPT meta field registration now includes 6 new verification fields alongside existing dosing/legal/editorial fields.

### Technical
- New classes: `PR_Core_Verification_Scanner`, `PR_Core_Settings`, `PR_Core_Verification_Widget`, `PR_Core_Verification_Display`, `PR_Core_Ajax_Handlers`.
- New unit tests: `test-verification-scanner.php` (status computation logic), `test-verification-settings.php` (cadence/cron scheduling).
- WordPress cron integration: `pr_core_verification_scan` hook scheduled at activation, cleared at deactivation, rescheduled when cadence changes.

## [0.2.2] — 2026-04-25

### Fixed
- Remove `rest_namespace` from peptide CPT registration. Custom namespace prevented Gutenberg block editor from loading peptide posts for editing (404 on wp/v2 REST route). All 89 peptide entries are now editable in the block editor.

## [0.2.1] — 2026-04-22

### Fixed
- **P1 hotfix: fatal `Undefined constant PR_Core_Peptide_CPT::TAX_FAMILY` on every peptide page.** The v0.2.0 release removed `TAX_FAMILY` from `PR_Core_Peptide_CPT` but left two stale references in `PR_Core_Peptide_Repository` (the `family` filter branch in `find_all()` and the families lookup in `post_to_dto()`). JSON-LD emission on single-peptide templates called `find_by_id()`, which called `post_to_dto()`, which hit the undefined constant and killed page rendering mid-`wp_head()`. QA gate on `874f93b` missed this because the unit tests cover the CPT class in isolation, not the repository's consumption of its constants. Post-mortem + QA checklist update to follow.

### Changed
- `PR_Core_Peptide_Repository::find_all()` — `family` filter is now silently ignored rather than throwing. Next minor bump will remove it from the REST schema.
- `PR_Core_Peptide_DTO::$families` is preserved but always populated as `[]`. Keeps REST response shape stable for existing clients; will be dropped with a release note.

## [0.2.0] — 2026-04-22

### Changed (BREAKING)
- CPT renamed from `pr_peptide` to `peptide`. Consolidates with PSA's existing `peptide` CPT which owns the 89 canonical peptide posts on production.
- Taxonomy renamed from `pr_peptide_category` to `peptide_category`. Same reason.
- CPT args harmonized as superset of PSA + prior PR Core registration (supports now includes thumbnail + revisions + custom-fields).
- Defensive registration: guards with `post_type_exists()` / `taxonomy_exists()` so PSA's parallel registration no-ops cleanly during the PSA v4.5.0 transition.

### Added
- `PR_Core_Activator::maybe_flush_on_version_change()` — one-shot rewrite flush on in-place version bumps (hooked at `init` priority 999). Eliminates the need for a manual `wp rewrite flush` after updates that change CPT/taxonomy slugs.
- `_pr_core_authored` post-meta flag contract — peptide posts created via PR Core UI carry this flag; `uninstall.php` uses it to scope teardown to plugin-owned posts only.
