<?php
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

	/** @var string Option group shared by all PR Core settings. */
	public const OPTION_GROUP = 'pr_core_settings';

	/** @var string Option key: enable/disable related articles. */
	private const RELATED_ENABLED = 'pr_core_related_posts_enabled';

	/** @var string Option key: related articles limit (1-6). */
	private const RELATED_LIMIT = 'pr_core_related_posts_limit';

	/** @var string Option key: WP-cron scan cadence. */
	private const SCAN_CADENCE = 'pr_core_scan_cadence';

	/** @var string Option key: default staleness threshold in days. */
	private const DEFAULT_THRESHOLD = 'pr_core_default_threshold';

	/** @var string Option key: high-velocity staleness threshold in days. */
	private const HIGH_VELOCITY_THRESHOLD = 'pr_core_high_velocity_threshold';

	/** @var string Option key: comma-separated notification email list. */
	private const VERIFICATION_EMAIL = 'pr_core_verification_email';

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
			[ __CLASS__, 'render_page' ]
		);
	}

	/**
	 * Render the settings page form.
	 *
	 * @return void
	 */
	public static function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'PR Core Settings', 'peptide-repo-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( self::OPTION_GROUP );
				do_settings_sections( self::OPTION_GROUP );
				submit_button();
				?>
			</form>
		</div>
		<?php
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
		add_action( 'update_option_' . self::SCAN_CADENCE, [ __CLASS__, 'reschedule_cron' ], 10, 2 );
	}

	// ── Related Articles ─────────────────────────────────────────────────

	/**
	 * Register Related Articles settings.
	 *
	 * @return void
	 */
	private static function register_related_articles_section(): void {
		register_setting( self::OPTION_GROUP, self::RELATED_ENABLED, [ 'type' => 'boolean', 'default' => true,    'sanitize_callback' => 'rest_sanitize_boolean' ] );
		register_setting( self::OPTION_GROUP, self::RELATED_LIMIT,   [ 'type' => 'integer', 'default' => 3,       'sanitize_callback' => [ __CLASS__, 'sanitize_limit' ] ] );

		add_settings_section( 'pr_core_related_articles', __( 'Related Articles', 'peptide-repo-core' ), '__return_false', self::OPTION_GROUP );
		add_settings_field( self::RELATED_ENABLED, __( 'Enable Related Articles', 'peptide-repo-core' ), [ __CLASS__, 'render_enabled_field' ], self::OPTION_GROUP, 'pr_core_related_articles' );
		add_settings_field( self::RELATED_LIMIT,   __( 'Number of Articles',      'peptide-repo-core' ), [ __CLASS__, 'render_limit_field'   ], self::OPTION_GROUP, 'pr_core_related_articles' );
	}

	/** @return void */
	public static function render_enabled_field(): void {
		$enabled = (bool) get_option( self::RELATED_ENABLED, true );
		printf(
			'<input type="checkbox" name="%s" value="1"%s /> %s',
			esc_attr( self::RELATED_ENABLED ),
			checked( $enabled, true, false ),
			esc_html__( 'Show related monographs on single peptide pages', 'peptide-repo-core' )
		);
	}

	/** @return void */
	public static function render_limit_field(): void {
		printf(
			'<input type="number" name="%s" value="%d" min="1" max="6" />',
			esc_attr( self::RELATED_LIMIT ),
			absint( get_option( self::RELATED_LIMIT, 3 ) )
		);
		echo '<p class="description">' . esc_html__( 'Number of articles to display (1-6). Default: 3.', 'peptide-repo-core' ) . '</p>';
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

	// ── Verification ─────────────────────────────────────────────────────

	/**
	 * Register Verification settings.
	 *
	 * @return void
	 */
	private static function register_verification_section(): void {
		register_setting( self::OPTION_GROUP, self::SCAN_CADENCE,            [ 'type' => 'string',  'default' => 'weekly', 'sanitize_callback' => [ __CLASS__, 'sanitize_cadence' ] ] );
		register_setting( self::OPTION_GROUP, self::DEFAULT_THRESHOLD,       [ 'type' => 'integer', 'default' => 180,      'sanitize_callback' => 'absint' ] );
		register_setting( self::OPTION_GROUP, self::HIGH_VELOCITY_THRESHOLD, [ 'type' => 'integer', 'default' => 60,       'sanitize_callback' => 'absint' ] );
		register_setting( self::OPTION_GROUP, self::VERIFICATION_EMAIL,      [ 'type' => 'string',  'default' => '',       'sanitize_callback' => [ __CLASS__, 'sanitize_emails' ] ] );

		add_settings_section( 'pr_core_verification', __( 'Verification', 'peptide-repo-core' ), [ __CLASS__, 'render_verification_section' ], self::OPTION_GROUP );
		add_settings_field( self::SCAN_CADENCE,            __( 'Scan cadence',                       'peptide-repo-core' ), [ __CLASS__, 'render_cadence_field'       ], self::OPTION_GROUP, 'pr_core_verification' );
		add_settings_field( self::DEFAULT_THRESHOLD,       __( 'Default staleness threshold (days)',  'peptide-repo-core' ), [ __CLASS__, 'render_threshold_field'     ], self::OPTION_GROUP, 'pr_core_verification' );
		add_settings_field( self::HIGH_VELOCITY_THRESHOLD, __( 'High-velocity threshold (days)',      'peptide-repo-core' ), [ __CLASS__, 'render_high_velocity_field' ], self::OPTION_GROUP, 'pr_core_verification' );
		add_settings_field( self::VERIFICATION_EMAIL,      __( 'Notification email(s)',               'peptide-repo-core' ), [ __CLASS__, 'render_email_field'         ], self::OPTION_GROUP, 'pr_core_verification' );
	}

	/** @return void */
	public static function render_verification_section(): void {
		echo '<p>' . esc_html__( 'Configure automatic staleness scanning and digest notifications.', 'peptide-repo-core' ) . '</p>';
	}

	/** @return void */
	public static function render_cadence_field(): void {
		$current = get_option( self::SCAN_CADENCE, 'weekly' );
		echo '<select name="' . esc_attr( self::SCAN_CADENCE ) . '">';
		foreach ( [ 'daily' => 'Daily', 'weekly' => 'Weekly', 'monthly' => 'Monthly' ] as $val => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $current, $val, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/** @return void */
	public static function render_threshold_field(): void {
		printf( '<input type="number" name="%s" value="%d" min="1" />', esc_attr( self::DEFAULT_THRESHOLD ), absint( get_option( self::DEFAULT_THRESHOLD, 180 ) ) );
	}

	/** @return void */
	public static function render_high_velocity_field(): void {
		printf( '<input type="number" name="%s" value="%d" min="1" />', esc_attr( self::HIGH_VELOCITY_THRESHOLD ), absint( get_option( self::HIGH_VELOCITY_THRESHOLD, 60 ) ) );
	}

	/** @return void */
	public static function render_email_field(): void {
		printf(
			'<input type="text" name="%s" value="%s" class="regular-text" placeholder="admin@example.com" />',
			esc_attr( self::VERIFICATION_EMAIL ),
			esc_attr( (string) get_option( self::VERIFICATION_EMAIL, '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Comma-separated. Leave blank to disable email digests.', 'peptide-repo-core' ) . '</p>';
	}

	/**
	 * Sanitize cadence to allowed WP-cron recurrence values.
	 *
	 * @param string $value Raw input.
	 * @return string
	 */
	public static function sanitize_cadence( string $value ): string {
		return in_array( $value, [ 'daily', 'weekly', 'monthly' ], true ) ? $value : 'weekly';
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
