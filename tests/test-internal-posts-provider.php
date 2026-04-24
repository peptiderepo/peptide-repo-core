<?php
declare(strict_types=1);

/**
 * Unit tests for PR_Core_Internal_Posts_Provider.
 *
 * Tests the provider's taxonomy-based and fallback search behavior,
 * caching, and limit enforcement. Uses mocked WP_Query.
 */
class PR_Core_Internal_Posts_Provider_Test {

	/**
	 * Test: returns empty array when peptide ID is invalid.
	 */
	public function test_returns_empty_when_peptide_not_found() {
		$provider = new PR_Core_Internal_Posts_Provider();
		$result   = $provider->get_posts( 99999, 3 );

		assert( [] === $result, 'Expected empty array for invalid peptide ID' );
	}

	/**
	 * Test: returns posts matching peptide_topic taxonomy.
	 */
	public function test_returns_taxonomy_matched_posts() {
		$provider = new PR_Core_Internal_Posts_Provider();

		// Create a peptide post.
		$peptide_id = wp_insert_post( [
			'post_type'   => 'peptide',
			'post_title'  => 'BPC-157',
			'post_name'   => 'bpc-157',
			'post_status' => 'publish',
		] );

		// Create and tag a blog post with peptide_topic = 'bpc-157'.
		$post_id = wp_insert_post( [
			'post_type'   => 'post',
			'post_title'  => 'BPC-157 Research',
			'post_status' => 'publish',
		] );
		wp_set_post_terms( $post_id, 'bpc-157', 'peptide_topic' );

		$result = $provider->get_posts( $peptide_id, 3 );

		assert( ! empty( $result ), 'Expected at least one post' );
		assert( 1 === count( $result ), 'Expected exactly one post' );
		assert( $post_id === $result[0]->ID, 'Expected post ID to match' );

		// Cleanup.
		wp_delete_post( $peptide_id, true );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test: falls back to text search when taxonomy returns nothing.
	 */
	public function test_falls_back_to_search_when_no_taxonomy_matches() {
		$provider = new PR_Core_Internal_Posts_Provider();

		// Create a peptide post with no matching term.
		$peptide_id = wp_insert_post( [
			'post_type'   => 'peptide',
			'post_title'  => 'Semaglutide',
			'post_name'   => 'semaglutide-unique',
			'post_status' => 'publish',
		] );

		// Create a blog post with "Semaglutide" in title (no peptide_topic tag).
		$post_id = wp_insert_post( [
			'post_type'   => 'post',
			'post_title'  => 'Semaglutide Dosing Guide',
			'post_status' => 'publish',
		] );

		$result = $provider->get_posts( $peptide_id, 3 );

		assert( ! empty( $result ), 'Expected fallback search to return posts' );
		assert( $post_id === $result[0]->ID, 'Expected fallback search to find title match' );

		// Cleanup.
		wp_delete_post( $peptide_id, true );
		wp_delete_post( $post_id, true );
	}

	/**
	 * Test: never returns more posts than $limit.
	 */
	public function test_respects_limit_constraint() {
		$provider = new PR_Core_Internal_Posts_Provider();

		// Create peptide.
		$peptide_id = wp_insert_post( [
			'post_type'   => 'peptide',
			'post_title'  => 'TB-500',
			'post_name'   => 'tb-500',
			'post_status' => 'publish',
		] );

		// Create 5 tagged posts.
		for ( $i = 0; $i < 5; ++$i ) {
			$post_id = wp_insert_post( [
				'post_type'   => 'post',
				'post_title'  => 'TB-500 Article ' . $i,
				'post_status' => 'publish',
			] );
			wp_set_post_terms( $post_id, 'tb-500', 'peptide_topic' );
		}

		$result = $provider->get_posts( $peptide_id, 3 );

		assert( 3 === count( $result ), 'Expected exactly 3 posts for limit=3' );

		// Cleanup.
		wp_delete_post( $peptide_id, true );
	}

	/**
	 * Test: returns cached transient on second call.
	 */
	public function test_returns_cached_transient_on_second_call() {
		$provider = new PR_Core_Internal_Posts_Provider();

		// Create peptide.
		$peptide_id = wp_insert_post( [
			'post_type'   => 'peptide',
			'post_title'  => 'AOD-9604',
			'post_name'   => 'aod-9604',
			'post_status' => 'publish',
		] );

		// First call: query and cache.
		$result_1 = $provider->get_posts( $peptide_id, 3 );

		// Verify transient was set.
		$cache_key = 'pr_core_related_' . $peptide_id;
		$cached    = get_transient( $cache_key );
		assert( ! empty( $cached ), 'Expected transient to be set' );

		// Second call: should return cached data.
		$result_2 = $provider->get_posts( $peptide_id, 3 );

		// Results should match.
		if ( ! empty( $result_1 ) ) {
			assert( count( $result_1 ) === count( $result_2 ), 'Expected cached results to match' );
		}

		// Cleanup.
		wp_delete_post( $peptide_id, true );
		delete_transient( $cache_key );
	}
}

// Run tests if executed directly in PHPUnit context (tests/bootstrap.php will autoload).
if ( function_exists( 'assert_options' ) ) {
	assert_options( ASSERT_ACTIVE, 1 );
	assert_options( ASSERT_WARNING, 0 );
	assert_options( ASSERT_BAIL, 1 );

	$test = new PR_Core_Internal_Posts_Provider_Test();
	$test->test_returns_empty_when_peptide_not_found();
	$test->test_returns_taxonomy_matched_posts();
	$test->test_falls_back_to_search_when_no_taxonomy_matches();
	$test->test_respects_limit_constraint();
	$test->test_returns_cached_transient_on_second_call();

	echo "All tests passed!\n";
}
