<?php
/**
 * Jsonld Article.
 *
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Meta-triggered JSON-LD emitter for PRAutoBlogger articles.
 *
 * What: Enriches Yoast's graph for `post` posts carrying _prab_schema_version=1.
 *       When triggered, retypes the page node to MedicalWebPage and adds
 *       citation[], about[] (Drug @id references), lastReviewed, and honest
 *       reviewedBy to Yoast's Article piece. Emits a standalone @graph block
 *       only when Yoast is inactive. Posts without the trigger meta are
 *       completely byte-unchanged.
 *
 * Ownership rule: prcore owns ALL JSON-LD; PRAutoBlogger writes post meta only.
 *
 * Yoast integration (mirrors the pattern in PR_Core_Jsonld):
 *   - wpseo_schema_webpage_type  → retype to MedicalWebPage (priority 10).
 *   - wpseo_schema_graph         → enrich Article + MedicalWebPage (priority 13).
 *   - wpseo_schema_graph_pieces  is intentionally NOT hooked (Yoast fatals on
 *     plain arrays; see class-pr-core-jsonld.php class-level docblock).
 *   - wp_head (priority 99)      → standalone fallback when Yoast absent.
 *
 * Contract: convo/prcore/decisions/2026-06-11-jsonld-contract-v1.md
 *
 * Who calls it: PR_Core::init() after PR_Core_Jsonld::register_hooks().
 * Dependencies: PR_Core_Prab_Meta_Reader, Yoast SEO ≥ 27.6 for integrated path.
 *
 * @see frontend/class-pr-core-prab-meta-reader.php — Meta reader / sanitizer.
 * @see frontend/class-pr-core-jsonld.php            — Peptide-page emitter (parallel).
 * @see ARCHITECTURE.md §2.7                          — JSON-LD output.
 *
 * @package Peptide_Repo_Core
 */
class PR_Core_Jsonld_Article {

	/** @var PR_Core_Prab_Meta_Reader Meta reader instance. */
	private PR_Core_Prab_Meta_Reader $reader;

	/**
	 * Construct the emitter with its meta reader.
	 */
	public function __construct() {
		$this->reader = new PR_Core_Prab_Meta_Reader();
	}

	/**
	 * Register Yoast and fallback hooks.
	 *
	 * Hooked by PR_Core::init(). Only registers hooks when on a frontend
	 * page — safe to call unconditionally; WP handles the rest.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_filter( 'wpseo_schema_webpage_type', array( $this, 'retype_article_page' ) );
		add_filter( 'wpseo_schema_graph', array( $this, 'enrich_article_graph' ), 13, 2 );
		add_action( 'wp_head', array( $this, 'emit_standalone_fallback' ), 99 );
	}

	/**
	 * Retype the page node to MedicalWebPage for triggered PRAB articles.
	 *
	 * Hooked on: wpseo_schema_webpage_type (priority 10).
	 * Passes through unchanged on non-triggered pages and non-`post` pages.
	 *
	 * @param string|array $type Existing Yoast page type (string or string[]).
	 * @return string|array 'MedicalWebPage' when triggered, $type otherwise.
	 */
	public function retype_article_page( string|array $type ): string|array {
		if ( ! is_singular( 'post' ) ) {
			return $type;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! $this->reader->is_triggered( $post_id ) ) {
			return $type;
		}
		return 'MedicalWebPage';
	}

	/**
	 * Enrich Yoast's assembled graph for triggered PRAB articles.
	 *
	 * Injects lastReviewed + reviewedBy into the MedicalWebPage node and adds
	 * citation[] + about[] to the Article node. Priority 13 ensures this runs
	 * after PR_Core_Jsonld's priority-12 Drug/FAQ injection.
	 *
	 * Never adds new WebPage or Article nodes — mutates Yoast's existing pieces.
	 *
	 * @param array<int, array<string, mixed>>             $graph   Assembled Yoast graph.
	 * @param \Yoast\WP\SEO\Context\Meta_Tags_Context|null $context Yoast schema context.
	 * @return array<int, array<string, mixed>> Enriched graph.
	 */
	public function enrich_article_graph( array $graph, $context ): array {
		if ( ! is_singular( 'post' ) ) {
			return $graph;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! $this->reader->is_triggered( $post_id ) ) {
			return $graph;
		}

		$review_mode  = $this->reader->get_review_mode( $post_id );
		$reviewed_at  = $this->reader->get_reviewed_at( $post_id );
		$reviewed_by  = $this->reader->get_reviewed_by( $post_id, $review_mode );
		$citations    = $this->reader->get_citations( $post_id );
		$about_stubs  = $this->reader->get_about_peptides( $post_id );

		foreach ( $graph as &$piece ) {
			if ( ! is_array( $piece ) ) {
				continue;
			}
			$types = is_array( $piece['@type'] ?? '' ) ? $piece['@type'] : array( $piece['@type'] ?? '' );

			// Enrich MedicalWebPage with lastReviewed + reviewedBy.
			if ( in_array( 'WebPage', $types, true ) || in_array( 'MedicalWebPage', $types, true ) ) {
				if ( null !== $reviewed_at ) {
					$piece['lastReviewed'] = $reviewed_at;
				}
				$piece['reviewedBy'] = $reviewed_by;
				continue;
			}

			// Enrich Article with citation[] and about[].
			if ( in_array( 'Article', $types, true ) ) {
				if ( ! empty( $citations ) ) {
					$piece['citation'] = $this->build_citations( $citations );
				}
				if ( ! empty( $about_stubs ) ) {
					$piece['about'] = $about_stubs;
				}
				continue;
			}
		}
		unset( $piece );

		return $graph;
	}

	/**
	 * Emit a standalone @graph block via wp_head when Yoast is not active.
	 *
	 * Suppressed when Yoast is loaded. Emits Article + MedicalWebPage nodes
	 * with Yoast-parity @id conventions so consumers see one shape regardless
	 * of whether Yoast is present.
	 *
	 * @return void
	 */
	public function emit_standalone_fallback(): void {
		if ( function_exists( 'YoastSEO' ) ) {
			return;
		}
		if ( ! is_singular( 'post' ) ) {
			return;
		}
		$post_id = (int) get_the_ID();
		if ( $post_id <= 0 || ! $this->reader->is_triggered( $post_id ) ) {
			return;
		}

		$permalink   = (string) get_permalink( $post_id );
		$post        = get_post( $post_id );
		$review_mode = $this->reader->get_review_mode( $post_id );
		$reviewed_at = $this->reader->get_reviewed_at( $post_id );
		$reviewed_by = $this->reader->get_reviewed_by( $post_id, $review_mode );
		$citations   = $this->reader->get_citations( $post_id );
		$about_stubs = $this->reader->get_about_peptides( $post_id );

		$webpage = array(
			'@type' => 'MedicalWebPage',
			'@id'   => $permalink . '#webpage',
			'url'   => $permalink,
		);
		if ( null !== $reviewed_at ) {
			$webpage['lastReviewed'] = $reviewed_at;
		}
		$webpage['reviewedBy'] = $reviewed_by;

		$article = array(
			'@type'    => 'Article',
			'@id'      => $permalink . '#article',
			'isPartOf' => array( '@id' => $permalink . '#webpage' ),
			'headline' => $post instanceof WP_Post ? sanitize_text_field( $post->post_title ) : '',
		);
		if ( ! empty( $citations ) ) {
			$article['citation'] = $this->build_citations( $citations );
		}
		if ( ! empty( $about_stubs ) ) {
			$article['about'] = $about_stubs;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode(
				array(
					'@context' => 'https://schema.org',
					'@graph'   => array( $webpage, $article ),
				),
				JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT
			)
		);
	}

	// ── Private builders ─────────────────────────────────────────────────

	/**
	 * Build schema.org citation nodes from validated citation records.
	 *
	 * Emits ScholarlyArticle when doi is present (DOI as sameAs URI),
	 * CreativeWork otherwise. Per contract v1, quality_score is never emitted.
	 *
	 * @param array<int, array{url: string, title: string, doi: string|null}> $citations Validated.
	 * @return array<int, array<string, mixed>> Schema citation nodes.
	 */
	private function build_citations( array $citations ): array {
		$nodes = array();
		foreach ( $citations as $c ) {
			$node = array(
				'@type' => null !== $c['doi'] ? 'ScholarlyArticle' : 'CreativeWork',
				'url'   => $c['url'],
				'name'  => $c['title'],
			);
			if ( null !== $c['doi'] ) {
				$node['sameAs'] = $c['doi'];
			}
			$nodes[] = $node;
		}
		return $nodes;
	}
}
