<?php
declare(strict_types=1);

/**
 * Repository for peptide CPT records.
 *
 * What: CRUD operations over peptide posts, returning typed DTOs.
 * Who calls it: PR_Core (public API), REST controller, admin UI, consumer plugins.
 * Dependencies: WordPress WP_Query, PR_Core_Peptide_DTO.
 *
 * @see dto/class-pr-core-peptide-dto.php         — Return type.
 * @see cpt/class-pr-core-peptide-cpt.php         — CPT and meta registration.
 * @see repositories/class-pr-core-dosing-repository.php — Related dosing data.
 * @package Peptide_Repo_Core
 */
class PR_Core_Peptide_Repository {

	/**
	 * Find a single peptide by post ID.
	 *
	 * @param int $id WordPress post ID.
	 * @return PR_Core_Peptide_DTO|null Null if not found or wrong post type.
	 */
	public function find_by_id( int $id ): ?PR_Core_Peptide_DTO {
		$post = get_post( $id );
		if ( ! $post || PR_Core_Peptide_CPT::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $this->post_to_dto( $post );
	}

	/**
	 * Find a peptide by slug.
	 *
	 * @param string $slug Post slug.
	 * @return PR_Core_Peptide_DTO|null
	 */
	public function find_by_slug( string $slug ): ?PR_Core_Peptide_DTO {
		$posts = get_posts(
			array(
				'post_type'      => PR_Core_Peptide_CPT::POST_TYPE,
				'name'           => sanitize_title( $slug ),
				'posts_per_page' => 1,
				'post_status'    => 'any',
			)
		);

		return ! empty( $posts ) ? $this->post_to_dto( $posts[0] ) : null;
	}

	/**
	 * Search peptides by name or alias.
	 *
	 * @param string $query Search term.
	 * @param int    $limit Max results (default 20).
	 * @return PR_Core_Peptide_DTO[]
	 */
	public function search( string $query, int $limit = 20 ): array {
		$query = sanitize_text_field( $query );

		$posts = get_posts(
			array(
				'post_type'      => PR_Core_Peptide_CPT::POST_TYPE,
				's'              => $query,
				'posts_per_page' => $limit,
				'post_status'    => 'publish',
			)
		);

		return array_map( array( $this, 'post_to_dto' ), $posts );
	}

	/**
	 * Find all peptides matching filters.
	 *
	 * @param array<string, mixed> $filters Supported: status, category, family,
	 *                                      evidence_strength, per_page, page.
	 * @return PR_Core_Peptide_DTO[]
	 */
	public function find_all( array $filters = array() ): array {
		$args = array(
			'post_type'      => PR_Core_Peptide_CPT::POST_TYPE,
			'posts_per_page' => (int) ( $filters['per_page'] ?? 100 ),
			'paged'          => (int) ( $filters['page'] ?? 1 ),
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$args['post_status'] = $filters['status'] ?? 'publish';

		if ( ! empty( $filters['category'] ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => PR_Core_Peptide_CPT::TAX_CATEGORY,
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $filters['category'] ),
				),
			);
		}

		// v0.2.0: `family` filter silently ignored — `pr_peptide_family` taxonomy.
		// was removed with the CPT consolidation. Callers passing `family` get.
		// all-category results rather than a fatal. Remove this key from the.
		// REST schema in a future minor bump.

		if ( ! empty( $filters['evidence_strength'] ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => 'evidence_strength',
					'value' => sanitize_text_field( $filters['evidence_strength'] ),
				),
			);
		}

		$posts = get_posts( $args );

		return array_map( array( $this, 'post_to_dto' ), $posts );
	}

	/**
	 * Count total peptides matching a status.
	 *
	 * @param string $status Post status (default 'publish').
	 * @return int
	 */
	public function count( string $status = 'publish' ): int {
		$counts = wp_count_posts( PR_Core_Peptide_CPT::POST_TYPE );
		return (int) ( $counts->$status ?? 0 );
	}

	/**
	 * Convert a WP_Post to a Peptide DTO.
	 *
	 * @param \WP_Post $post WordPress post object.
	 * @return PR_Core_Peptide_DTO
	 */
	private function post_to_dto( \WP_Post $post ): PR_Core_Peptide_DTO {
		$aliases_raw = get_post_meta( $post->ID, 'aliases', true );
		$aliases     = json_decode( $aliases_raw ?: '[]', true ) ?: array(); // phpcs:ignore Universal.Operators.DisallowShortTernary.Found

		$categories = wp_get_post_terms( $post->ID, PR_Core_Peptide_CPT::TAX_CATEGORY, array( 'fields' => 'names' ) );
		// v0.2.0: `pr_peptide_family` taxonomy removed. DTO `families` field.
		// preserved as empty array to keep the REST response shape stable for.
		// clients; will be dropped in a future minor bump with a release note.
		$families = array();

		return new PR_Core_Peptide_DTO(
			array(
				'id'                       => $post->ID,
				'title'                    => $post->post_title,
				'slug'                     => $post->post_name,
				'content'                  => $post->post_content,
				'excerpt'                  => $post->post_excerpt,
				'status'                   => $post->post_status,
				'display_name'             => get_post_meta( $post->ID, 'display_name', true ) ?: '', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'aliases'                  => $aliases,
				'molecular_formula'        => get_post_meta( $post->ID, 'molecular_formula', true ) ?: '', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'molecular_weight'         => (float) get_post_meta( $post->ID, 'molecular_weight', true ),
				'cas_number'               => get_post_meta( $post->ID, 'cas_number', true ) ?: '', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'drugbank_id'              => get_post_meta( $post->ID, 'drugbank_id', true ) ?: '', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'chembl_id'                => get_post_meta( $post->ID, 'chembl_id', true ) ?: '', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'evidence_strength'        => get_post_meta( $post->ID, 'evidence_strength', true ) ?: 'preclinical', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'editorial_review_status'  => get_post_meta( $post->ID, 'editorial_review_status', true ) ?: 'draft', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'last_editorial_review_at' => get_post_meta( $post->ID, 'last_editorial_review_at', true ) ?: '', // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
				'medical_editor_id'        => (int) get_post_meta( $post->ID, 'medical_editor_id', true ),
				'categories'               => is_array( $categories ) ? $categories : array(),
				'families'                 => $families,
			)
		);
	}
}
