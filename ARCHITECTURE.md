# Peptide Repo Core — Architecture

Peptide Repo Core is a WordPress plugin that provides the canonical peptide data layer for the peptiderepo.com ecosystem. It defines the `pr_peptide` custom post type, custom tables for dosing rows and legal status cells, typed repository classes, an AI candidate queue for semi-automated data extraction, a shared disclaimer component, and JSON-LD structured data output. Every consumer plugin (PR Theme, PRAutoBlogger, Peptide News, Peptide Search AI, and all Tier 1 tools) reads peptide data through this plugin's versioned PHP API, never directly.

---

## Data Flow

```
                         ┌───────────────────────┐
                         │  PubMed / Literature   │
                         └──────────┬────────────┘
                                    │ (future: extraction pipeline)
                                    ▼
                         ┌───────────────────────┐
                         │ AI Candidate Queue     │  pr_ai_candidate_queue table
                         │ (pending → review)     │  Confidence-sorted
                         └──────────┬────────────┘
                                    │ Approve
                                    ▼
┌──────────────────┐    ┌───────────────────────┐    ┌──────────────────┐
│ Admin UI / REST  │───▶│   Dosing Repository   │───▶│ pr_dosing_rows   │
│ (manual entry)   │    │   Legal Repository    │    │ pr_legal_cells   │
└──────────────────┘    └───────────────────────┘    └──────────────────┘
                                    │
                                    ▼
                         ┌───────────────────────┐
                         │  Peptide Repository   │  pr_peptide CPT + meta
                         └──────────┬────────────┘
                                    │
                    ┌───────────────┼───────────────┐
                    ▼               ▼               ▼
            ┌──────────┐    ┌──────────┐    ┌──────────┐
            │ REST API │    │ JSON-LD  │    │ Consumer │
            │ pr-core  │    │ Drug     │    │ Plugins  │
            │ /v1/     │    │ schema   │    │ via API  │
            └──────────┘    └──────────┘    └──────────┘
```

---

## File Tree

```
peptide-repo-core/
├── peptide-repo-core.php              # Plugin bootstrap — constants, autoloader, activation hooks
├── uninstall.php                      # Full teardown: tables, posts, terms, options, capabilities
├── ARCHITECTURE.md                    # This file
├── CONVENTIONS.md                     # Naming patterns, extension guides
├── CHANGELOG.md                       # Semantic versioning changelog
│
├── .github/
│   └── workflows/
│       └── ci.yml                     # PHP lint (8.0-8.2), PHPCS, 300-line check
│
├── assets/
│   └── css/
│       └── admin.css                  # Minimal admin meta box styles
│
├── includes/
│   ├── class-pr-core.php              # Main orchestrator — boots subsystems, registers public filters
│   ├── class-pr-core-activator.php    # Activation: migrations, capabilities, rewrite flush
│   ├── class-pr-core-deactivator.php  # Deactivation: rewrite flush only (data preserved)
│   ├── class-pr-core-autoloader.php   # SPL autoloader for PR_Core_ prefixed classes
│   ├── class-pr-core-seed-data.php    # Dev fixture: 3 peptides, 10 dosing rows, 5 legal cells
│   │
│   ├── cpt/
│   │   └── class-pr-core-peptide-cpt.php  # CPT registration, taxonomies, meta fields, sanitizers
│   │
│   ├── migrations/
│   │   ├── class-pr-core-migration-runner.php           # Sequential migration engine
│   │   ├── class-pr-core-migration-0001-dosing-rows.php # pr_dosing_rows table
│   │   ├── class-pr-core-migration-0002-legal-cells.php # pr_legal_cells table + unique constraint
│   │   └── class-pr-core-migration-0003-candidate-queue.php # pr_ai_candidate_queue table
│   │
│   ├── dto/
│   │   ├── class-pr-core-peptide-dto.php     # Typed peptide value object
│   │   ├── class-pr-core-dosing-row-dto.php  # Typed dosing row value object
│   │   ├── class-pr-core-legal-cell-dto.php  # Typed legal cell value object
│   │   └── class-pr-core-candidate-dto.php   # Typed candidate queue value object
│   │
│   ├── repositories/
│   │   ├── class-pr-core-peptide-repository.php          # CRUD for pr_peptide CPT
│   │   ├── class-pr-core-dosing-repository.php           # CRUD for pr_dosing_rows
│   │   ├── class-pr-core-legal-repository.php            # CRUD for pr_legal_cells
│   │   └── class-pr-core-candidate-queue-repository.php  # CRUD + approve/reject for queue
│   │
│   ├── admin/
│   │   ├── class-pr-core-admin.php                # Admin initialization, menu, styles
│   │   ├── class-pr-core-peptide-metaboxes.php    # Identifiers, dosing, legal meta boxes
│   │   ├── class-pr-core-admin-columns.php        # Evidence, editorial, dosing count columns
│   │   └── class-pr-core-candidate-queue-page.php # AI candidate review admin screen
│   │
│   ├── frontend/
│   │   ├── class-pr-core-disclaimer.php   # Shortcode + static API for surface disclaimers
│   │   └── class-pr-core-jsonld.php       # schema.org Drug JSON-LD on single peptide pages
│   │
│   └── api/
│       └── class-pr-core-rest-controller.php  # REST endpoints for peptides, dosing, legal
│
└── tests/
    ├── unit/                          # PHPUnit tests (mocked WP)
    └── integration/                   # WordPress integration tests
```

---

## Custom Tables

### `{prefix}pr_dosing_rows`

High-cardinality dosing data, 1:many with peptide. Indexed on peptide_id, route, population, pubmed_id.
Supersede pattern: corrections create a new row and set `superseded_by_id` on the old one.

### `{prefix}pr_legal_cells`

Per-country legal status, 1:many with peptide. Unique constraint on (peptide_id, country_code, superseded_by_id) — only one active cell per peptide x country.

### `{prefix}pr_ai_candidate_queue`

AI-extracted dosing candidates. Status flow: pending → approved/rejected → merged (if approved). Approved rows copy into `pr_dosing_rows` with `source = 'ai-candidate-approved'`.

---

## Public API

### Repositories (typed DTOs, never raw objects)

- `PR_Core_Peptide_Repository` — find_by_id, find_by_slug, search, find_all, count
- `PR_Core_Dosing_Repository` — find_by_id, find_by_peptide, insert, supersede, count_by_peptide
- `PR_Core_Legal_Repository` — find_by_id, find_by_peptide, find_by_peptide_and_country, find_by_country, insert, supersede
- `PR_Core_Candidate_Queue_Repository` — find_by_id, find_by_status, insert, approve, reject, count_by_status

### Filters

- `pr_core_get_indexable_corpus` — returns `{ id, url, title, body, type }` entries for search indexing
- `pr_core_disclaimer_for_surface` — returns disclaimer text for a given surface identifier
- `pr_core_evidence_strength_label` — maps enum value to localized label
- `pr_core_jsonld_peptide` — filter JSON-LD schema before output
- `pr_core_register_peptide_fields` — add computed fields to peptide DTO (planned)

### Actions

- `pr_core_before_peptide_publish`, `pr_core_after_peptide_publish`
- `pr_core_before_dosing_row_publish`, `pr_core_after_dosing_row_publish`
- `pr_core_before_legal_cell_publish`, `pr_core_after_legal_cell_publish`
- `pr_core_candidate_approved`, `pr_core_candidate_rejected`

### REST API (namespace: `pr-core/v1`)

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| GET | /peptides | Public | List peptides with filters |
| GET | /peptides/{id} | Public | Single peptide |
| GET | /peptides/{id}/dosing | Public | Dosing rows for peptide |
| POST | /peptides/{id}/dosing | manage_peptide_content | Add dosing row |
| GET | /peptides/{id}/legal | Public | Legal cells for peptide |
| POST | /peptides/{id}/legal | manage_peptide_content | Add legal cell |

---

## Key Decisions

### #1: Separate plugin for canonical schema
Consumer plugins read through versioned PHP API + hooks, never direct table access. Decouples domain schema from feature lifecycle. See ADR-0002.

### #2: Custom tables for dosing and legal data
Post-meta doesn't scale for high-cardinality data queried by multiple dimensions (peptide + route + population). Custom tables with proper indexes.

### #3: Supersede pattern instead of hard deletes
Corrections create new rows; old rows get `superseded_by_id`. Preserves full audit trail for regulatory accountability.

### #4: AI candidate queue with human-in-the-loop
Automated extraction populates the queue; humans approve/reject. Approved rows copy into dosing table with provenance tracking (`source`, `ai_candidate_id`).

### #5: Disclaimer component owned by core
Single editorial review point. All consumer plugins render the same versioned disclaimer text. Surface-specific copy (dosing, legal, reconstitution, AI answer) stored in one wp_option.

### #6: JSON-LD from day one
Drug schema on single pages increases LLM citation rate. Filter hook allows consumer plugins to extend the schema object.
