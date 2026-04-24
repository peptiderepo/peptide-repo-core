<?php
/**
 * Unit tests for PR_Core_Peptide_CPT (v0.2.0 CPT consolidation).
 *
 * Run: php tests/unit/test-peptide-cpt.php
 * Exit code 0 = all pass, 1 = any failure.
 *
 * What's covered:
 *   - Constants match the v0.2.0 consolidated contract (peptide / peptide_category).
 *   - TAX_FAMILY constant is removed.
 *   - register_peptide_post_type() guard: no-op when post_type_exists returns true.
 *   - register_peptide_post_type() payload: args match the harmonized contract
 *     (thumbnail in supports, rewrite slug peptides, rest_namespace absent — Gutenberg requires wp/v2).
 *   - register_taxonomies() guard: no-op when taxonomy_exists returns true.
 *   - register_taxonomies() only registers peptide_category; pr_peptide_family is gone.
 *
 * Not covered here (follow-up with proper PHPUnit + wp-env harness):
 *   - Actual rewrite rule map on a live WP install.
 *   - Activation flow end-to-end (migrations + capabilities + flush).
 *   - REST route registration smoke.
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

echo "== PR_Core_Peptide_CPT v0.2.0 unit tests ==\n\n";

/* ── Constants ──────────────────────────────────────────────────────── */

echo "Constants:\n";
pr_assert_equals( 'peptide', PR_Core_Peptide_CPT::POST_TYPE, 'POST_TYPE is "peptide"' );
pr_assert_equals( 'peptide_category', PR_Core_Peptide_CPT::TAX_CATEGORY, 'TAX_CATEGORY is "peptide_category"' );
pr_assert_equals( 'manage_peptide_content', PR_Core_Peptide_CPT::CAPABILITY, 'CAPABILITY unchanged' );

$reflection = new ReflectionClass( PR_Core_Peptide_CPT::class );
pr_assert( ! $reflection->hasConstant( 'TAX_FAMILY' ), 'TAX_FAMILY constant removed' );

/* ── CPT guard: post_type_exists === true => no-op ──────────────────── */

echo "\nCPT registration guard:\n";
pr_core_test_reset();
$GLOBALS['pr_core_test_state']['existing_post_types'] = [ 'peptide' ];
PR_Core_Peptide_CPT::register_peptide_post_type();
pr_assert(
	empty( $GLOBALS['pr_core_test_state']['registered_post_types'] ),
	'register_peptide_post_type() no-ops when post_type_exists("peptide") is true'
);

/* ── CPT registration payload ───────────────────────────────────────── */

echo "\nCPT registration payload:\n";
pr_core_test_reset();
PR_Core_Peptide_CPT::register_peptide_post_type();

pr_assert(
	isset( $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'] ),
	'register_post_type was called with slug "peptide"'
);

$args = $GLOBALS['pr_core_test_state']['registered_post_types']['peptide'] ?? [];
pr_assert_equals( true, $args['public'] ?? null, 'public = true' );
pr_assert_equals( true, $args['publicly_queryable'] ?? null, 'publicly_queryable = true' );
pr_assert_equals( true, $args['show_in_rest'] ?? null, 'show_in_rest = true' );
pr_assert_equals( 'peptides', $args['rest_base'] ?? null, 'rest_base = "peptides"' );
pr_assert( ! array_key_exists( 'rest_namespace', $args ), 'rest_namespace absent — defaults to wp/v2 (Gutenberg requirement)' );
pr_assert_equals( 'post', $args['capability_type'] ?? null, 'capability_type = "post"' );
pr_assert_equals( true, $args['map_meta_cap'] ?? null, 'map_meta_cap = true' );
pr_assert_equals( false, $args['hierarchical'] ?? null, 'hierarchical = false' );
pr_assert_equals( true, $args['has_archive'] ?? null, 'has_archive = true' );
pr_assert_equals( 'peptides', $args['rewrite']['slug'] ?? null, 'rewrite slug = "peptides"' );
pr_assert_equals( false, $args['rewrite']['with_front'] ?? null, 'rewrite with_front = false' );

$supports = $args['supports'] ?? [];
foreach ( [ 'title', 'editor', 'thumbnail', 'excerpt', 'revisions', 'custom-fields' ] as $feature ) {
	pr_assert( in_array( $feature, $supports, true ), "supports includes \"{$feature}\"" );
}

/* ── Taxonomy guard: taxonomy_exists === true => no-op ──────────────── */

echo "\nTaxonomy registration guard:\n";
pr_core_test_reset();
$GLOBALS['pr_core_test_state']['existing_taxonomies'] = [ 'peptide_category' ];
PR_Core_Peptide_CPT::register_taxonomies();
pr_assert(
	empty( $GLOBALS['pr_core_test_state']['registered_taxonomies'] ),
	'register_taxonomies() no-ops when taxonomy_exists("peptide_category") is true'
);

/* ── Taxonomy registration payload ──────────────────────────────────── */

echo "\nTaxonomy registration payload:\n";
pr_core_test_reset();
PR_Core_Peptide_CPT::register_taxonomies();

pr_assert(
	isset( $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_category'] ),
	'peptide_category taxonomy registered'
);
pr_assert(
	! isset( $GLOBALS['pr_core_test_state']['registered_taxonomies']['pr_peptide_family'] ),
	'pr_peptide_family taxonomy NOT registered (removed in v0.2.0)'
);
pr_assert(
	! isset( $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_family'] ),
	'peptide_family taxonomy NOT registered either (the rename-only path would have left this lingering)'
);

$tax = $GLOBALS['pr_core_test_state']['registered_taxonomies']['peptide_category'] ?? [];
pr_assert_equals( 'peptide', $tax['object_type'] ?? null, 'peptide_category attached to peptide CPT' );
pr_assert_equals( true, $tax['args']['hierarchical'] ?? null, 'peptide_category hierarchical = true' );
pr_assert_equals( true, $tax['args']['show_in_rest'] ?? null, 'peptide_category show_in_rest = true' );
pr_assert_equals( 'peptide-category', $tax['args']['rewrite']['slug'] ?? null, 'peptide_category rewrite slug = "peptide-category"' );

/* ── Hook registration ──────────────────────────────────────────────── */

echo "\nHook registration:\n";
pr_core_test_reset();
$cpt = new PR_Core_Peptide_CPT();
$cpt->register_hooks();

$hooks = array_column( $GLOBALS['pr_core_test_state']['added_actions'], 'hook' );
pr_assert( in_array( 'init', $hooks, true ), 'register_hooks() adds action on "init"' );

/* ── Summary ────────────────────────────────────────────────────────── */

exit( pr_test_summary() );
