<?php
declare(strict_types=1);

/**
 * Settings page for PR Core plugin features.
 *
 * What: Registers admin menu, renders settings form, and handles option saves
 *       for features like Related Articles.
 * Who calls it: PR_Core_Admin::register_hooks().
 * Dependencies: None (reads/writes wp_options).
 */
class PR_Core_Settings {

	/** @var string Option key: enable/disable related articles. */
	private const ENABLED_OPTION = 'pr_core_related_posts_enabled';

	/** @var string Option key: related articles limit (1-6). */
	private const LIMIT_OPTION = 'pr_core_related_posts_limit';

	/**
	 * Register admin page and settings hooks.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add submenu page under Peptides.
	 *
	 * @return void
	 */
	public function add_settings_page(): void {
		add_submenu_page(
			'edit.php?post_type=' . PR_Core_Peptide_CPT::POST_TYPE,
			__( 'PR Core Settings', 'peptide-repo-core' ),
			__( 'Settings', 'peptide-repo-core' ),
			'manage_options',
			'pr-core-settings',
			[ $this, 'render_page' ]
		);
	}

	/**
	 * Register settings fields.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'pr_core_settings',
			self::ENABLED_OPTION,
			[
				'type'              => 'boolean',
				'default'           => true,
				'sanitize_callback' => 'rest_sanitize_boolean',
			]
		);

		register_setting(
			'pr_core_settings',
			self::LIMIT_OPTION,
			[
				'type'              => 'integer',
				'default'           => 3,
				'sanitize_callback' => [ $this, 'sanitize_limit' ],
			]
		);

		add_settings_section(
			'pr_core_related_articles',
			__( 'Related Articles', 'peptide-repo-core' ),
			[ $this, 'render_section' ],
			'pr_core_settings'
		);

		add_settings_field(
			self::ENABLED_OPTION,
			__( 'Enable Related Articles', 'peptide-repo-core' ),
			[ $this, 'render_enabled_field' ],
			'pr_core_settings',
			'pr_core_related_articles'
		);

		add_settings_field(
			self::LIMIT_OPTION,
			__( 'Number of Articles to Display', 'peptide-repo-core' ),
			[ $this, 'render_limit_field' ],
			'pr_core_settings',
			'pr_core_related_articles'
		);
	}

	/**
	 * Render the settings page.
	 *
	 * @return void
	 */
	public function render_page(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Peptide Repo Core Settings', 'peptide-repo-core' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'pr_core_settings' );
				do_settings_sections( 'pr_core_settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render section description.
	 *
	 * @return void
	 */
	public function render_section(): void {
		echo '<p>' . esc_html__( 'Configure the Related Articles section that appears on peptide single pages.', 'peptide-repo-core' ) . '</p>';
	}

	/**
	 * Render enabled toggle field.
	 *
	 * @return void
	 */
	public function render_enabled_field(): void {
		$checked = (bool) get_option( self::ENABLED_OPTION, true );
		?>
		<input
			type="checkbox"
			name="<?php echo esc_attr( self::ENABLED_OPTION ); ?>"
			value="1"
			<?php checked( $checked, true ); ?>
		/>
		<label>
			<?php esc_html_e( 'Display related articles on peptide pages', 'peptide-repo-core' ); ?>
		</label>
		<?php
	}

	/**
	 * Render limit field.
	 *
	 * @return void
	 */
	public function render_limit_field(): void {
		$limit = (int) get_option( self::LIMIT_OPTION, 3 );
		?>
		<input
			type="number"
			name="<?php echo esc_attr( self::LIMIT_OPTION ); ?>"
			value="<?php echo esc_attr( (string) $limit ); ?>"
			min="1"
			max="6"
		/>
		<p class="description">
			<?php esc_html_e( 'Number of articles to display (1-6). Default: 3.', 'peptide-repo-core' ); ?>
		</p>
		<?php
	}

	/**
	 * Sanitize limit to 1-6 range.
	 *
	 * @param mixed $value Raw input.
	 * @return int Sanitized value.
	 */
	public function sanitize_limit( $value ): int {
		$limit = (int) $value;
		return min( 6, max( 1, $limit ) );
	}
}
