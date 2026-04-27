<?php
declare(strict_types=1);

/**
 * Main orchestrator for the Peptide Repo Core plugin.
 *
 * What: Registers all hooks, runs migrations, boots subsystems.
 * Who calls it: peptide-repo-core.php on plugins_loaded.
 * Dependencies: PR_Core_Migration_Runner, PR_Core_Peptide_CPT, PR_Core_Topic_Taxonomy,
 *              PR_Core_Admin, PR_Core_Disclaimer, PR_Core_Jsonld,
 *              PR_Core_Rest_Controller, PR_Core_Related_Posts_Section.
 *
 * @see peptide-repo-core.php — Bootstrap that instantiates this class.
 * @see ARCHITECTURE.md    — Full data flow diagram.
 */
class PR_Core {

	/**
	 * Initialize all plugin subsystems.
	 *
	 * Side effects: registers WordPress hooks, runs pending migrations.
	 *
	 * @return void
	 */
	public function init(): void {
		// Run migrations before anything else.
		$runner = new PR_Core_Migration_Runner();
		$runner->run_pending();

		// Register CPT + taxonomies (fires on init).
		$cpt = new PR_Core_Peptide_CPT();
		$cpt->register_hooks();

		// Register peptide_topic taxonomy (fires on init).
		$topic_tax = new PR_Core_Topic_Taxonomy();
		$topic_tax->register_hooks();

		// Register repo_daily CPT (fires on init).
		$repo_daily_cpt = new PR_Core_Repo_Daily_CPT();
		$repo_daily_cpt->register_hooks();

		// Register repo_daily_category taxonomy (fires on init).
		$repo_daily_tax = new PR_Core_Repo_Daily_Taxonomy();
		$repo_daily_tax->register_hooks();

		// One-shot rewrite flush on in-place version bumps. Runs at the very
		// end of init (priority 999) so all CPTs/taxonomies — ours and
		// anyone else's — are registered first. Handles updates deployed
		// without a deactivate/reactivate cycle (e.g., SCP/rsync pushes).
		add_action( 'init', [ PR_Core_Activator::class, 'maybe_flush_on_version_change' ], 999 );

		// Verification scanner: register cron hook and schedule if not already scheduled.
		if ( ! wp_next_scheduled( 'pr_core_verification_scan' ) ) {
			wp_schedule_event( time(), get_option( 'pr_core_scan_cadence', 'weekly' ), 'pr_core_verification_scan' );
		}
		add_action( 'pr_core_verification_scan', [ PR_Core_Verification_Scanner::class, 'run_scan' ] );

		// Ajax handlers must be registered outside is_admin() — admin-ajax.php
		// does not define WP_ADMIN, so is_admin() returns false for ajax requests.
		add_action( 'wp_ajax_pr_core_mark_verified', [ PR_Core_Ajax_Handlers::class, 'handle_mark_verified' ] );
		add_action( 'wp_ajax_pr_core_scan_now',      [ PR_Core_Ajax_Handlers::class, 'handle_scan_now' ] );

		// Admin UI (fires on admin_init, admin_menu).
		if ( is_admin() ) {
			add_action( 'admin_menu', [ PR_Core_Settings::class, 'add_settings_page' ] );
			add_action( 'admin_init', [ PR_Core_Settings::class, 'register_settings' ] );
			add_action( 'wp_dashboard_setup', [ PR_Core_Verification_Widget::class, 'register' ] );

			$admin = new PR_Core_Admin();
			$admin->register_hooks();
		}

		// Frontend: disclaimer shortcode + JSON-LD + verification display.
		PR_Core_Verification_Display::init();

		$disclaimer = new PR_Core_Disclaimer();
		$disclaimer->register_hooks();

		$jsonld = new PR_Core_Jsonld();
		$jsonld->register_hooks();

		// Related Articles section (frontend).
		$related_posts = new PR_Core_Related_Posts_Section(
			new PR_Core_Internal_Posts_Provider()
		);
		$related_posts->register_hooks();

		// Enqueue related-posts CSS on single peptide pages.
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_styles' ] );

		// REST API.
		$rest = new PR_Core_Rest_Controller();
		$rest->register_hooks();

		// Expose public API filters.
		$this->register_public_filters();
	}

	/**
	 * Enqueue frontend CSS on single peptide pages.
	 *
	 * @return void
	 */
	public function enqueue_frontend_styles(): void {
		if ( ! is_singular( PR_Core_Peptide_CPT::POST_TYPE ) ) {
			return;
		}

		wp_enqueue_style(
			'pr-core-related-posts',
			PR_CORE_PLUGIN_URL . 'assets/css/related-posts.css',
			[],
			PR_CORE_VERSION
		);
	}

	/**
	 * Register the public filter hooks that consumer plugins rely on.
	 *
	 * Filters:
	 * - pr_core_get_indexable_corpus: Returns indexable content for search plugins.
	 * - pr_core_disclaimer_for_surface: Returns disclaimer text for a given surface.
	 * - pr_core_evidence_strength_label: Maps enum to human-readable label.
	 *
	 * @return void
	 */
	private function register_public_filters(): void {
		add_filter( 'pr_core_get_indexable_corpus', [ $this, 'filter_indexable_corpus' ] );
		add_filter( 'pr_core_disclaimer_for_surface', [ $this, 'filter_disclaimer_for_surface' ], 10, 2 );
		add_filter( 'pr_core_evidence_strength_label', [ $this, 'filter_evidence_label' ], 10, 2 );
	}

	/**
	 * Return indexable corpus entries for search plugins (e.g., Peptide Search AI).
	 *
	 * Each entry: { id, url, title, body, type }.
	 *
	 * @param array<int, array<string, mixed>> $entries Existing entries from other filters.
	 * @return array<int, array<string, mixed>>
	 */
	public function filter_indexable_corpus( array $entries ): array {
		$repo = new PR_Core_Peptide_Repository();
		$peptides = $repo->find_all( [ 'status' => 'publish' ] );

		foreach ( $peptides as $dto ) {
			$entries[] = [
				'id'    => $dto->id,
				'url'   => get_permalink( $dto->id ),
				'title' => $dto->title,
				'body'  => $dto->excerpt . "\n" . $dto->content,
				'type'  => 'peptide_monograph',
			];
		}

		return $entries;
	}

	/**
	 * Return disclaimer text for a given surface identifier.
	 *
	 * @param string $text     Existing text (empty string default).
	 * @param string $surface Surface identifier (dosing, legal, reconstitution, ai-answer).
	 * @return string Disclaimer HTML.
	 */
	public function filter_disclaimer_for_surface( string $text, string $surface ): string {
		return PR_Core_Disclaimer::get_disclaimer_text( $surface );
	}

	/**
	 * Map evidence_strength enum value to human-readable label.
	 *
	 * @param string $label     Existing label (empty string default).
	 * @param string $strength Enum value.
	 * @return string Localized label.
	 */
	public function filter_evidence_label( string $label, string $strength ): string {
		$map = [
			'preclinical'    => __( 'Preclinical', 'peptide-repo-core' ),
			'case-series'    => __( 'Case Series', 'peptide-repo-core' ),
			'observational'  => __( 'Observational', 'peptide-repo-core' ),
			'rct-small'      => __( 'Small RCT', 'peptide-repo-core' ),
			'rct-large'      => __( 'Large RCT', 'peptide-repo-core' ),
			'meta-analysis'  => __( 'Meta-Analysis', 'peptide-repo-core' ),
		];

		return $map[ $strength ] ?? $strength;
	}
}
