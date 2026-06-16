<?php
declare(strict_types=1);

/**
 * Builds the schema.org Drug graph piece for a peptide page.
 *
 * What: Assembles a Drug (+ MolecularEntity) schema node from peptide DTO
 *       and the new _pr_* schema-input meta fields added in v0.6.0.
 *
 * Who calls it: PR_Core_Jsonld::build_drug_piece() during Yoast graph assembly
 *               or standalone @graph construction.
 *
 * Dependencies: PR_Core_Peptide_DTO, WordPress get_post_meta(), get_permalink().
 *
 * @see frontend/class-pr-core-jsonld.php — Orchestrator that calls this builder.
 * @see ARCHITECTURE.md                   — §2.7 JSON-LD output.
 */
class PR_Core_Jsonld_Drug {

	/** @var string Meta key: molecular formula sourced by migration 0004. */
	private const META_FORMULA = '_pr_molecular_formula';

	/** @var string Meta key: molecular weight (float string) sourced by migration 0004. */
	private const META_WEIGHT = '_pr_molecular_weight';

	/** @var string Meta key: aliases JSON array sourced by migration 0004. */
	private const META_ALIASES = '_pr_aliases';

	/** @var string Unit text for molecularWeight QuantitativeValue. */
	private const UNIT_TEXT = 'g/mol';

	/**
	 * Build a schema.org Drug piece array for a peptide.
	 *
	 * @param PR_Core_Peptide_DTO $peptide Peptide DTO.
	 * @return array<string, mixed> Schema.org Drug node.
	 */
	public function build( PR_Core_Peptide_DTO $peptide ): array {
		$permalink = get_permalink( $peptide->id );

		$node = array(
			'@type' => array( 'Drug', 'MolecularEntity' ),
			'@id'   => $permalink . '#drug',
			'name'  => $peptide->display_name ?: $peptide->title,
			'url'   => $permalink,
		);

		if ( '' !== $peptide->excerpt ) {
			$node['description'] = $peptide->excerpt;
		}

		// alternateName: prefer _pr_aliases (v0.6.0), fall back to DTO aliases field.
		$aliases = $this->get_aliases( $peptide );
		if ( ! empty( $aliases ) ) {
			$node['alternateName'] = $aliases;
		}

		// molecularFormula: prefer _pr_molecular_formula, fall back to DTO field.
		$formula = $this->get_formula( $peptide );
		if ( '' !== $formula ) {
			$node['molecularFormula'] = $formula;
		}

		// molecularWeight: prefer _pr_molecular_weight, fall back to DTO field.
		$weight = $this->get_weight( $peptide );
		if ( $weight > 0.0 ) {
			$node['molecularWeight'] = array(
				'@type'    => 'QuantitativeValue',
				'value'    => $weight,
				'unitText' => self::UNIT_TEXT,
			);
		}

		// External identifiers: CAS and DrugBank omitted in v0.6.0 (no source).
		// @see ARCHITECTURE.md §v0.6.0 schema sprint — future enrichment.
		$legal_status = $this->build_legal_status();
		if ( null !== $legal_status ) {
			$node['legalStatus'] = $legal_status;
		}

		/**
		 * Filter the Drug schema node for a peptide.
		 *
		 * Known listeners: none.
		 * Registered at: PR_Core::register_public_filters() — carried forward from v0.5.0.
		 *
		 * @param array<string, mixed> $node    Drug schema node.
		 * @param PR_Core_Peptide_DTO  $peptide Peptide DTO.
		 */
		return apply_filters( 'pr_core_jsonld_peptide', $node, $peptide );
	}

	/**
	 * Get aliases array from _pr_aliases meta, falling back to DTO aliases.
	 *
	 * @param PR_Core_Peptide_DTO $peptide Peptide DTO.
	 * @return string[] Sanitized alias strings.
	 */
	private function get_aliases( PR_Core_Peptide_DTO $peptide ): array {
		$raw = get_post_meta( $peptide->id, self::META_ALIASES, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
			if ( is_array( $decoded ) && ! empty( $decoded ) ) {
				return array_values( array_filter( array_map( 'sanitize_text_field', $decoded ) ) );
			}
		}

		// Fall back to DTO aliases (legacy bare-key meta).
		return $peptide->aliases;
	}

	/**
	 * Get molecular formula from _pr_molecular_formula meta, falling back to DTO.
	 *
	 * @param PR_Core_Peptide_DTO $peptide Peptide DTO.
	 * @return string Sanitized formula string, or '' if not available.
	 */
	private function get_formula( PR_Core_Peptide_DTO $peptide ): string {
		$raw = (string) get_post_meta( $peptide->id, self::META_FORMULA, true );
		if ( '' !== $raw ) {
			return sanitize_text_field( $raw );
		}
		return $peptide->molecular_formula;
	}

	/**
	 * Get molecular weight float from _pr_molecular_weight meta, falling back to DTO.
	 *
	 * @param PR_Core_Peptide_DTO $peptide Peptide DTO.
	 * @return float Weight in g/mol, or 0.0 if not available.
	 */
	private function get_weight( PR_Core_Peptide_DTO $peptide ): float {
		$raw = (string) get_post_meta( $peptide->id, self::META_WEIGHT, true );
		if ( '' !== $raw ) {
			$weight = (float) $raw;
			if ( $weight > 0.0 ) {
				return $weight;
			}
		}
		return $peptide->molecular_weight;
	}

	/**
	 * Build a standard legalStatus node for research compounds.
	 *
	 * Returns null when legalStatus should be omitted (future: when per-peptide
	 * status is available from legal cells).
	 *
	 * @return array<string, string>|null
	 */
	private function build_legal_status(): ?array {
		return array(
			'@type'       => 'DrugLegalStatus',
			'description' => esc_html__( 'Not approved for human therapeutic use; supplied for laboratory research purposes only.', 'peptide-repo-core' ),
		);
	}
}
