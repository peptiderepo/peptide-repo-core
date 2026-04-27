<?php
declare(strict_types=1);

/**
 * Meta boxes for Repo Daily edit screen: author and clinical review flag.
 *
 * What: Renders and saves two meta fields on the repo_daily edit screen.
 * Who calls it: PR_Core_Admin via add_meta_boxes and save_post hooks.
 * Dependencies: None.
 *
 * Meta fields:
 * - `_repo_daily_author` (string, default "Boo Sheeran") — byline displayed in templates.
 * - `_repo_daily_clinical_review_required` (bool, stored as '1'/'') — editorial flag.
 *
 * @see admin/class-pr-core-admin.php — Registers these hooks.
 * @see cpt/class-pr-core-repo-daily-cpt.php — CPT definition.
 */
class PR_Core_Repo_Daily_Metaboxes {

	/** @var string Default author byline for Repo Daily articles. */
	private const DEFAULT_AUTHOR = 'Boo Sheeran';

	/**
	 * Register meta box on the repo_daily edit screen.
	 *
	 * @return void
	 */
	public function add_meta_boxes(): void {
		add_meta_box(
			'pr-core-repo-daily-meta',
			__( 'Article Settings', 'peptide-repo-core' ),
			[ $this, 'render_meta_box' ],
			PR_Core_Repo_Daily_CPT::POST_TYPE,
			'normal',
			'default'
		);
	}

	/**
	 * Render the Article Settings meta box.
	 *
	 * @param \WP_Post $post Current post.
	 * @return void
	 */
	public function render_meta_box( \WP_Post $post ): void {
		wp_nonce_field( 'pr_core_repo_daily_meta', 'pr_core_repo_daily_nonce' );

		$author = sanitize_text_field(
			get_post_meta( $post->ID, '_repo_daily_author', true ) ?: self::DEFAULT_AUTHOR
		);
		$requires_review = get_post_meta( $post->ID, '_repo_daily_clinical_review_required', true );

		echo '<table class="form-table pr-core-meta-table">';

		// Author field.
		printf(
			'<tr><th><label for="pr_core_repo_daily_author">%s</label></th><td><input type="text" id="pr_core_repo_daily_author" name="pr_core_repo_daily_author" value="%s" class="regular-text" /></td></tr>',
			esc_html__( 'Author / Byline', 'peptide-repo-core' ),
			esc_attr( $author )
		);

		// Clinical review flag.
		printf(
			'<tr><th><label for="pr_core_repo_daily_clinical_review_required">%s</label></th><td><input type="checkbox" id="pr_core_repo_daily_clinical_review_required" name="pr_core_repo_daily_clinical_review_required" value="1" %s /> <span class="description">%s</span></td></tr>',
			esc_html__( 'Clinical Review Required', 'peptide-repo-core' ),
			checked( $requires_review, '1', false ),
			esc_html__( 'Flag for PR Clinical Review before publish', 'peptide-repo-core' )
		);

		echo '</table>';
	}

	/**
	 * Save repo_daily meta fields on post save.
	 *
	 * Side effects: updates post meta.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return void
	 */
	public function save_meta( int $post_id, \WP_Post $post ): void {
		if ( ! isset( $_POST['pr_core_repo_daily_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pr_core_repo_daily_nonce'] ) ), 'pr_core_repo_daily_meta' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		// Save author field.
		if ( isset( $_POST['pr_core_repo_daily_author'] ) ) {
			$author = sanitize_text_field( wp_unslash( $_POST['pr_core_repo_daily_author'] ) );
			// Use default if empty.
			if ( empty( $author ) ) {
				$author = self::DEFAULT_AUTHOR;
			}
			update_post_meta( $post_id, '_repo_daily_author', $author );
		}

		// Save clinical review flag.
		$requires_review = isset( $_POST['pr_core_repo_daily_clinical_review_required'] ) ? '1' : '';
		update_post_meta( $post_id, '_repo_daily_clinical_review_required', $requires_review );
	}
}
