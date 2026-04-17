<?php
declare(strict_types=1);

/**
 * Typed data-transfer object for an AI candidate queue entry.
 *
 * What: Immutable value object wrapping a row from pr_ai_candidate_queue.
 * Who calls it: PR_Core_Candidate_Queue_Repository returns these.
 * Dependencies: None.
 *
 * @see migrations/class-pr-core-migration-0003-candidate-queue.php — Table schema.
 * @see repositories/class-pr-core-candidate-queue-repository.php   — Creates these.
 */
class PR_Core_Candidate_DTO {

	public readonly int $id;
	public readonly int $peptide_id;
	public readonly ?float $dose_min;
	public readonly ?float $dose_max;
	public readonly string $dose_unit;
	public readonly string $route;
	public readonly ?string $frequency;
	public readonly ?int $duration_value;
	public readonly ?string $duration_unit;
	public readonly string $population;
	public readonly ?string $indication;
	public readonly string $evidence_strength;
	public readonly ?string $study_title;
	public readonly ?int $study_year;
	public readonly ?string $citation_pubmed_id;
	public readonly ?string $citation_doi;
	public readonly ?string $citation_url;
	public readonly ?string $notes;
	public readonly float $extraction_confidence;
	public readonly string $queue_status;
	public readonly string $extracted_at;
	public readonly ?int $reviewed_by;
	public readonly ?string $reviewed_at;
	public readonly ?string $reviewer_notes;

	/**
	 * @param array<string, mixed> $data Associative array from database row.
	 */
	public function __construct( array $data ) {
		$this->id                    = (int) ( $data['id'] ?? 0 );
		$this->peptide_id            = (int) ( $data['peptide_id'] ?? 0 );
		$this->dose_min              = isset( $data['dose_min'] ) ? (float) $data['dose_min'] : null;
		$this->dose_max              = isset( $data['dose_max'] ) ? (float) $data['dose_max'] : null;
		$this->dose_unit             = (string) ( $data['dose_unit'] ?? '' );
		$this->route                 = (string) ( $data['route'] ?? '' );
		$this->frequency             = $data['frequency'] ?? null;
		$this->duration_value        = isset( $data['duration_value'] ) ? (int) $data['duration_value'] : null;
		$this->duration_unit         = $data['duration_unit'] ?? null;
		$this->population            = (string) ( $data['population'] ?? '' );
		$this->indication            = $data['indication'] ?? null;
		$this->evidence_strength     = (string) ( $data['evidence_strength'] ?? 'preclinical' );
		$this->study_title           = $data['study_title'] ?? null;
		$this->study_year            = isset( $data['study_year'] ) ? (int) $data['study_year'] : null;
		$this->citation_pubmed_id    = $data['citation_pubmed_id'] ?? null;
		$this->citation_doi          = $data['citation_doi'] ?? null;
		$this->citation_url          = $data['citation_url'] ?? null;
		$this->notes                 = $data['notes'] ?? null;
		$this->extraction_confidence = (float) ( $data['extraction_confidence'] ?? 0 );
		$this->queue_status          = (string) ( $data['queue_status'] ?? 'pending' );
		$this->extracted_at          = (string) ( $data['extracted_at'] ?? '' );
		$this->reviewed_by           = isset( $data['reviewed_by'] ) ? (int) $data['reviewed_by'] : null;
		$this->reviewed_at           = $data['reviewed_at'] ?? null;
		$this->reviewer_notes        = $data['reviewer_notes'] ?? null;
	}

	/**
	 * Convert to associative array for admin UI output.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'                    => $this->id,
			'peptide_id'            => $this->peptide_id,
			'dose_min'              => $this->dose_min,
			'dose_max'              => $this->dose_max,
			'dose_unit'             => $this->dose_unit,
			'route'                 => $this->route,
			'frequency'             => $this->frequency,
			'population'            => $this->population,
			'indication'            => $this->indication,
			'evidence_strength'     => $this->evidence_strength,
			'study_title'           => $this->study_title,
			'citation_pubmed_id'    => $this->citation_pubmed_id,
			'extraction_confidence' => $this->extraction_confidence,
			'queue_status'          => $this->queue_status,
			'extracted_at'          => $this->extracted_at,
		];
	}
}
