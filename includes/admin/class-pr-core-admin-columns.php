<?php
declare(strict_types=1);

/**
 * Custom admin list-table columns for the peptide CPT.
 *
 * What: Adds evidence strength, editorial status, and dosing count columns.
 * Who calls it: PR_Core_Admin via manage_posts_columns and custom_column hooks.
 * Dependencies: PR_Core_Dosing_Repository for row counts.
 *
 * @see admin/class-pr-core-admin.php — Registers column hooks.
 */
class PR_Core_Admin_Columns {

	/**
	 * Add custom columns to the peptide list table.
	 *
	 * @param array<string, string> $columns Existing columns.
	 * @return array<string, string> Modified columns.
	 */
	public function add_columns( array $columns ): array {
		$new = [];
		foreach ( $columns as $key => $label ) {
			$new[ $key ] = $label;

			// Insert custom columns after the title column.
			if ( 'title' === $key ) {
				$new['pr_evidence']  = __( 'Evidence', 'peptide-repo-core' );
				$new['pr_editorial'] = __( 'Editorial', 'peptide-repo-core' );
				$new['pr_dosing']    = __( 'Dosing Rows', 'peptide-repo-core' );
			}
		}

		return $new;
	}

	/**
	 * Render custom column content for a peptide row.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 * @return void
	 */
	public function render_column( string $column, int $post_id ): void {
		switch ( $column ) {
			case 'pr_evidence':
				$strength = get_post_meta( $post_id, 'evidence_strength', true ) ?: 'preclinical';
				$label    = apply_filters( 'pr_core_evidence_strength_label', $strength, $strength );
				echo esc_html( $label );
				break;

			case 'pr_editorial':
				$status = get_post_meta( $post_id, 'editorial_review_status', true ) ?: 'draft';
				echo esc_html( ucfirst( str_replace( '-', ' ', $status ) ) );
				break;

			case 'pr_dosing':
				$count = ( new PR_Core_Dosing_Repository() )->count_by_peptide( $post_id );
				echo esc_html( (string) $count );
				break;
		}
	}
}
