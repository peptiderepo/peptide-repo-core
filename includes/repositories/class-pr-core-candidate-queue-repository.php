<?php
declare(strict_types=1);

/**
 * Repository for AI candidate queue (pr_ai_candidate_queue table).
 *
 * What: CRUD for AI-extracted dosing candidates awaiting human review.
 * Who calls it: Admin candidate queue page, extraction pipeline (stub).
 * Dependencies: WordPress $wpdb, PR_Core_Candidate_DTO, PR_Core_Dosing_Repository.
 *
 * Flow: Extract (pending) -> Review (approve/reject) -> Merge into dosing rows.
 *
 * @see migrations/class-pr-core-migration-0003-candidate-queue.php — Table schema.
 * @see dto/class-pr-core-candidate-dto.php                         — Return type.
 * @see admin/class-pr-core-candidate-queue-page.php                — Admin UI.
 */
class PR_Core_Candidate_Queue_Repository {

	/**
	 * Find a single candidate by ID.
	 *
	 * @param int $id Row ID.
	 * @return PR_Core_Candidate_DTO|null
	 */
	public function find_by_id( int $id ): ?PR_Core_Candidate_DTO {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_ai_candidate_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? new PR_Core_Candidate_DTO( $row ) : null;
	}

	/**
	 * Find candidates by status, optionally filtered by peptide.
	 *
	 * @param string $status     Queue status: pending, approved, rejected, merged.
	 * @param int    $peptide_id Optional peptide filter (0 = all).
	 * @param int    $limit      Max results.
	 * @return PR_Core_Candidate_DTO[]
	 */
	public function find_by_status( string $status = 'pending', int $peptide_id = 0, int $limit = 50 ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_ai_candidate_queue';

		$where  = [ 'queue_status = %s' ];
		$params = [ sanitize_text_field( $status ) ];

		if ( $peptide_id > 0 ) {
			$where[]  = 'peptide_id = %d';
			$params[] = $peptide_id;
		}

		$params[] = $limit;
		$where_sql = implode( ' AND ', $where );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY extraction_confidence DESC LIMIT %d",
				...$params
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static fn( array $row ) => new PR_Core_Candidate_DTO( $row ),
			$rows
		);
	}

	/**
	 * Insert a new candidate into the queue.
	 *
	 * Side effects: database insert.
	 *
	 * @param array<string, mixed> $data Candidate data.
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_ai_candidate_queue';

		$row = [
			'peptide_id'            => absint( $data['peptide_id'] ?? 0 ),
			'dose_min'              => isset( $data['dose_min'] ) ? (float) $data['dose_min'] : null,
			'dose_max'              => isset( $data['dose_max'] ) ? (float) $data['dose_max'] : null,
			'dose_unit'             => sanitize_text_field( $data['dose_unit'] ?? '' ),
			'route'                 => sanitize_text_field( $data['route'] ?? '' ),
			'frequency'             => isset( $data['frequency'] ) ? sanitize_text_field( $data['frequency'] ) : null,
			'duration_value'        => isset( $data['duration_value'] ) ? absint( $data['duration_value'] ) : null,
			'duration_unit'         => isset( $data['duration_unit'] ) ? sanitize_text_field( $data['duration_unit'] ) : null,
			'population'            => sanitize_text_field( $data['population'] ?? '' ),
			'indication'            => isset( $data['indication'] ) ? sanitize_text_field( $data['indication'] ) : null,
			'evidence_strength'     => PR_Core_Peptide_CPT::sanitize_evidence_strength( $data['evidence_strength'] ?? '' ),
			'study_title'           => isset( $data['study_title'] ) ? sanitize_text_field( $data['study_title'] ) : null,
			'study_year'            => isset( $data['study_year'] ) ? absint( $data['study_year'] ) : null,
			'citation_pubmed_id'    => isset( $data['citation_pubmed_id'] ) ? sanitize_text_field( $data['citation_pubmed_id'] ) : null,
			'citation_doi'          => isset( $data['citation_doi'] ) ? sanitize_text_field( $data['citation_doi'] ) : null,
			'citation_url'          => isset( $data['citation_url'] ) ? esc_url_raw( $data['citation_url'] ) : null,
			'notes'                 => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			'extraction_confidence' => (float) ( $data['extraction_confidence'] ?? 0 ),
			'queue_status'          => 'pending',
			'extracted_at'          => current_time( 'mysql' ),
		];

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $row );

		return false !== $result ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Approve a candidate: copy to dosing rows, mark as merged.
	 *
	 * Fires pr_core_candidate_approved action.
	 *
	 * Side effects: database insert (dosing row) + update (queue row).
	 *
	 * @param int    $candidate_id Queue row ID.
	 * @param int    $reviewer_id  User ID of the reviewer.
	 * @param string $notes        Optional reviewer notes.
	 * @return int New dosing row ID, or 0 on failure.
	 */
	public function approve( int $candidate_id, int $reviewer_id, string $notes = '' ): int {
		$candidate = $this->find_by_id( $candidate_id );
		if ( ! $candidate || 'pending' !== $candidate->queue_status ) {
			return 0;
		}

		// Copy candidate data into a dosing row.
		$dosing_repo = new PR_Core_Dosing_Repository();
		$dosing_id   = $dosing_repo->insert( [
			'peptide_id'         => $candidate->peptide_id,
			'dose_min'           => $candidate->dose_min,
			'dose_max'           => $candidate->dose_max,
			'dose_unit'          => $candidate->dose_unit,
			'route'              => $candidate->route,
			'frequency'          => $candidate->frequency,
			'duration_value'     => $candidate->duration_value,
			'duration_unit'      => $candidate->duration_unit,
			'population'         => $candidate->population,
			'indication'         => $candidate->indication,
			'evidence_strength'  => $candidate->evidence_strength,
			'study_title'        => $candidate->study_title,
			'study_year'         => $candidate->study_year,
			'citation_pubmed_id' => $candidate->citation_pubmed_id,
			'citation_doi'       => $candidate->citation_doi,
			'citation_url'       => $candidate->citation_url,
			'notes'              => $candidate->notes,
			'source'             => 'ai-candidate-approved',
			'ai_candidate_id'    => $candidate_id,
			'added_by'           => $reviewer_id,
			'reviewed_by'        => $reviewer_id,
			'reviewed_at'        => current_time( 'mysql' ),
		] );

		if ( 0 === $dosing_id ) {
			return 0;
		}

		$this->update_status( $candidate_id, 'merged', $reviewer_id, $notes );

		do_action( 'pr_core_candidate_approved', $candidate_id, $dosing_id );

		return $dosing_id;
	}

	/**
	 * Reject a candidate.
	 *
	 * Fires pr_core_candidate_rejected action.
	 *
	 * Side effects: database update.
	 *
	 * @param int    $candidate_id Queue row ID.
	 * @param int    $reviewer_id  User ID.
	 * @param string $notes        Rejection reason.
	 * @return bool
	 */
	public function reject( int $candidate_id, int $reviewer_id, string $notes = '' ): bool {
		$result = $this->update_status( $candidate_id, 'rejected', $reviewer_id, $notes );

		if ( $result ) {
			do_action( 'pr_core_candidate_rejected', $candidate_id );
		}

		return $result;
	}

	/**
	 * Count candidates by status.
	 *
	 * @param string $status Queue status.
	 * @return int
	 */
	public function count_by_status( string $status = 'pending' ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_ai_candidate_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE queue_status = %s", $status )
		);
	}

	/**
	 * Update a candidate's queue status.
	 *
	 * @param int    $id          Row ID.
	 * @param string $status      New status.
	 * @param int    $reviewer_id Reviewer user ID.
	 * @param string $notes       Optional notes.
	 * @return bool
	 */
	private function update_status( int $id, string $status, int $reviewer_id, string $notes ): bool {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_ai_candidate_queue';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->update(
			$table,
			[
				'queue_status'   => sanitize_text_field( $status ),
				'reviewed_by'    => $reviewer_id,
				'reviewed_at'    => current_time( 'mysql' ),
				'reviewer_notes' => sanitize_textarea_field( $notes ),
			],
			[ 'id' => $id ],
			[ '%s', '%d', '%s', '%s' ],
			[ '%d' ]
		);

		return false !== $result;
	}
}
