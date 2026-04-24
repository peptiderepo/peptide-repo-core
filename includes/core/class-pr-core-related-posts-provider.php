<?php
declare(strict_types=1);

/**
 * Contract for any provider that retrieves posts related to a peptide.
 *
 * What: Interface defining the get_posts() contract for related-post providers.
 * Who calls it: PR_Core_Related_Posts_Section accepts any implementation.
 * Dependencies: None.
 *
 * @see includes/core/class-pr-core-internal-posts-provider.php — default implementation.
 * @see includes/core/class-pr-core-related-posts-section.php   — consumer.
 */
interface PR_Core_Related_Posts_Provider {

	/**
	 * Get posts related to a peptide.
	 *
	 * @param int $peptide_id The peptide post ID.
	 * @param int $limit      Maximum number of posts to return.
	 * @return array<int, \WP_Post> Array of WP_Post objects (may be empty).
	 */
	public function get_posts( int $peptide_id, int $limit ): array;
}
