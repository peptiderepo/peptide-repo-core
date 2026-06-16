<?php
/**
 * Regression tests for v0.6.1: Yoast wpseo_schema_webpage_type array contract.
 *
 * Root cause caught on staging: v0.6.0 retype_to_medical_webpage() was
 * strict-typed string, but Yoast passes ['WebPage'] (array) on every singular
 * page. strict_types=1 → TypeError → HTTP 500 on all monographs + plain Pages.
 * Secondary bug: enrich_webpage_piece compared 'WebPage' === $piece['@type']
 * but @type is an array, so enrichment (lastReviewed/reviewedBy/audience)
 * never injected on any page.
 *
 * These tests FAIL against v0.6.0 and PASS after the v0.6.1 fix.
 *
 * Run: php tests/unit/test-jsonld-webpage.php
 * Exit 0 = all pass, 1 = any failure.
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// ── Additional stubs ─────────────────────────────────────────────────────.

if ( ! defined( 'PR_CORE_TARGET_SCHEMA_VERSION' ) ) {
	define( 'PR_CORE_TARGET_SCHEMA_VERSION', 4 );
}

$GLOBALS['pr_test_postmeta']      = [];
$GLOBALS['pr_test_is_singular']   = false;
$GLOBALS['pr_test_singular_type'] = '';
$GLOBALS['pr_test_the_id']        = 0;

function get_post_meta( $post_id, string $key, bool $single = false ) {
	if ( $single ) {
		return $GLOBALS['pr_test_postmeta'][ (int) $post_id ][ $key ] ?? '';
	}
	return array_values( $GLOBALS['pr_test_postmeta'][ (int) $post_id ] ?? [] );
}

function get_permalink( $post_id = null ): string {
	return 'https://peptiderepo.com/peptides/bpc-157/';
}

function get_the_ID(): int {
	return $GLOBALS['pr_test_the_id'];
}

function is_singular( $type = '' ): bool {
	if ( '' === $type ) {
		return $GLOBALS['pr_test_is_singular'];
	}
	return $GLOBALS['pr_test_is_singular'] && ( $GLOBALS['pr_test_singular_type'] === $type );
}

function get_post_modified_time( $format, $gmt, $post_id ) {
	return '2026-04-25';
}

function get_userdata( int $id ) {
	return false;
}

function get_author_posts_url( int $id ): string {
	return 'https://peptiderepo.com/author/' . $id;
}

function home_url( string $path = '' ): string {
	return 'https://peptiderepo.com' . $path;
}

function apply_filters( string $tag, $value ) {
	return $value;
}

// Load class under test.
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-webpage.php';

echo "== JSON-LD WebPage v0.6.1 regression tests ==\n\n";

$enricher = new PR_Core_Jsonld_Webpage();

// ── retype_to_medical_webpage: array input, non-peptide singular ──────────.

echo "retype_to_medical_webpage — array input:\n";

// Non-peptide singular: Yoast passes ['WebPage']; must return unchanged, no TypeError.
$GLOBALS['pr_test_is_singular']   = true;
$GLOBALS['pr_test_singular_type'] = 'page';  // plain WP Page, not peptide CPT.

$result = $enricher->retype_to_medical_webpage( [ 'WebPage' ] );
pr_assert_equals( [ 'WebPage' ], $result, 'non-peptide singular: array returned unchanged' );

// Peptide singular: Yoast passes ['WebPage']; must return 'MedicalWebPage' (string).
$GLOBALS['pr_test_is_singular']   = true;
$GLOBALS['pr_test_singular_type'] = 'peptide';

$result = $enricher->retype_to_medical_webpage( [ 'WebPage' ] );
pr_assert_equals( 'MedicalWebPage', $result, 'peptide singular: returns MedicalWebPage (string)' );

// String input still works (non-singular special types like CollectionPage).
$GLOBALS['pr_test_is_singular']   = false;
$GLOBALS['pr_test_singular_type'] = '';

$result = $enricher->retype_to_medical_webpage( 'CollectionPage' );
pr_assert_equals( 'CollectionPage', $result, 'non-singular string: passes through unchanged' );

// ── enrich_webpage_piece: @type as array injects enrichments ─────────────.

echo "\nenrich_webpage_piece — @type as array:\n";

$GLOBALS['pr_test_is_singular']   = true;
$GLOBALS['pr_test_singular_type'] = 'peptide';
$GLOBALS['pr_test_the_id']        = 42;
$GLOBALS['pr_test_postmeta'][42]  = [
	'_pr_last_reviewed' => '2026-06-01',
];

// Graph piece with @type as an array (real Yoast shape on singulars).
$graph = [
	[
		'@type' => [ 'WebPage' ],
		'@id'   => 'https://peptiderepo.com/peptides/bpc-157/#webpage',
		'name'  => 'BPC-157',
	],
];

$result_graph = $enricher->enrich_webpage_piece( $graph, new stdClass() );
$piece        = $result_graph[0] ?? [];

pr_assert( isset( $piece['lastReviewed'] ), '@type=array: lastReviewed injected' );
pr_assert_equals( '2026-06-01', $piece['lastReviewed'], '@type=array: lastReviewed value correct' );
pr_assert( isset( $piece['reviewedBy'] ), '@type=array: reviewedBy injected' );
pr_assert( isset( $piece['audience'] ), '@type=array: audience injected' );

// Also works when @type is 'MedicalWebPage' (string — after our retype fires first).
$graph_retyed = [
	[
		'@type' => 'MedicalWebPage',
		'@id'   => 'https://peptiderepo.com/peptides/bpc-157/#webpage',
		'name'  => 'BPC-157',
	],
];

$result_retyped = $enricher->enrich_webpage_piece( $graph_retyed, new stdClass() );
pr_assert( isset( $result_retyped[0]['lastReviewed'] ), 'MedicalWebPage string @type: enrichment injects' );

// Non-peptide page: enrichment must NOT fire even with array @type.
$GLOBALS['pr_test_is_singular']   = true;
$GLOBALS['pr_test_singular_type'] = 'page';

$graph_non_peptide = [
	[
		'@type' => [ 'WebPage' ],
		'@id'   => 'https://peptiderepo.com/about/#webpage',
	],
];

$result_non_peptide = $enricher->enrich_webpage_piece( $graph_non_peptide, new stdClass() );
pr_assert( ! isset( $result_non_peptide[0]['lastReviewed'] ), 'non-peptide: lastReviewed NOT injected' );

// ── Summary ──────────────────────────────────────────────────────────────.

exit( pr_test_summary() );
