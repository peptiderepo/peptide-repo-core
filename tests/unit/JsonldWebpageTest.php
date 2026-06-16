<?php
/**
 * Regression tests for v0.6.1: Yoast wpseo_schema_webpage_type array contract.
 *
 * Ported from tests/unit/test-jsonld-webpage.php.
 *
 * Root cause: v0.6.0 retype_to_medical_webpage() was strict-typed string, but
 * Yoast passes ['WebPage'] (array) on every singular page.
 * strict_types=1 → TypeError → HTTP 500 on all monographs + plain Pages.
 *
 * These tests fail against v0.6.0 and pass after the v0.6.1 fix.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Regression tests for PR_Core_Jsonld_Webpage v0.6.1 array-type contract.
 */
class JsonldWebpageTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_test_postmeta']      = [];
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$GLOBALS['pr_test_the_id']        = 0;
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-webpage.php';
	}

	// ── retype_to_medical_webpage: array input ────────────────────────

	public function test_retype_non_peptide_singular_array_unchanged(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'page';
		$result = ( new PR_Core_Jsonld_Webpage() )->retype_to_medical_webpage( [ 'WebPage' ] );
		$this->assertSame( [ 'WebPage' ], $result );
	}

	public function test_retype_peptide_singular_array_returns_medical_webpage_string(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$result = ( new PR_Core_Jsonld_Webpage() )->retype_to_medical_webpage( [ 'WebPage' ] );
		$this->assertSame( 'MedicalWebPage', $result );
	}

	public function test_retype_string_input_non_singular_passthrough(): void {
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$result = ( new PR_Core_Jsonld_Webpage() )->retype_to_medical_webpage( 'CollectionPage' );
		$this->assertSame( 'CollectionPage', $result );
	}

	// ── enrich_webpage_piece: @type as array injects enrichments ──────

	public function test_enrich_webpage_piece_array_type_injects_last_reviewed(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$GLOBALS['pr_test_the_id']        = 42;
		$GLOBALS['pr_test_postmeta'][42]  = [ '_pr_last_reviewed' => '2026-06-01' ];

		$graph        = [ [ '@type' => [ 'WebPage' ], '@id' => 'https://peptiderepo.com/peptides/bpc-157/#webpage', 'name' => 'BPC-157' ] ];
		$result_graph = ( new PR_Core_Jsonld_Webpage() )->enrich_webpage_piece( $graph, new stdClass() );
		$piece        = $result_graph[0] ?? [];
		$this->assertArrayHasKey( 'lastReviewed', $piece );
		$this->assertSame( '2026-06-01', $piece['lastReviewed'] );
	}

	public function test_enrich_webpage_piece_array_type_injects_reviewed_by(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$GLOBALS['pr_test_the_id']        = 42;
		$GLOBALS['pr_test_postmeta'][42]  = [ '_pr_last_reviewed' => '2026-06-01' ];

		$graph        = [ [ '@type' => [ 'WebPage' ], '@id' => 'https://peptiderepo.com/peptides/bpc-157/#webpage' ] ];
		$result_graph = ( new PR_Core_Jsonld_Webpage() )->enrich_webpage_piece( $graph, new stdClass() );
		$this->assertArrayHasKey( 'reviewedBy', $result_graph[0] ?? [] );
	}

	public function test_enrich_webpage_piece_array_type_injects_audience(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$GLOBALS['pr_test_the_id']        = 42;
		$GLOBALS['pr_test_postmeta'][42]  = [ '_pr_last_reviewed' => '2026-06-01' ];

		$graph        = [ [ '@type' => [ 'WebPage' ], '@id' => 'https://peptiderepo.com/peptides/bpc-157/#webpage' ] ];
		$result_graph = ( new PR_Core_Jsonld_Webpage() )->enrich_webpage_piece( $graph, new stdClass() );
		$this->assertArrayHasKey( 'audience', $result_graph[0] ?? [] );
	}

	public function test_enrich_webpage_piece_string_medical_webpage_also_enriches(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$GLOBALS['pr_test_the_id']        = 42;
		$GLOBALS['pr_test_postmeta'][42]  = [ '_pr_last_reviewed' => '2026-06-01' ];

		$graph        = [ [ '@type' => 'MedicalWebPage', '@id' => 'https://peptiderepo.com/peptides/bpc-157/#webpage' ] ];
		$result_graph = ( new PR_Core_Jsonld_Webpage() )->enrich_webpage_piece( $graph, new stdClass() );
		$this->assertArrayHasKey( 'lastReviewed', $result_graph[0] ?? [] );
	}

	public function test_enrich_webpage_piece_non_peptide_does_not_inject(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'page';
		$GLOBALS['pr_test_the_id']        = 50;
		$GLOBALS['pr_test_postmeta'][50]  = [];

		$graph        = [ [ '@type' => [ 'WebPage' ], '@id' => 'https://peptiderepo.com/about/#webpage' ] ];
		$result_graph = ( new PR_Core_Jsonld_Webpage() )->enrich_webpage_piece( $graph, new stdClass() );
		$this->assertArrayNotHasKey( 'lastReviewed', $result_graph[0] ?? [] );
	}
}
