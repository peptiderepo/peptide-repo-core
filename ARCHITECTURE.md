# Peptide Repo Core — Architecture

Peptide Repo Core is a WordPress plugin that provides the canonical peptide data layer for the peptiderepo.com ecosystem. It owns the `peptide` custom post type (consolidated in v0.2.0; previously registered by Peptide Search AI pre-schema-sprint — PSA v4.5.0 drops its registration in favor of this one), the `peptide_category` taxonomy, custom tables for dosing rows and legal status cells, typed repository classes, an AI candidate queue for semi-automated data extraction, a shared disclaimer component, and JSON-LD structured data output. Every consumer plugin (PR Theme, PRAutoBlogger, Peptide News, Peptide Search AI, and all Tier 1 tools) reads peptide data through this plugin's versioned PHP API, never directly.

**Ownership model (v0.2.0+).** PR Core is the sole registrant of the `peptide` CPT and `peptide_category` taxonomy. Registration is guarded by `post_type_exists()` / `taxonomy_exists()` so a second registration from any other plugin (historically PSA, which now defers) no-ops cleanly and deploy order between plugins does not matter. See CONVENTIONS.md for the CPT ownership rule.

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
                         │  Peptide Repository   │  peptide CPT + meta
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
├── uninstall.php                      # Selective teardown: plugin-owned posts + tables + options + caps
├── ARCHITECTURE.md                    # This file
├── CONVENTIONS.md                     # Naming patterns, extension guides
├── CHANGELOG.md                       # Semantic versioning changelog
├── composer.json                      # Dev dependencies (PHPCS, WPCS) and lint scripts
├── phpcs.xml.dist                     # PHPCS ruleset configuration
│
├── .github/
│   └── workflows/
│       └── ci.yml                     # PHP lint (8.1-8.3), PHPCS WordPress, 300-line check, unit tests
│
├── assets/
│   └── css/
│       └── admin.css                  # Minimal admin meta box styles
│
├── includes/
│   ├── class-pr-core.php              # Main orchestrator — boots subsystems, registers public filters
│   ├── class-pr-core-activator.php    # Activation + version-change flush handler
│   ├── class-pr-core-deactivator.php  # Deactivation: rewrite flush only (data preserved)
│   ├── class-pr-core-autoloader.php   # SPL autoloader for PR_Core_ prefixed classes
│   ├── class-pr-core-seed-data.php    # Dev fixture: 3 peptides, 10 dosing rows, 5 legal cells
│   │
│   ├── cpt/
│   │   ├── class-pr-core-peptide-cpt.php       # CPT + peptide_category registration (guarded), meta fields, sanitizers
│   │   └── class-pr-core-schema-sanitizers.php # Sanitizers for v0.6.0 schema-input meta (formula, weight, aliases, FAQ)
│   │   └── class-pr-core-repo-daily-cpt.php    # Repo Daily CPT registration for editorial content
│   │
│   ├── taxonomies/
│   │   └── class-pr-core-repo-daily-taxonomy.php  # repo_daily_category taxonomy + term seeding
│   │
│   ├── migrations/
│   │   ├── class-pr-core-migration-runner.php           # Sequential migration engine
│   │   ├── class-pr-core-migration-0001-dosing-rows.php # pr_dosing_rows table
│   │   ├── class-pr-core-migration-0002-legal-cells.php # pr_legal_cells table + unique constraint
│   │   ├── class-pr-core-migration-0003-candidate-queue.php # pr_ai_candidate_queue table
│   │   ├── class-pr-core-migration-0004-backfill-peptide-meta.php # Backfill _pr_* schema meta from PSA psa_* keys
│   │   └── class-pr-core-pubchem-client.php              # PubChem REST client (used by migration 0004)
│   │
│   ├── dto/
│   │   ├── class-pr-core-peptide-dto.php     # Typed peptide value object
│   │   ├── class-pr-core-dosing-row-dto.php  # Typed dosing row value object
│   │   ├── class-pr-core-legal-cell-dto.php  # Typed legal cell value object
│   │   └── class-pr-core-candidate-dto.php   # Typed candidate queue value object
│   │
│   ├── repositories/
│   │   ├── class-pr-core-peptide-repository.php          # CRUD for peptide CPT
│   │   ├── class-pr-core-dosing-repository.php           # CRUD for pr_dosing_rows
│   │   ├── class-pr-core-legal-repository.php            # CRUD for pr_legal_cells
│   │   └── class-pr-core-candidate-queue-repository.php  # CRUD + approve/reject for queue
│   │
│   ├── admin/
│   │   ├── class-pr-core-admin.php                    # Admin initialization, menu, styles
│   │   ├── class-pr-core-peptide-metaboxes.php        # Identifiers, dosing, legal meta boxes
│   │   ├── class-pr-core-repo-daily-metaboxes.php     # Author byline and clinical review flag meta boxes
│   │   ├── class-pr-core-admin-columns.php            # Evidence, editorial, dosing count columns
│   │   └── class-pr-core-candidate-queue-page.php     # AI candidate review admin screen
│   │
│   ├── frontend/
│   │   ├── class-pr-core-disclaimer.php       # Shortcode + static API for surface disclaimers
│   │   ├── class-pr-core-jsonld.php           # Orchestrator: Yoast-integrated Drug/FAQ/MedicalWebPage emission
│   │   ├── class-pr-core-jsonld-drug.php      # Drug (+ MolecularEntity) schema piece builder
│   │   ├── class-pr-core-jsonld-webpage.php   # MedicalWebPage retype + lastReviewed/reviewedBy enrichment
│   │   └── class-pr-core-jsonld-faq.php       # FAQPage schema piece (emits only when _pr_faq_items populated)
│   │
│   └── api/
│       └── class-pr-core-rest-controller.php  # REST endpoints for peptides, dosing, legal
│
└── tests/
    ├── bootstrap.php                  # Lightweight WP function mocks for no-PHPUnit unit runs
    └── unit/
        └── test-peptide-cpt.php       # CPT + taxonomy guard + args payload assertions
```

---

## Content Types

| CPT | Slug | Taxonomy | Purpose | Registrant |
|-----|------|----------|---------|-----------|
| `peptide` | `/peptides/[slug]/` | `peptide_category` | Canonical peptide monographs with dosing, legal, evidence metadata | PR Core v0.2.0 (consolidated from PSA) |
| `repo_daily` | `/daily/[slug]/` | `repo_daily_category` | Editorial publications (articles, guides, comparisons, news) generated by PRAutoBlogger, published under Boo Sheeran byline | PR Core v0.5.0 |

**Verification scanner scope:** The verification scanner (class-pr-core-verification-scanner.php) queries `post_type=peptide` only. `repo_daily` posts are excluded automatically by CPT separation and do not appear in the overdue queue.

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

### #6: JSON-LD from day one (v0.6.0: Yoast-integrated)
Drug schema on single pages increases LLM citation rate. Filter hook allows consumer plugins to extend the schema object.

**v0.6.0 Yoast integration (ratified jsonld-contract-v1 2026-06-11):**

- **Integrated path (Yoast active):** PR Core hooks into `wpseo_schema_graph_pieces` (priority 11) to inject Drug and FAQPage pieces. Yoast owns `WebPage`/`BreadcrumbList` — we never emit those. `wpseo_schema_webpage_type` filter retypes the page node to `MedicalWebPage`. `wpseo_schema_graph` (priority 11) enriches the existing WebPage piece with `lastReviewed`, `reviewedBy`, and `audience`.
- **Standalone fallback (no Yoast):** `wp_head` action (priority 99) emits a complete `@graph` (Drug + FAQPage) only. Suppressed automatically when `function_exists('YoastSEO')` is true.
- **Drug `@id`:** `{permalink}#drug` — stable for cross-plugin `about` linkage from PRAutoBlogger articles.
- **Molecular data source:** `_pr_molecular_formula`, `_pr_molecular_weight`, `_pr_aliases` (written by migration 0004, sourced from PSA `psa_*` keys or PubChem REST API).
- **FAQ emission:** `_pr_faq_items` post-meta (JSON array of `{question, answer}` objects). Node emitted only when items exist.
- **Dosing omitted from schema:** YMYL constraint — no dosing data in JSON-LD.

JSON-LD class tree (v0.8.0):

| Class | File | Responsibility |
|---|---|---|
| `PR_Core_Jsonld` | `frontend/class-pr-core-jsonld.php` | Orchestrator: registers hooks, Yoast vs. standalone routing |
| `PR_Core_Jsonld_Drug` | `frontend/class-pr-core-jsonld-drug.php` | Builds Drug (+ MolecularEntity) schema node |
| `PR_Core_Jsonld_Webpage` | `frontend/class-pr-core-jsonld-webpage.php` | MedicalWebPage retype + lastReviewed/reviewedBy enrichment (peptide pages) |
| `PR_Core_Jsonld_Faq` | `frontend/class-pr-core-jsonld-faq.php` | FAQPage node (emit-only-when-populated) |
| `PR_Core_Jsonld_Article` | `frontend/class-pr-core-jsonld-article.php` | PRAB article emitter: MedicalWebPage retype + Article citation/about/reviewedBy enrichment |
| `PR_Core_Prab_Meta_Reader` | `frontend/class-pr-core-prab-meta-reader.php` | Reads and sanitises _prab_* meta (contract v1 reader) |

**PRAB article emission (v0.8.0, contract v1):**
- Trigger: `_prab_schema_version=1` on a standard `post`.
- Yoast path (priority 13, after peptide enrichment at 11+12): retypes page node to `MedicalWebPage`; enriches Yoast's existing Article piece with `citation[]`, `about[]` (Drug @id references), `lastReviewed`, honest `reviewedBy`.
- No-Yoast fallback: standalone `@graph` (Article + MedicalWebPage) on `wp_head` priority 99.
- **Honest reviewedBy:** Person only when `_prab_review_mode=human` + valid `_prab_reviewed_by` WP user ID. All other cases: Organization "Peptide Repo". Never a fabricated person.
- **`about` linkage:** Drug stubs reference `{peptide_permalink}#drug` (stable @id from v0.6.0).
- prcore reads `_prab_*` meta only; PRAutoBlogger writes only. uninstall.php unaffected.
- All `_prab_*` meta treated as untrusted at read time.

### #7: PR Core owns the `peptide` CPT and `peptide_category` taxonomy (v0.2.0)
Prior to v0.2.0, PR Core registered `pr_peptide` while Peptide Search AI registered `peptide` — both claimed the public rewrite slug `peptides`, and WP's rewrite resolver picked PR Core's empty CPT, 404'ing all 89 production peptide detail pages. v0.2.0 consolidates both registrations onto a single `peptide` CPT, owned by PR Core. PSA v4.5.0 drops its CPT/taxonomy registration; its meta boxes (`psa_peptide_data`, `psa_extended_data`), directory shortcode, KB article renderer, and search widget continue operating on the shared `peptide` CPT regardless of who registers it. Registration on both sides is guarded with `post_type_exists()` / `taxonomy_exists()` so deploy order is forgiving.

## §2.9 Uninstall specification

PR Core `uninstall.php` removes plugin-owned data only:

1. **Drops custom tables** — `pr_dosing_rows`, `pr_legal_cells`, `pr_ai_candidate_queue`.
2. **Deletes `peptide` posts only if they carry the `_pr_core_authored` meta flag.** The 89 canonical peptide posts predate PR Core and were authored via PSA; they are never blanket-deleted on PR Core uninstall.
3. **Does not delete `peptide_category` terms.** Shared-ownership taxonomy; term metadata the site relies on outlasts this plugin.
4. **Deletes `pr_core_*` options.**
5. **Removes `manage_peptide_content` capability from all roles.**
6. **Deletes v0.6.0 schema-input meta keys** (`_pr_molecular_formula`, `_pr_molecular_weight`, `_pr_aliases`, `_pr_faq_items`) from all peptide posts. These are PR Core-authored values (sourced from PubChem / PSA) and are safe to remove on uninstall. They do not overlap with PSA's `psa_*` namespace — PSA's own meta is unaffected.
