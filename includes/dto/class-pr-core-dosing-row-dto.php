<?php
/**
 * Dosing Row Dto.
 *
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Immutable value object wrapping a row from pr_dosing_rows
 *
 * @package Peptide_Repo_Core
 */

/**
 * Typed data-transfer object for a dosing row.
 *
 * What: Immutable value object wrapping a row from pr_dosing_rows.
 * Who calls it: PR_Core_Dosing_Repository returns these; consumers read them.
 * Dependencies: None.
 *
 * @see migrations/class-pr-core-migration-0001-dosing-rows.php — Table schema.
 * @see repositories/class-pr-core-dosing-repository.php        — Creates these from DB rows.
 */
class PR_Core_Dosing_Row_DTO {

	/**
	 * Int.
	 *
	 * @var int
	 */
	public readonly int $id;
	/**
	 * Int.
	 *
	 * @var int
	 */
	public readonly int $peptide_id;
	/**
	 * ?float.
	 *
	 * @var ?float
	 */
	public readonly ?float $dose_min;
	/**
	 * ?float.
	 *
	 * @var ?float
	 */
	public readonly ?float $dose_max;
	/**
	 * String.
	 *
	 * @var string
	 */
	public readonly string $dose_unit;
	/**
	 * String.
	 *
	 * @var string
	 */
	public readonly string $route;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $frequency;
	/**
	 * ?int.
	 *
	 * @var ?int
	 */
	public readonly ?int $duration_value;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $duration_unit;
	/**
	 * String.
	 *
	 * @var string
	 */
	public readonly string $population;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $indication;
	/**
	 * String.
	 *
	 * @var string
	 */
	public readonly string $evidence_strength;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $study_title;
	/**
	 * ?int.
	 *
	 * @var ?int
	 */
	public readonly ?int $study_year;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $citation_pubmed_id;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $citation_doi;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $citation_url;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $notes;
	/**
	 * Int.
	 *
	 * @var int
	 */
	public readonly int $schema_version;
	/**
	 * String.
	 *
	 * @var string
	 */
	public readonly string $source;
	/**
	 * ?int.
	 *
	 * @var ?int
	 */
	public readonly ?int $ai_candidate_id;
	/**
	 * Int.
	 *
	 * @var int
	 */
	public readonly int $added_by;
	/**
	 * String.
	 *
	 * @var string
	 */
	public readonly string $added_at;
	/**
	 * ?int.
	 *
	 * @var ?int
	 */
	public readonly ?int $reviewed_by;
	/**
	 * ?string.
	 *
	 * @var ?string
	 */
	public readonly ?string $reviewed_at;
	/**
	 * ?int.
	 *
	 * @var ?int
	 */
	public readonly ?int $superseded_by_id;

	/**
	 *   Construct.
	 *
	 * @param array<string, mixed> $data Associative array from database row.
	 */
	public function __construct( array $data ) {
		$this->id                 = (int) ( $data['id'] ?? 0 );
		$this->peptide_id         = (int) ( $data['peptide_id'] ?? 0 );
		$this->dose_min           = isset( $data['dose_min'] ) ? (float) $data['dose_min'] : null;
		$this->dose_max           = isset( $data['dose_max'] ) ? (float) $data['dose_max'] : null;
		$this->dose_unit          = (string) ( $data['dose_unit'] ?? '' );
		$this->route              = (string) ( $data['route'] ?? '' );
		$this->frequency          = $data['frequency'] ?? null;
		$this->duration_value     = isset( $data['duration_value'] ) ? (int) $data['duration_value'] : null;
		$this->duration_unit      = $data['duration_unit'] ?? null;
		$this->population         = (string) ( $data['population'] ?? '' );
		$this->indication         = $data['indication'] ?? null;
		$this->evidence_strength  = (string) ( $data['evidence_strength'] ?? 'preclinical' );
		$this->study_title        = $data['study_title'] ?? null;
		$this->study_year         = isset( $data['study_year'] ) ? (int) $data['study_year'] : null;
		$this->citation_pubmed_id = $data['citation_pubmed_id'] ?? null;
		$this->citation_doi       = $data['citation_doi'] ?? null;
		$this->citation_url       = $data['citation_url'] ?? null;
		$this->notes              = $data['notes'] ?? null;
		$this->schema_version     = (int) ( $data['schema_version'] ?? 1 );
		$this->source             = (string) ( $data['source'] ?? 'manual' );
		$this->ai_candidate_id    = isset( $data['ai_candidate_id'] ) ? (int) $data['ai_candidate_id'] : null;
		$this->added_by           = (int) ( $data['added_by'] ?? 0 );
		$this->added_at           = (string) ( $data['added_at'] ?? '' );
		$this->reviewed_by        = isset( $data['reviewed_by'] ) ? (int) $data['reviewed_by'] : null;
		$this->reviewed_at        = $data['reviewed_at'] ?? null;
		$this->superseded_by_id   = isset( $data['superseded_by_id'] ) ? (int) $data['superseded_by_id'] : null;
	}

	/**
	 * Convert to associative array for REST/admin output.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return array(
			'id'                 => $this->id,
			'peptide_id'         => $this->peptide_id,
			'dose_min'           => $this->dose_min,
			'dose_max'           => $this->dose_max,
			'dose_unit'          => $this->dose_unit,
			'route'              => $this->route,
			'frequency'          => $this->frequency,
			'duration_value'     => $this->duration_value,
			'duration_unit'      => $this->duration_unit,
			'population'         => $this->population,
			'indication'         => $this->indication,
			'evidence_strength'  => $this->evidence_strength,
			'study_title'        => $this->study_title,
			'study_year'         => $this->study_year,
			'citation_pubmed_id' => $this->citation_pubmed_id,
			'citation_doi'       => $this->citation_doi,
			'citation_url'       => $this->citation_url,
			'notes'              => $this->notes,
			'source'             => $this->source,
			'added_at'           => $this->added_at,
		);
	}
}
