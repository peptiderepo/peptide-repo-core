# Changelog

All notable changes to Peptide Repo Core are documented here.
Format: [Semantic Versioning](https://semver.org/).

## [0.3.0] — 2026-04-24

### Added
- **Related Articles feature** — New `peptide_topic` taxonomy links blog posts to peptides by slug. Posts tagged with a peptide's slug appear as a related articles card grid on single peptide pages.
- `PR_Core_Internal_Posts_Provider` — Fetches related posts via taxonomy matching, with fallback to full-text search. Results cached 1 hour per peptide.
- `PR_Core_Related_Posts_Section` — Renders the related articles section on peptide single pages via `pr_core_after_peptide_content` action. Respects admin settings for feature toggle and display limit.
- `PR_Core_Settings` — Admin settings page under Peptides menu. Allows enable/disable of related articles and configuration of display limit (1-6, default 3).
- Template part `template-parts/related-posts/card.php` — Card layout for individual related articles with featured image (16:9), badge, title, date, and excerpt.
- CSS file `assets/css/related-posts.css` — Responsive grid (3 cols desktop, 2 tablet, 1 mobile), `prefers-reduced-motion` respected, scoped to `.pr-related-posts`.
- Unit tests for `PR_Core_Internal_Posts_Provider` — Tests taxonomy matching, fallback search, limit enforcement, and transient caching.
- `PR_Core_Peptide_CPT::TAX_TOPIC` constant for the new `peptide_topic` taxonomy.

### Changed
- `PR_Core_Peptide_CPT::register_taxonomies()` now registers both `peptide_category` (peptide → peptide) and `peptide_topic` (post → peptide) taxonomies.
- `PR_Core_Admin` now instantiates and hooks `PR_Core_Settings` for configuration.
- `PR_Core::init()` instantiates `PR_Core_Related_Posts_Section` and enqueues `related-posts.css` on single peptide pages.
- `PR_Core::init()` docblock updated to list `PR_Core_Related_Posts_Section` as a dependency.
- Version bumped to `0.3.0`.

### Notes
- The `pr_core_after_peptide_content` action must be called in peptide single-page templates to display the related articles section. This hook fires after the main peptide content.
- Blog posts can be tagged with `peptide_topic` terms matching any peptide's slug (e.g., 'bpc-157', 'tb-500') to appear as related articles on that peptide's page.
- Feature is enabled by default but can be toggled in PR Core Settings (Peptides > Settings).
- Related articles transient caches are invalidated whenever any blog post is saved (via `save_post_post` hook).

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
- Lightweight unit-test harness (`tests/bootstrap.php` + `tests/unit/test-peptide-cpt.php`) that exercises CPT/taxonomy guards and args payload shape without a PHPUnit + wp-env dependency. Wired into the existing PHP-lint CI job.

### Removed
- `pr_peptide_family` taxonomy — never populated, never surfaced in UI.
- All `pr_peptide*` CPT-slug-derived references (constants, docblock mentions, uninstall string literals).
- Blanket `DELETE FROM posts WHERE post_type = 'pr_peptide'` on uninstall. Replaced with a join-on-`_pr_core_authored` selective delete so PSA-authored peptide posts are never destroyed by PR Core teardown.
- Taxonomy-term cleanup on uninstall. `peptide_category` is shared ownership post-v0.2.0; term data the site still needs is preserved.

### Fixed
- Production peptide detail pages (all 89) were 404'ing due to a rewrite slug collision between PSA's `peptide` CPT and PR Core's `pr_peptide` CPT both claiming `/peptides/%postname%/`. PR Core v0.1.1's empty `pr_peptide` CPT was winning the rewrite resolution. This release consolidates both to a single `peptide` CPT owned by PR Core.

### Notes
- Scope of the `pr_peptide*` scrub is intentionally narrow: CPT-slug-derived identifiers only. Class names (`PR_Core_Peptide_CPT`), file names (`class-pr-core-peptide-cpt.php`), plugin constants (`PR_CORE_VERSION`), option keys (`pr_core_*`), and the REST namespace (`pr-core/v1`) are the plugin's own namespace, not the CPT slug, and are preserved verbatim.
- PR Core-defined meta keys (`display_name`, `aliases`, `evidence_strength`, `editorial_review_status`, …) are gated by `manage_peptide_content`. The activator grants this capability to `administrator` and `editor` roles on activation. Prod sites where the activation hook never fired (v0.1.1 was deployed manually) will receive the grant on next `wp plugin activate peptide-repo-core`. Follow-up thread to decide whether a dedicated `peptide_editor` role is more appropriate than extending core `editor`.

## [0.1.1] — 2026-04-19

### Fixed
- **Fatal error on activation: `NAMESPACE` is a PHP reserved keyword.** Renamed
  to `REST_NAMESPACE` in the REST controller.
- **Fatal error: autoloader cannot resolve `PR_Core` class.** Class name equals
  the autoloader prefix exactly, producing wrong filename. Added explicit
  require_once in bootstrap (same pattern as PRAutoBlogger).

## [0.1.0] — 2026-04-17

### Added
- `pr_peptide` custom post type with REST API, archive, and taxonomies (`pr_peptide_category`, `pr_peptide_family`).
- Post-meta fields: display_name, aliases, molecular_formula, molecular_weight, cas_number, drugbank_id, chembl_id, evidence_strength, editorial_review_status, last_editorial_review_at, medical_editor_id.
- Custom table `pr_dosing_rows` with schema migration 0001 — high-cardinality dosing data with citation tracking, evidence strength, and supersede (soft-delete) pattern.
- Custom table `pr_legal_cells` with schema migration 0002 — per-country legal status with unique constraint on peptide x country (active).
- Custom table `pr_ai_candidate_queue` with schema migration 0003 — AI-extracted dosing candidates awaiting human review.
- Migration runner with sequential versioning (`pr_core_schema_version` option, `PR_CORE_TARGET_SCHEMA_VERSION` constant).
- Typed DTOs: `PR_Core_Peptide_DTO`, `PR_Core_Dosing_Row_DTO`, `PR_Core_Legal_Cell_DTO`, `PR_Core_Candidate_DTO`.
- Repository classes: `PR_Core_Peptide_Repository`, `PR_Core_Dosing_Repository`, `PR_Core_Legal_Repository`, `PR_Core_Candidate_Queue_Repository`.
- REST API (`pr-core/v1`): list/get peptides, list/create dosing rows, list/create legal cells.
- Admin UI: Scientific Identifiers meta box, Dosing Data meta box (read-only), Legal Status meta box (read-only), custom list-table columns (Evidence, Editorial, Dosing Rows).
- AI Candidate Queue admin page with approve/reject workflow (copies approved rows to dosing table).
- Shared disclaimer component: `[pr_disclaimer surface="dosing"]` shortcode, `PR_Core_Disclaimer::render()` static method, `pr_core_disclaimer_for_surface` filter.
- JSON-LD emission: schema.org `Drug` type on single peptide pages with `pr_core_jsonld_peptide` filter.
- Public API filters: `pr_core_get_indexable_corpus`, `pr_core_disclaimer_for_surface`, `pr_core_evidence_strength_label`.
- Public API actions: `pr_core_before/after_peptide_publish`, `pr_core_before/after_dosing_row_publish`, `pr_core_before/after_legal_cell_publish`, `pr_core_candidate_approved/rejected`.
- `manage_peptide_content` capability granted to administrators and editors on activation.
- Full teardown in `uninstall.php` (tables, posts, terms, options, capabilities).
- Seed data fixture: 3 peptides (BPC-157, Semaglutide, TB-500), 10 dosing rows, 5 legal cells.
- CI workflow: PHP lint (8.0/8.1/8.2), PHPCS (WordPress standard), 300-line file check.
