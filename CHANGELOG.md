# Changelog

All notable changes to Peptide Repo Core are documented here.
Format: [Semantic Versioning](https://semver.org/).

## [0.6.2] — 2026-06-13

### Fixed
- **Critical**: Peptide monograph pages (e.g. `/peptides/bpc-157/`) returned HTTP 500 after v0.6.0 launch. Root cause: `PR_Core_Jsonld::inject_graph_pieces()` appended plain PHP arrays (Drug, FAQ) to Yoast's `wpseo_schema_graph_pieces` filter. Yoast's `Schema_Generator::filter_graph_pieces_to_generate()` then called `get_class()` and `is_needed()` on every piece, expecting `Abstract_Schema_Piece` objects — a plain array causes `PHP Fatal: get_class(): Argument #1 ($object) must be of type object, array given`. Non-peptide pages (/, /about/, /peptides/ archive) were unaffected because the `is_singular('peptide')` guard prevented entry.
- Fix: removed `wpseo_schema_graph_pieces` hook entirely. Drug and FAQPage nodes are now injected via a new `inject_graph_nodes()` method hooked on `wpseo_schema_graph` at priority 12, which receives Yoast's fully-assembled graph as an array of plain arrays — safe for plain array appends.
- Added regression test `tests/unit/test-jsonld-peptide-piece.php` that reproduces the v0.6.1 `get_class(array)` fatal and verifies the v0.6.2 `inject_graph_nodes` path emits Drug + FAQ correctly across peptide, non-peptide, and empty-FAQ scenarios.

### Changed
- `PR_Core_Jsonld::register_hooks()`: no longer registers `wpseo_schema_graph_pieces`; registers `wpseo_schema_graph` at priority 12 for Drug/FAQ injection (in addition to priority 11 for `enrich_webpage_piece`, unchanged).
- `PR_Core_Jsonld::inject_graph_pieces()` renamed to `inject_graph_nodes()`; signature updated from `(array $pieces, $context)` to `(array $graph, $context)` to reflect that it now receives the assembled graph, not the pieces array.



## [0.6.1] — 2026-06-13

### Fixed
- Fix: Yoast `wpseo_schema_webpage_type` passes `string|array` on singulars — accept `string|array` in `retype_to_medical_webpage()` signature; return type updated to match (PHP 8.1+ union). Eliminates `TypeError` → HTTP 500 on all singular pages (monographs + plain Pages) under `strict_types=1`.
- Fix: `enrich_webpage_piece` compared `'WebPage' === $piece['@type']` but Yoast sets `@type` as an array on singular pages. Normalise `@type` to array before `in_array()` check; `lastReviewed`, `reviewedBy`, and `audience` now inject correctly.
- Add regression test `tests/unit/test-jsonld-webpage.php`: exercises `retype_to_medical_webpage()` with array input for peptide and non-peptide contexts (no TypeError), and `enrich_webpage_piece` with `@type` as array (enrichment injects). Tests fail against v0.6.0, pass after fix.

## [0.6.0] — 2026-06-13

### Added
- **Backfill migration 0004** (`class-pr-core-migration-0004-backfill-peptide-meta.php`): Copies PSA-stored PubChem data into new `_pr_*` schema-input meta keys across all published peptide posts. Source: `psa_molecular_formula` → `_pr_molecular_formula`, `psa_molecular_weight` → `_pr_molecular_weight` (strip "Da", floatval), `psa_aliases` → `_pr_aliases` (comma-split, sanitize, JSON array). For 4 posts without PSA data (igf-1-lr3, cagrilintide, ghk-cu, epitalon), falls back to PubChem REST API via new `PR_Core_Pubchem_Client`. Migration is idempotent + re-runnable; skips posts where all three keys are already populated.
- **Schema-input meta keys** (registered in CPT, v0.6.0):
  - `_pr_molecular_formula` — sanitized molecular formula string.
  - `_pr_molecular_weight` — float as string (g/mol); "Da" suffix normalized out.
  - `_pr_aliases` — JSON array of sanitized alternate names.
  - `_pr_faq_items` — JSON array of `{question, answer}` objects for FAQPage emission.
- **`PR_Core_Schema_Sanitizers`** (`cpt/class-pr-core-schema-sanitizers.php`): Static sanitizers for the four schema-input meta keys, split from `PR_Core_Peptide_CPT` to keep the CPT file under 300 lines.
- **`PR_Core_Pubchem_Client`** (`migrations/class-pr-core-pubchem-client.php`): Lightweight PubChem REST client with CID/name lookup, synonym fetch, and MAX_RETRIES=3 exponential backoff. Used exclusively by migration 0004.
- **Yoast-integrated JSON-LD emission** (replaces standalone `wp_head` script):
  - `PR_Core_Jsonld_Drug` — builds Drug (+ MolecularEntity) schema node with stable `@id = {permalink}#drug`, reads `_pr_*` schema-input meta, `alternateName`/`molecularFormula`/`molecularWeight` (g/mol) emitted only when non-empty.
  - `PR_Core_Jsonld_Webpage` — retypes Yoast's WebPage to `MedicalWebPage` via `wpseo_schema_webpage_type`; enriches it with `lastReviewed`, `reviewedBy` (Person or Organization), and `audience` via `wpseo_schema_graph`.
  - `PR_Core_Jsonld_Faq` — builds FAQPage node from `_pr_faq_items`; emitted only when items exist.
  - `PR_Core_Jsonld` — orchestrator hooking all three builders into Yoast's graph; maintains standalone `@graph` fallback when Yoast is inactive.
- **`CONTEXT.md`** — new domain glossary covering all new terms (`_pr_*` keys, Drug node, MedicalWebPage, FAQPage, Yoast integration contract, PubChem CID).

### Changed
- `PR_Core_Jsonld` rewritten: replaces standalone `wp_head` script-tag emission with Yoast graph-filter integration. Standalone `@graph` retained as fallback only. **No duplicate WebPage/BreadcrumbList** — Yoast owns those.
- `PR_Core_Peptide_CPT::get_meta_fields()` — adds 4 new `_pr_*` fields; existing `aliases`/`molecular_formula`/`molecular_weight` fields unchanged.
- `PR_Core_Migration_Runner::MIGRATIONS` — adds `PR_Core_Migration_0004_Backfill_Peptide_Meta` at index 3.
- `PR_CORE_TARGET_SCHEMA_VERSION` bumped from 3 to 4.
- `uninstall.php` — purges `_pr_molecular_formula`, `_pr_molecular_weight`, `_pr_aliases`, `_pr_faq_items` from all peptide posts on uninstall.
- `ARCHITECTURE.md` — §#6 JSON-LD rewritten; §2.9 uninstall spec updated; file tree updated for new classes.
- `CONVENTIONS.md` — schema-input meta convention added; Yoast JSON-LD extension pattern added.

### Fixed
- `tests/unit/test-migration-0004.php`: `error_log()` stub now guarded with `function_exists()` to prevent PHP fatal redeclaration error in CI PHP Lint (8.1/8.2/8.3).
- `tests/bootstrap.php`: `wp_json_encode` stub updated to accept `int $flags = 0` second argument, matching production call signature; guarded with `function_exists()`.
- `cpt/class-pr-core-schema-sanitizers.php`: `sanitize_molecular_formula()` rewritten from character-allowlist to element-formula grammar validation (Option B). Now strips control characters first, then extracts the maximal leading run of valid `[A-Z][a-z]?\d*` element tokens and group separators. Inputs containing trailing English words (e.g., `"C10H12\ninjection"`) correctly return the formula prefix only (`"C10H12"`); fully invalid inputs (e.g., `"<script>alert(1)</script>"`) return `""`. Parenthesised formulas (e.g., `"C2H5(OH)"`) preserved unchanged. Seven-case unit test coverage added.

### Technical
- Schema version: `PR_CORE_TARGET_SCHEMA_VERSION` = 4.
- New test files: `tests/unit/test-migration-0004.php`, `tests/unit/test-jsonld.php`.
- Yoast filter names verified against Yoast 27.6: `wpseo_schema_graph_pieces`, `wpseo_schema_webpage_type`, `wpseo_schema_graph`.
- No CAS/DrugBank emission in v0.6.0 (no reliable source — documented in ARCHITECTURE.md for future enrichment sprint).

## [0.5.0] — 2026-04-27

### Added
- **Repo Daily CPT**: New `repo_daily` custom post type at `/daily/[slug]/` for editorial publications (articles, guides, comparisons, news) generated by PRAutoBlogger and published under the Boo Sheeran byline.
- **Repo Daily Category Taxonomy**: Non-hierarchical `repo_daily_category` taxonomy with four seeded terms (article, guide, comparison, news) for editorial classification.
- **Repo Daily Meta Fields**:
  - `_repo_daily_author` (string, default "Boo Sheeran") — byline displayed in theme templates. Never hardcoded; always read from meta.
  - `_repo_daily_clinical_review_required` (bool, stored as '1'/'') — editorial flag marking posts that require PR Clinical Review before publication.
- **Repo Daily Admin Meta Box**: "Article Settings" meta box on repo_daily edit screen with author byline field and clinical review flag checkbox.
- **Bulk Author Migration**: On activation, sets `_repo_daily_author = 'Boo Sheeran'` on all post_type=post posts bearing the psa_source meta key (autoblogged articles). Idempotent; existing meta values preserved. Posts remain post_type=post; forward-only migration for byline retroactivity.

### Changed
- `PR_Core_Admin::register_hooks()` now registers repo_daily metaboxes alongside peptide metaboxes.

### Technical
- New classes: `PR_Core_Repo_Daily_CPT`, `PR_Core_Repo_Daily_Taxonomy`, `PR_Core_Repo_Daily_Metaboxes`.
- New method: `PR_Core_Activator::migrate_autoblogged_author_meta()` — idempotent bulk meta setter.
- Taxonomy seeding: `PR_Core_Repo_Daily_Taxonomy::seed_terms()` called on activation.

### Documentation
- ARCHITECTURE.md: Added repo_daily CPT to content-type table; documented scanner exclusion by CPT separation.
- CONVENTIONS.md: Added "How to add a new post meta field to repo_daily posts" extension pattern.

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
