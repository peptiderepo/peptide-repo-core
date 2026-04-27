<?php
declare(strict_types=1);

/**
 * AJAX handlers for verification scanner actions.
 *
 * What: Handles mark-verified and scan-now admin-ajax actions.
 * Who calls it: PR_Core::init() registers the wp_ajax hooks.
 * Dependencies: None.
 *
 * @see admin/class-pr-core-peptide-metaboxes.php — "Mark verified today" button in sidebar.
 * @see admin/class-pr-core-verification-widget.php — "Scan now" button in dashboard widget.
 */
class PR_Core_Ajax_Handlers {

	/**
	 * Handle mark-verified AJAX action.
	 *
	 * Updates _pr_last_source_verified to today, saves notes, recomputes status.
	 *
	 * Side effects: Updates post meta, returns JSON.
	 *
	 * @return void
	 */
	public static function handle_mark_verified(): void {
		// Verify nonce.
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'wp_nonce' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Get post ID and verify capability.
		$post_id = absint( wp_unslash( $_POST['post_id'] ?? 0 ) );
		if ( ! $post_id || ! current_user_can( PR_Core_Peptide_CPT::CAPABILITY ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Update verified date.
		update_post_meta( $post_id, '_pr_last_source_verified', current_time( 'mysql' ) );

		// Save notes if provided.
		$notes = sanitize_textarea_field( wp_unslash( $_POST['notes'] ?? '' ) );
		if ( $notes ) {
			update_post_meta( $post_id, '_pr_verification_notes', $notes );
		}

		// Recompute status to 'current'.
		update_post_meta( $post_id, '_pr_verification_status', 'current' );

		wp_send_json_success( [ 'message' => 'Marked verified' ] );
	}

	/**
	 * Handle scan-now AJAX action.
	 *
	 * Runs verification scanner immediately.
	 *
	 * Side effects: Runs scan, returns JSON.
	 *
	 * @return void
	 */
	public static function handle_scan_now(): void {
		// Verify nonce.
		$nonce = sanitize_text_field( wp_unslash( $_POST['nonce'] ?? '' ) );
		if ( ! wp_verify_nonce( $nonce, 'pr_core_scan_now' ) ) {
			wp_send_json_error( 'Invalid nonce' );
		}

		// Verify capability.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		// Run scan.
		PR_Core_Verification_Scanner::run_scan();

		wp_send_json_success( [ 'message' => 'Scan complete' ] );
	}
}
