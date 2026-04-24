<?php
declare(strict_types=1);

/**
 * Fetches posts related to a peptide via taxonomy and fallback text search.
 *
 * What: Implements PR_Core_Related_Posts_Provider by querying posts tagged
 *       with the peptide's slug as a peptide_topic term. Falls back to
 *       full-text search if no taxonomy matches found.
 * Who calls it: PR_Core_Related_Posts_Section instantiates and calls this.
 * Dependencies: PR_Core_Related_Posts_Provider interface, WP_Query,
 *               peptide_topic taxonomy.
 *
 * @see includes/interfaces/interface-related-posts-provider.php
 * @see includes/core/class-related-posts-section.php
 */
class PR_Core_Internal_Posts_Provider implements PR_Core_Related_Posts_Provider {

	/**
	 * Get posts related to a peptide.
	 *
	 * 1. Queries posts tagged with the peptide slug as a peptide_topic term.
	 * 2. If 0 posts found, falls back to full-text search on post_title.
	 * 3. Caches result as a transient (1 hour TTL).
	 * 4. Never exceeds $limit posts.
	 *
	 * Side effects: Sets/updates transient cache for this peptide.
	 *
	 * @param int $peptide_id The peptide post ID.
	 * @param int $limit      Maximum number of posts to return.
	 * @return array<int, \WP_Post> Array of WP_Post objects (may be empty).
	 */
	public function get_posts( int $peptide_id, int $limit ): array {
		$peptide = get_post( $peptide_id );
		if ( ! $peptide || 'peptide' !== $peptide->post_type ) {
			return [];
		}

		$cache_key = 'pr_core_related_' . $peptide_id;
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) ) {
			return array_slice( $cached, 0, $limit );
		}

		// Primary: query posts tagged with peptide's slug as peptide_topic.
		$posts = $this->query_by_taxonomy( $peptide->post_name, $limit );

		// Fallback: if empty, search by peptide title.
		if ( empty( $posts ) ) {
			$posts = $this->query_by_search( $peptide->post_title, $limit );
		}

		// Cache for 1 hour.
		set_transient( $cache_key, $posts, HOUR_IN_SECONDS );

		return $posts;
	}

	/**
	 * Query posts tagged with a specific peptide_topic term.
	 *
	 * @param string $peptide_slug The peptide's post_name (slug).
	 * @param int    $limit        Maximum posts to return.
	 * @return array<int, \WP_Post>
	 */
	private function query_by_taxonomy( string $peptide_slug, int $limit ): array {
		$query = new WP_Query( [
			'post_type'      => 'post',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => [
				[
					'taxonomy' => 'peptide_topic',
					'field'    => 'slug',
					'terms'    => $peptide_slug,
				],
			],
		] );

		return $query->posts ?: [];
	}

	/**
	 * Fallback: query posts by full-text search on post_title.
	 *
	 * @param string $search_term The peptide's display title.
	 * @param int    $limit       Maximum posts to return.
	 * @return array<int, \WP_Post>
	 */
	private function query_by_search( string $search_term, int $limit ): array {
		$query = new WP_Query( [
			'post_type'      => 'post',
			'posts_per_page' => $limit,
			'orderby'        => 'date',
			'order'          => 'DESC',
			's'              => $search_term,
		] );

		return $query->posts ?: [];
	}
}
