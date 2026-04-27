<?php
declare(strict_types=1);

/**
 * Admin initialization and hook registration.
 *
 * What: Registers admin menu pages, meta boxes, and custom columns for peptides.
 * Who calls it: PR_Core::init() when is_admin().
 * Dependencies: PR_Core_Peptide_Metaboxes, PR_Core_Verification_Metabox,
 *               PR_Core_Candidate_Queue_Page, PR_Core_Admin_Columns.
 *
 * @see admin/class-pr-core-peptide-metaboxes.php    — Dosing + legal meta boxes.
 * @see admin/class-pr-core-verification-metabox.php — Verification sidebar meta box.
 * @see admin/class-pr-core-candidate-queue-page.php — Candidate review screen.
 * @see admin/class-pr-core-admin-columns.php        — Custom list-table columns.
 */
class PR_Core_Admin {

	/**
	 * Register all admin hooks.
	 *
	 * Side effects: registers add_meta_boxes, save_post, admin_menu hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		$metaboxes = new PR_Core_Peptide_Metaboxes();
		add_action( 'add_meta_boxes', [ $metaboxes, 'add_meta_boxes' ] );
		add_action( 'save_post_' . PR_Core_Peptide_CPT::POST_TYPE, [ $metaboxes, 'save_meta' ], 10, 2 );

		add_action( 'add_meta_boxes', [ PR_Core_Verification_Metabox::class, 'register' ] );

		$columns = new PR_Core_Admin_Columns();
		add_filter( 'manage_' . PR_Core_Peptide_CPT::POST_TYPE . '_posts_columns', [ $columns, 'add_columns' ] );
		add_action( 'manage_' . PR_Core_Peptide_CPT::POST_TYPE . '_posts_custom_column', [ $columns, 'render_column' ], 10, 2 );

		// Repo Daily meta boxes.
		$repo_daily_metaboxes = new PR_Core_Repo_Daily_Metaboxes();
		add_action( 'add_meta_boxes', [ $repo_daily_metaboxes, 'add_meta_boxes' ] );
		add_action( 'save_post_' . PR_Core_Repo_Daily_CPT::POST_TYPE, [ $repo_daily_metaboxes, 'save_meta' ], 10, 2 );

		add_action( 'admin_menu', [ $this, 'add_admin_pages' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_styles' ] );
	}

	/**
	 * Add the Candidate Queue submenu page under the Peptides menu.
	 *
	 * Side effects: registers admin menu page.
	 *
	 * @return void
	 */
	public function add_admin_pages(): void {
		$pending_count = ( new PR_Core_Candidate_Queue_Repository() )->count_by_status( 'pending' );
		$badge         = $pending_count > 0 ? sprintf( ' <span class="awaiting-mod">%d</span>', $pending_count ) : '';

		add_submenu_page(
			'edit.php?post_type=' . PR_Core_Peptide_CPT::POST_TYPE,
			__( 'AI Candidate Queue', 'peptide-repo-core' ),
			__( 'Candidate Queue', 'peptide-repo-core' ) . $badge,
			PR_Core_Peptide_CPT::CAPABILITY,
			'pr-core-candidates',
			[ new PR_Core_Candidate_Queue_Page(), 'render' ]
		);
	}

	/**
	 * Enqueue minimal admin CSS for meta boxes.
	 *
	 * @param string $hook_suffix Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_styles( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( ! $screen || PR_Core_Peptide_CPT::POST_TYPE !== $screen->post_type ) {
			return;
		}

		wp_enqueue_style(
			'pr-core-admin',
			PR_CORE_PLUGIN_URL . 'assets/css/admin.css',
			[],
			PR_CORE_VERSION
		);
	}
}
