<?php
/**
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Static sanitizer methods for _pr_molecular_formula, _pr_molecular_weight,
 *
 * @package Peptide_Repo_Core
 */

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
	 * Regex token for a single element symbol + optional count (e.g., "C10", "Na", "O").
	 *
	 * Matches: one uppercase letter, optionally followed by one lowercase letter,
	 * optionally followed by one or more digits.
	 *
	 * @var string
	 */
	const FORMULA_TOKEN = '[A-Z][a-z]?\d*';

	/**
	 * Regex character class for allowed group-separator / structural characters.
	 *
	 * Covers: parentheses, square brackets, interpunct (·, U+00B7), period, digits.
	 * These may appear between element tokens in complex or hydrated formulas.
	 *
	 * @var string
	 */
	const FORMULA_SEP = '[().\[\]\xB7\d]';

	/**
	 * Sanitize a molecular formula string (for _pr_molecular_formula).
	 *
	 * Strips HTML tags and control characters, then validates the value against
	 * an element-formula grammar. Returns the longest leading run of valid
	 * formula tokens and separators; returns '' when nothing valid is found.
	 *
	 * Grammar (per token): [A-Z][a-z]?\d* — an element symbol plus optional count.
	 * Separators: ( ) [ ] · . and standalone digits (for hydrated formulas).
	 * Input is treated as UNTRUSTED; downstream JSON escaping is unchanged.
	 *
	 * Examples:
	 *   "C62H98N16O22"          → "C62H98N16O22"  (valid, unchanged)
	 *   "C10H12\ninjection"     → "C10H12"         (injection stripped)
	 *   "C2H5(OH)"              → "C2H5(OH)"       (parenthesised formula preserved)
	 *   "<script>alert(1)</script>" → ""            (no valid leading token)
	 *   ""                      → ""               (empty input)
	 *
	 * @param mixed $value Raw input value.
	 * @return string Sanitized formula string, or '' when no valid formula found.
	 */
	public static function sanitize_molecular_formula( $value ): string {
		// 1. Strip control characters (including newlines, tabs, null bytes).
		$value = preg_replace( '/[\x00-\x1F\x7F]/', '', (string) $value ) ?? '';
		// 2. Strip HTML tags; then strip remaining unsafe text characters.
		$value = sanitize_text_field( wp_strip_all_tags( $value ) );
		if ( '' === $value ) {
			return '';
		}
		// 3. Extract the maximal leading run of valid element tokens and separators.
		// A valid run must start with an element token ([A-Z][a-z]?\d*), not a separator.
		$pattern = '/^((?:' . self::FORMULA_TOKEN . '|' . self::FORMULA_SEP . ')+)/u'; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
		if ( preg_match( $pattern, $value, $m ) ) {
			return $m[1];
		}
		return '';
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

		$clean = array();
		foreach ( $value as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$question = sanitize_text_field( (string) ( $item['question'] ?? '' ) );
			$answer   = sanitize_textarea_field( (string) ( $item['answer'] ?? '' ) );
			if ( '' !== $question && '' !== $answer ) {
				$clean[] = array(
					'question' => $question,
					'answer'   => $answer,
				);
			}
		}

		return wp_json_encode( $clean, JSON_UNESCAPED_UNICODE ) ?: '[]'; // phpcs:ignore Universal.Operators.DisallowShortTernary.Found
	}
}
