# Peptide Repo Core — Conventions

## Naming

- **Class prefix:** `PR_Core_` — all classes use this prefix.
- **Hook prefix:** `pr_core_` — all actions and filters start with this.
- **Option prefix:** `pr_core_` — all wp_options keys start with this.
- **Table prefix:** `pr_` — custom tables use `{wpdb->prefix}pr_*`.
- **CSS prefix:** `.pr-core-` — admin and frontend styles.
- **Text domain:** `peptide-repo-core`.

## File Naming

Autoloader convention: `PR_Core_Foo_Bar` maps to `class-pr-core-foo-bar.php`.
Files live in `includes/` or one level of subdirectory under `includes/`.

## How To: Add a New Post-Meta Field to Peptide Posts

1. Add the field to `PR_Core_Peptide_CPT::get_meta_fields()` in `cpt/class-pr-core-peptide-cpt.php`.
2. Add the field to `PR_Core_Peptide_DTO` constructor and `to_array()` in `dto/class-pr-core-peptide-dto.php`.
3. Add the field to `PR_Core_Peptide_Repository::post_to_dto()` in `repositories/class-pr-core-peptide-repository.php`.
4. Add a form input in `PR_Core_Peptide_Metaboxes::render_identifiers_box()` in `admin/class-pr-core-peptide-metaboxes.php`.
5. The `save_meta()` method reads from the same field list — no changes needed unless custom save logic applies.
6. **Schema-input meta fields** (`_pr_*` keys introduced in v0.6.0) use `PR_Core_Schema_Sanitizers` as the sanitize_callback. Add new sanitizers to `cpt/class-pr-core-schema-sanitizers.php`, not to `PR_Core_Peptide_CPT` (keeps CPT under 300 lines).
7. If the field is purged on uninstall, add it to the `$schema_meta_keys` array in `uninstall.php`.

## How To: Add a New Post-Meta Field to Repo Daily Posts

1. Define the meta key (start with `_repo_daily_`) and sanitization logic (use `sanitize_text_field()` for strings, cast to bool for checkboxes).
2. Create a new metabox or extend `PR_Core_Repo_Daily_Metaboxes::render_meta_box()` in `admin/class-pr-core-repo-daily-metaboxes.php`.
3. In `PR_Core_Repo_Daily_Metaboxes::save_meta()`, handle the field: read from `$_POST`, sanitize, and call `update_post_meta()`.
4. Example: The `_repo_daily_clinical_review_required` checkbox checks for `isset( $_POST['key'] )` and stores `'1'` or `''`; `get_post_meta()` is type-safe because the value is always one of two strings, never a bool.

## How To: Add a New Migration

1. Create `includes/migrations/class-pr-core-migration-NNNN-description.php`.
2. The class must be named `PR_Core_Migration_NNNN_Description` with a public `up()` method.
3. Add the class name to `PR_Core_Migration_Runner::MIGRATIONS` array (index = version - 1).
4. Bump `PR_CORE_TARGET_SCHEMA_VERSION` in `peptide-repo-core.php`.
5. All migrations must be idempotent (safe to run twice).
6. **Idempotency caveat (all-or-nothing skip):** migration 0004's skip guard requires *all* populated keys; a post with legitimately empty aliases will re-run on manual re-invoke — this is safe (write_meta only overwrites with non-empty values) but expected.

## How To: Add a New REST Endpoint

1. Add a `register_rest_route()` call in `PR_Core_Rest_Controller::register_routes()`.
2. Implement the callback method in the same class.
3. Use `check_write_permission()` for any write endpoints.
4. Return typed DTOs via `->to_array()` for consistent response shape.

## How To: Add a New Disclaimer Surface

1. Add a default text entry in `PR_Core_Disclaimer::DEFAULTS`.
2. Use `[pr_disclaimer surface="new-surface"]` in templates.
3. Custom copy can be set via `PR_Core_Disclaimer::update_disclaimer()`.

## How To: Extend the JSON-LD Drug Schema

v0.6.0 uses four focused classes (see ARCHITECTURE.md §#6). To add a new field to the Drug node:

1. Add the logic to `PR_Core_Jsonld_Drug::build()` in `includes/frontend/class-pr-core-jsonld-drug.php`.
2. Read data from the appropriate `_pr_*` post-meta key (not from PSA `psa_*` keys directly).
3. **Always omit the field when the meta is empty** — never emit blank/null/zero values.
4. To add a new schema piece (not Drug), create a new `PR_Core_Jsonld_<Piece>` class in `frontend/` and inject it from `PR_Core_Jsonld::inject_graph_pieces()`.
5. The `pr_core_jsonld_peptide` filter on the Drug node array is preserved for consumer plugins.


## How To: Add Fields to the PRAB Article JSON-LD (contract v2+)

The `_prab_*` meta contract is versioned (`_prab_schema_version`). The contract is frozen at v1
until prcore and PRAB coordinate a version bump. To add new fields:

1. Propose the new field in `convo/prcore/decisions/` (CTO ratifies; PRAB and prcore must agree).
2. Bump `_prab_schema_version` to 2 in PRAB's meta write code — do NOT bump before prcore ships support.
3. Add the new field to `PR_Core_Prab_Meta_Reader` (sanitize at read time, degrade gracefully).
4. Add emission logic to `PR_Core_Jsonld_Article` — emit only when field is non-empty.
5. Add unit tests for the new field in `tests/unit/JsonldArticleTest.php`.
6. Bump `SUPPORTED_VERSION` in `PR_Core_Prab_Meta_Reader::SUPPORTED_VERSION` to 2.
7. Update the contract decision doc and CHANGELOG.

Key rules:
- prcore **reads** `_prab_*` meta only. PRAB **writes** only. The namespace boundary is hard.
- `uninstall.php` must NOT touch `_prab_*` keys — they belong to PRAB's namespace.
- `quality_score` and similar audit fields must never be emitted (no schema.org mapping).
- The `reviewedBy` Person guard is non-negotiable: Person only with human mode + valid user ID.

## Error Handling

- Repository methods return `null` or `0` on failure, never throw.
- REST endpoints return `WP_Error` with appropriate HTTP status codes.
- All database queries use `$wpdb->prepare()` for parameterization.
- Admin actions verify nonces and capabilities before processing.

## Evidence Strength Enum

Ordered weakest to strongest: `preclinical`, `case-series`, `observational`, `rct-small`, `rct-large`, `meta-analysis`. Validated by `PR_Core_Peptide_CPT::sanitize_evidence_strength()`.

## Legal Status Enum

Values: `prescription`, `ruo`, `otc`, `restricted`, `banned`, `unclear`. Validated by `PR_Core_Legal_Repository::sanitize_row()`.

## Supersede Pattern (Soft Delete)

Dosing rows and legal cells use a `superseded_by_id` column instead of hard deletes. When a correction is needed, the old row gets `superseded_by_id` set to the new row's ID. Queries for "active" data filter on `superseded_by_id IS NULL`. This preserves full audit history.

## CPT Ownership

- **One plugin owns each CPT.** PR Core owns the `peptide` CPT and the `peptide_category` taxonomy (as of v0.2.0). No other plugin in this ecosystem registers these post types or taxonomies directly.
- **All registrations must guard with existence checks.** CPT registrations call `if ( post_type_exists( $slug ) ) return;` before `register_post_type()`, and taxonomy registrations call `if ( taxonomy_exists( $slug ) ) return;` before `register_taxonomy()`. This makes deploy order between plugins irrelevant — whichever runs first wins, the other no-ops — and prevents rewrite-slug collisions when two plugins temporarily overlap during a transition.
- **Consumers read, do not register.** Plugins that need to query or display `peptide` posts (PSA, PR Theme, PRAutoBlogger, Peptide News, Tier 1 tools) call `get_posts( [ 'post_type' => 'peptide' ] )` / `WP_Query` without registering the CPT themselves. If a consumer plugin needs its own custom post type for a different domain (e.g., `reconstitution_protocol`), it registers its own slug and does not touch `peptide`.
- **Meta keys are plugin-namespaced, not CPT-namespaced.** PR Core writes `_pr_core_*` or bare keys registered via `register_post_meta()`. PSA writes `psa_*`. The two namespaces coexist on the same `peptide` post because they don't collide.

## `_pr_core_authored` Flag

Any `peptide` post created via PR Core's own UI (not authored via PSA's meta boxes) must be tagged with post-meta key `_pr_core_authored` = `1`. This is the flag `uninstall.php` uses to scope teardown to plugin-owned posts only. Posts originating from PSA (the 89 canonical peptide monographs on production) do not carry this flag and are never deleted on PR Core uninstall.
