<?php
declare(strict_types=1);

/**
 * Dashboard widget for verification status overview.
 *
 * What: WordPress dashboard widget showing peptides needing review (due/overdue).
 * Who calls it: PR_Core::init() hooks wp_dashboard_setup.
 * Dependencies: PR_Core_Peptide_Repository.
 *
 * @see scanner/class-pr-core-verification-scanner.php — Computes status via run_scan().
 */
class PR_Core_Verification_Widget {

	/**
	 * Register the dashboard widget.
	 *
	 * Side effects: Registers WP dashboard widget.
	 *
	 * @return void
	 */
	public static function register(): void {
		wp_add_dashboard_widget(
			'pr_core_verification_status',
			__( 'Monographs Needing Review', 'peptide-repo-core' ),
			[ __CLASS__, 'render' ]
		);
	}

	/**
	 * Render the widget content.
	 *
	 * @return void
	 */
	public static function render(): void {
		$repo = new PR_Core_Peptide_Repository();
		$peptides = $repo->find_all( [ 'status' => 'publish' ] );

		$due_overdue = [];

		foreach ( $peptides as $dto ) {
			$status = get_post_meta( $dto->id, '_pr_verification_status', true ) ?: 'current';

			if ( in_array( $status, [ 'due', 'overdue' ], true ) ) {
				$last_verified = get_post_meta( $dto->id, '_pr_last_source_verified', true );
				$days_since    = $last_verified
					? (int) ( ( time() - strtotime( $last_verified ) ) / DAY_IN_SECONDS )
					: 999;

				$due_overdue[] = [
					'id'           => $dto->id,
					'title'        => $dto->title,
					'status'       => $status,
					'velocity'     => get_post_meta( $dto->id, '_pr_verification_velocity', true ) ?: 'medium',
					'last_verified' => $last_verified,
					'days_since'   => $days_since,
				];
			}
		}

		// Sort by days_since descending (most overdue first).
		usort( $due_overdue, static function ( $a, $b ) {
			return $b['days_since'] <=> $a['days_since'];
		} );

		if ( empty( $due_overdue ) ) {
			echo '<p>' . esc_html__( 'All monographs are current. ', 'peptide-repo-core' );
			echo esc_html__( 'Next scan: ', 'peptide-repo-core' );
			$next = wp_next_scheduled( 'pr_core_verification_scan' );
			if ( $next ) {
				echo esc_html( wp_date( 'Y-m-d H:i:s', $next ) );
			} else {
				echo esc_html__( '(not scheduled)', 'peptide-repo-core' );
			}
			echo '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Title', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Verdict', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Velocity', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Last Verified', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Days Overdue', 'peptide-repo-core' ) . '</th>';
			echo '<th></th>';
			echo '</tr></thead><tbody>';

			foreach ( $due_overdue as $item ) {
				$edit_url = get_edit_post_link( $item['id'] );
				printf(
					'<tr><td><a href="%s">%s</a></td><td><span class="badge badge-%s">%s</span></td><td>%s</td><td>%s</td><td>%d</td><td><a href="%s" class="button button-small">Edit</a></td></tr>',
					esc_url( get_permalink( $item['id'] ) ),
					esc_html( $item['title'] ),
					esc_attr( $item['status'] ),
					esc_html( ucfirst( $item['status'] ) ),
					esc_html( ucfirst( $item['velocity'] ) ),
					esc_html( $item['last_verified'] ?? '—' ),
					$item['days_since'],
					esc_url( $edit_url )
				);
			}

			echo '</tbody></table>';
		}

		echo '<p>';
		$nonce = wp_create_nonce( 'pr_core_scan_now' );
		printf(
			'<form method="post" style="display:inline;"><input type="hidden" name="action" value="pr_core_scan_now" /><input type="hidden" name="nonce" value="%s" /><button type="submit" class="button button-primary">%s</button></form>',
			esc_attr( $nonce ),
			esc_html__( 'Scan Now', 'peptide-repo-core' )
		);
		echo '</p>';
	}
}
