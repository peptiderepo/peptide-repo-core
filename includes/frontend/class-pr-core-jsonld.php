<?php
/**
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Integrates Drug, MedicalWebPage, and FAQPage schema into Yoast's graph
 *
 * @package Peptide_Repo_Core
 */

/**
 * JSON-LD / schema.org structured data emission for peptide pages.
 *
 * What: Integrates Drug, MedicalWebPage, and FAQPage schema into Yoast's graph
 *       on single peptide pages. When Yoast is inactive, emits a standalone
 *
 *       @graph block via wp_head as a fallback.
 *
 * Who calls it: PR_Core::init() calls register_hooks(). Yoast hooks fire on
 *               every frontend page that Yoast processes.
 *
 * Dependencies:
 *   - Yoast SEO (wordpress-seo) ≥ 27.6 for integrated emission path.
 *   - PR_Core_Jsonld_Drug, PR_Core_Jsonld_Webpage, PR_Core_Jsonld_Faq (builders).
 *   - PR_Core_Peptide_Repository, PR_Core_Peptide_CPT (data).
 *
 * Yoast integration contract (ratified 2026-06-13, v0.6.2):
 *   - wpseo_schema_graph_pieces delivers OBJECTS (Abstract_Schema_Piece subclasses)
 *     to Yoast, which calls get_class() + is_needed() on every item. Injecting plain
 *     arrays there causes a PHP Fatal. Drug/FAQ nodes must be injected via
 *     wpseo_schema_graph instead, which receives the fully-assembled graph as
 *     array-of-arrays and is safe for plain array appends.
 *   - wpseo_schema_graph_pieces is therefore left UNHOOKED by this plugin.
 *   - Integrated path: Yoast owns WebPage and BreadcrumbList — we never duplicate.
 *   - Standalone fallback: emits a complete @graph (Drug + FAQPage) only.
 *   - Drug @id: {permalink}#drug (stable for cross-plugin linking).
 *
 * @see frontend/class-pr-core-jsonld-drug.php    — Drug piece builder.
 * @see frontend/class-pr-core-jsonld-webpage.php — MedicalWebPage enrichment.
 * @see frontend/class-pr-core-jsonld-faq.php     — FAQPage piece builder.
 * @see ARCHITECTURE.md                           — §2.7 JSON-LD output.
 */
class PR_Core_Jsonld {

	/**
	 * Drug piece builder.
	 *
	 * @var PR_Core_Jsonld_Drug Drug piece builder.
	 */
	private PR_Core_Jsonld_Drug $drug_builder;

	/**
	 * MedicalWebPage enricher.
	 *
	 * @var PR_Core_Jsonld_Webpage MedicalWebPage enricher.
	 */
	private PR_Core_Jsonld_Webpage $webpage_enricher;

	/**
	 * FAQ piece builder.
	 *
	 * @var PR_Core_Jsonld_Faq FAQ piece builder.
	 */
	private PR_Core_Jsonld_Faq $faq_builder;

	/**
	 * Construct the orchestrator with its piece builders.
	 */
	public function __construct() {
		$this->drug_builder     = new PR_Core_Jsonld_Drug();
		$this->webpage_enricher = new PR_Core_Jsonld_Webpage();
		$this->faq_builder      = new PR_Core_Jsonld_Faq();
	}

	/**
	 * Register hooks for JSON-LD output.
	 *
	 * Registers the Yoast-integrated path via wpseo_schema_graph (safe for plain
	 * array appends) and the standalone wp_head fallback (active only when Yoast
	 * is absent). Does NOT hook wpseo_schema_graph_pieces — Yoast calls
	 * get_class() + is_needed() on every item in that filter's array and will
	 * fatal on a plain PHP array (see class-level docblock).
	 *
	 * Side effects: calls add_filter(), add_action().
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// wpseo_schema_webpage_type: retype WebPage -> MedicalWebPage on peptide singles.
		add_filter( 'wpseo_schema_webpage_type', array( $this->webpage_enricher, 'retype_to_medical_webpage' ) );

		// wpseo_schema_graph: two callbacks at different priorities.
		// Priority 11 -- enrich_webpage_piece: merge lastReviewed/reviewedBy/audience into the.
		// existing WebPage node that Yoast already emitted.
		// Priority 12 -- inject_graph_nodes: append Drug + FAQPage arrays to the graph.
		// Must run after enrich_webpage_piece so the WebPage node is already.
		// enriched before Drug/FAQ are appended.
		add_filter( 'wpseo_schema_graph', array( $this->webpage_enricher, 'enrich_webpage_piece' ), 11, 2 );
		add_filter( 'wpseo_schema_graph', array( $this, 'inject_graph_nodes' ), 12, 2 );

		// Standalone fallback: emits only when Yoast is not producing output.
		add_action( 'wp_head', array( $this, 'emit_standalone_fallback' ), 99 );
	}

	/**
	 * Append Drug and FAQPage nodes to Yoast's fully-assembled schema graph.
	 *
	 * Hooked on: wpseo_schema_graph (priority 12, after enrich_webpage_piece).
	 * This filter receives Yoast's completed graph as an array of plain arrays.
	 * Appending plain Drug/FAQ arrays here is safe -- unlike wpseo_schema_graph_pieces
	 * which calls get_class() and is_needed() on each item expecting objects.
	 *
	 * @param array<int, array<string, mixed>>             $graph   Assembled Yoast graph.
	 * @param \Yoast\WP\SEO\Context\Meta_Tags_Context|null $context Yoast schema context.
	 * @return array<int, array<string, mixed>> Graph with Drug (and optionally FAQ) appended.
	 */
	public function inject_graph_nodes( array $graph, $context ): array {
		if ( ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return $graph;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $graph;
		}

		$peptide = ( new PR_Core_Peptide_Repository() )->find_by_id( (int) $post_id );
		if ( ! $peptide ) {
			return $graph;
		}

		$graph[] = $this->drug_builder->build( $peptide );

		$faq_node = $this->faq_builder->build( (int) $post_id, get_permalink( (int) $post_id ) );
		if ( null !== $faq_node ) {
			$graph[] = $faq_node;
		}

		return $graph;
	}

	/**
	 * Emit a standalone @graph block via wp_head when Yoast is not active.
	 *
	 * Guard: suppressed if Yoast is loaded (function_exists check). This is the
	 * no-Yoast fallback required by the jsonld-contract. When Yoast is present,
	 * the integrated path (inject_graph_nodes via wpseo_schema_graph) handles emission.
	 *
	 * Side effects: outputs a script tag in wp_head.
	 *
	 * @return void
	 */
	public function emit_standalone_fallback(): void {
		if ( function_exists( 'YoastSEO' ) ) {
			return;
		}

		if ( ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$peptide = ( new PR_Core_Peptide_Repository() )->find_by_id( (int) $post_id );
		if ( ! $peptide ) {
			return;
		}

		$permalink = get_permalink( (int) $post_id );
		$graph     = array( $this->drug_builder->build( $peptide ) );

		$faq_node = $this->faq_builder->build( (int) $post_id, $permalink );
		if ( null !== $faq_node ) {
			$graph[] = $faq_node;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => $graph,
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
			)
		);
	}
}
