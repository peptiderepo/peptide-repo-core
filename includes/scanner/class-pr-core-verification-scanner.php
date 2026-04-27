<?php
declare(strict_types=1);

/**
 * Verification scanner for peptide monographs.
 *
 * What: Runs periodic scans of all published peptides, computes verification status
 *       (current/due/overdue), logs results, and sends digest notifications.
 * Who calls it: PR_Core::init() hooks wp_schedule_event; runs via wp_cron.
 * Dependencies: PR_Core_Peptide_Repository, wp_mail().
 *
 * @see admin/class-pr-core-settings.php — Cadence and threshold settings.
 * @see cpt/class-pr-core-peptide-cpt.php — Verification meta field specs.
 */
class PR_Core_Verification_Scanner {

	const DEFAULT_THRESHOLD_DAYS  = 180;
	const HIGH_VELOCITY_THRESHOLD = 60;
	const LOW_VELOCITY_THRESHOLD  = 365;
	const SCAN_LOG_OPTION_KEY     = 'pr_core_verification_scan_log';
	const MAX_LOG_ENTRIES         = 90;

	/**
	 * Run verification scan on all published peptides.
	 *
	 * Side effects: Updates _pr_verification_status, appends to scan log, sends email.
	 *
	 * @return void
	 */
	public static function run_scan(): void {
		$repo = new PR_Core_Peptide_Repository();
		$peptides = $repo->find_all( [ 'status' => 'publish' ] );

		$due_peptides = [];
		$overdue_peptides = [];

		foreach ( $peptides as $dto ) {
			$last_verified = get_post_meta( $dto->id, '_pr_last_source_verified', true );
			$velocity      = get_post_meta( $dto->id, '_pr_verification_velocity', true ) ?: 'medium';

			// Empty verified date = treat as overdue.
			if ( empty( $last_verified ) ) {
				update_post_meta( $dto->id, '_pr_verification_status', 'overdue' );
				$overdue_peptides[] = $dto;
				continue;
			}

			// Delegate to compute_status() — keeps logic testable without DB calls.
			$status = self::compute_status( $last_verified, $velocity );

			update_post_meta( $dto->id, '_pr_verification_status', $status );

			if ( 'due' === $status ) {
				$due_peptides[] = $dto;
			} elseif ( 'overdue' === $status ) {
				$overdue_peptides[] = $dto;
			}
		}

		// Log the scan result.
		self::log_scan( count( $peptides ), count( $due_peptides ), count( $overdue_peptides ) );

		// Send digest email if configured and there are items needing review.
		if ( ! empty( $due_peptides ) || ! empty( $overdue_peptides ) ) {
			self::send_digest_email( $due_peptides, $overdue_peptides );
		}
	}


	/**
	 * Compute verification status for a single peptide.
	 *
	 * Extracted for direct unit-testability without database or WP-cron dependencies.
	 *
	 * @param string $last_verified YYYY-MM-DD date of last source verification. Empty = overdue.
	 * @param string $velocity      Velocity tier: high | medium | low.
	 * @return string Status: current | due | overdue.
	 */
	public static function compute_status( string $last_verified, string $velocity ): string {
		if ( empty( $last_verified ) ) {
			return 'overdue';
		}

		$days_since = ( time() - strtotime( $last_verified ) ) / DAY_IN_SECONDS;
		$threshold  = match ( $velocity ) {
			'high' => (int) get_option( 'pr_core_high_velocity_threshold', self::HIGH_VELOCITY_THRESHOLD ),
			'low'  => self::LOW_VELOCITY_THRESHOLD,
			default => (int) get_option( 'pr_core_default_threshold', self::DEFAULT_THRESHOLD_DAYS ),
		};

		return $days_since < ( $threshold * 0.9 )
			? 'current'
			: ( $days_since < $threshold ? 'due' : 'overdue' );
	}

	/**
	 * Log scan summary to WP option, keeping last N entries.
	 *
	 * @param int $total  Total peptides scanned.
	 * @param int $due    Count of due peptides.
	 * @param int $overdue Count of overdue peptides.
	 * @return void
	 */
	private static function log_scan( int $total, int $due, int $overdue ): void {
		$log = get_option( self::SCAN_LOG_OPTION_KEY, [] );
		if ( ! is_array( $log ) ) {
			$log = [];
		}

		$log[] = [
			'timestamp' => current_time( 'mysql' ),
			'total'     => $total,
			'due'       => $due,
			'overdue'   => $overdue,
		];

		// Keep only the last MAX_LOG_ENTRIES.
		if ( count( $log ) > self::MAX_LOG_ENTRIES ) {
			$log = array_slice( $log, -self::MAX_LOG_ENTRIES );
		}

		update_option( self::SCAN_LOG_OPTION_KEY, $log );
	}

	/**
	 * Send digest email to configured recipient(s).
	 *
	 * @param array<PR_Core_Peptide_DTO> $due_peptides Peptides due for review.
	 * @param array<PR_Core_Peptide_DTO> $overdue_peptides Peptides overdue for review.
	 * @return void
	 */
	private static function send_digest_email( array $due_peptides, array $overdue_peptides ): void {
		$recipients = get_option( 'pr_core_verification_email', '' );
		if ( empty( $recipients ) ) {
			return;
		}

		// Parse comma-separated emails.
		$emails = array_map( 'trim', explode( ',', $recipients ) );
		$emails = array_filter( $emails, static function ( $email ) {
			return is_email( $email );
		} );

		if ( empty( $emails ) ) {
			return;
		}

		$subject = sprintf(
			'[Peptide Repo] Verification digest: %d due, %d overdue',
			count( $due_peptides ),
			count( $overdue_peptides )
		);

		$body = self::build_email_body( $due_peptides, $overdue_peptides );

		wp_mail( $emails, $subject, $body, [ 'Content-Type: text/html; charset=UTF-8' ] );
	}

	/**
	 * Build HTML email body for digest.
	 *
	 * @param array<PR_Core_Peptide_DTO> $due_peptides Peptides due for review.
	 * @param array<PR_Core_Peptide_DTO> $overdue_peptides Peptides overdue for review.
	 * @return string HTML email body.
	 */
	private static function build_email_body( array $due_peptides, array $overdue_peptides ): string {
		$body = '<h2>Verification Digest</h2>';

		if ( ! empty( $overdue_peptides ) ) {
			$body .= '<h3>Overdue for review (' . count( $overdue_peptides ) . ')</h3><ul>';
			foreach ( $overdue_peptides as $dto ) {
				$edit_url = get_edit_post_link( $dto->id );
				$body .= sprintf(
					'<li><a href="%s">%s</a></li>',
					esc_url( $edit_url ),
					esc_html( $dto->title )
				);
			}
			$body .= '</ul>';
		}

		if ( ! empty( $due_peptides ) ) {
			$body .= '<h3>Due for review (' . count( $due_peptides ) . ')</h3><ul>';
			foreach ( $due_peptides as $dto ) {
				$edit_url = get_edit_post_link( $dto->id );
				$body .= sprintf(
					'<li><a href="%s">%s</a></li>',
					esc_url( $edit_url ),
					esc_html( $dto->title )
				);
			}
			$body .= '</ul>';
		}

		$body .= '<p><a href="' . esc_url( admin_url( 'index.php?page=pr-core-verification' ) ) . '">View verification dashboard</a></p>';

		return $body;
	}
}
