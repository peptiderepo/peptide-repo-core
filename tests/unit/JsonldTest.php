<?php
/**
 * Unit tests for JSON-LD v0.6.0 emission.
 *
 * Ported from tests/unit/test-jsonld.php.
 *
 * Coverage:
 *   - Drug node: @id, @type, fields present/absent based on meta.
 *   - FAQPage: present/absent, @id, mainEntity count.
 *   - MedicalWebPage retype on/off peptide singular.
 *   - No duplicate WebPage/BreadcrumbList nodes from our builders.
 *   - PR_Core_Schema_Sanitizers::sanitize_faq_items() validation.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for JSON-LD Drug/FAQ/Webpage emission.
 */
class JsonldTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_test_postmeta']      = [];
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$GLOBALS['pr_test_the_id']        = 0;
		$GLOBALS['pr_core_test_state']    = [
			'existing_post_types'   => [],
			'existing_taxonomies'   => [],
			'registered_post_types' => [],
			'registered_taxonomies' => [],
			'registered_meta'       => [],
			'added_actions'         => [],
			'added_filters'         => [],
		];
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-schema-sanitizers.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/dto/class-pr-core-peptide-dto.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-drug.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-faq.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-webpage.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php';
	}

	// ── Helper ────────────────────────────────────────────────────────

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function make_peptide_dto( array $overrides = [] ): PR_Core_Peptide_DTO {
		return new PR_Core_Peptide_DTO( array_merge( [
			'id'                       => 1,
			'title'                    => 'BPC-157',
			'slug'                     => 'bpc-157',
			'content'                  => '',
			'excerpt'                  => 'A synthetic peptide.',
			'status'                   => 'publish',
			'display_name'             => 'BPC-157',
			'aliases'                  => [],
			'molecular_formula'        => '',
			'molecular_weight'         => 0.0,
			'cas_number'               => '',
			'drugbank_id'              => '',
			'chembl_id'                => '',
			'evidence_strength'        => 'preclinical',
			'editorial_review_status'  => 'published',
			'last_editorial_review_at' => '',
			'medical_editor_id'        => 0,
			'categories'               => [],
			'families'                 => [],
		], $overrides ) );
	}

	// ── Drug @id ─────────────────────────────────────────────────────

	public function test_drug_node_has_id(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayHasKey( '@id', $drug_node );
	}

	public function test_drug_node_id_format(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertSame( 'https://peptiderepo.com/peptides/bpc-157/#drug', $drug_node['@id'] );
	}

	// ── Drug @type ────────────────────────────────────────────────────

	public function test_drug_type_is_array(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertIsArray( $drug_node['@type'] );
	}

	public function test_drug_type_includes_drug(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertContains( 'Drug', $drug_node['@type'] );
	}

	public function test_drug_type_includes_molecular_entity(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertContains( 'MolecularEntity', $drug_node['@type'] );
	}

	// ── Drug fields present when meta set ────────────────────────────

	public function test_molecular_formula_present_when_meta_set(): void {
		$GLOBALS['pr_test_postmeta'][1] = [ '_pr_molecular_formula' => 'C62H98N16O22', '_pr_molecular_weight' => '1419.5', '_pr_aliases' => '[]' ];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayHasKey( 'molecularFormula', $drug_node );
		$this->assertSame( 'C62H98N16O22', $drug_node['molecularFormula'] );
	}

	public function test_molecular_weight_present_when_meta_set(): void {
		$GLOBALS['pr_test_postmeta'][1] = [ '_pr_molecular_formula' => '', '_pr_molecular_weight' => '1419.5', '_pr_aliases' => '[]' ];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayHasKey( 'molecularWeight', $drug_node );
		$this->assertSame( 'QuantitativeValue', $drug_node['molecularWeight']['@type'] );
		$this->assertSame( 1419.5, $drug_node['molecularWeight']['value'] );
		$this->assertSame( 'g/mol', $drug_node['molecularWeight']['unitText'] );
	}

	public function test_alternate_name_present_when_aliases_set(): void {
		$GLOBALS['pr_test_postmeta'][1] = [ '_pr_molecular_formula' => '', '_pr_molecular_weight' => '0', '_pr_aliases' => '["Body Protection Compound-157","PL 14736"]' ];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayHasKey( 'alternateName', $drug_node );
		$this->assertCount( 2, $drug_node['alternateName'] );
	}

	// ── Drug fields omitted when meta empty ──────────────────────────

	public function test_molecular_formula_omitted_when_meta_empty(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayNotHasKey( 'molecularFormula', $drug_node );
	}

	public function test_molecular_weight_omitted_when_meta_empty(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayNotHasKey( 'molecularWeight', $drug_node );
	}

	public function test_alternate_name_omitted_when_aliases_empty(): void {
		$GLOBALS['pr_test_postmeta'][1] = [ '_pr_aliases' => '[]' ];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$this->assertArrayNotHasKey( 'alternateName', $drug_node );
	}

	// ── FAQPage emission ──────────────────────────────────────────────

	public function test_faq_piece_not_null_when_items_present(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_faq_items' => wp_json_encode( [
				[ 'question' => 'What is BPC-157?', 'answer' => 'A synthetic peptide.' ],
				[ 'question' => 'Is it legal?', 'answer' => 'Research use only.' ],
			] ),
		];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertNotNull( $faq_piece );
	}

	public function test_faq_piece_type_is_faq_page(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_faq_items' => wp_json_encode( [ [ 'question' => 'Q?', 'answer' => 'A.' ] ] ),
		];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertSame( 'FAQPage', $faq_piece['@type'] );
	}

	public function test_faq_piece_id_format(): void {
		$permalink = 'https://peptiderepo.com/peptides/bpc-157/';
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_faq_items' => wp_json_encode( [ [ 'question' => 'Q?', 'answer' => 'A.' ] ] ),
		];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, $permalink );
		$this->assertSame( $permalink . '#faq', $faq_piece['@id'] );
	}

	public function test_faq_piece_has_main_entity(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_faq_items' => wp_json_encode( [
				[ 'question' => 'What is BPC-157?', 'answer' => 'A synthetic peptide.' ],
				[ 'question' => 'Is it legal?', 'answer' => 'Research use only.' ],
			] ),
		];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertArrayHasKey( 'mainEntity', $faq_piece );
		$this->assertCount( 2, $faq_piece['mainEntity'] );
	}

	public function test_faq_main_entity_question_type(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_faq_items' => wp_json_encode( [ [ 'question' => 'Q?', 'answer' => 'A.' ] ] ),
		];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertSame( 'Question', $faq_piece['mainEntity'][0]['@type'] );
	}

	public function test_faq_main_entity_has_accepted_answer(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_faq_items' => wp_json_encode( [ [ 'question' => 'Q?', 'answer' => 'A.' ] ] ),
		];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertArrayHasKey( 'acceptedAnswer', $faq_piece['mainEntity'][0] );
	}

	public function test_faq_piece_null_when_items_json_empty(): void {
		$GLOBALS['pr_test_postmeta'][1] = [ '_pr_faq_items' => '[]' ];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertNull( $faq_piece );
	}

	public function test_faq_piece_null_when_items_absent(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$faq_piece = ( new PR_Core_Jsonld_Faq() )->build( 1, 'https://peptiderepo.com/peptides/bpc-157/' );
		$this->assertNull( $faq_piece );
	}

	// ── MedicalWebPage retype ─────────────────────────────────────────

	public function test_retype_returns_medical_webpage_on_peptide_singular(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$result = ( new PR_Core_Jsonld_Webpage() )->retype_to_medical_webpage( 'WebPage' );
		$this->assertSame( 'MedicalWebPage', $result );
	}

	public function test_retype_passes_through_on_non_peptide_page(): void {
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$result = ( new PR_Core_Jsonld_Webpage() )->retype_to_medical_webpage( 'WebPage' );
		$this->assertSame( 'WebPage', $result );
	}

	// ── No duplicate nodes from our builders ──────────────────────────

	public function test_builders_do_not_emit_webpage_type(): void {
		$GLOBALS['pr_test_postmeta'][1] = [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '["Body Protection Compound-157","PL 14736"]',
		];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$types     = is_array( $drug_node['@type'] ) ? $drug_node['@type'] : [ $drug_node['@type'] ];
		$this->assertNotContains( 'WebPage', $types );
		$this->assertNotContains( 'MedicalWebPage', $types );
	}

	public function test_builders_do_not_emit_breadcrumb_type(): void {
		$GLOBALS['pr_test_postmeta'][1] = [];
		$peptide   = $this->make_peptide_dto();
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $peptide );
		$types     = is_array( $drug_node['@type'] ) ? $drug_node['@type'] : [ $drug_node['@type'] ];
		$this->assertNotContains( 'BreadcrumbList', $types );
	}

	// ── sanitize_faq_items() ──────────────────────────────────────────

	public function test_sanitize_faq_items_valid_input(): void {
		$result  = PR_Core_Schema_Sanitizers::sanitize_faq_items( wp_json_encode( [ [ 'question' => 'Q?', 'answer' => 'A.' ] ] ) );
		$decoded = json_decode( $result, true );
		$this->assertIsArray( $decoded );
		$this->assertCount( 1, $decoded );
	}

	public function test_sanitize_faq_items_empty_json_array(): void {
		$this->assertSame( '[]', PR_Core_Schema_Sanitizers::sanitize_faq_items( '[]' ) );
	}

	public function test_sanitize_faq_items_malformed_json(): void {
		$this->assertSame( '[]', PR_Core_Schema_Sanitizers::sanitize_faq_items( 'not-json' ) );
	}

	public function test_sanitize_faq_items_drops_item_missing_answer(): void {
		$result  = PR_Core_Schema_Sanitizers::sanitize_faq_items( wp_json_encode( [ [ 'question' => 'No answer here?' ] ] ) );
		$decoded = json_decode( $result, true );
		$this->assertCount( 0, $decoded );
	}
}
