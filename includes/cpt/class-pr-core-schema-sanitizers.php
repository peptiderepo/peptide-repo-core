<?php
declare(strict_types=1);

/**
 * Sanitizers for v0.6.0 schema-input meta fields.
 *
 * What: Static sanitizer methods for _pr_molecular_formula, _pr_molecular_weight,
 *       and _pr_faq_items meta fields introduced in v0.6.0.
 *
 * Who calls it: PR_Core_Peptide_CPT::get_meta_fields() registers these as
 *               sanitize_callback values for register_post_meta().
 *
 * Dependencies: WordPress sanitize_text_field, sanitize_textarea_field, wp_json_encode.
 *
 * @see cpt/class-pr-core-peptide-cpt.php — Meta field registration.
 * @see ARCHITECTURE.md                   — §v0.6.0 schema sprint.
 */
class PR_Core_Schema_Sanitizers {

	/**
	 * Sanitize a molecular formula string (for _pr_molecular_formula).
	 *
	 * Strips HTML tags and allows only characters valid in a chemical formula.
	 * Input is treated as UNTRUSTED.
	 *
	 * @param mixed $value Raw input value.
	 * @return string Sanitized formula string (e.g., "C62H98N16O22").
	 */
	public static function sanitize_molecular_formula( $value ): string {
		$value = sanitize_text_field( wp_strip_all_tags( (string) $value ) );
		return preg_replace( '/[^A-Za-z0-9().\[\]{}]/', '', $value ) ?? '';
	}

	/**
	 * Sanitize a molecular weight stored as a float string (for _pr_molecular_weight).
	 *
	 * Strips any trailing "Da" unit suffix, returns a float string.
	 * Stored as string in post_meta to avoid float precision issues.
	 * Returns empty string for invalid/zero inputs.
	 *
	 * @param mixed $value Raw input value (e.g., "1419.5" or "1419.5 Da").
	 * @return string Float string (e.g., "1419.5"), or "" on invalid input.
	 */
	public static function sanitize_molecular_weight_string( $value ): string {
		$clean = trim( preg_replace( '/\s*[Dd][Aa]\s*$/', '', trim( (string) $value ) ) ?? '' );
		$f     = (float) $clean;
		return $f > 0.0 ? (string) $f : '';
	}

	/**
	 * Sanitize a JSON array string (e.g., for legacy aliases field or _pr_aliases).
	 *
	 * Accepts a JSON string or PHP array. Sanitizes each element individually.
	 * Input is UNTRUSTED.
	 *
	 * @param mixed $value Raw input (JSON string or array).
	 * @return string JSON array of sanitized strings, or '[]'.
	 */
	public static function sanitize_json_array( $value ): string {
		if ( is_array( $value ) ) {
			$value = wp_json_encode( array_map( 'sanitize_text_field', $value ) );
		}

		$decoded = json_decode( (string) $value, true );
		if ( ! is_array( $decoded ) ) {
			return '[]';
		}

		return wp_json_encode( array_values( array_map( 'sanitize_text_field', $decoded ) ) );
	}

	/**
	 * Sanitize FAQ items JSON (for _pr_faq_items).
	 *
	 * Expects a JSON array of {question: string, answer: string} objects.
	 * Sanitizes each question and answer individually.
	 * Input is UNTRUSTED — malformed entries are silently dropped.
	 *
	 * @param mixed $value Raw input (JSON string or PHP array).
	 * @return string JSON array of sanitized FAQ item objects, or '[]'.
	 */
	public static function sanitize_faq_items( $value ): string {
		if ( is_string( $value ) ) {
			$value = json_decode( $value, true );
		}

		if ( ! is_array( $value ) ) {
			return '[]';
		}

		$clean = [];
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = sanitize_text_field( (string) ( $item['question'] ?? '' ) );
			$answer   = sanitize_textarea_field( (string) ( $item['answer'] ?? '' ) );
			if ( '' !== $question && '' !== $answer ) {
				$clean[] = [
					'question' => $question,
					'answer'   => $answer,
				];
			}
		}

		return wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) ?: '[]';
	}
}
