<?php
/**
 * Peptide Meta Schema.
 *
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Returns the complete meta field definition map used by PR_Core_Peptide_CPT
 *
 * @package Peptide_Repo_Core
 */

/**
 * Meta field schema definitions for the peptide CPT.
 *
 * What: Returns the complete meta field definition map used by PR_Core_Peptide_CPT.
 * Who calls it: PR_Core_Peptide_CPT::get_meta_fields() delegates here.
 * Dependencies: PR_Core_Peptide_CPT (for sanitize callbacks), PR_Core_Schema_Sanitizers,
 *               PR_Core_Verification_Sanitizers.
 *
 * @see cpt/class-pr-core-peptide-cpt.php — Consumer of this schema.
 */
class PR_Core_Peptide_Meta_Schema {

	/**
	 * Return the full meta field definition array for the peptide CPT.
	 *
	 * Keys map to meta_key strings; values are arrays with type, default, and sanitize.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_fields(): array {
		return array(
			'display_name'              => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'aliases'                   => array(
				'type'     => 'string',
				'default'  => '[]',
				'sanitize' => array( PR_Core_Schema_Sanitizers::class, 'sanitize_json_array' ),
			),
			'molecular_formula'         => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'molecular_weight'          => array(
				'type'     => 'number',
				'default'  => 0,
				'sanitize' => 'floatval',
			),
			'cas_number'                => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'drugbank_id'               => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'chembl_id'                 => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'evidence_strength'         => array(
				'type'     => 'string',
				'default'  => 'preclinical',
				'sanitize' => array( PR_Core_Peptide_CPT::class, 'sanitize_evidence_strength' ),
			),
			'editorial_review_status'   => array(
				'type'     => 'string',
				'default'  => 'draft',
				'sanitize' => array( PR_Core_Peptide_CPT::class, 'sanitize_review_status' ),
			),
			'last_editorial_review_at'  => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'medical_editor_id'         => array(
				'type'     => 'integer',
				'default'  => 0,
				'sanitize' => 'absint',
			),
			'_pr_last_source_verified'  => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'_pr_last_reviewed'         => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'_pr_next_review_by'        => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_text_field',
			),
			'_pr_verification_velocity' => array(
				'type'     => 'string',
				'default'  => 'medium',
				'sanitize' => array( PR_Core_Verification_Sanitizers::class, 'sanitize_velocity' ),
			),
			'_pr_verification_notes'    => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => 'sanitize_textarea_field',
			),
			'_pr_verification_status'   => array(
				'type'     => 'string',
				'default'  => 'current',
				'sanitize' => array( PR_Core_Verification_Sanitizers::class, 'sanitize_status' ),
			),
			'_pr_molecular_formula'     => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( PR_Core_Schema_Sanitizers::class, 'sanitize_molecular_formula' ),
			),
			'_pr_molecular_weight'      => array(
				'type'     => 'string',
				'default'  => '',
				'sanitize' => array( PR_Core_Schema_Sanitizers::class, 'sanitize_molecular_weight_string' ),
			),
			'_pr_aliases'               => array(
				'type'     => 'string',
				'default'  => '[]',
				'sanitize' => array( PR_Core_Schema_Sanitizers::class, 'sanitize_json_array' ),
			),
			'_pr_faq_items'             => array(
				'type'     => 'string',
				'default'  => '[]',
				'sanitize' => array( PR_Core_Schema_Sanitizers::class, 'sanitize_faq_items' ),
			),
		);
	}
}
