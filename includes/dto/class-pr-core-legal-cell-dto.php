<?php
declare(strict_types=1);

/**
 * Typed data-transfer object for a legal status cell.
 *
 * What: Immutable value object wrapping a row from pr_legal_cells.
 * Who calls it: PR_Core_Legal_Repository returns these; consumers read them.
 * Dependencies: None.
 *
 * @see migrations/class-pr-core-migration-0002-legal-cells.php — Table schema.
 * @see repositories/class-pr-core-legal-repository.php         — Creates these from DB rows.
 */
class PR_Core_Legal_Cell_DTO {

	public readonly int $id;
	public readonly int $peptide_id;
	public readonly string $country_code;
	public readonly string $status;
	public readonly ?string $regulatory_framework;
	public readonly ?string $regulatory_text_url;
	public readonly ?string $regulatory_text_quote;
	public readonly ?string $notes;
	public readonly string $last_verified_at;
	public readonly int $schema_version;
	public readonly int $reviewer_id;
	public readonly ?int $superseded_by_id;

	/**
	 * @param array<string, mixed> $data Associative array from database row.
	 */
	public function __construct( array $data ) {
		$this->id                    = (int) ( $data['id'] ?? 0 );
		$this->peptide_id            = (int) ( $data['peptide_id'] ?? 0 );
		$this->country_code          = (string) ( $data['country_code'] ?? '' );
		$this->status                = (string) ( $data['status'] ?? 'unclear' );
		$this->regulatory_framework  = $data['regulatory_framework'] ?? null;
		$this->regulatory_text_url   = $data['regulatory_text_url'] ?? null;
		$this->regulatory_text_quote = $data['regulatory_text_quote'] ?? null;
		$this->notes                 = $data['notes'] ?? null;
		$this->last_verified_at      = (string) ( $data['last_verified_at'] ?? '' );
		$this->schema_version        = (int) ( $data['schema_version'] ?? 1 );
		$this->reviewer_id           = (int) ( $data['reviewer_id'] ?? 0 );
		$this->superseded_by_id      = isset( $data['superseded_by_id'] ) ? (int) $data['superseded_by_id'] : null;
	}

	/**
	 * Check if this cell is the active (non-superseded) version.
	 *
	 * @return bool
	 */
	public function is_active(): bool {
		return null === $this->superseded_by_id;
	}

	/**
	 * Convert to associative array for REST/admin output.
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'                    => $this->id,
			'peptide_id'            => $this->peptide_id,
			'country_code'          => $this->country_code,
			'status'                => $this->status,
			'regulatory_framework'  => $this->regulatory_framework,
			'regulatory_text_url'   => $this->regulatory_text_url,
			'regulatory_text_quote' => $this->regulatory_text_quote,
			'notes'                 => $this->notes,
			'last_verified_at'      => $this->last_verified_at,
			'is_active'             => $this->is_active(),
		];
	}
}
