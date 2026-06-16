<?php
/**
 * Regression tests for v0.6.2: inject_graph_pieces (plain array) fatal on Yoast.
 *
 * Ported from tests/unit/test-jsonld-peptide-piece.php.
 *
 * Root cause: v0.6.0/v0.6.1 hooked wpseo_schema_graph_pieces and appended plain
 * PHP arrays. Yoast's filter_graph_pieces_to_generate() called get_class() on
 * every piece, causing: "PHP Fatal: get_class(): Argument #1 ($object) must be
 * of type object, array given".
 *
 * Fix: inject via wpseo_schema_graph (priority 12) using inject_graph_nodes().
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for PR_Core_Jsonld::inject_graph_nodes() and related regression.
 */
class JsonldPeptidePieceTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_test_postmeta']      = [];
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$GLOBALS['pr_test_the_id']        = 0;
		$GLOBALS['pr_test_peptide_dto']   = null;
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

	// ── Helpers ───────────────────────────────────────────────────────.

	/**
	 * @param array<string, mixed> $overrides
	 */
	private function make_dto( array $overrides = [] ): PR_Core_Peptide_DTO {
		return new PR_Core_Peptide_DTO( array_merge( [
			'id'                       => 36,
			'title'                    => 'BPC-157',
			'slug'                     => 'bpc-157',
			'content'                  => '',
			'excerpt'                  => 'A synthetic pentadecapeptide.',
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

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private function make_existing_graph(): array {
		return [
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
	}

	private function set_peptide_context( int $post_id, array $meta = [] ): void {
		$GLOBALS['pr_test_the_id']        = $post_id;
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = PR_Core_Peptide_CPT::POST_TYPE;
		$GLOBALS['pr_test_postmeta'][ $post_id ] = $meta;
		$GLOBALS['pr_test_peptide_dto']   = $this->make_dto( [ 'id' => $post_id ] );
		// Supply a WP_Post object so PR_Core_Peptide_Repository::find_by_id() passes.
		// the post_type check and post_to_dto() type hint (our get_post() stub reads.
		// from pr_test_posts).
		$post                                = new WP_Post();
		$post->ID                            = $post_id;
		$post->post_type                     = PR_Core_Peptide_CPT::POST_TYPE;
		$post->post_title                    = 'BPC-157';
		$post->post_name                     = 'bpc-157';
		$post->post_content                  = '';
		$post->post_excerpt                  = '';
		$post->post_status                   = 'publish';
		$GLOBALS['pr_test_posts'][ $post_id ] = $post;
	}

	// ── Regression: get_class(array) throws ───────────────────────────.

	public function test_get_class_on_plain_array_throws(): void {
		$GLOBALS['pr_test_postmeta'][36] = [];
		$drug_node = ( new PR_Core_Jsonld_Drug() )->build( $this->make_dto() );
		$caught    = false;
		try {
			get_class( $drug_node );
		} catch ( TypeError $e ) {
			$caught = true;
		} catch ( Error $e ) {
			$caught = true;
		}
		$this->assertTrue( $caught, 'get_class(array) must throw — confirms the v0.6.1 fatal' );
	}

	// ── inject_graph_nodes does not throw ─────────────────────────────.

	public function test_inject_graph_nodes_does_not_throw(): void {
		$this->set_peptide_context( 36, [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '["Body Protection Compound"]',
		] );
		$jsonld = new PR_Core_Jsonld();
		$threw  = false;
		try {
			$jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		} catch ( Throwable $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw );
	}

	public function test_inject_graph_nodes_appends_drug_node(): void {
		$this->set_peptide_context( 36, [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '["Body Protection Compound"]',
		] );
		$jsonld = new PR_Core_Jsonld();
		$result = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$this->assertGreaterThan( count( $this->make_existing_graph() ), count( $result ) );
	}

	public function test_inject_graph_nodes_exactly_one_drug_node(): void {
		$this->set_peptide_context( 36, [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '["Body Protection Compound"]',
		] );
		$jsonld     = new PR_Core_Jsonld();
		$result     = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$drug_nodes = array_filter( $result, static function ( $n ) {
			$types = is_array( $n['@type'] ?? null ) ? $n['@type'] : [ $n['@type'] ?? '' ];
			return in_array( 'Drug', $types, true );
		} );
		$this->assertCount( 1, $drug_nodes );
	}

	public function test_inject_graph_nodes_drug_id_format(): void {
		$this->set_peptide_context( 36, [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '[]',
		] );
		$jsonld     = new PR_Core_Jsonld();
		$result     = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$drug_nodes = array_values( array_filter( $result, static function ( $n ) {
			$types = is_array( $n['@type'] ?? null ) ? $n['@type'] : [ $n['@type'] ?? '' ];
			return in_array( 'Drug', $types, true );
		} ) );
		$this->assertSame( 'https://peptiderepo.com/peptides/bpc-157/#drug', $drug_nodes[0]['@id'] );
	}

	public function test_inject_graph_nodes_drug_has_molecular_formula(): void {
		$this->set_peptide_context( 36, [
			'_pr_molecular_formula' => 'C62H98N16O22',
			'_pr_molecular_weight'  => '1419.5',
			'_pr_aliases'           => '[]',
		] );
		$jsonld     = new PR_Core_Jsonld();
		$result     = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$drug_nodes = array_values( array_filter( $result, static function ( $n ) {
			$types = is_array( $n['@type'] ?? null ) ? $n['@type'] : [ $n['@type'] ?? '' ];
			return in_array( 'Drug', $types, true );
		} ) );
		$this->assertArrayHasKey( 'molecularFormula', $drug_nodes[0] );
	}

	public function test_inject_graph_nodes_drug_molecular_weight_quantitative_value(): void {
		$this->set_peptide_context( 36, [ '_pr_molecular_weight' => '1419.5' ] );
		$jsonld     = new PR_Core_Jsonld();
		$result     = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$drug_nodes = array_values( array_filter( $result, static function ( $n ) {
			$types = is_array( $n['@type'] ?? null ) ? $n['@type'] : [ $n['@type'] ?? '' ];
			return in_array( 'Drug', $types, true );
		} ) );
		$this->assertSame( 'QuantitativeValue', $drug_nodes[0]['molecularWeight']['@type'] );
		$this->assertSame( 1419.5, $drug_nodes[0]['molecularWeight']['value'] );
		$this->assertSame( 'g/mol', $drug_nodes[0]['molecularWeight']['unitText'] );
	}

	public function test_inject_graph_nodes_preserves_webpage_and_breadcrumb(): void {
		$this->set_peptide_context( 36, [] );
		$jsonld     = new PR_Core_Jsonld();
		$result     = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$webpage    = array_filter( $result, static fn( $n ) => ( $n['@type'] ?? '' ) === 'MedicalWebPage' );
		$breadcrumb = array_filter( $result, static fn( $n ) => ( $n['@type'] ?? '' ) === 'BreadcrumbList' );
		$this->assertCount( 1, $webpage );
		$this->assertCount( 1, $breadcrumb );
	}

	// ── FAQ injection via inject_graph_nodes ──────────────────────────.

	public function test_inject_graph_nodes_faq_present_when_items_exist(): void {
		$this->set_peptide_context( 36, [
			'_pr_faq_items' => wp_json_encode( [
				[ 'question' => 'What is BPC-157?', 'answer' => 'A synthetic pentadecapeptide.' ],
				[ 'question' => 'Is it safe?', 'answer' => 'Research use only.' ],
			] ),
		] );
		$jsonld    = new PR_Core_Jsonld();
		$result    = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$faq_nodes = array_filter( $result, static fn( $n ) => ( $n['@type'] ?? '' ) === 'FAQPage' );
		$this->assertCount( 1, $faq_nodes );
		$faq_node = array_values( $faq_nodes )[0];
		$this->assertSame( 'https://peptiderepo.com/peptides/bpc-157/#faq', $faq_node['@id'] );
		$this->assertCount( 2, $faq_node['mainEntity'] );
	}

	public function test_inject_graph_nodes_faq_absent_when_items_empty(): void {
		$this->set_peptide_context( 36, [ '_pr_faq_items' => '[]' ] );
		$jsonld    = new PR_Core_Jsonld();
		$result    = $jsonld->inject_graph_nodes( $this->make_existing_graph(), null );
		$faq_nodes = array_filter( $result, static fn( $n ) => ( $n['@type'] ?? '' ) === 'FAQPage' );
		$this->assertCount( 0, $faq_nodes );
	}

	// ── Non-peptide page: no injection ────────────────────────────────.

	public function test_inject_graph_nodes_noop_on_non_peptide_page(): void {
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$GLOBALS['pr_test_the_id']        = 0;
		$non_peptide_graph = [ [ '@type' => 'WebPage', '@id' => 'https://peptiderepo.com/about/#webpage' ] ];
		$jsonld            = new PR_Core_Jsonld();
		$result            = $jsonld->inject_graph_nodes( $non_peptide_graph, null );
		$this->assertCount( 1, $result );
	}

	// ── register_hooks: wpseo_schema_graph_pieces must NOT be hooked ──.

	public function test_jsonld_does_not_hook_wpseo_schema_graph_pieces(): void {
		$src = file_get_contents( PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php' );
		$this->assertStringNotContainsString(
			"'wpseo_schema_graph_pieces'",
			$src,
			'class-pr-core-jsonld.php must NOT hook wpseo_schema_graph_pieces (v0.6.2 fix)'
		);
	}

	public function test_jsonld_references_inject_graph_nodes(): void {
		$src = file_get_contents( PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php' );
		$this->assertStringContainsString( 'inject_graph_nodes', $src );
	}

	public function test_jsonld_hooks_wpseo_schema_graph(): void {
		$src = file_get_contents( PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld.php' );
		$this->assertStringContainsString( "'wpseo_schema_graph'", $src );
	}
}
