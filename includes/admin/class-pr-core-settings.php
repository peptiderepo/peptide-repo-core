<?php
/**
 * Settings.
 *
 * @package Peptide_Repo_Core
 */

declare(strict_types=1);

/**
 * Settings page for PR Core plugin configuration.
 *
 * What: Static admin settings class. Registers and renders the PR Core settings
 *       page under Peptides, with sections for Related Articles and Verification.
 * Who calls it: PR_Core::init() via admin_menu and admin_init hooks.
 * Dependencies: PR_Core_Peptide_CPT (for CAPABILITY constant).
 *
 * @see class-pr-core.php — Registers admin_menu + admin_init hooks pointing here.
 */
class PR_Core_Settings {

	/** @var string Option group shared by all PR Core settings. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const OPTION_GROUP = 'pr_core_settings';

	/** @var string Option key: enable/disable related articles. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const RELATED_ENABLED = 'pr_core_related_posts_enabled';

	/** @var string Option key: related articles limit (1-6). */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const RELATED_LIMIT = 'pr_core_related_posts_limit';

	/** @var string Option key: WP-cron scan cadence. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const SCAN_CADENCE = 'pr_core_scan_cadence';

	/** @var string Option key: default staleness threshold in days. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const DEFAULT_THRESHOLD = 'pr_core_default_threshold';

	/** @var string Option key: high-velocity staleness threshold in days. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const HIGH_VELOCITY_THRESHOLD = 'pr_core_high_velocity_threshold';

	/** @var string Option key: comma-separated notification email list. */ // phpcs:ignore Generic.Commenting.DocComment.MissingShort
	public const VERIFICATION_EMAIL = 'pr_core_verification_email';

	/**
	 * Register the settings submenu page under Peptides.
	 *
	 * Side effects: calls add_submenu_page().
	 *
	 * @return void
	 */
	public static function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . PR_Core_Peptide_CPT::POST_TYPE,
			__( 'PR Core Settings', 'peptide-repo-core' ),
			__( 'Settings', 'peptide-repo-core' ),
			'manage_options',
			'pr-core-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Render the settings page HTML. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		PR_Core_Settings_Renderer::render_page();
	}

	/**
	 * Register all settings, sections, and fields on admin_init.
	 *
	 * Side effects: calls register_setting(), add_settings_section(),
	 *               add_settings_field(), add_action() for cron reschedule.
	 *
	 * @return void
	 */
	public static function register_settings(): void {
		self::register_related_articles_section();
		self::register_verification_section();
		add_action( 'update_option_' . self::SCAN_CADENCE, array( __CLASS__, 'reschedule_cron' ), 10, 2 );
	}

	// ── Related Articles ─────────────────────────────────────────────────.

	/**
	 * Register Related Articles settings.
	 *
	 * @return void
	 */
	private static function register_related_articles_section(): void {
		register_setting(
			self::OPTION_GROUP,
			self::RELATED_ENABLED,
			array(
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::RELATED_LIMIT,
			array(
				'type'              => 'integer',
				'default'           => 3,
				'sanitize_callback' => array( __CLASS__, 'sanitize_limit' ),
			)
		);

		add_settings_section( 'pr_core_related_articles', __( 'Related Articles', 'peptide-repo-core' ), '__return_false', self::OPTION_GROUP );
		add_settings_field( self::RELATED_ENABLED, __( 'Enable Related Articles', 'peptide-repo-core' ), array( __CLASS__, 'render_enabled_field' ), self::OPTION_GROUP, 'pr_core_related_articles' );
		add_settings_field( self::RELATED_LIMIT, __( 'Number of Articles', 'peptide-repo-core' ), array( __CLASS__, 'render_limit_field' ), self::OPTION_GROUP, 'pr_core_related_articles' );
	}

	/**
	 * Render the enable/disable checkbox field. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_enabled_field(): void {
		PR_Core_Settings_Renderer::render_enabled_field();
	}

	/**
	 * Render the article count limit field. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_limit_field(): void {
		PR_Core_Settings_Renderer::render_limit_field();
	}

	/**
	 * Sanitize the related articles limit to 1-6.
	 *
	 * @param mixed $value Raw input.
	 * @return int
	 */
	public static function sanitize_limit( $value ): int {
		return min( 6, max( 1, (int) $value ) );
	}

	// ── Verification ─────────────────────────────────────────────────────.

	/**
	 * Register Verification settings.
	 *
	 * @return void
	 */
	private static function register_verification_section(): void {
		register_setting(
			self::OPTION_GROUP,
			self::SCAN_CADENCE,
			array(
				'type'              => 'string',
				'default'           => 'weekly',
				'sanitize_callback' => array( __CLASS__, 'sanitize_cadence' ),
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::DEFAULT_THRESHOLD,
			array(
				'type'              => 'integer',
				'default'           => 180,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::HIGH_VELOCITY_THRESHOLD,
			array(
				'type'              => 'integer',
				'default'           => 60,
				'sanitize_callback' => 'absint',
			)
		);
		register_setting(
			self::OPTION_GROUP,
			self::VERIFICATION_EMAIL,
			array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => array( __CLASS__, 'sanitize_emails' ),
			)
		);

		add_settings_section( 'pr_core_verification', __( 'Verification', 'peptide-repo-core' ), array( __CLASS__, 'render_verification_section' ), self::OPTION_GROUP );
		add_settings_field( self::SCAN_CADENCE, __( 'Scan cadence', 'peptide-repo-core' ), array( __CLASS__, 'render_cadence_field' ), self::OPTION_GROUP, 'pr_core_verification' );
		add_settings_field( self::DEFAULT_THRESHOLD, __( 'Default staleness threshold (days)', 'peptide-repo-core' ), array( __CLASS__, 'render_threshold_field' ), self::OPTION_GROUP, 'pr_core_verification' );
		add_settings_field( self::HIGH_VELOCITY_THRESHOLD, __( 'High-velocity threshold (days)', 'peptide-repo-core' ), array( __CLASS__, 'render_high_velocity_field' ), self::OPTION_GROUP, 'pr_core_verification' );
		add_settings_field( self::VERIFICATION_EMAIL, __( 'Notification email(s)', 'peptide-repo-core' ), array( __CLASS__, 'render_email_field' ), self::OPTION_GROUP, 'pr_core_verification' );
	}

	/**
	 * Render the verification section header. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_verification_section(): void {
		PR_Core_Settings_Renderer::render_verification_section();
	}

	/**
	 * Render the scan cadence select field. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_cadence_field(): void {
		PR_Core_Settings_Renderer::render_cadence_field();
	}

	/**
	 * Render the score threshold input field. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_threshold_field(): void {
		PR_Core_Settings_Renderer::render_threshold_field();
	}

	/**
	 * Render the high-velocity scan toggle field. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_high_velocity_field(): void {
		PR_Core_Settings_Renderer::render_high_velocity_field();
	}

	/**
	 * Render the alert email input field. Delegates to PR_Core_Settings_Renderer.
	 *
	 * @return void
	 */
	public static function render_email_field(): void {
		PR_Core_Settings_Renderer::render_email_field();
	}

	/**
	 * Sanitize cadence to allowed WP-cron recurrence values.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_cadence( string $value ): string {
		return in_array( $value, array( 'daily', 'weekly', 'monthly' ), true ) ? $value : 'weekly';
	}

	/**
	 * Sanitize comma-separated email list, keeping only valid addresses.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_emails( string $value ): string {
		if ( '' === $value ) {
			return '';
		}
		$valid = array_filter( array_map( 'trim', explode( ',', $value ) ), 'is_email' );
		return implode( ', ', $valid );
	}

	/**
	 * Reschedule the verification cron when cadence changes.
	 *
	 * Fires on: update_option_pr_core_scan_cadence.
	 *
	 * @param mixed $old_value Previous option value.
	 * @param mixed $new_value New option value.
	 * @return void
	 */
	public static function reschedule_cron( $old_value, $new_value ): void {
		if ( $old_value === $new_value ) {
			return;
		}
		wp_clear_scheduled_hook( 'pr_core_verification_scan' );
		wp_schedule_event( time(), (string) $new_value, 'pr_core_verification_scan' );
	}
}
