# Peptide Repo Core — Domain Glossary (CONTEXT.md)

This file defines domain terms introduced or used in the peptide-repo-core codebase.
Per AGENT-OPERATING-STANDARD v1.2.0, update this file in the same PR as any change
that introduces, renames, or redefines a domain term.

---

## Peptide

A short chain of amino acids studied for biological activity. In this codebase, a
"peptide" refers to one of the 93 canonical monograph posts (`post_type = 'peptide'`)
on peptiderepo.com. Each monograph covers identity, mechanism, evidence, and regulatory
status. **No dosing data appears in schema output** (YMYL constraint).

## Monograph

A single-peptide page (`/peptides/{slug}/`) containing the canonical research profile
for that compound. Populated from the `peptide` CPT plus its associated meta fields,
dosing rows (`pr_dosing_rows` table), and legal cells (`pr_legal_cells` table).

## Drug node

The `schema.org/Drug` structured-data entity emitted on each monograph page. Stable
`@id` is `{permalink}#drug` — this URI is the cross-plugin linking target used by
PRAutoBlogger article `about` references (per `jsonld-contract-v1`, ratified 2026-06-11).

## MedicalWebPage

A `schema.org/MedicalWebPage` subtype of `WebPage`. Applied to peptide monograph pages
via the `wpseo_schema_webpage_type` Yoast filter. Signals to search engines and LLMs
that the page carries medical/scientific content reviewed by an organization.

## FAQPage

A `schema.org/FAQPage` node emitted when `_pr_faq_items` post-meta is populated.
Contains `Question`/`Answer` pairs curated by the CMO content sprint (v0.6.0 ships
the meta key and emitter; population is a separate sprint). Emitted only when items
exist — never an empty node.

## _pr_molecular_formula

Post-meta key (string). Stores the molecular formula of a peptide compound
(e.g., `C62H98N16O22`). Written by migration 0004 from PSA's `psa_molecular_formula`
key. Sanitized: only `[A-Za-z0-9().\[\]{}]` characters allowed.

## _pr_molecular_weight

Post-meta key (string). Stores the molecular weight of a peptide compound as a
float string (e.g., `"1419.5"`). Unit: g/mol (equivalent to Da, but g/mol is the
standard SI label used in schema.org `QuantitativeValue`). Written by migration 0004
from PSA's `psa_molecular_weight` — "Da" suffix stripped, `floatval()` applied.

## _pr_aliases

Post-meta key (string, JSON array). Stores alternate names / synonyms for a peptide
(e.g., `'["Body Protection Compound-157","PL 14736","Bepecin"]'`). Written by
migration 0004 from PSA's comma-separated `psa_aliases`. Each alias sanitized via
`sanitize_text_field`. Maps to `schema.org/alternateName` array on the Drug node.

## _pr_faq_items

Post-meta key (string, JSON array of objects). Stores curated FAQ entries for a
peptide monograph, each as `{question: string, answer: string}`. Registered in v0.6.0;
population is a CMO content sprint. Maps to `schema.org/FAQPage` when non-empty.
Sanitized by `PR_Core_Schema_Sanitizers::sanitize_faq_items()` on save.
Purged on uninstall.

## Schema-input meta

The collective term for the four `_pr_*` meta keys (`_pr_molecular_formula`,
`_pr_molecular_weight`, `_pr_aliases`, `_pr_faq_items`) introduced in v0.6.0. These
are the data-layer inputs to the JSON-LD emitter. The emitter reads only these keys
at render time — it never reads PSA's `psa_*` keys directly (no cross-plugin read
coupling). Sourced from PSA via migration 0004 (one-time backfill).

## psa_* meta

Post-meta keys written by Peptide Search AI (PSA) plugin using its PubChem integration.
Includes `psa_molecular_formula`, `psa_molecular_weight`, `psa_aliases`,
`psa_pubchem_cid`, `psa_cas_number`, `psa_drugbank_id`. Migration 0004 treats all
`psa_*` values as UNTRUSTED and sanitizes them before writing to PR Core's `_pr_*` keys.
PR Core never reads `psa_*` keys at render time.

## PubChem CID

A numeric compound identifier in the NCBI PubChem database (e.g., `9941957` for
BPC-157). Used by migration 0004 as a fallback lookup for the 4 peptide posts that
lack `psa_molecular_formula`. Fetched via `PR_Core_Pubchem_Client`.

## Yoast integration contract

The agreed schema architecture ratified 2026-06-11 (`convo/prcore/decisions/2026-06-11-jsonld-contract-v1.md`):
PR Core integrates into Yoast's schema graph via `wpseo_schema_*` filters; never
emits duplicate `WebPage` or `BreadcrumbList` nodes; maintains a standalone `@graph`
fallback for when Yoast is inactive. The Drug `@id` convention (`{permalink}#drug`)
is part of this contract.

## Evidence strength

Ordered enum: `preclinical`, `case-series`, `observational`, `rct-small`, `rct-large`,
`meta-analysis`. Stored in the `evidence_strength` post-meta key. Does **not** appear
in schema.org output (dosing/evidence YMYL constraint).

## Verdict

One of five editorial states assigned to a monograph: `effective`, `promising`,
`mixed`, `insufficient_evidence`, `avoid`. Stored in post content via a verdict card
Gutenberg block. Not surfaced in JSON-LD (dosing YMYL constraint).

## Migration runner

`PR_Core_Migration_Runner` — runs numbered migrations (0001–N) sequentially on every
`plugins_loaded`. Compares `pr_core_schema_version` wp_option against
`PR_CORE_TARGET_SCHEMA_VERSION` constant. All migrations are idempotent and re-runnable.
