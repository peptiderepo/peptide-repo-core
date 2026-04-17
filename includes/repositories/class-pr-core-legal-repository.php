<?php
declare(strict_types=1);

/**
 * Repository for legal status cells (pr_legal_cells table).
 *
 * What: CRUD operations for per-country legal status data, returning typed DTOs.
 * Who calls it: REST controller, admin meta boxes, Legal Status Tracker tool.
 * Dependencies: WordPress $wpdb, PR_Core_Legal_Cell_DTO.
 *
 * Returns only active (non-superseded) cells by default. Superseded cells
 * are retained for audit history.
 *
 * @see migrations/class-pr-core-migration-0002-legal-cells.php — Table schema.
 * @see dto/class-pr-core-legal-cell-dto.php                    — Return type.
 */
class PR_Core_Legal_Repository {

	/**
	 * Find a single legal cell by ID.
	 *
	 * @param int $id Row ID.
	 * @return PR_Core_Legal_Cell_DTO|null
	 */
	public function find_by_id( int $id ): ?PR_Core_Legal_Cell_DTO {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_legal_cells';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ),
			ARRAY_A
		);

		return $row ? new PR_Core_Legal_Cell_DTO( $row ) : null;
	}

	/**
	 * Find all active legal cells for a peptide.
	 *
	 * @param int $peptide_id Post ID.
	 * @return PR_Core_Legal_Cell_DTO[]
	 */
	public function find_by_peptide( int $peptide_id ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_legal_cells';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE peptide_id = %d AND superseded_by_id IS NULL ORDER BY country_code ASC",
				$peptide_id
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static fn( array $row ) => new PR_Core_Legal_Cell_DTO( $row ),
			$rows
		);
	}

	/**
	 * Find the active legal cell for a specific peptide + country.
	 *
	 * @param int    $peptide_id   Post ID.
	 * @param string $country_code ISO 3166-1 alpha-2 code.
	 * @return PR_Core_Legal_Cell_DTO|null
	 */
	public function find_by_peptide_and_country( int $peptide_id, string $country_code ): ?PR_Core_Legal_Cell_DTO {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_legal_cells';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE peptide_id = %d AND country_code = %s AND superseded_by_id IS NULL",
				$peptide_id,
				strtoupper( sanitize_text_field( $country_code ) )
			),
			ARRAY_A
		);

		return $row ? new PR_Core_Legal_Cell_DTO( $row ) : null;
	}

	/**
	 * Find all active cells for a country (across all peptides).
	 *
	 * @param string $country_code ISO 3166-1 alpha-2 code.
	 * @return PR_Core_Legal_Cell_DTO[]
	 */
	public function find_by_country( string $country_code ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_legal_cells';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE country_code = %s AND superseded_by_id IS NULL ORDER BY peptide_id ASC",
				strtoupper( sanitize_text_field( $country_code ) )
			),
			ARRAY_A
		) ?: [];

		return array_map(
			static fn( array $row ) => new PR_Core_Legal_Cell_DTO( $row ),
			$rows
		);
	}

	/**
	 * Insert a new legal cell.
	 *
	 * Fires pr_core_before_legal_cell_publish and pr_core_after_legal_cell_publish.
	 *
	 * Side effects: database insert.
	 *
	 * @param array<string, mixed> $data Cell data.
	 * @return int Inserted row ID, or 0 on failure.
	 */
	public function insert( array $data ): int {
		global $wpdb;
		$table = $wpdb->prefix . 'pr_legal_cells';

		$row = $this->sanitize_row( $data );

		do_action( 'pr_core_before_legal_cell_publish', $row );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$result = $wpdb->insert( $table, $row );

		if ( false === $result ) {
			return 0;
		}

		$id = (int) $wpdb->insert_id;

		do_action( 'pr_core_after_legal_cell_publish', $id, $row );

		return $id;
	}

	/**
	 * Supersede an existing legal cell (update with full history).
	 *
	 * @param int                  $old_id   ID of the cell to supersede.
	 * @param array<string, mixed> $new_data Updated cell data.
	 * @return int New cell ID, or 0 on failure.
	 */
	public function supersede( int $old_id, array $new_data ): int {
		$new_id = $this->insert( $new_data );

		if ( 0 === $new_id ) {
			return 0;
		}

		global $wpdb;
		$table = $wpdb->prefix . 'pr_legal_cells';

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
	 * Sanitize a legal cell row.
	 *
	 * @param array<string, mixed> $data Raw input.
	 * @return array<string, mixed> Sanitized row.
	 */
	private function sanitize_row( array $data ): array {
		$valid_statuses = PR_Core_Migration_0002_Legal_Cells::STATUSES;
		$status         = sanitize_text_field( $data['status'] ?? 'unclear' );

		if ( ! in_array( $status, $valid_statuses, true ) ) {
			$status = 'unclear';
		}

		return [
			'peptide_id'            => absint( $data['peptide_id'] ?? 0 ),
			'country_code'          => strtoupper( sanitize_text_field( $data['country_code'] ?? '' ) ),
			'status'                => $status,
			'regulatory_framework'  => isset( $data['regulatory_framework'] ) ? sanitize_text_field( $data['regulatory_framework'] ) : null,
			'regulatory_text_url'   => isset( $data['regulatory_text_url'] ) ? esc_url_raw( $data['regulatory_text_url'] ) : null,
			'regulatory_text_quote' => isset( $data['regulatory_text_quote'] ) ? sanitize_textarea_field( $data['regulatory_text_quote'] ) : null,
			'notes'                 => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : null,
			'last_verified_at'      => sanitize_text_field( $data['last_verified_at'] ?? current_time( 'mysql' ) ),
			'schema_version'        => 1,
			'reviewer_id'           => absint( $data['reviewer_id'] ?? get_current_user_id() ),
		];
	}
}
