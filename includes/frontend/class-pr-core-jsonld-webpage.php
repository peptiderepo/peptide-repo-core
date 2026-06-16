<?php
/**
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Hooks into Yoast's wpseo_schema_webpage_type filter to retype the page
 *
 * @package Peptide_Repo_Core
 */

/**
 * Enriches Yoast's WebPage node to MedicalWebPage for peptide single pages.
 *
 * What: Hooks into Yoast's wpseo_schema_webpage_type filter to retype the page
 *       node to MedicalWebPage, and into wpseo_schema_graph to inject
 *       lastReviewed, reviewedBy, and audience enrichments.
 *
 * Who calls it: PR_Core_Jsonld::register_hooks() registers both filters.
 * Dependencies: Yoast SEO (wordpress-seo) ≥ 27.6 when active; graceful no-op otherwise.
 *
 * @see frontend/class-pr-core-jsonld.php — Parent orchestrator.
 * @see ARCHITECTURE.md                   — §2.7 JSON-LD output, Yoast integration contract.
 */
class PR_Core_Jsonld_Webpage {

	/**
	 * Meta key for last editorial review date (ISO date string).
	 *
	 * @var string Meta key for last editorial review date (ISO date string).
	 */
	private const META_LAST_REVIEWED = '_pr_last_reviewed';

	/**
	 * Meta key for last source verification date (ISO date string).
	 *
	 * @var string Meta key for last source verification date (ISO date string).
	 */
	private const META_LAST_VERIFIED = '_pr_last_source_verified';

	/**
	 * Return 'MedicalWebPage' as the schema type for peptide single pages.
	 *
	 * Hooked on: wpseo_schema_webpage_type (Yoast SEO ≥ 19.x).
	 * Passes through unchanged on non-peptide pages.
	 *
	 * Yoast contract: passes a plain string (e.g., 'CollectionPage') for
	 * non-singular special types, but an ARRAY (e.g., ['WebPage']) for every
	 * singular page — including plain Pages and monographs. Under strict_types=1
	 * a string-only signature throws TypeError on all singulars. This method
	 * therefore accepts string|array and returns the same union type so callers
	 * (Yoast's filter chain) receive the value in the shape they passed it.
	 *
	 * @param string|array $type Existing Yoast page type — string or string[].
	 * @return string|array 'MedicalWebPage' (string) on peptide singles; $type unchanged otherwise.
	 */
	public function retype_to_medical_webpage( string|array $type ): string|array {
		if ( is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return 'MedicalWebPage';
		}
		return $type;
	}

	/**
	 * Enrich Yoast's graph pieces array for peptide single pages.
	 *
	 * Injects lastReviewed, reviewedBy, and audience into the WebPage/MedicalWebPage
	 * node already in Yoast's graph. Does NOT add new WebPage or BreadcrumbList nodes.
	 *
	 * Hooked on: wpseo_schema_graph (Yoast SEO ≥ 19.x), priority 11.
	 *
	 * @param array<int, array<string, mixed>> $graph  Yoast schema graph pieces array.
	 * @param \WPSEO_Schema_Context            $context Yoast schema context object.
	 * @return array<int, array<string, mixed>> Modified graph.
	 */
	public function enrich_webpage_piece( array $graph, $context ): array {
		if ( ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return $graph;
		}

		$post_id     = get_the_ID();
		$enrichments = $this->build_webpage_enrichments( (int) $post_id );

		if ( empty( $enrichments ) ) {
			return $graph;
		}

		foreach ( $graph as &$piece ) {
			if ( ! is_array( $piece ) ) {
				continue;
			}

			// Yoast may set @type as a plain string OR an array of strings.
			// Normalise to array so in_array() works regardless of shape.
			$raw_type = $piece['@type'] ?? '';
			$types    = is_array( $raw_type ) ? $raw_type : array( $raw_type );

			if ( in_array( 'WebPage', $types, true ) || in_array( 'MedicalWebPage', $types, true ) ) {
				foreach ( $enrichments as $key => $value ) {
					$piece[ $key ] = $value;
				}
				break;
			}
		}

		return $graph;
	}

	/**
	 * Build MedicalWebPage enrichment fields for a peptide post.
	 *
	 * @param int $post_id WordPress post ID.
	 * @return array<string, mixed> Enrichment fields to merge into the WebPage node.
	 */
	private function build_webpage_enrichments( int $post_id ): array {
		$enrichments = array();

		// lastReviewed: prefer _pr_last_reviewed, fall back to _pr_last_source_verified,.
		// then post modified date.
		$last_reviewed = (string) get_post_meta( $post_id, self::META_LAST_REVIEWED, true );
		if ( '' === $last_reviewed ) {
			$last_reviewed = (string) get_post_meta( $post_id, self::META_LAST_VERIFIED, true );
		}
		if ( '' === $last_reviewed ) {
			$last_reviewed = get_post_modified_time( 'Y-m-d', false, $post_id ) ?: ''; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		}

		if ( '' !== $last_reviewed ) {
			$enrichments['lastReviewed'] = sanitize_text_field( $last_reviewed );
		}

		// reviewedBy: Person only when a medical editor is assigned; otherwise Organization.
		$editor_id = (int) get_post_meta( $post_id, 'medical_editor_id', true );
		if ( $editor_id > 0 ) {
			$editor = get_userdata( $editor_id );
			if ( $editor ) {
				$enrichments['reviewedBy'] = array(
					'@type' => 'Person',
					'name'  => sanitize_text_field( $editor->display_name ),
					'url'   => get_author_posts_url( $editor_id ),
				);
			}
		}

		if ( ! isset( $enrichments['reviewedBy'] ) ) {
			$enrichments['reviewedBy'] = array(
				'@type' => 'Organization',
				'name'  => 'Peptide Repo',
				'url'   => home_url(),
			);
		}

		// audience: MedicalAudience for researchers.
		$enrichments['audience'] = array(
			'@type'        => 'MedicalAudience',
			'audienceType' => 'Researcher',
		);

		return $enrichments;
	}
}
