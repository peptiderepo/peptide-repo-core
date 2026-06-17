<?php
/**
 * Regression tests for PR_Core_Settings_Renderer constant references.
 *
 * Covers the regression introduced in v0.7.0 (#22): the renderer class was
 * split out of PR_Core_Settings but all self::CONST references were left
 * pointing at constants only defined on PR_Core_Settings. This caused
 * PHP Fatal: Undefined constant PR_Core_Settings_Renderer::OPTION_GROUP
 * (and 6 others) on every load of the Settings admin page.
 *
 * Fix (v0.7.1): constants promoted to public on PR_Core_Settings; renderer
 * references them as PR_Core_Settings::*.
 *
 * @package PeptideRepoCore\Tests
 */

use PHPUnit\Framework\TestCase;

// ── Additional WP stubs required by the renderer's render_*() methods ─────

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $echo = true ): string {
		$result = $checked === $current ? ' checked="checked"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'selected' ) ) {
	function selected( $selected, $current = true, bool $echo = true ): string {
		$result = $selected === $current ? ' selected="selected"' : '';
		if ( $echo ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'settings_fields' ) ) {
	function settings_fields( string $option_group ): void {}
}

if ( ! function_exists( 'do_settings_sections' ) ) {
	function do_settings_sections( string $page ): void {}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button(): void {}
}

/**
 * Tests that PR_Core_Settings_Renderer resolves its constants without fatals.
 */
class SettingsRendererTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['pr_core_options']    = [];
		$GLOBALS['pr_core_cron_calls'] = [];
		$GLOBALS['pr_core_test_state'] = [
			'existing_post_types'   => [],
			'existing_taxonomies'   => [],
			'registered_post_types' => [],
			'registered_taxonomies' => [],
			'registered_meta'       => [],
			'added_actions'         => [],
			'added_filters'         => [],
		];
		require_once PR_CORE_PLUGIN_DIR . 'includes/cpt/class-pr-core-peptide-cpt.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/admin/class-pr-core-settings.php';
		require_once PR_CORE_PLUGIN_DIR . 'includes/admin/class-pr-core-settings-renderer.php';
	}

	// ── Cross-class constant accessibility ────────────────────────────────.

	/**
	 * OPTION_GROUP must be a non-empty string on PR_Core_Settings.
	 * Renderer calls PR_Core_Settings::OPTION_GROUP — verifies it resolves.
	 */
	public function test_option_group_constant_accessible_on_settings(): void {
		$this->assertNotEmpty( PR_Core_Settings::OPTION_GROUP );
		$this->assertIsString( PR_Core_Settings::OPTION_GROUP );
	}

	public function test_related_enabled_constant_accessible(): void {
		$this->assertNotEmpty( PR_Core_Settings::RELATED_ENABLED );
	}

	public function test_related_limit_constant_accessible(): void {
		$this->assertNotEmpty( PR_Core_Settings::RELATED_LIMIT );
	}

	public function test_scan_cadence_constant_accessible(): void {
		$this->assertNotEmpty( PR_Core_Settings::SCAN_CADENCE );
	}

	public function test_default_threshold_constant_accessible(): void {
		$this->assertNotEmpty( PR_Core_Settings::DEFAULT_THRESHOLD );
	}

	public function test_high_velocity_threshold_constant_accessible(): void {
		$this->assertNotEmpty( PR_Core_Settings::HIGH_VELOCITY_THRESHOLD );
	}

	public function test_verification_email_constant_accessible(): void {
		// Value is intentionally a non-empty option key string.
		$this->assertIsString( PR_Core_Settings::VERIFICATION_EMAIL );
		$this->assertNotEmpty( PR_Core_Settings::VERIFICATION_EMAIL );
	}

	// ── Renderer render_*_field: no fatal on constant resolution ─────────.

	/**
	 * render_enabled_field() calls PR_Core_Settings::RELATED_ENABLED twice.
	 * Before the fix this fatalled. Capture output to confirm no crash.
	 */
	public function test_render_enabled_field_no_fatal(): void {
		ob_start();
		PR_Core_Settings_Renderer::render_enabled_field();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="checkbox"', $output );
	}

	/**
	 * render_limit_field() calls PR_Core_Settings::RELATED_LIMIT twice.
	 */
	public function test_render_limit_field_no_fatal(): void {
		ob_start();
		PR_Core_Settings_Renderer::render_limit_field();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="number"', $output );
	}

	/**
	 * render_cadence_field() calls PR_Core_Settings::SCAN_CADENCE twice.
	 */
	public function test_render_cadence_field_no_fatal(): void {
		ob_start();
		PR_Core_Settings_Renderer::render_cadence_field();
		$output = ob_get_clean();
		$this->assertStringContainsString( '<select', $output );
	}

	/**
	 * render_threshold_field() calls PR_Core_Settings::DEFAULT_THRESHOLD twice.
	 */
	public function test_render_threshold_field_no_fatal(): void {
		ob_start();
		PR_Core_Settings_Renderer::render_threshold_field();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="number"', $output );
	}

	/**
	 * render_high_velocity_field() calls PR_Core_Settings::HIGH_VELOCITY_THRESHOLD twice.
	 */
	public function test_render_high_velocity_field_no_fatal(): void {
		ob_start();
		PR_Core_Settings_Renderer::render_high_velocity_field();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="number"', $output );
	}

	/**
	 * render_email_field() calls PR_Core_Settings::VERIFICATION_EMAIL twice.
	 */
	public function test_render_email_field_no_fatal(): void {
		ob_start();
		PR_Core_Settings_Renderer::render_email_field();
		$output = ob_get_clean();
		$this->assertStringContainsString( 'type="text"', $output );
	}

	// ── Constant value contracts ──────────────────────────────────────────.

	public function test_option_group_value(): void {
		$this->assertSame( 'pr_core_settings', PR_Core_Settings::OPTION_GROUP );
	}

	public function test_related_enabled_value(): void {
		$this->assertSame( 'pr_core_related_posts_enabled', PR_Core_Settings::RELATED_ENABLED );
	}

	public function test_related_limit_value(): void {
		$this->assertSame( 'pr_core_related_posts_limit', PR_Core_Settings::RELATED_LIMIT );
	}

	public function test_scan_cadence_value(): void {
		$this->assertSame( 'pr_core_scan_cadence', PR_Core_Settings::SCAN_CADENCE );
	}

	public function test_default_threshold_value(): void {
		$this->assertSame( 'pr_core_default_threshold', PR_Core_Settings::DEFAULT_THRESHOLD );
	}

	public function test_high_velocity_threshold_value(): void {
		$this->assertSame( 'pr_core_high_velocity_threshold', PR_Core_Settings::HIGH_VELOCITY_THRESHOLD );
	}

	public function test_verification_email_value(): void {
		$this->assertSame( 'pr_core_verification_email', PR_Core_Settings::VERIFICATION_EMAIL );
	}
}
