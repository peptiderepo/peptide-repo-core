<?php
/**
 * Unit tests for v0.6.0 JSON-LD emission: Drug @id, MedicalWebPage type,
 * FAQ conditional emission, no duplicate nodes.
 *
 * Run: php tests/unit/test-jsonld.php
 * Exit 0 = all pass, 1 = any failure.
 *
 * Coverage:
 *   - Drug node: @id = {permalink}#drug, @type includes 'Drug'.
 *   - Drug node: molecularFormula present when _pr_molecular_formula set.
 *   - Drug node: molecularWeight present and uses g/mol when _pr_molecular_weight set.
 *   - Drug node: alternateName present when _pr_aliases has items.
 *   - Drug node: molecularFormula OMITTED when meta empty.
 *   - Drug node: molecularWeight OMITTED when meta empty.
 *   - Drug node: alternateName OMITTED when _pr_aliases is empty array.
 *   - FAQPage: present when _pr_faq_items has items.
 *   - FAQPage: absent when _pr_faq_items is empty or '[]'.
 *   - FAQPage: @id = {permalink}#faq.
 *   - MedicalWebPage retype: retype_to_medical_webpage() returns 'MedicalWebPage' on peptide singles.
 *   - MedicalWebPage retype: passes through unchanged on non-peptide pages.
 *   - No duplicate WebPage or BreadcrumbList nodes emitted by our code.
 *   - PR_Core_Schema_Sanitizers::sanitize_faq_items() validation.
 *
 * @package PeptideRepoCore
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

// ── Additional stubs ─────────────────────────────────────────────────────

if ( ! defined( 'PR_CORE_TARGET_SCHEMA_VERSION' ) ) {
	define( 'PR_CORE_TARGET_SCHEMA_VERSION', 4 );
}

$GLOBALS['pr_test_postmeta']     = [];
$GLOBALS['pr_test_is_singular']  = false;
$GLOBALS['pr_test_singular_type'] = '';
$GLOBALS['pr_test_the_id']       = 0;

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

function get_post_meta_val( $post_id, $key ) {
	return $GLOBALS['pr_test_postmeta'][ (int) $post_id ][ $key ] ?? '';
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
	return $value; // No-op in test context.
}

function esc_html( string $val ): string {
	return htmlspecialchars( $val, ENT_QUOTES, 'UTF-8' );
}

function esc_html__( string $text, string $domain = '' ): string {
	return $text;
}

function wp_strip_all_tags( $value ): string {
	return strip_tags( (string) $value );
}

function sanitize_textarea_field( $value ): string {
	return is_scalar( $value ) ? trim( (string) $value ) : '';
}

// Load classes under test.
require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-schema-sanitizers.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/dto/class-pr-core-peptide-dto.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-drug.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-faq.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-webpage.php';
require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php';

// ── Helpers ──────────────────────────────────────────────────────────────

/**
 * Create a minimal PR_Core_Peptide_DTO for testing.
 *
 * @param array<string, mixed> $overrides Field overrides.
 * @return PR_Core_Peptide_DTO
 */
function make_peptide_dto( array $overrides = [] ): PR_Core_Peptide_DTO {
	return new PR_Core_Peptide_DTO( array_merge( [
		'id'                => 1,
		'title'             => 'BPC-157',
		'slug'              => 'bpc-157',
		'content'           => '',
		'excerpt'           => 'A synthetic peptide.',
		'status'            => 'publish',
		'display_name'      => 'BPC-157',
		'aliases'           => [],
		'molecular_formula' => '',
		'molecular_weight'  => 0.0,
		'cas_number'        => '',
		'drugbank_id'       => '',
		'chembl_id'         => '',
		'evidence_strength' => 'preclinical',
		'editorial_review_status' => 'published',
		'last_editorial_review_at' => '',
		'medical_editor_id' => 0,
		'categories'        => [],
		'families'          => [],
	], $overrides ) );
}

echo "== JSON-LD v0.6.0 unit tests ==\n\n";

// ── Drug @id ─────────────────────────────────────────────────────────────

echo "Drug @id:\n";

$GLOBALS['pr_test_postmeta'][1] = [];
$peptide   = make_peptide_dto();
$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );

pr_assert( isset( $drug_node['@id'] ), 'Drug node has @id' );
pr_assert_equals(
	'https://peptiderepo.com/peptides/bpc-157/#drug',
	$drug_node['@id'],
	'Drug @id = {permalink}#drug'
);

// ── Drug @type ───────────────────────────────────────────────────────────

echo "\nDrug @type:\n";
pr_assert( is_array( $drug_node['@type'] ), 'Drug @type is an array' );
pr_assert( in_array( 'Drug', $drug_node['@type'], true ), '@type includes "Drug"' );
pr_assert( in_array( 'MolecularEntity', $drug_node['@type'], true ), '@type includes "MolecularEntity"' );

// ── Fields present when meta set ─────────────────────────────────────────

echo "\nFields present when _pr_* meta set:\n";

$GLOBALS['pr_test_postmeta'][1] = [
	'_pr_molecular_formula' => 'C62H98N16O22',
	'_pr_molecular_weight'  => '1419.5',
	'_pr_aliases'           => '["Body Protection Compound-157","PL 14736"]',
];

$drug_node_populated = ( new PR_Core_Jsonld_Drug() )->build( $peptide );

pr_assert( isset( $drug_node_populated['molecularFormula'] ), 'molecularFormula present when meta set' );
pr_assert_equals( 'C62H98N16O22', $drug_node_populated['molecularFormula'], 'molecularFormula value correct' );

pr_assert( isset( $drug_node_populated['molecularWeight'] ), 'molecularWeight present when meta set' );
pr_assert_equals( 'QuantitativeValue', $drug_node_populated['molecularWeight']['@type'], 'molecularWeight @type is QuantitativeValue' );
pr_assert_equals( 1419.5, $drug_node_populated['molecularWeight']['value'], 'molecularWeight value correct' );
pr_assert_equals( 'g/mol', $drug_node_populated['molecularWeight']['unitText'], 'molecularWeight unitText = g/mol' );

pr_assert( isset( $drug_node_populated['alternateName'] ), 'alternateName present when _pr_aliases set' );
pr_assert_equals( 2, count( $drug_node_populated['alternateName'] ), 'alternateName has correct count' );

// ── Fields omitted when meta empty ───────────────────────────────────────

echo "\nFields OMITTED when meta empty:\n";

$GLOBALS['pr_test_postmeta'][1] = [];
$drug_node_empty = ( new PR_Core_Jsonld_Drug() )->build( $peptide );

pr_assert( ! isset( $drug_node_empty['molecularFormula'] ), 'molecularFormula OMITTED when meta empty' );
pr_assert( ! isset( $drug_node_empty['molecularWeight'] ), 'molecularWeight OMITTED when meta empty' );
pr_assert( ! isset( $drug_node_empty['alternateName'] ), 'alternateName OMITTED when _pr_aliases empty' );

// ── FAQPage present when _pr_faq_items has items ─────────────────────────

echo "\nFAQPage emission:\n";

$faq_builder = new PR_Core_Jsonld_Faq();
$permalink   = 'https://peptiderepo.com/peptides/bpc-157/';

$GLOBALS['pr_test_postmeta'][1] = [
	'_pr_faq_items' => wp_json_encode( [
		[ 'question' => 'What is BPC-157?', 'answer' => 'A synthetic peptide.' ],
		[ 'question' => 'Is it legal?', 'answer' => 'Research use only.' ],
	] ),
];

$faq_piece = $faq_builder->build( 1, $permalink );
pr_assert( null !== $faq_piece, 'FAQPage piece not null when items present' );
pr_assert_equals( 'FAQPage', $faq_piece['@type'], 'FAQPage @type correct' );
pr_assert_equals( $permalink . '#faq', $faq_piece['@id'], 'FAQPage @id = {permalink}#faq' );
pr_assert( isset( $faq_piece['mainEntity'] ), 'FAQPage has mainEntity' );
pr_assert_equals( 2, count( $faq_piece['mainEntity'] ), 'FAQPage has 2 questions' );
pr_assert_equals( 'Question', $faq_piece['mainEntity'][0]['@type'], 'Question @type correct' );
pr_assert( isset( $faq_piece['mainEntity'][0]['acceptedAnswer'] ), 'Question has acceptedAnswer' );

// ── FAQPage absent when _pr_faq_items empty ──────────────────────────────

$GLOBALS['pr_test_postmeta'][1] = [ '_pr_faq_items' => '[]' ];
$faq_empty = $faq_builder->build( 1, $permalink );
pr_assert( null === $faq_empty, 'FAQPage piece IS null when _pr_faq_items is "[]"' );

$GLOBALS['pr_test_postmeta'][1] = [];
$faq_absent = $faq_builder->build( 1, $permalink );
pr_assert( null === $faq_absent, 'FAQPage piece IS null when _pr_faq_items absent' );

// ── MedicalWebPage retype ────────────────────────────────────────────────

echo "\nMedicalWebPage retype:\n";

$webpage_enricher = new PR_Core_Jsonld_Webpage();

$GLOBALS['pr_test_is_singular']  = true;
$GLOBALS['pr_test_singular_type'] = 'peptide';
$result = $webpage_enricher->retype_to_medical_webpage( 'WebPage' );
pr_assert_equals( 'MedicalWebPage', $result, 'returns MedicalWebPage on peptide single' );

$GLOBALS['pr_test_is_singular']  = false;
$GLOBALS['pr_test_singular_type'] = '';
$result = $webpage_enricher->retype_to_medical_webpage( 'WebPage' );
pr_assert_equals( 'WebPage', $result, 'passes through unchanged on non-peptide pages' );

// ── No duplicate WebPage / BreadcrumbList nodes ───────────────────────────

echo "\nNo duplicate WebPage/BreadcrumbList nodes:\n";

// Verify our builders only produce Drug and FAQPage — never WebPage or BreadcrumbList.
$GLOBALS['pr_test_postmeta'][1] = [ '_pr_faq_items' => wp_json_encode( [ [ 'question' => 'Q', 'answer' => 'A' ] ] ) ];
$types_from_builders = [];
foreach ( [ $drug_node_populated, $faq_piece ] as $piece ) {
	if ( null === $piece ) { continue; }
	$t = $piece['@type'] ?? '';
	foreach ( ( is_array( $t ) ? $t : [ $t ] ) as $type ) {
		$types_from_builders[] = $type;
	}
}
pr_assert( ! in_array( 'WebPage', $types_from_builders, true ), 'WebPage NOT in our emitted pieces' );
pr_assert( ! in_array( 'MedicalWebPage', $types_from_builders, true ), 'MedicalWebPage NOT in injected pieces (Yoast owns node)' );
pr_assert( ! in_array( 'BreadcrumbList', $types_from_builders, true ), 'BreadcrumbList NOT in our emitted pieces' );
pr_assert( in_array( 'Drug', $types_from_builders, true ), 'Drug IS in our emitted pieces' );
pr_assert( in_array( 'FAQPage', $types_from_builders, true ), 'FAQPage IS in our emitted pieces when items present' );

// ── PR_Core_Schema_Sanitizers::sanitize_faq_items() ──────────────────────

echo "\nPR_Core_Schema_Sanitizers::sanitize_faq_items():\n";

$valid_input = wp_json_encode( [
	[ 'question' => 'What is BPC-157?', 'answer' => 'A peptide.' ],
] );
$result = PR_Core_Schema_Sanitizers::sanitize_faq_items( $valid_input );
$decoded = json_decode( $result, true );
pr_assert( is_array( $decoded ), 'sanitize_faq_items returns JSON-decodable string' );
pr_assert_equals( 1, count( $decoded ), 'correct count after sanitize' );

$result_empty = PR_Core_Schema_Sanitizers::sanitize_faq_items( '[]' );
pr_assert_equals( '[]', $result_empty, 'empty JSON array returns "[]"' );

$result_malformed = PR_Core_Schema_Sanitizers::sanitize_faq_items( 'not-json' );
pr_assert_equals( '[]', $result_malformed, 'malformed JSON returns "[]"' );

// Item missing 'answer' key: should be dropped.
$incomplete_input = wp_json_encode( [ [ 'question' => 'No answer here?' ] ] );
$result_incomplete = PR_Core_Schema_Sanitizers::sanitize_faq_items( $incomplete_input );
$decoded_incomplete = json_decode( $result_incomplete, true );
pr_assert_equals( 0, count( $decoded_incomplete ), 'item without answer field dropped' );

// ── Summary ──────────────────────────────────────────────────────────────

exit( pr_test_summary() );
