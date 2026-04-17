<?php
declare(strict_types=1);

/**
 * Repository for dosing rows (pr_dosing_rows table).
 *
 * What: CRUD operations for dosing data, returning typed DTOs.
 * Who calls it: REST controller, admin meta boxes, consumer plugins (Dosage Reference tool).
 * Dependencies: WordPress $wpdb, PR_Core_Dosing_Row_DTO.
 *
 * @see migrations/class-pr-core-migration-0001-dosing-rows.php — Table schema.
 * @see dto/class-pr-core-dosing-row-dto.php                    — Return type.
 */
class PR_Core_Dosing_Repository {

	/**
	 * Find a single dosing row by ID.
	 *
	 * @param int $id Row ID.
	 * @return PR_Core_Dosing_Row_DTO|null
	 */
	public function find_by_id( int $id ): ?PR_Core_Dosing_Row_DTO {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_dosing_rows';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? new PR_Core_Dosing_Row_DTO( $row ) : null;
	}

	/**
	 * Find all active (non-superseded) dosing rows for a peptide.
	 *
	 * @param int                  $peptide_id Post ID of the peptide.
	 * @param array<string, mixed> $filters    Optional: route, population, evidence_strength.
	 * @return PR_Core_Dosing_Row_DTO[]
	 */
	public function find_by_peptide( int $peptide_id, array $filters = [] ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_dosing_rows';

		$where  = [ 'peptide_id = %d', 'superseded_by_id IS NULL' ];
		$params = [ $peptide_id ];

		if ( ! empty( $filters['route'] ) ) {
			$where[]  = 'route = %s';
			$params[] = sanitize_text_field( $filters['route'] );
		}

		if ( ! empty( $filters['population'] ) ) {
			$where[]  = 'population = %s';
			$params[] = sanitize_text_field( $filters['population'] );
		}

		if ( ! empty( $filters['evidence_strength'] ) ) {
			$where[]  = 'evidence_strength = %s';
			$params[] = sanitize_text_field( $filters['evidence_strength'] );
		}

		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY evidence_strength DESC, study_year DESC",
				...$params
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static fn( array $row ) => new PR_Core_Dosing_Row_DTO( $row ),
			$rows
		);
	}

	/**
	 * Insert a new dosing row.
	 *
	 * Fires pr_core_before_dosing_row_publish and pr_core_after_dosing_row_publish.
	 *
	 * Side effects: database insert.
	 *
	 * @param array<string, mixed> $data Row data (peptide_id, dose_min, dose_max, etc.).
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_dosing_rows';

		$row = $this->sanitize_row( $data );
		$row['added_at']       = current_time( 'mysql' );
		$row['schema_version'] = 1;

		/** @see PR_Core::register_public_filters() — Documented lifecycle hook. */
		do_action( 'pr_core_before_dosing_row_publish', $row );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $row );

		if ( false === $result ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;

		do_action( 'pr_core_after_dosing_row_publish', $id, $row );

		return $id;
	}

	/**
	 * Supersede an existing dosing row (soft-delete pattern).
	 *
	 * Creates a new row with corrections and marks the old row as superseded.
	 *
	 * Side effects: database insert + update.
	 *
	 * @param int                  $old_id   ID of the row to supersede.
	 * @param array<string, mixed> $new_data Corrected row data.
	 * @return int New row ID, or 0 on failure.
	 */
	public function supersede( int $old_id, array $new_data ): int {
		$new_id = $this->insert( $new_data );

		if ( 0 === $new_id ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pr_dosing_rows';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$table,
			[ 'superseded_by_id' => $new_id ],
			[ 'id' => $old_id ],
			[ '%d' ],
			[ '%d' ]
		);

		return $new_id;
	}

	/**
	 * Count active dosing rows for a peptide.
	 *
	 * @param int $peptide_id Post ID.
	 * @return int
	 */
	public function count_by_peptide( int $peptide_id ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_dosing_rows';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE peptide_id = %d AND superseded_by_id IS NULL",
				$peptide_id
			)
		);
	}

	/**
	 * Sanitize and validate a dosing row array.
	 *
	 * @param array<string, mixed> $data Raw input.
	 * @return array<string, mixed> Sanitized row.
	 */
	private function sanitize_row( array $data ): array {
		return [
			'peptide_id'         => absint( $data['peptide_id'] ?? 0 ),
			'dose_min'           => isset( $data['dose_min'] ) ? (float) $data['dose_min'] : null,
			'dose_max'           => isset( $data['dose_max'] ) ? (float) $data['dose_max'] : null,
			'dose_unit'          => sanitize_text_field( $data['dose_unit'] ?? '' ),
			'route'              => sanitize_text_field( $data['route'] ?? '' ),
			'frequency'          => isset( $data['frequency'] ) ? sanitize_text_field( $data['frequency'] ) : null,
			'duration_value'     => isset( $data['duration_value'] ) ? absint( $data['duration_value'] ) : null,
			'duration_unit'      => isset( $data['duration_unit'] ) ? sanitize_text_field( $data['duration_unit'] ) : null,
			'population'         => sanitize_text_field( $data['population'] ?? '' ),
			'indication'         => isset( $data['indication'] ) ? sanitize_text_field( $data['indication'] ) : null,
			'evidence_strength'  => PR_Core_Peptide_CPT::sanitize_evidence_strength( $data['evidence_strength'] ?? '' ),
			'study_title'        => isset( $data['study_title'] ) ? sanitize_text_field( $data['study_title'] ) : null,
			'study_year'         => isset( $data['study_year'] ) ? absint( $data['study_year'] ) : null,
			'citation_pubmed_id' => isset( $data['citation_pubmed_id'] ) ? sanitize_text_field( $data['citation_pubmed_id'] ) : null,
			'citation_doi'       => isset( $data['citation_doi'] ) ? sanitize_text_field( $data['citation_doi'] ) : null,
			'citation_url'       => isset( $data['citation_url'] ) ? esc_url_raw( $data['citation_url'] ) : null,
			'notes'              => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			'source'             => sanitize_text_field( $data['source'] ?? 'manual' ),
			'ai_candidate_id'    => isset( $data['ai_candidate_id'] ) ? absint( $data['ai_candidate_id'] ) : null,
			'added_by'           => absint( $data['added_by'] ?? get_current_user_id() ),
			'reviewed_by'        => isset( $data['reviewed_by'] ) ? absint( $data['reviewed_by'] ) : null,
			'reviewed_at'        => $data['reviewed_at'] ?? null,
		];
	}
}
