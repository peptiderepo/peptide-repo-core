<?php
declare(strict_types=1);

/**
 * JSON-LD / schema.org structured data emission for peptide pages.
 *
 * What: Emits Drug schema on single peptide pages, Dataset schema on archives.
 * Who calls it: PR_Core::init() registers wp_head hook.
 * Dependencies: PR_Core_Peptide_Repository for data lookup.
 *
 * Consumer plugins can override via pr_core_jsonld_peptide filter.
 *
 * @see ARCHITECTURE.md — Section 2.7 JSON-LD output.
 */
class PR_Core_Jsonld {

	/**
	 * Register hooks for JSON-LD output.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'wp_head', [ $this, 'emit_jsonld' ], 99 );
	}

	/**
	 * Emit JSON-LD on peptide single pages.
	 *
	 * Side effects: outputs script tag in wp_head.
	 *
	 * @return void
	 */
	public function emit_jsonld(): void {
		if ( ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return;
		}

		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return;
		}

		$repo    = new PR_Core_Peptide_Repository();
		$peptide = $repo->find_by_id( $post_id );

		if ( ! $peptide ) {
			return;
		}

		$schema = $this->build_drug_schema( $peptide );

		/**
		 * Filter the JSON-LD schema for a peptide page.
		 *
		 * @param array<string, mixed>  $schema  Schema.org data array.
		 * @param PR_Core_Peptide_DTO   $peptide The peptide DTO.
		 */
		$schema = apply_filters( 'pr_core_jsonld_peptide', $schema, $peptide );

		if ( empty( $schema ) ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
		);
	}

	/**
	 * Build a schema.org Drug object for a peptide.
	 *
	 * @param PR_Core_Peptide_DTO $peptide Peptide data.
	 * @return array<string, mixed> Schema.org JSON-LD structure.
	 */
	private function build_drug_schema( PR_Core_Peptide_DTO $peptide ): array {
		$schema = [
			'@context'         => 'https://schema.org',
			'@type'            => 'Drug',
			'name'             => $peptide->display_name ?: $peptide->title,
			'url'              => get_permalink( $peptide->id ),
			'description'      => $peptide->excerpt,
		];

		if ( ! empty( $peptide->aliases ) ) {
			$schema['alternateName'] = $peptide->aliases;
		}

		if ( '' !== $peptide->molecular_formula ) {
			$schema['molecularFormula'] = $peptide->molecular_formula;
		}

		if ( $peptide->molecular_weight > 0 ) {
			$schema['molecularWeight'] = [
				'@type'    => 'QuantitativeValue',
				'value'    => $peptide->molecular_weight,
				'unitText' => 'Da',
			];
		}

		// External identifiers as codes.
		$codes = [];
		if ( '' !== $peptide->cas_number ) {
			$codes[] = [
				'@type'       => 'MedicalCode',
				'codeValue'   => $peptide->cas_number,
				'codingSystem' => 'CAS',
			];
		}

		if ( '' !== $peptide->drugbank_id ) {
			$codes[] = [
				'@type'       => 'MedicalCode',
				'codeValue'   => $peptide->drugbank_id,
				'codingSystem' => 'DrugBank',
			];
		}

		if ( ! empty( $codes ) ) {
			$schema['code'] = $codes;
		}

		return $schema;
	}
}
