<?php
declare(strict_types=1);

/**
 * Typed data-transfer object for a peptide record.
 *
 * What: Immutable value object wrapping a pr_peptide post + its meta fields.
 * Who calls it: PR_Core_Peptide_Repository returns these; consumers read them.
 * Dependencies: None.
 *
 * @see cpt/class-pr-core-peptide-cpt.php       — Meta field definitions.
 * @see repositories/class-pr-core-peptide-repository.php — Creates these from WP_Post.
 */
class PR_Core_Peptide_DTO {

	public readonly int $id;
	public readonly string $title;
	public readonly string $slug;
	public readonly string $content;
	public readonly string $excerpt;
	public readonly string $status;
	public readonly string $display_name;
	/** @var string[] */
	public readonly array $aliases;
	public readonly string $molecular_formula;
	public readonly float $molecular_weight;
	public readonly string $cas_number;
	public readonly string $drugbank_id;
	public readonly string $chembl_id;
	public readonly string $evidence_strength;
	public readonly string $editorial_review_status;
	public readonly string $last_editorial_review_at;
	public readonly int $medical_editor_id;
	/** @var string[] Category term names. */
	public readonly array $categories;
	/** @var string[] Family term names. */
	public readonly array $families;

	/**
	 * @param array<string, mixed> $data Associative array of peptide fields.
	 */
	public function __construct( array $data ) {
		$this->id                       = (int) ( $data['id'] ?? 0 );
		$this->title                    = (string) ( $data['title'] ?? '' );
		$this->slug                     = (string) ( $data['slug'] ?? '' );
		$this->content                  = (string) ( $data['content'] ?? '' );
		$this->excerpt                  = (string) ( $data['excerpt'] ?? '' );
		$this->status                   = (string) ( $data['status'] ?? 'draft' );
		$this->display_name             = (string) ( $data['display_name'] ?? '' );
		$this->aliases                  = (array) ( $data['aliases'] ?? [] );
		$this->molecular_formula        = (string) ( $data['molecular_formula'] ?? '' );
		$this->molecular_weight         = (float) ( $data['molecular_weight'] ?? 0 );
		$this->cas_number               = (string) ( $data['cas_number'] ?? '' );
		$this->drugbank_id              = (string) ( $data['drugbank_id'] ?? '' );
		$this->chembl_id                = (string) ( $data['chembl_id'] ?? '' );
		$this->evidence_strength        = (string) ( $data['evidence_strength'] ?? 'preclinical' );
		$this->editorial_review_status  = (string) ( $data['editorial_review_status'] ?? 'draft' );
		$this->last_editorial_review_at = (string) ( $data['last_editorial_review_at'] ?? '' );
		$this->medical_editor_id        = (int) ( $data['medical_editor_id'] ?? 0 );
		$this->categories               = (array) ( $data['categories'] ?? [] );
		$this->families                 = (array) ( $data['families'] ?? [] );
	}

	/**
	 * Convert to associative array (e.g., for REST API responses).
	 *
	 * @return array<string, mixed>
	 */
	public function to_array(): array {
		return [
			'id'                       => $this->id,
			'title'                    => $this->title,
			'slug'                     => $this->slug,
			'excerpt'                  => $this->excerpt,
			'display_name'             => $this->display_name,
			'aliases'                  => $this->aliases,
			'molecular_formula'        => $this->molecular_formula,
			'molecular_weight'         => $this->molecular_weight,
			'cas_number'               => $this->cas_number,
			'drugbank_id'              => $this->drugbank_id,
			'chembl_id'                => $this->chembl_id,
			'evidence_strength'        => $this->evidence_strength,
			'editorial_review_status'  => $this->editorial_review_status,
			'last_editorial_review_at' => $this->last_editorial_review_at,
			'medical_editor_id'        => $this->medical_editor_id,
			'categories'               => $this->categories,
			'families'                 => $this->families,
			'url'                      => get_permalink( $this->id ),
		];
	}
}
