<?php
declare(strict_types=1);

/**
 * Meta boxes for the peptide edit screen: scientific identifiers, dosing, and legal.
 *
 * What: Renders and saves three meta boxes on the peptide edit screen.
 * Who calls it: PR_Core_Admin via add_meta_boxes and save_post hooks.
 * Dependencies: PR_Core_Dosing_Repository, PR_Core_Legal_Repository.
 *
 * @see admin/class-pr-core-admin.php — Registers these hooks.
 * @see cpt/class-pr-core-peptide-cpt.php — Meta field definitions.
 */
class PR_Core_Peptide_Metaboxes {

	/**
	 * Register meta boxes on the peptide edit screen.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'pr-core-identifiers',
			__( 'Scientific Identifiers', 'peptide-repo-core' ),
			[ $this, 'render_identifiers_box' ],
			PR_Core_Peptide_CPT::POST_TYPE,
			'normal',
			'high'
		);

		add_meta_box(
			'pr-core-dosing',
			__( 'Dosing Data', 'peptide-repo-core' ),
			[ $this, 'render_dosing_box' ],
			PR_Core_Peptide_CPT::POST_TYPE,
			'normal',
			'default'
		);

		add_meta_box(
			'pr-core-legal',
			__( 'Legal Status by Country', 'peptide-repo-core' ),
			[ $this, 'render_legal_box' ],
			PR_Core_Peptide_CPT::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the Scientific Identifiers meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_identifiers_box( \WP_Post $post ): void {
		wp_nonce_field( 'pr_core_save_meta', 'pr_core_meta_nonce' );

		$fields = [
			'display_name'      => __( 'Display Name', 'peptide-repo-core' ),
			'aliases'           => __( 'Aliases (JSON array)', 'peptide-repo-core' ),
			'molecular_formula' => __( 'Molecular Formula', 'peptide-repo-core' ),
			'molecular_weight'  => __( 'Molecular Weight (Da)', 'peptide-repo-core' ),
			'cas_number'        => __( 'CAS Number', 'peptide-repo-core' ),
			'drugbank_id'       => __( 'DrugBank ID', 'peptide-repo-core' ),
			'chembl_id'         => __( 'ChEMBL ID', 'peptide-repo-core' ),
		];

		echo '<table class="form-table pr-core-meta-table">';
		foreach ( $fields as $key => $label ) {
			$value = esc_attr( (string) get_post_meta( $post->ID, $key, true ) );
			printf(
				'<tr><th><label for="pr_core_%1$s">%2$s</label></th><td><input type="text" id="pr_core_%1$s" name="pr_core_%1$s" value="%3$s" class="regular-text" /></td></tr>',
				esc_attr( $key ),
				esc_html( $label ),
				$value
			);
		}

		// Evidence strength dropdown.
		$current_strength = get_post_meta( $post->ID, 'evidence_strength', true ) ?: 'preclinical';
		echo '<tr><th><label for="pr_core_evidence_strength">' . esc_html__( 'Evidence Strength', 'peptide-repo-core' ) . '</label></th><td><select id="pr_core_evidence_strength" name="pr_core_evidence_strength">';
		foreach ( PR_Core_Peptide_CPT::EVIDENCE_STRENGTHS as $strength ) {
			$label = apply_filters( 'pr_core_evidence_strength_label', $strength, $strength );
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $strength ),
				selected( $current_strength, $strength, false ),
				esc_html( $label )
			);
		}
		echo '</select></td></tr>';

		// Editorial review status.
		$current_review = get_post_meta( $post->ID, 'editorial_review_status', true ) ?: 'draft';
		echo '<tr><th><label for="pr_core_editorial_review_status">' . esc_html__( 'Editorial Status', 'peptide-repo-core' ) . '</label></th><td><select id="pr_core_editorial_review_status" name="pr_core_editorial_review_status">';
		foreach ( PR_Core_Peptide_CPT::REVIEW_STATUSES as $status ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $status ),
				selected( $current_review, $status, false ),
				esc_html( ucfirst( str_replace( '-', ' ', $status ) ) )
			);
		}
		echo '</select></td></tr>';

		echo '</table>';
	}

	/**
	 * Render the Dosing Data meta box (read-only summary with link to manage).
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_dosing_box( \WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the peptide first, then add dosing data.', 'peptide-repo-core' ) . '</p>';
			return;
		}

		$repo = new PR_Core_Dosing_Repository();
		$rows = $repo->find_by_peptide( $post->ID );

		if ( empty( $rows ) ) {
			echo '<p>' . esc_html__( 'No dosing rows yet.', 'peptide-repo-core' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Route', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Dose Range', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Population', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Evidence', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Source', 'peptide-repo-core' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $rows as $row ) {
				$dose = $row->dose_min !== null ? number_format( $row->dose_min, 2 ) : '?';
				if ( $row->dose_max !== null && $row->dose_max !== $row->dose_min ) {
					$dose .= ' – ' . number_format( $row->dose_max, 2 );
				}
				$dose .= ' ' . esc_html( $row->dose_unit );

				$evidence_label = apply_filters( 'pr_core_evidence_strength_label', $row->evidence_strength, $row->evidence_strength );

				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $row->route ),
					esc_html( $dose ),
					esc_html( $row->population ),
					esc_html( $evidence_label ),
					esc_html( $row->source )
				);
			}
			echo '</tbody></table>';
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Dosing rows are managed via the REST API or AI Candidate Queue. Direct admin editing coming in v0.2.', 'peptide-repo-core' )
		);
	}

	/**
	 * Render the Legal Status meta box (read-only summary).
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_legal_box( \WP_Post $post ): void {
		if ( 'auto-draft' === $post->post_status ) {
			echo '<p>' . esc_html__( 'Save the peptide first, then add legal data.', 'peptide-repo-core' ) . '</p>';
			return;
		}

		$repo  = new PR_Core_Legal_Repository();
		$cells = $repo->find_by_peptide( $post->ID );

		if ( empty( $cells ) ) {
			echo '<p>' . esc_html__( 'No legal status data yet.', 'peptide-repo-core' ) . '</p>';
		} else {
			echo '<table class="widefat striped"><thead><tr>';
			echo '<th>' . esc_html__( 'Country', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Status', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Framework', 'peptide-repo-core' ) . '</th>';
			echo '<th>' . esc_html__( 'Verified', 'peptide-repo-core' ) . '</th>';
			echo '</tr></thead><tbody>';

			foreach ( $cells as $cell ) {
				printf(
					'<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
					esc_html( $cell->country_code ),
					esc_html( ucfirst( $cell->status ) ),
					esc_html( $cell->regulatory_framework ?? '—' ),
					esc_html( $cell->last_verified_at )
				);
			}
			echo '</tbody></table>';
		}

		printf(
			'<p class="description">%s</p>',
			esc_html__( 'Legal cells are managed via the REST API. Direct admin editing coming in v0.2.', 'peptide-repo-core' )
		);
	}

	/**
	 * Save peptide meta fields on post save.
	 *
	 * Side effects: updates post meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['pr_core_meta_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pr_core_meta_nonce'] ) ), 'pr_core_save_meta' ) ) {
			return;
		}

		if ( ! current_user_can( PR_Core_Peptide_CPT::CAPABILITY ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		$meta_fields = PR_Core_Peptide_CPT::get_meta_fields();

		foreach ( $meta_fields as $key => $config ) {
			$input_key = 'pr_core_' . $key;
			if ( ! isset( $_POST[ $input_key ] ) ) {
				continue;
			}

			$raw   = wp_unslash( $_POST[ $input_key ] );
			$clean = is_callable( $config['sanitize'] )
				? call_user_func( $config['sanitize'], $raw )
				: sanitize_text_field( (string) $raw );

			update_post_meta( $post_id, $key, $clean );
		}

		// Set last_editorial_review_at when status changes to published.
		$new_review_status = sanitize_text_field( $_POST['pr_core_editorial_review_status'] ?? '' );
		if ( 'published' === $new_review_status ) {
			$old_status = get_post_meta( $post_id, 'editorial_review_status', true );
			if ( $old_status !== 'published' ) {
				update_post_meta( $post_id, 'last_editorial_review_at', current_time( 'mysql' ) );
				update_post_meta( $post_id, 'medical_editor_id', get_current_user_id() );
			}
		}

		do_action( 'pr_core_after_peptide_publish', $post_id, $post );
	}
}
