<?php
declare(strict_types=1);

/**
 * Interface for fetching related posts for a peptide.
 *
 * What: Defines the contract for retrieving posts related to a given peptide.
 * Who calls it: PR_Core_Related_Posts_Section uses implementations to fetch related articles.
 * Dependencies: None.
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
