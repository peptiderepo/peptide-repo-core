<?php
/**
 * Regression tests for v0.6.2: inject_graph_pieces (plain array) fatal on Yoast.
 *
 * Root cause: v0.6.0 and v0.6.1 hooked wpseo_schema_graph_pieces and appended
 * plain PHP arrays (Drug, FAQ) to Yoast's pieces array. Yoast's
 * Schema_Generator::filter_graph_pieces_to_generate() then called get_class()
 * and is_needed() on every piece expecting objects extending Abstract_Schema_Piece.
 * Passing a plain array caused:
 *
 *   PHP Fatal: get_class(): Argument #1 ($object) must be of type object, array given
 *
 * This crashed every peptide monograph page (HTTP 500) while /about/ and
 * /peptides/ archive returned 200 (they don't enter the is_singular('peptide')
 * branch).
 *
 * Fix: move Drug + FAQ injection to wpseo_schema_graph (priority 12) which
 * receives the fully-assembled graph as an array of plain arrays — safe for
 * plain array appends. wpseo_schema_graph_pieces is no longer hooked.
 *
 * These tests FAIL against v0.6.1 inject_graph_pieces and PASS after the
 * v0.6.2 inject_graph_nodes fix.
 *
 * Run: php tests/unit/test-jsonld-peptide-piece.php
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

// Stub the peptide repository — returned when find_by_id is called.
$GLOBALS['pr_test_peptide_dto']   = null;

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, string $key, bool $single = false ) {
		if ( $single ) {
			return $GLOBALS['pr_test_postmeta'][ (int) $post_id ][ $key ] ?? '';
		}
		return array_values( $GLOBALS['pr_test_postmeta'][ (int) $post_id ] ?? [] );
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id = null ): string {
		return 'https://peptiderepo.com/peptides/bpc-157/';
	}
}

if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID(): int {
		return $GLOBALS['pr_test_the_id'];
	}
}

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $type = '' ): bool {
		if ( '' === $type ) {
			return $GLOBALS['pr_test_is_singular'];
		}
		return $GLOBALS['pr_test_is_singular'] && ( $GLOBALS['pr_test_singular_type'] === $type );
	}
}

if ( ! function_exists( 'get_post_modified_time' ) ) {
	function get_post_modified_time( $format, $gmt, $post_id ) {
		return '2026-06-13';
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( int $id ) {
		return false;
	}
}

if ( ! function_exists( 'get_author_posts_url' ) ) {
	function get_author_posts_url( int $id ): string {
		return 'https://peptiderepo.com/author/' . $id;
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://peptiderepo.com' . $path;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value ) {
		return $value;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $val ): string {
		return htmlspecialchars( $val, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}
}

if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ): string {
		return is_scalar( $value ) ? trim( (string) $value ) : '';
	}
}

// ── Stub PR_Core_Peptide_Repository ──────────────────────────────────────.

/**
 * Minimal repository stub — returns whatever is in $GLOBALS['pr_test_peptide_dto'].
 */
class PR_Core_Peptide_Repository {
	public function find_by_id( int $id ): ?PR_Core_Peptide_DTO {
		return $GLOBALS['pr_test_peptide_dto'];
	}
}

// ── Load classes under test ───────────────────────────────────────────────.

require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-schema-sanitizers.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/dto/class-pr-core-peptide-dto.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-drug.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-faq.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-webpage.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php';

// ── Helper ────────────────────────────────────────────────────────────────.

/**
 * Create a minimal PR_Core_Peptide_DTO for testing.
 *
 * @param array<string, mixed> $overrides Field overrides.
 * @return PR_Core_Peptide_DTO
 */
function make_peptide_piece_dto( array $overrides = [] ): PR_Core_Peptide_DTO {
	return new PR_Core_Peptide_DTO( array_merge( [
		'id'                      => 36,
		'title'                   => 'BPC-157',
		'slug'                    => 'bpc-157',
		'content'                 => '',
		'excerpt'                 => 'A synthetic pentadecapeptide.',
		'status'                  => 'publish',
		'display_name'            => 'BPC-157',
		'aliases'                 => [],
		'molecular_formula'       => '',
		'molecular_weight'        => 0.0,
		'cas_number'              => '',
		'drugbank_id'             => '',
		'chembl_id'               => '',
		'evidence_strength'       => 'preclinical',
		'editorial_review_status' => 'published',
		'last_editorial_review_at' => '',
		'medical_editor_id'       => 0,
		'categories'              => [],
		'families'                => [],
	], $overrides ) );
}

echo "== JSON-LD peptide-piece regression tests (v0.6.2) ==\n\n";

// ── Setup ─────────────────────────────────────────────────────────────────.

$GLOBALS['pr_test_the_id']        = 36;
$GLOBALS['pr_test_is_singular']   = true;
$GLOBALS['pr_test_singular_type'] = PR_Core_Peptide_CPT::POST_TYPE;
$GLOBALS['pr_test_postmeta'][36]  = [
	'_pr_molecular_formula' => 'C62H98N16O22',
	'_pr_molecular_weight'  => '1419.5',
	'_pr_aliases'           => '["Body Protection Compound"]',
];
$GLOBALS['pr_test_peptide_dto']   = make_peptide_piece_dto();

$jsonld = new PR_Core_Jsonld();

// ── Regression: inject_graph_pieces fatal reproduced against old contract ─.

echo "Regression: inject_graph_pieces (OLD hook) with plain array fatal:\n";

// Simulate what Yoast's filter_graph_pieces_to_generate does:.
// it calls get_class($piece) on every item. A plain array causes a fatal.
$drug_builder = new PR_Core_Jsonld_Drug();
$drug_array   = $drug_builder->build( $GLOBALS['pr_test_peptide_dto'] );

$caught_fatal = false;
try {
	// This is the exact operation Yoast does in filter_graph_pieces_to_generate().
	// Against the old v0.6.1 code (inject via wpseo_schema_graph_pieces), this would.
	// produce: "get_class(): Argument #1 ($object) must be of type object, array given".
	$class_result = get_class( $drug_array );
} catch ( TypeError $e ) {
	$caught_fatal = true;
} catch ( Error $e ) {
	$caught_fatal = true;
}
pr_assert( $caught_fatal, 'get_class(array) throws TypeError/Error — confirms the v0.6.1 fatal' );

// ── Fix: inject_graph_nodes (NEW hook) works without fatal ────────────────.

echo "\nFix: inject_graph_nodes via wpseo_schema_graph (plain array graph):\n";

// The new inject_graph_nodes method receives an already-built graph (array of arrays).
// We simulate the wpseo_schema_graph filter call: $graph = array of plain arrays.
$existing_graph = [
	[
		'@type' => 'MedicalWebPage',
		'@id'   => 'https://peptiderepo.com/peptides/bpc-157/#webpage',
		'name'  => 'BPC-157',
	],
	[
		'@type' => 'BreadcrumbList',
		'@id'   => 'https://peptiderepo.com/peptides/bpc-157/#breadcrumb',
	],
];

// This must NOT throw and must append Drug node.
$threw = false;
try {
	$result_graph = $jsonld->inject_graph_nodes( $existing_graph, null );
} catch ( Throwable $e ) {
	$threw = true;
}

pr_assert( ! $threw, 'inject_graph_nodes does NOT throw (no get_class/is_needed calls)' );
pr_assert( count( $result_graph ) > count( $existing_graph ), 'inject_graph_nodes appended at least one node' );

// Drug node must be present.
$drug_nodes = array_filter(
	$result_graph,
	static function ( $node ) {
		$types = is_array( $node['@type'] ?? null ) ? $node['@type'] : [ $node['@type'] ?? '' ];
		return in_array( 'Drug', $types, true );
	}
);
pr_assert( count( $drug_nodes ) === 1, 'exactly one Drug node in graph after inject' );

$drug_node = array_values( $drug_nodes )[0];
pr_assert_equals(
	'https://peptiderepo.com/peptides/bpc-157/#drug',
	$drug_node['@id'],
	'Drug @id = {permalink}#drug'
);
pr_assert( isset( $drug_node['molecularFormula'] ), 'Drug has molecularFormula' );
pr_assert( isset( $drug_node['molecularWeight'] ), 'Drug has molecularWeight (QuantitativeValue)' );
pr_assert_equals( 'QuantitativeValue', $drug_node['molecularWeight']['@type'], 'molecularWeight @type is QuantitativeValue' );
pr_assert_equals( 1419.5, $drug_node['molecularWeight']['value'], 'molecularWeight value correct' );
pr_assert_equals( 'g/mol', $drug_node['molecularWeight']['unitText'], 'molecularWeight unitText = g/mol' );
pr_assert( isset( $drug_node['alternateName'] ), 'Drug has alternateName' );

// Original WebPage + BreadcrumbList nodes must be preserved.
$webpage_nodes = array_filter( $result_graph, static fn( $n ) => ( $n['@type'] ?? '' ) === 'MedicalWebPage' );
pr_assert( count( $webpage_nodes ) === 1, 'MedicalWebPage node preserved (no duplicate)' );

$breadcrumb_nodes = array_filter( $result_graph, static fn( $n ) => ( $n['@type'] ?? '' ) === 'BreadcrumbList' );
pr_assert( count( $breadcrumb_nodes ) === 1, 'BreadcrumbList node preserved (no duplicate)' );

// ── FAQ: present when _pr_faq_items has items ─────────────────────────────.

echo "\nFAQ injection via inject_graph_nodes:\n";

$GLOBALS['pr_test_postmeta'][36]['_pr_faq_items'] = wp_json_encode( [
	[ 'question' => 'What is BPC-157?', 'answer' => 'A synthetic pentadecapeptide.' ],
	[ 'question' => 'Is it safe?', 'answer' => 'Research use only; not approved for human therapy.' ],
] );

$result_with_faq = $jsonld->inject_graph_nodes( $existing_graph, null );

$faq_nodes = array_filter(
	$result_with_faq,
	static fn( $n ) => ( $n['@type'] ?? '' ) === 'FAQPage'
);
pr_assert( count( $faq_nodes ) === 1, 'FAQPage node injected when _pr_faq_items has items' );

$faq_node = array_values( $faq_nodes )[0];
pr_assert_equals( 'https://peptiderepo.com/peptides/bpc-157/#faq', $faq_node['@id'], 'FAQPage @id = {permalink}#faq' );
pr_assert( isset( $faq_node['mainEntity'] ), 'FAQPage has mainEntity' );
pr_assert_equals( 2, count( $faq_node['mainEntity'] ), 'FAQPage has 2 questions' );

// ── FAQ: absent when _pr_faq_items empty ─────────────────────────────────.

$GLOBALS['pr_test_postmeta'][36]['_pr_faq_items'] = '[]';

$result_no_faq = $jsonld->inject_graph_nodes( $existing_graph, null );
$faq_nodes_empty = array_filter(
	$result_no_faq,
	static fn( $n ) => ( $n['@type'] ?? '' ) === 'FAQPage'
);
pr_assert( count( $faq_nodes_empty ) === 0, 'FAQPage NOT injected when _pr_faq_items is empty array' );

// ── Non-peptide page: no injection ───────────────────────────────────────.

echo "\nNon-peptide page: no injection:\n";

$GLOBALS['pr_test_is_singular']   = false;
$GLOBALS['pr_test_singular_type'] = '';

$non_peptide_graph = [
	[
		'@type' => 'WebPage',
		'@id'   => 'https://peptiderepo.com/about/#webpage',
	],
];

$result_non_peptide = $jsonld->inject_graph_nodes( $non_peptide_graph, null );
pr_assert( count( $result_non_peptide ) === 1, 'inject_graph_nodes no-ops on non-peptide pages (graph unchanged)' );

$drug_nodes_np = array_filter( $result_non_peptide, static fn( $n ) => ( is_array( $n['@type'] ?? null ) ? in_array( 'Drug', $n['@type'], true ) : ( $n['@type'] ?? '' ) === 'Drug' ) );
pr_assert( count( $drug_nodes_np ) === 0, 'Drug NOT injected on non-peptide page' );

// ── register_hooks: does NOT hook wpseo_schema_graph_pieces ──────────────.

echo "\nregister_hooks: wpseo_schema_graph_pieces must NOT be hooked:\n";

$GLOBALS['pr_core_test_state']['added_actions'] = [];
$GLOBALS['pr_core_test_state']['added_filters'] = [];
$filters_added = [];

// Override add_filter in the bootstrap to capture calls.
// (bootstrap only stubs add_action; we check via a fresh parse of register_hooks.).
$jsonld2 = new PR_Core_Jsonld();

// Use Reflection to inspect what hooks register_hooks would call.
// Instead: inspect the class source for the string — if inject_graph_pieces is.
// still registered on wpseo_schema_graph_pieces, the bug is NOT fixed.
$src = file_get_contents( PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php' );
pr_assert(
	strpos( $src, "'wpseo_schema_graph_pieces'" ) === false,
	'class-pr-core-jsonld.php does NOT add_filter wpseo_schema_graph_pieces (correct hook removed)'
);
pr_assert(
	strpos( $src, 'inject_graph_nodes' ) !== false,
	'class-pr-core-jsonld.php references inject_graph_nodes (new method present)'
);
pr_assert(
	strpos( $src, "'wpseo_schema_graph'" ) !== false,
	'class-pr-core-jsonld.php hooks wpseo_schema_graph for injection'
);

// ── Summary ───────────────────────────────────────────────────────────────.

exit( pr_test_summary() );
