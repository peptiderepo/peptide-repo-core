<?php
declare(strict_types=1);

/**
 * Seed data orchestrator for development and testing.
 *
 * What: Creates 3 peptide posts, 10 dosing rows, and 5 legal cells using fixture data.
 * Who calls it: WP-CLI command or admin action (manual trigger only).
 * Dependencies: PR_Core_Seed_Fixtures, PR_Core_Peptide_CPT, PR_Core_Dosing_Repository,
 *               PR_Core_Legal_Repository.
 *
 * @see class-pr-core-seed-fixtures.php — Static fixture data arrays.
 * @see ARCHITECTURE.md — Seed data specification.
 * @package Peptide_Repo_Core
 */
class PR_Core_Seed_Data {

	/**
	 * Run the seed to populate the database with sample data.
	 *
	 * Side effects: creates posts, inserts dosing rows and legal cells.
	 *
	 * @return array{peptides: int, dosing_rows: int, legal_cells: int} Counts of created records.
	 */
	public static function run(): array {
		$peptide_ids = self::seed_peptides();
		$dosing      = self::seed_dosing_rows( $peptide_ids );
		$legal       = self::seed_legal_cells( $peptide_ids );

		return array(
			'peptides'    => count( $peptide_ids ),
			'dosing_rows' => $dosing,
			'legal_cells' => $legal,
		);
	}

	/**
	 * Create 3 canonical peptide posts using fixture data from PR_Core_Seed_Fixtures.
	 *
	 * @return array<string, int> Map of slug => post ID.
	 */
	private static function seed_peptides(): array {
		$peptides = PR_Core_Seed_Fixtures::get_peptides();
		$ids      = array();

		foreach ( $peptides as $p ) {
			$existing = get_posts(
				array(
					'post_type'      => PR_Core_Peptide_CPT::POST_TYPE,
					'name'           => $p['slug'],
					'posts_per_page' => 1,
					'post_status'    => 'any',
				)
			);

			if ( ! empty( $existing ) ) {
				$ids[ $p['slug'] ] = $existing[0]->ID;
				continue;
			}

			$post_id = wp_insert_post(
				array(
					'post_type'    => PR_Core_Peptide_CPT::POST_TYPE,
					'post_title'   => $p['title'],
					'post_name'    => $p['slug'],
					'post_excerpt' => $p['excerpt'],
					'post_status'  => 'publish',
					'post_content' => '',
				)
			);

			if ( is_wp_error( $post_id ) ) {
				continue;
			}

			$meta_keys = array( 'display_name', 'aliases', 'molecular_formula', 'molecular_weight', 'cas_number', 'drugbank_id', 'chembl_id', 'evidence_strength' );
			foreach ( $meta_keys as $key ) {
				update_post_meta( $post_id, $key, $p[ $key ] );
			}
			update_post_meta( $post_id, 'editorial_review_status', 'published' );
			update_post_meta( $post_id, 'last_editorial_review_at', current_time( 'mysql' ) );

			$ids[ $p['slug'] ] = $post_id;
		}

		return $ids;
	}

	/**
	 * Seed 10 dosing rows across the 3 peptides using fixture data.
	 *
	 * @param array<string, int> $peptide_ids Map of slug => post ID.
	 * @return int Number of rows inserted.
	 */
	private static function seed_dosing_rows( array $peptide_ids ): int {
		$repo  = new PR_Core_Dosing_Repository();
		$rows  = PR_Core_Seed_Fixtures::get_dosing_rows();
		$count = 0;

		foreach ( $rows as $row ) {
			$slug = $row['peptide'];
			unset( $row['peptide'] );

			if ( ! isset( $peptide_ids[ $slug ] ) ) {
				continue;
			}

			$row['peptide_id'] = $peptide_ids[ $slug ];
			$row['source']     = 'manual';
			$row['added_by']   = get_current_user_id() !== 0 ? get_current_user_id() : 1;

			if ( $repo->insert( $row ) > 0 ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Seed 5 legal cells across BPC-157 and Semaglutide using fixture data.
	 *
	 * @param array<string, int> $peptide_ids Map of slug => post ID.
	 * @return int Number of cells inserted.
	 */
	private static function seed_legal_cells( array $peptide_ids ): int {
		$repo  = new PR_Core_Legal_Repository();
		$cells = PR_Core_Seed_Fixtures::get_legal_cells();
		$count = 0;

		foreach ( $cells as $cell ) {
			$slug = $cell['peptide'];
			unset( $cell['peptide'] );

			if ( ! isset( $peptide_ids[ $slug ] ) ) {
				continue;
			}

			$cell['peptide_id']  = $peptide_ids[ $slug ];
			$cell['reviewer_id'] = get_current_user_id() !== 0 ? get_current_user_id() : 1;

			if ( $repo->insert( $cell ) > 0 ) {
				++$count;
			}
		}

		return $count;
	}
}
