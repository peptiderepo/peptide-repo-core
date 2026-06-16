<?php
declare(strict_types=1);

/**
 * Static methods that output HTML for each settings field on the PR Core settings page
 *
 * @package Peptide_Repo_Core
 */

/**
 * Render methods for the PR Core settings admin page.
 *
 * What: Static methods that output HTML for each settings field on the PR Core settings page.
 * Who calls it: PR_Core_Settings — register_*_section() methods reference these via add_settings_field().
 * Dependencies: PR_Core_Settings (for option key constants).
 *
 * @see admin/class-pr-core-settings.php — Settings registration and sanitization.
 */
class PR_Core_Settings_Renderer {

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
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_enabled_field(): void {
		$enabled = (bool) get_option( self::RELATED_ENABLED, true );
		printf(
			'<input type="checkbox" name="%s" value="1"%s /> %s',
			esc_attr( self::RELATED_ENABLED ),
			checked( $enabled, true, false ),
			esc_html__( 'Show related monographs on single peptide pages', 'peptide-repo-core' )
		);
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_limit_field(): void {
		printf(
			'<input type="number" name="%s" value="%d" min="1" max="6" />',
			esc_attr( self::RELATED_LIMIT ),
			absint( get_option( self::RELATED_LIMIT, 3 ) )
		);
		echo '<p class="description">' . esc_html__( 'Number of articles to display (1-6). Default: 3.', 'peptide-repo-core' ) . '</p>';
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_verification_section(): void {
		echo '<p>' . esc_html__( 'Configure automatic staleness scanning and digest notifications.', 'peptide-repo-core' ) . '</p>';
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_cadence_field(): void {
		$current = get_option( self::SCAN_CADENCE, 'weekly' );
		echo '<select name="' . esc_attr( self::SCAN_CADENCE ) . '">';
		foreach ( array(
			'daily'   => 'Daily',
			'weekly'  => 'Weekly',
			'monthly' => 'Monthly',
		) as $val => $label ) {
			printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $current, $val, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_threshold_field(): void {
		printf( '<input type="number" name="%s" value="%d" min="1" />', esc_attr( self::DEFAULT_THRESHOLD ), absint( get_option( self::DEFAULT_THRESHOLD, 180 ) ) );
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_high_velocity_field(): void {
		printf( '<input type="number" name="%s" value="%d" min="1" />', esc_attr( self::HIGH_VELOCITY_THRESHOLD ), absint( get_option( self::HIGH_VELOCITY_THRESHOLD, 60 ) ) );
	}

	/**
	 * Render output.
	 *
	 * @return void
	 */
	public static function render_email_field(): void {
		printf(
			'<input type="text" name="%s" value="%s" class="regular-text" placeholder="admin@example.com" />',
			esc_attr( self::VERIFICATION_EMAIL ),
			esc_attr( (string) get_option( self::VERIFICATION_EMAIL, '' ) )
		);
		echo '<p class="description">' . esc_html__( 'Comma-separated. Leave blank to disable email digests.', 'peptide-repo-core' ) . '</p>';
	}
}
