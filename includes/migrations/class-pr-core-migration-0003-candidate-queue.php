<?php
declare(strict_types=1);

/**
 * Migration 0003: Create the pr_ai_candidate_queue table.
 *
 * What: Creates the AI-extracted dosing candidate queue table.
 * Who calls it: PR_Core_Migration_Runner::run_pending().
 * Dependencies: WordPress $wpdb, dbDelta().
 *
 * Approved rows are copied into pr_dosing_rows with source='ai-candidate-approved'
 * and ai_candidate_id set; queue row marked 'merged'.
 *
 * @see ARCHITECTURE.md — AI-assist candidate-extraction pipeline.
 */
class PR_Core_Migration_0003_Candidate_Queue {

	/** @var string[] Valid queue status enum values. */
	public const STATUSES = [
		'pending',
		'approved',
		'rejected',
		'merged',
	];

	/**
	 * Create the pr_ai_candidate_queue table.
	 *
	 * Side effects: database DDL via dbDelta().
	 *
	 * @return void
	 */
	public function up(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'pr_ai_candidate_queue';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table} (
			id                      BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			peptide_id              BIGINT UNSIGNED NOT NULL,
			dose_min                DECIMAL(12,4) NULL,
			dose_max                DECIMAL(12,4) NULL,
			dose_unit               VARCHAR(16) NOT NULL,
			route                   VARCHAR(32) NOT NULL,
			frequency               VARCHAR(64) NULL,
			duration_value          INT UNSIGNED NULL,
			duration_unit           VARCHAR(16) NULL,
			population              VARCHAR(32) NOT NULL,
			indication              VARCHAR(255) NULL,
			evidence_strength       VARCHAR(32) NOT NULL,
			study_title             VARCHAR(500) NULL,
			study_year              SMALLINT UNSIGNED NULL,
			citation_pubmed_id      VARCHAR(16) NULL,
			citation_doi            VARCHAR(128) NULL,
			citation_url            VARCHAR(500) NULL,
			notes                   TEXT NULL,
			extraction_confidence   FLOAT NOT NULL DEFAULT 0,
			queue_status            VARCHAR(16) NOT NULL DEFAULT 'pending',
			extracted_at            DATETIME NOT NULL,
			reviewed_by             BIGINT UNSIGNED NULL,
			reviewed_at             DATETIME NULL,
			reviewer_notes          TEXT NULL,
			PRIMARY KEY  (id),
			KEY idx_peptide (peptide_id),
			KEY idx_status (queue_status),
			KEY idx_confidence (extraction_confidence),
			KEY idx_peptide_status (peptide_id, queue_status)
		) {$charset};";

		dbDelta( $sql );
	}
}
