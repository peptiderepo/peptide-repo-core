<?php
declare(strict_types=1);

/**
 * Migration 0001: Create the pr_dosing_rows table.
 *
 * What: Creates the high-cardinality dosing data table (1:many with peptide).
 * Who calls it: PR_Core_Migration_Runner::run_pending().
 * Dependencies: WordPress $wpdb, dbDelta().
 *
 * @see ARCHITECTURE.md — Table schema specification.
 */
class PR_Core_Migration_0001_Dosing_Rows {

	/**
	 * Create the pr_dosing_rows table.
	 *
	 * Side effects: database DDL via dbDelta().
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'pr_dosing_rows';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id                  BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			peptide_id          BIGINT UNSIGNED NOT NULL,
			dose_min            DECIMAL(12,4) NULL,
			dose_max            DECIMAL(12,4) NULL,
			dose_unit           VARCHAR(16) NOT NULL,
			route               VARCHAR(32) NOT NULL,
			frequency           VARCHAR(64) NULL,
			duration_value      INT UNSIGNED NULL,
			duration_unit       VARCHAR(16) NULL,
			population          VARCHAR(32) NOT NULL,
			indication          VARCHAR(255) NULL,
			evidence_strength   VARCHAR(32) NOT NULL,
			study_title         VARCHAR(500) NULL,
			study_year          SMALLINT UNSIGNED NULL,
			citation_pubmed_id  VARCHAR(16) NULL,
			citation_doi        VARCHAR(128) NULL,
			citation_url        VARCHAR(500) NULL,
			notes               TEXT NULL,
			schema_version      SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			source              VARCHAR(32) NOT NULL DEFAULT 'manual',
			ai_candidate_id     BIGINT UNSIGNED NULL,
			added_by            BIGINT UNSIGNED NOT NULL,
			added_at            DATETIME NOT NULL,
			reviewed_by         BIGINT UNSIGNED NULL,
			reviewed_at         DATETIME NULL,
			superseded_by_id    BIGINT UNSIGNED NULL,
			PRIMARY KEY  (id),
			KEY idx_peptide (peptide_id),
			KEY idx_peptide_route (peptide_id, route),
			KEY idx_peptide_population (peptide_id, population),
			KEY idx_pubmed (citation_pubmed_id)
		) {$charset};";

		dbDelta( $sql );
	}
}
