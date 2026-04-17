<?php
declare(strict_types=1);

/**
 * Admin page for the AI Candidate Queue.
 *
 * What: Renders a review screen for AI-extracted dosing candidates.
 * Who calls it: PR_Core_Admin::add_admin_pages() registers this as a submenu page.
 * Dependencies: PR_Core_Candidate_Queue_Repository, PR_Core_Peptide_Repository.
 *
 * Researchers see pending candidates sorted by confidence, can approve/reject with notes.
 *
 * @see admin/class-pr-core-admin.php                              — Menu registration.
 * @see repositories/class-pr-core-candidate-queue-repository.php  — Data layer.
 */
class PR_Core_Candidate_Queue_Page {

	/**
	 * Render the candidate queue admin page.
	 *
	 * Side effects: processes approve/reject actions, outputs HTML.
	 *
	 * @return void
	 */
	public function render(): void {
		if ( ! current_user_can( PR_Core_Peptide_CPT::CAPABILITY ) ) {
			wp_die( esc_html__( 'You do not have permission to access this page.', 'peptide-repo-core' ) );
		}

		$this->handle_actions();

		$repo       = new PR_Core_Candidate_Queue_Repository();
		$candidates = $repo->find_by_status( 'pending' );
		$pep_repo   = new PR_Core_Peptide_Repository();

		echo '<div class="wrap">';
		echo '<h1>' . esc_html__( 'AI Candidate Queue', 'peptide-repo-core' ) . '</h1>';
		echo '<p>' . esc_html__( 'Review AI-extracted dosing data candidates. Approved rows are copied to the dosing table.', 'peptide-repo-core' ) . '</p>';

		if ( empty( $candidates ) ) {
			echo '<p><strong>' . esc_html__( 'No pending candidates.', 'peptide-repo-core' ) . '</strong></p>';
			echo '</div>';
			return;
		}

		echo '<table class="widefat striped">';
		echo '<thead><tr>';
		echo '<th>' . esc_html__( 'Peptide', 'peptide-repo-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Route', 'peptide-repo-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Dose Range', 'peptide-repo-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Population', 'peptide-repo-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Study', 'peptide-repo-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Confidence', 'peptide-repo-core' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'peptide-repo-core' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $candidates as $c ) {
			$peptide = $pep_repo->find_by_id( $c->peptide_id );
			$name    = $peptide ? $peptide->title : '#' . $c->peptide_id;

			$dose = $c->dose_min !== null ? number_format( $c->dose_min, 2 ) : '?';
			if ( $c->dose_max !== null && $c->dose_max !== $c->dose_min ) {
				$dose .= ' – ' . number_format( $c->dose_max, 2 );
			}
			$dose .= ' ' . esc_html( $c->dose_unit );

			$study = $c->study_title ? esc_html( mb_substr( $c->study_title, 0, 60 ) ) : '—';
			if ( $c->citation_pubmed_id ) {
				$study .= sprintf( ' <a href="https://pubmed.ncbi.nlm.nih.gov/%s/" target="_blank">PubMed</a>', esc_attr( $c->citation_pubmed_id ) );
			}

			$confidence_pct = number_format( $c->extraction_confidence * 100, 0 ) . '%';
			$approve_url    = wp_nonce_url(
				add_query_arg( [ 'action' => 'pr_core_approve', 'candidate_id' => $c->id ] ),
				'pr_core_queue_action'
			);
			$reject_url     = wp_nonce_url(
				add_query_arg( [ 'action' => 'pr_core_reject', 'candidate_id' => $c->id ] ),
				'pr_core_queue_action'
			);

			echo '<tr>';
			printf( '<td>%s</td>', esc_html( $name ) );
			printf( '<td>%s</td>', esc_html( $c->route ) );
			printf( '<td>%s</td>', esc_html( $dose ) );
			printf( '<td>%s</td>', esc_html( $c->population ) );
			printf( '<td>%s</td>', $study ); // Contains escaped HTML + link.
			printf( '<td>%s</td>', esc_html( $confidence_pct ) );
			printf(
				'<td><a href="%s" class="button button-primary button-small">%s</a> <a href="%s" class="button button-small">%s</a></td>',
				esc_url( $approve_url ),
				esc_html__( 'Approve', 'peptide-repo-core' ),
				esc_url( $reject_url ),
				esc_html__( 'Reject', 'peptide-repo-core' )
			);
			echo '</tr>';
		}

		echo '</tbody></table></div>';
	}

	/**
	 * Process approve/reject actions from query parameters.
	 *
	 * Side effects: database writes, admin notices.
	 *
	 * @return void
	 */
	private function handle_actions(): void {
		$action = sanitize_text_field( $_GET['action'] ?? '' );

		if ( ! in_array( $action, [ 'pr_core_approve', 'pr_core_reject' ], true ) ) {
			return;
		}

		if ( ! check_admin_referer( 'pr_core_queue_action' ) ) {
			return;
		}

		$candidate_id = absint( $_GET['candidate_id'] ?? 0 );
		if ( 0 === $candidate_id ) {
			return;
		}

		$repo        = new PR_Core_Candidate_Queue_Repository();
		$reviewer_id = get_current_user_id();

		if ( 'pr_core_approve' === $action ) {
			$dosing_id = $repo->approve( $candidate_id, $reviewer_id );
			if ( $dosing_id > 0 ) {
				add_action( 'admin_notices', static function () {
					echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Candidate approved and added to dosing data.', 'peptide-repo-core' ) . '</p></div>';
				} );
			}
		} else {
			$repo->reject( $candidate_id, $reviewer_id );
			add_action( 'admin_notices', static function () {
				echo '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Candidate rejected.', 'peptide-repo-core' ) . '</p></div>';
			} );
		}
	}
}
