<?php
declare(strict_types=1);

/**
 * Verification status meta box for peptide edit screen.
 *
 * What: Renders and saves verification status sidebar meta box.
 * Who calls it: PR_Core_Admin via add_meta_boxes and save_post hooks.
 * Dependencies: None.
 *
 * @see admin/class-pr-core-peptide-metaboxes.php — Other edit-screen meta boxes.
 */
class PR_Core_Verification_Metabox {

	/**
	 * Register the verification meta box.
	 *
	 * @return void
	 */
	public static function register(): void {
		add_meta_box(
			'pr-core-verification',
			__( 'Verification Status', 'peptide-repo-core' ),
			[ __CLASS__, 'render' ],
			PR_Core_Peptide_CPT::POST_TYPE,
			'side',
			'default'
		);
	}

	/**
	 * Render the verification status meta box (sidebar).
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public static function render( \WP_Post $post ): void {
		wp_nonce_field( 'pr_core_verification_nonce', 'pr_core_verification_nonce' );

		$last_verified = get_post_meta( $post->ID, '_pr_last_source_verified', true );
		$velocity      = get_post_meta( $post->ID, '_pr_verification_velocity', true ) ?: 'medium';
		$status        = get_post_meta( $post->ID, '_pr_verification_status', true ) ?: 'current';
		$notes         = get_post_meta( $post->ID, '_pr_verification_notes', true ) ?: '';

		echo '<p><strong>' . esc_html__( 'Last Verified', 'peptide-repo-core' ) . ':</strong><br />';
		echo '<code style="color: #666;">' . esc_html( $last_verified ?: '—' ) . '</code></p>';

		echo '<p><strong>' . esc_html__( 'Verification Velocity', 'peptide-repo-core' ) . ':</strong><br />';
		echo '<select name="pr_core__pr_verification_velocity" style="width:100%;">';
		foreach ( [ 'low' => 'Low', 'medium' => 'Medium', 'high' => 'High' ] as $val => $label ) {
			printf(
				'<option value="%s"%s>%s</option>',
				esc_attr( $val ),
				selected( $velocity, $val, false ),
				esc_html( $label )
			);
		}
		echo '</select></p>';

		echo '<p><strong>' . esc_html__( 'Status', 'peptide-repo-core' ) . ':</strong><br />';
		$badge_class = match( $status ) {
			'due' => 'style="background:#ff9800;color:white;padding:4px 8px;border-radius:3px;"',
			'overdue' => 'style="background:#f44336;color:white;padding:4px 8px;border-radius:3px;"',
			default => 'style="background:#4caf50;color:white;padding:4px 8px;border-radius:3px;"',
		};
		echo '<span ' . $badge_class . '>' . esc_html( ucfirst( $status ) ) . '</span></p>';

		echo '<p><strong>' . esc_html__( 'Verification Notes', 'peptide-repo-core' ) . ':</strong><br />';
		printf(
			'<textarea name="pr_core__pr_verification_notes" rows="3" style="width:100%;">%s</textarea>',
			esc_textarea( $notes )
		);
		echo '</p>';

		echo '<button type="button" class="button button-primary" id="pr-core-mark-verified">' . esc_html__( 'Mark Verified Today', 'peptide-repo-core' ) . '</button>';

		echo '<script>
		document.getElementById("pr-core-mark-verified").addEventListener("click", function() {
			const data = new FormData();
			data.append("action", "pr_core_mark_verified");
			data.append("post_id", ' . (int) $post->ID . ');
			data.append("nonce", document.querySelector("[name=\'_wpnonce\']").value);
			data.append("notes", document.querySelector("[name=\'pr_core__pr_verification_notes\']").value);
			fetch(ajaxurl, { method: "POST", body: data })
				.then(r => r.json())
				.then(d => { if(d.success) { location.reload(); } else { alert("Error: " + (d.data || "Unknown")); }})
				.catch(e => { alert("Error: " + e); });
		});
		</script>';
	}
}
