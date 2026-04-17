<?php
declare(strict_types=1);

/**
 * Migration 0002: Create the pr_legal_cells table.
 *
 * What: Creates the per-country legal status table (1:many with peptide).
 * Who calls it: PR_Core_Migration_Runner::run_pending().
 * Dependencies: WordPress $wpdb, dbDelta().
 *
 * Uniqueness: only one active cell per peptide x country (superseded_by_id NULL).
 *
 * @see ARCHITECTURE.md — Table schema specification.
 */
class PR_Core_Migration_0002_Legal_Cells {

	/** @var string[] Valid legal status enum values. */
	public const STATUSES = [
		'prescription',
		'ruo',
		'otc',
		'restricted',
		'banned',
		'unclear',
	];

	/**
	 * Create the pr_legal_cells table.
	 *
	 * Side effects: database DDL via dbDelta().
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'pr_legal_cells';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id                     BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			peptide_id             BIGINT UNSIGNED NOT NULL,
			country_code           CHAR(2) NOT NULL,
			status                 VARCHAR(32) NOT NULL,
			regulatory_framework   VARCHAR(128) NULL,
			regulatory_text_url    VARCHAR(500) NULL,
			regulatory_text_quote  TEXT NULL,
			notes                  TEXT NULL,
			last_verified_at       DATETIME NOT NULL,
			schema_version         SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			reviewer_id            BIGINT UNSIGNED NOT NULL,
			superseded_by_id       BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY idx_peptide (peptide_id),
			KEY idx_country (country_code)
		) {$charset};";

		dbDelta( $sql );

		// dbDelta doesn't support UNIQUE with nullable columns well.
		// Add the unique constraint manually if not present.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$index_exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND INDEX_NAME = %s",
				DB_NAME,
				$table,
				'uq_peptide_country_active'
			)
		);

		if ( '0' === $index_exists ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->query(
				"ALTER TABLE {$table} ADD UNIQUE KEY uq_peptide_country_active (peptide_id, country_code, superseded_by_id)"
			);
		}
	}
}
