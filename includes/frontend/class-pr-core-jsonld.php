<?php
declare(strict_types=1);

/**
 * JSON-LD / schema.org structured data emission for peptide pages.
 *
 * What: Integrates Drug, MedicalWebPage, and FAQPage schema into Yoast's graph
 *       on single peptide pages. When Yoast is inactive, emits a standalone
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
 * Contract (ratified 2026-06-11, jsonld-contract-v1.md):
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

	/** @var PR_Core_Jsonld_Drug Drug piece builder. */
	private PR_Core_Jsonld_Drug $drug_builder;

	/** @var PR_Core_Jsonld_Webpage MedicalWebPage enricher. */
	private PR_Core_Jsonld_Webpage $webpage_enricher;

	/** @var PR_Core_Jsonld_Faq FAQ piece builder. */
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
	 * Registers both the Yoast-integrated path (active when Yoast is loaded)
	 * and the standalone wp_head fallback (active only when Yoast is absent).
	 *
	 * Side effects: calls add_filter(), add_action().
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		// Integrated path: hook into Yoast's schema graph.
		// wpseo_schema_graph_pieces: filter on the array of schema piece objects.
		// wpseo_schema_webpage_type: filter returning the WebPage @type string.
		add_filter( 'wpseo_schema_graph_pieces', [ $this, 'inject_graph_pieces' ], 11, 2 );
		add_filter( 'wpseo_schema_webpage_type', [ $this->webpage_enricher, 'retype_to_medical_webpage' ] );
		add_filter( 'wpseo_schema_graph', [ $this->webpage_enricher, 'enrich_webpage_piece' ], 11, 2 );

		// Standalone fallback: emits only when Yoast is not producing output.
		add_action( 'wp_head', [ $this, 'emit_standalone_fallback' ], 99 );
	}

	/**
	 * Inject Drug and FAQPage pieces into Yoast's schema graph.
	 *
	 * Hooked on: wpseo_schema_graph_pieces (priority 11, after Yoast's own pieces).
	 * This filter receives Yoast's piece objects array. We append plain arrays;
	 * Yoast 27.6 accepts both objects and plain arrays in this filter.
	 *
	 * @param array<int, mixed>     $pieces  Existing graph pieces from Yoast.
	 * @param \WPSEO_Schema_Context $context Yoast schema context object.
	 * @return array<int, mixed> Pieces array with Drug and FAQPage appended.
	 */
	public function inject_graph_pieces( array $pieces, $context ): array {
		if ( ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return $pieces;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $pieces;
		}

		$peptide = ( new PR_Core_Peptide_Repository() )->find_by_id( (int) $post_id );
		if ( ! $peptide ) {
			return $pieces;
		}

		$pieces[] = $this->drug_builder->build( $peptide );

		$faq_piece = $this->faq_builder->build( (int) $post_id, get_permalink( (int) $post_id ) );
		if ( null !== $faq_piece ) {
			$pieces[] = $faq_piece;
		}

		return $pieces;
	}

	/**
	 * Emit a standalone @graph block via wp_head when Yoast is not active.
	 *
	 * Guard: suppressed if Yoast is loaded (function_exists check). This is the
	 * no-Yoast fallback required by the jsonld-contract. When Yoast is present,
	 * the integrated path (inject_graph_pieces) handles emission.
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
		$graph     = [ $this->drug_builder->build( $peptide ) ];

		$faq_piece = $this->faq_builder->build( (int) $post_id, $permalink );
		if ( null !== $faq_piece ) {
			$graph[] = $faq_piece;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				[ '@context' => 'https://schema.org', '@graph' => $graph ],
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
			)
		);
	}
}
