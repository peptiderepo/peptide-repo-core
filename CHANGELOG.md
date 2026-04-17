# Changelog

All notable changes to Peptide Repo Core are documented here.
Format: [Semantic Versioning](https://semver.org/).

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
