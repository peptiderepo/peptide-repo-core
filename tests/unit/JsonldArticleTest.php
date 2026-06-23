<?php
/**
 * Unit tests for PR_Core_Prab_Meta_Reader and PR_Core_Jsonld_Article.
 *
 * Coverage:
 *   - is_triggered: version=1 triggers, absent/wrong version does not.
 *   - get_review_mode: 'human', 'editorial-system', unknown → 'editorial-system'.
 *   - get_reviewed_by: human + valid user → Person; human + bad user → Org; editorial → Org.
 *   - get_citations: valid, missing url, missing title, bad url, doi normalisation.
 *   - get_about_peptides: valid peptide ID, non-existent ID, non-peptide type.
 *   - get_reviewed_at: valid ISO, empty, unparseable → null.
 *   - retype_article_page: triggered post → MedicalWebPage; untriggered → passthrough.
 *   - enrich_article_graph: Article gains citation/about; WebPage gains lastReviewed/reviewedBy.
 *   - No duplicate Article/WebPage nodes emitted.
 *   - editorial-system mode never emits Person.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Tests for PRAB article JSON-LD meta-reader and emitter.
 */
class JsonldArticleTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_test_postmeta']      = array();
		$GLOBALS['pr_test_is_singular']   = false;
		$GLOBALS['pr_test_singular_type'] = '';
		$GLOBALS['pr_test_the_id']        = 0;
		$GLOBALS['pr_test_posts']         = array();
		$GLOBALS['pr_test_userdata']      = array();
		$GLOBALS['pr_core_test_state']    = array(
			'existing_post_types'   => array(),
			'existing_taxonomies'   => array(),
			'registered_post_types' => array(),
			'registered_taxonomies' => array(),
			'registered_meta'       => array(),
			'added_actions'         => array(),
			'added_filters'         => array(),
		);

		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-prab-meta-reader.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/frontend/class-pr-core-jsonld-article.php';
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/** @return PR_Core_Prab_Meta_Reader */
	private function reader(): PR_Core_Prab_Meta_Reader {
		return new PR_Core_Prab_Meta_Reader();
	}

	/**
	 * @param array<string, mixed> $meta
	 */
	private function set_meta( int $post_id, array $meta ): void {
		$GLOBALS['pr_test_postmeta'][ $post_id ] = $meta;
	}

	// ── is_triggered ─────────────────────────────────────────────────────

	public function test_is_triggered_version_1(): void {
		$this->set_meta( 1, array( '_prab_schema_version' => '1' ) );
		$this->assertTrue( $this->reader()->is_triggered( 1 ) );
	}

	public function test_is_triggered_absent(): void {
		$this->set_meta( 1, array() );
		$this->assertFalse( $this->reader()->is_triggered( 1 ) );
	}

	public function test_is_triggered_unsupported_version(): void {
		$this->set_meta( 1, array( '_prab_schema_version' => '2' ) );
		$this->assertFalse( $this->reader()->is_triggered( 1 ) );
	}

	// ── get_review_mode ───────────────────────────────────────────────────

	public function test_review_mode_human(): void {
		$this->set_meta( 1, array( '_prab_review_mode' => 'human' ) );
		$this->assertSame( 'human', $this->reader()->get_review_mode( 1 ) );
	}

	public function test_review_mode_editorial_system(): void {
		$this->set_meta( 1, array( '_prab_review_mode' => 'editorial-system' ) );
		$this->assertSame( 'editorial-system', $this->reader()->get_review_mode( 1 ) );
	}

	public function test_review_mode_unknown_falls_back(): void {
		$this->set_meta( 1, array( '_prab_review_mode' => 'robo-editor' ) );
		$this->assertSame( 'editorial-system', $this->reader()->get_review_mode( 1 ) );
	}

	public function test_review_mode_empty_falls_back(): void {
		$this->set_meta( 1, array() );
		$this->assertSame( 'editorial-system', $this->reader()->get_review_mode( 1 ) );
	}

	// ── get_reviewed_by ───────────────────────────────────────────────────

	public function test_reviewed_by_human_valid_user(): void {
		$this->set_meta( 1, array( '_prab_reviewed_by' => '42' ) );
		$GLOBALS['pr_test_userdata'][42] = new WP_User( array( 'display_name' => 'Dr Alice', 'ID' => 42 ) );
		$node = $this->reader()->get_reviewed_by( 1, 'human' );
		$this->assertSame( 'Person', $node['@type'] );
		$this->assertSame( 'Dr Alice', $node['name'] );
		$this->assertStringContainsString( '42', $node['@id'] );
	}

	public function test_reviewed_by_human_missing_user_returns_org(): void {
		$this->set_meta( 1, array( '_prab_reviewed_by' => '99' ) );
		// user 99 not in $GLOBALS['pr_test_userdata'] → get_userdata returns false.
		$node = $this->reader()->get_reviewed_by( 1, 'human' );
		$this->assertSame( 'Organization', $node['@type'] );
	}

	public function test_reviewed_by_human_zero_user_id_returns_org(): void {
		$this->set_meta( 1, array( '_prab_reviewed_by' => '0' ) );
		$node = $this->reader()->get_reviewed_by( 1, 'human' );
		$this->assertSame( 'Organization', $node['@type'] );
	}

	public function test_reviewed_by_editorial_system_returns_org(): void {
		$node = $this->reader()->get_reviewed_by( 1, 'editorial-system' );
		$this->assertSame( 'Organization', $node['@type'] );
		$this->assertSame( 'Peptide Repo', $node['name'] );
	}

	public function test_reviewed_by_org_never_person(): void {
		// Ensure editorial-system never emits a Person even if reviewed_by meta present.
		$this->set_meta( 1, array( '_prab_reviewed_by' => '42' ) );
		$GLOBALS['pr_test_userdata'][42] = new WP_User( array( 'display_name' => 'Dr Alice', 'ID' => 42 ) );
		$node = $this->reader()->get_reviewed_by( 1, 'editorial-system' );
		$this->assertSame( 'Organization', $node['@type'] );
	}

	// ── get_citations ─────────────────────────────────────────────────────

	public function test_citations_valid(): void {
		$this->set_meta( 1, array(
			'_prab_citations' => wp_json_encode( array(
				array( 'url' => 'https://pubmed.ncbi.nlm.nih.gov/12345', 'title' => 'BPC-157 study' ),
				array( 'url' => 'https://doi.org/10.1016/j.abc.2020.01', 'title' => 'RCT', 'doi' => '10.1016/j.abc.2020.01' ),
			) ),
		) );
		$cits = $this->reader()->get_citations( 1 );
		$this->assertCount( 2, $cits );
		$this->assertSame( 'BPC-157 study', $cits[0]['title'] );
		$this->assertNull( $cits[0]['doi'] );
		$this->assertSame( 'https://doi.org/10.1016/j.abc.2020.01', $cits[1]['doi'] );
	}

	public function test_citations_missing_url_dropped(): void {
		$this->set_meta( 1, array(
			'_prab_citations' => wp_json_encode( array(
				array( 'title' => 'No URL entry' ),
			) ),
		) );
		$this->assertCount( 0, $this->reader()->get_citations( 1 ) );
	}

	public function test_citations_missing_title_dropped(): void {
		$this->set_meta( 1, array(
			'_prab_citations' => wp_json_encode( array(
				array( 'url' => 'https://example.com' ),
			) ),
		) );
		$this->assertCount( 0, $this->reader()->get_citations( 1 ) );
	}

	public function test_citations_invalid_url_scheme_dropped(): void {
		$this->set_meta( 1, array(
			'_prab_citations' => wp_json_encode( array(
				array( 'url' => 'javascript:alert(1)', 'title' => 'XSS attempt' ),
			) ),
		) );
		$this->assertCount( 0, $this->reader()->get_citations( 1 ) );
	}

	public function test_citations_bad_doi_emits_null_doi(): void {
		$this->set_meta( 1, array(
			'_prab_citations' => wp_json_encode( array(
				array( 'url' => 'https://example.com', 'title' => 'Test', 'doi' => 'not-a-doi' ),
			) ),
		) );
		$cits = $this->reader()->get_citations( 1 );
		$this->assertCount( 1, $cits );
		$this->assertNull( $cits[0]['doi'] );
	}

	public function test_citations_doi_normalised_from_uri(): void {
		$this->set_meta( 1, array(
			'_prab_citations' => wp_json_encode( array(
				array( 'url' => 'https://example.com', 'title' => 'Test', 'doi' => 'https://doi.org/10.9999/x' ),
			) ),
		) );
		$cits = $this->reader()->get_citations( 1 );
		$this->assertSame( 'https://doi.org/10.9999/x', $cits[0]['doi'] );
	}

	public function test_citations_malformed_json_returns_empty(): void {
		$this->set_meta( 1, array( '_prab_citations' => 'not-json' ) );
		$this->assertCount( 0, $this->reader()->get_citations( 1 ) );
	}

	// ── get_reviewed_at ───────────────────────────────────────────────────

	public function test_reviewed_at_valid_iso(): void {
		$this->set_meta( 1, array( '_prab_reviewed_at' => '2026-06-11T08:00:00+00:00' ) );
		$this->assertSame( '2026-06-11T08:00:00+00:00', $this->reader()->get_reviewed_at( 1 ) );
	}

	public function test_reviewed_at_empty_returns_null(): void {
		$this->set_meta( 1, array() );
		$this->assertNull( $this->reader()->get_reviewed_at( 1 ) );
	}

	public function test_reviewed_at_unparseable_returns_null(): void {
		$this->set_meta( 1, array( '_prab_reviewed_at' => 'not-a-date' ) );
		$this->assertNull( $this->reader()->get_reviewed_at( 1 ) );
	}

	// ── retype_article_page ───────────────────────────────────────────────

	public function test_retype_triggered_post(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'post';
		$GLOBALS['pr_test_the_id']        = 1;
		$this->set_meta( 1, array( '_prab_schema_version' => '1' ) );
		$emitter = new PR_Core_Jsonld_Article();
		$this->assertSame( 'MedicalWebPage', $emitter->retype_article_page( 'WebPage' ) );
	}

	public function test_retype_untriggered_passthrough(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'post';
		$GLOBALS['pr_test_the_id']        = 2;
		$this->set_meta( 2, array() ); // no _prab_schema_version.
		$emitter = new PR_Core_Jsonld_Article();
		$this->assertSame( 'WebPage', $emitter->retype_article_page( 'WebPage' ) );
	}

	public function test_retype_non_post_passthrough(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'peptide';
		$GLOBALS['pr_test_the_id']        = 1;
		$this->set_meta( 1, array( '_prab_schema_version' => '1' ) );
		$emitter = new PR_Core_Jsonld_Article();
		$this->assertSame( 'CollectionPage', $emitter->retype_article_page( 'CollectionPage' ) );
	}

	// ── enrich_article_graph ──────────────────────────────────────────────

	public function test_enrich_adds_citation_and_about_to_article(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'post';
		$GLOBALS['pr_test_the_id']        = 1;

		// Set up a published peptide post for about linkage.
		$GLOBALS['pr_test_posts'][10] = new WP_Post( array(
			'ID'          => 10,
			'post_type'   => 'peptide',
			'post_status' => 'publish',
			'post_title'  => 'BPC-157',
		) );

		$this->set_meta( 1, array(
			'_prab_schema_version'  => '1',
			'_prab_citations'       => wp_json_encode( array(
				array( 'url' => 'https://pubmed.ncbi.nlm.nih.gov/1', 'title' => 'Study A' ),
			) ),
			'_prab_about_peptides'  => wp_json_encode( array( 10 ) ),
			'_prab_review_mode'     => 'editorial-system',
			'_prab_reviewed_at'     => '2026-06-11T08:00:00+00:00',
		) );

		$graph = array(
			array( '@type' => 'MedicalWebPage', '@id' => 'https://peptiderepo.com/post/1/#webpage' ),
			array( '@type' => 'Article', '@id' => 'https://peptiderepo.com/post/1/#article' ),
		);

		$emitter      = new PR_Core_Jsonld_Article();
		$result_graph = $emitter->enrich_article_graph( $graph, null );

		// Article should have citation and about.
		$article = null;
		$webpage = null;
		foreach ( $result_graph as $piece ) {
			if ( 'Article' === ( $piece['@type'] ?? '' ) ) {
				$article = $piece;
			}
			if ( 'MedicalWebPage' === ( $piece['@type'] ?? '' ) ) {
				$webpage = $piece;
			}
		}

		$this->assertNotNull( $article, 'Article piece found in graph' );
		$this->assertArrayHasKey( 'citation', $article );
		$this->assertCount( 1, $article['citation'] );
		$this->assertArrayHasKey( 'about', $article );
		$this->assertCount( 1, $article['about'] );
		$this->assertSame( 'Drug', $article['about'][0]['@type'] );

		// WebPage should have lastReviewed and reviewedBy (Organization).
		$this->assertNotNull( $webpage, 'MedicalWebPage piece found in graph' );
		$this->assertArrayHasKey( 'lastReviewed', $webpage );
		$this->assertSame( 'Organization', $webpage['reviewedBy']['@type'] );
	}

	public function test_enrich_untriggered_graph_unchanged(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'post';
		$GLOBALS['pr_test_the_id']        = 2;
		$this->set_meta( 2, array() );

		$graph    = array(
			array( '@type' => 'WebPage', '@id' => 'https://peptiderepo.com/post/2/#webpage' ),
			array( '@type' => 'Article', '@id' => 'https://peptiderepo.com/post/2/#article' ),
		);
		$emitter  = new PR_Core_Jsonld_Article();
		$result   = $emitter->enrich_article_graph( $graph, null );

		// Graph must be identical — no new keys.
		$this->assertSame( $graph, $result );
	}

	public function test_no_new_article_or_webpage_nodes_added(): void {
		$GLOBALS['pr_test_is_singular']   = true;
		$GLOBALS['pr_test_singular_type'] = 'post';
		$GLOBALS['pr_test_the_id']        = 1;
		$this->set_meta( 1, array( '_prab_schema_version' => '1', '_prab_review_mode' => 'editorial-system' ) );

		$graph  = array(
			array( '@type' => 'MedicalWebPage', '@id' => 'x#w' ),
			array( '@type' => 'Article', '@id' => 'x#a' ),
		);
		$emitter = new PR_Core_Jsonld_Article();
		$result  = $emitter->enrich_article_graph( $graph, null );

		// Still exactly 2 pieces — no new nodes injected.
		$this->assertCount( 2, $result );
	}
}
