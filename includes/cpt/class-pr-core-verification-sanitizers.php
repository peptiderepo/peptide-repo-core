<?php
declare(strict_types=1);

/**
 * Sanitizers for verification meta fields.
 *
 * What: Standalone sanitization functions for verification_velocity and verification_status fields.
 * Who calls it: PR_Core_Peptide_CPT::get_meta_fields() sanitize callbacks.
 * Dependencies: None.
 *
 * @see cpt/class-pr-core-peptide-cpt.php — Calls these from meta field registration.
 */
class PR_Core_Verification_Sanitizers {

	/**
	 * Sanitize verification_velocity to allowed enum values.
	 *
	 * @param string $value Raw input.
	 * @return string Valid enum value (low, medium, high).
	 */
	public static function sanitize_velocity( string $value ): string {
		return in_array( $value, [ 'low', 'medium', 'high' ], true ) ? $value : 'medium';
	}

	/**
	 * Sanitize verification_status to allowed enum values.
	 *
	 * @param string $value Raw input.
	 * @return string Valid enum value (current, due, overdue).
	 */
	public static function sanitize_status( string $value ): string {
		return in_array( $value, [ 'current', 'due', 'overdue' ], true ) ? $value : 'current';
	}
}
